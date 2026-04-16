<?php
// api/notifications.php
// GET  → returns notifications + unread count for logged-in user
// POST action=mark_read   id=N  → mark one as read
// POST action=mark_all_read     → mark all read
// POST action=send (admin only) → broadcast a notification
ini_set('display_errors', 0); error_reporting(0); ob_start();
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) { ob_end_clean(); echo json_encode(['notifications'=>[],'unread'=>0]); exit(); }
require_once '../config.php';

try {
    $conn      = db_connect();
    $id_number = $_SESSION['user']['id_number'] ?? '';
    $role      = $_SESSION['user']['role']      ?? 'student';

    // Auto-create table if missing
    $conn->query("CREATE TABLE IF NOT EXISTS `notifications` (
        `id`             INT AUTO_INCREMENT PRIMARY KEY,
        `user_id_number` VARCHAR(20)  NOT NULL,
        `type`           VARCHAR(20)  DEFAULT 'info',
        `title`          VARCHAR(150) DEFAULT '',
        `message`        TEXT         DEFAULT NULL,
        `is_read`        TINYINT(1)   DEFAULT 0,
        `created_at`     DATETIME     DEFAULT CURRENT_TIMESTAMP,
        KEY `idx_user` (`user_id_number`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    /* ── POST actions ─────────────────────────────── */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data   = json_decode(file_get_contents('php://input'), true) ?: [];
        $action = $data['action'] ?? '';

        if ($action === 'mark_read') {
            $nid = (int)($data['id'] ?? 0);
            if ($nid) {
                $s = $conn->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id_number=?");
                $s->bind_param('is', $nid, $id_number);
                $s->execute();
            }
            ob_end_clean(); echo json_encode(['success'=>true]); exit();
        }

        if ($action === 'mark_all_read') {
            $s = $conn->prepare("UPDATE notifications SET is_read=1 WHERE user_id_number=?");
            $s->bind_param('s', $id_number);
            $s->execute();
            ob_end_clean(); echo json_encode(['success'=>true]); exit();
        }

        // Admin-only: push notification to a user or all students
        if ($action === 'send' && $role === 'admin') {
            $target  = $data['target']  ?? 'all';
            $type    = $data['type']    ?? 'info';
            $title   = $data['title']   ?? '';
            $message = $data['message'] ?? '';

            if ($target === 'all') {
                $res = $conn->query("SELECT id_number FROM users WHERE role='student'");
                while ($row = $res->fetch_assoc()) {
                    $s = $conn->prepare("INSERT INTO notifications (user_id_number,type,title,message) VALUES (?,?,?,?)");
                    $s->bind_param('ssss', $row['id_number'], $type, $title, $message);
                    $s->execute();
                }
            } else {
                $s = $conn->prepare("INSERT INTO notifications (user_id_number,type,title,message) VALUES (?,?,?,?)");
                $s->bind_param('ssss', $target, $type, $title, $message);
                $s->execute();
            }
            ob_end_clean(); echo json_encode(['success'=>true]); exit();
        }

        ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Unknown action']); exit();
    }

    /* ── GET: fetch notifications ─────────────────── */
    $s = $conn->prepare(
        "SELECT * FROM notifications WHERE user_id_number=? ORDER BY created_at DESC LIMIT 30"
    );
    $s->bind_param('s', $id_number);
    $s->execute();
    $rows   = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $unread = count(array_filter($rows, fn($r) => !(int)$r['is_read']));
    $s->close();
    $conn->close();

    ob_end_clean();
    echo json_encode(['notifications' => $rows, 'unread' => $unread]);

} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode(['notifications' => [], 'unread' => 0]);
}

