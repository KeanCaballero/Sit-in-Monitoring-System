<?php
// api/announcement_post.php
// GET  → all announcements (latest first)
// POST { title, message } → admin creates announcement + notifies all students
ini_set('display_errors', 0); error_reporting(0); ob_start();
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) { ob_end_clean(); echo json_encode([]); exit(); }
require_once '../config.php';

try {
    $conn = db_connect();

    /* ── POST: create ─────────────────────────────── */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data    = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $title   = trim($data['title']   ?? '');
        $message = trim($data['message'] ?? '');
        $role    = $_SESSION['user']['role'] ?? 'student';

        if (!$message) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Message is required.']);
            exit();
        }
        if ($role !== 'admin') {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
            exit();
        }

        // Save announcement
        $s = $conn->prepare("INSERT INTO announcements (title, message, created_by) VALUES (?, ?, 'admin')");
        $s->bind_param('ss', $title, $message);
        $s->execute();

        // Ensure notifications table exists
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

        // Notify all students
        $notifTitle = 'New Announcement';
        $notifMsg   = ($title ? "$title: " : '') . $message;
        if (strlen($notifMsg) > 200) $notifMsg = substr($notifMsg, 0, 197) . '…';

        $res = $conn->query("SELECT id_number FROM users WHERE role='student'");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $uid = $row['id_number'];
                $s2  = $conn->prepare("INSERT INTO notifications (user_id_number, type, title, message) VALUES (?, 'announcement', ?, ?)");
                $s2->bind_param('sss', $uid, $notifTitle, $notifMsg);
                $s2->execute();
            }
        }

        ob_end_clean();
        echo json_encode(['success' => true]);
        exit();
    }

    /* ── GET: list ────────────────────────────────── */
    $res  = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 30");
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $conn->close();

    ob_end_clean();
    echo json_encode($rows);

} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode([]);
}