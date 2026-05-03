<?php
// api/leaderboard.php
// GET              → returns top 50 students ranked by points + sit-in count
// POST action=add_points      → admin: award points to student
// POST action=reset_sessions  → admin: reset all sessions to 30
ini_set('display_errors', 0); error_reporting(0); ob_start();
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) { ob_end_clean(); echo json_encode([]); exit(); }
require_once '../config.php';

try {
    $conn = db_connect();
    $role = $_SESSION['user']['role'] ?? 'student';

    /* ── POST actions ─────────────────────────────── */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data   = json_decode(file_get_contents('php://input'), true) ?: [];
        $action = $data['action'] ?? '';

        if ($action === 'add_points' && $role === 'admin') {
            $id_number = trim($data['id_number'] ?? '');
            $points    = (int)($data['points'] ?? 0);
            $reason    = trim($data['reason']   ?? '');

            if (!$id_number || $points <= 0) {
                ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Invalid input']); exit();
            }

            // Update user points
            $s = $conn->prepare("UPDATE users SET points = points + ? WHERE id_number = ?");
            $s->bind_param('is', $points, $id_number);
            $s->execute();

            if ($s->affected_rows === 0) {
                ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Student not found']); exit();
            }

            // Log in points_log
            $s2 = $conn->prepare("INSERT INTO points_log (id_number, points, reason) VALUES (?, ?, ?)");
            $s2->bind_param('sis', $id_number, $points, $reason);
            $s2->execute();

            // Notify student
            $conn->query("CREATE TABLE IF NOT EXISTS `notifications` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id_number` VARCHAR(20) NOT NULL,
                `type` VARCHAR(20) DEFAULT 'info',
                `title` VARCHAR(150) DEFAULT '',
                `message` TEXT DEFAULT NULL,
                `is_read` TINYINT DEFAULT 0,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY `idx_user` (`user_id_number`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $notifMsg = "+{$points} points awarded" . ($reason ? " — $reason" : "");
            $s3 = $conn->prepare("INSERT INTO notifications (user_id_number, type, title, message) VALUES (?, 'success', 'Points Awarded 🌟', ?)");
            $s3->bind_param('ss', $id_number, $notifMsg);
            $s3->execute();

            ob_end_clean(); echo json_encode(['success' => true]); exit();
        }

        if ($action === 'reset_sessions' && $role === 'admin') {
            $conn->query("UPDATE users SET remaining_sessions = 30 WHERE role = 'student'");
            ob_end_clean(); echo json_encode(['success' => true]); exit();
        }

        ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Unauthorized or unknown action']); exit();
    }

    /* ── GET: leaderboard data ────────────────────── */
    $s = $conn->prepare(
        "SELECT u.id_number, u.first_name, u.last_name, u.course, u.year_level, u.points,
                COUNT(si.id)                                                                      AS total_sitins,
                COALESCE(SUM(
                    CASE WHEN si.timed_out_at IS NOT NULL
                    THEN TIMESTAMPDIFF(MINUTE, si.created_at, si.timed_out_at)
                    ELSE 0 END), 0)                                                               AS total_duration_mins,
                COALESCE(MAX(
                    CASE WHEN si.timed_out_at IS NOT NULL
                    THEN TIMESTAMPDIFF(MINUTE, si.created_at, si.timed_out_at)
                    ELSE 0 END), 0)                                                               AS longest_session_mins,
                COALESCE(AVG(
                    CASE WHEN si.timed_out_at IS NOT NULL
                    THEN TIMESTAMPDIFF(MINUTE, si.created_at, si.timed_out_at)
                    ELSE NULL END), 0)                                                            AS avg_duration_mins
         FROM users u
         LEFT JOIN sit_ins si ON si.id_number = u.id_number AND si.status = 'Done'
         WHERE u.role = 'student'
         GROUP BY u.id_number
         ORDER BY u.points DESC, total_sitins DESC, total_duration_mins DESC
         LIMIT 50"
    );
    $s->execute();
    $rawRows = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();
    $conn->close();

    // Add computed display fields
    $rows = array_map(function($r) {
        $totalMins   = (int)$r['total_duration_mins'];
        $longestMins = (int)$r['longest_session_mins'];
        $avgMins     = (float)$r['avg_duration_mins'];
        $r['total_hours']        = round($totalMins   / 60, 1);
        $r['longest_session_hr'] = round($longestMins / 60, 1);
        $r['avg_duration_hr']    = round($avgMins     / 60, 1);
        $r['total_duration_mins'] = $totalMins;
        return $r;
    }, $rawRows);

    ob_end_clean();
    echo json_encode($rows);

} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode([]);
}