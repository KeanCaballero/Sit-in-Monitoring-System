<?php
// admin_sitin_timeout.php
ini_set('display_errors', 0);
error_reporting(0);
ob_start();
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    require_once 'config.php';
    $conn = db_connect();

    $data   = json_decode(file_get_contents('php://input'), true);
    $sit_id = intval($data['sit_id'] ?? 0);

    if (!$sit_id) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid sit_id.']);
        exit();
    }

    $stmt = $conn->prepare("UPDATE sit_ins SET status = 'Done', timed_out_at = NOW() WHERE id = ? AND status = 'Active'");
    $stmt->bind_param('i', $sit_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected > 0) {
        // ── AUTO POINTS SYSTEM ───────────────────────────────
        // Fetch the sit-in record to calculate duration
        $sr = $conn->prepare(
            "SELECT id_number, created_at, timed_out_at
             FROM sit_ins WHERE id = ? LIMIT 1"
        );
        $sr->bind_param('i', $sit_id);
        $sr->execute();
        $sitin = $sr->get_result()->fetch_assoc();
        $sr->close();

        if ($sitin && $sitin['timed_out_at']) {
            $id_number    = $sitin['id_number'];
            $created_at   = new DateTime($sitin['created_at']);
            $timed_out_at = new DateTime($sitin['timed_out_at']);
            $duration_mins = (int)(($timed_out_at->getTimestamp() - $created_at->getTimestamp()) / 60);
            if ($duration_mins < 0) $duration_mins = 0;

            // ── POINTS FORMULA ───────────────────────────────
            // +1  Completion bonus (any finished session)
            // +1 per 30 min of sit-in time (max 3 pts)
            // +1 bonus if session >= 2 hours
            // ────────────────────────────────────────────────
            $completion_pts  = 1;
            $duration_pts    = min(3, (int)floor($duration_mins / 30));
            $long_bonus_pts  = ($duration_mins >= 120) ? 1 : 0;
            $total_pts       = $completion_pts + $duration_pts + $long_bonus_pts;

            if ($total_pts > 0) {
                // Build a readable reason
                $dur_label = $duration_mins >= 60
                    ? round($duration_mins / 60, 1) . 'h'
                    : $duration_mins . 'min';
                $reason = "Sit-in completed ({$dur_label})"
                    . " — +{$completion_pts} completion"
                    . ($duration_pts  ? ", +{$duration_pts} duration"   : "")
                    . ($long_bonus_pts ? ", +{$long_bonus_pts} long-session bonus" : "");

                // Update user points
                $up = $conn->prepare("UPDATE users SET points = points + ? WHERE id_number = ?");
                $up->bind_param('is', $total_pts, $id_number);
                $up->execute();
                $up->close();

                // Log in points_log
                $conn->query("CREATE TABLE IF NOT EXISTS `points_log` (
                    `id`         INT AUTO_INCREMENT PRIMARY KEY,
                    `id_number`  VARCHAR(20) NOT NULL,
                    `points`     INT NOT NULL DEFAULT 0,
                    `reason`     TEXT DEFAULT NULL,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    KEY `idx_id_number` (`id_number`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                $pl = $conn->prepare("INSERT INTO points_log (id_number, points, reason) VALUES (?, ?, ?)");
                $pl->bind_param('sis', $id_number, $total_pts, $reason);
                $pl->execute();
                $pl->close();

                // Notify student
                $notif_title = "Points Awarded 🌟 (+{$total_pts} pts)";
                $notif_msg   = "+{$total_pts} points for sit-in ({$dur_label})";
                if ($long_bonus_pts) $notif_msg .= " — Long session bonus included!";
                $no = $conn->prepare(
                    "INSERT INTO notifications (user_id_number, type, title, message)
                     VALUES (?, 'success', ?, ?)"
                );
                $no->bind_param('sss', $id_number, $notif_title, $notif_msg);
                $no->execute();
                $no->close();
            }
        }
        // ── END AUTO POINTS ─────────────────────────────────
    }

    $conn->close();

    ob_end_clean();
    echo json_encode(['success' => $affected > 0]);

} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}