<?php
// api/reservation_fetch.php
// Proxies to root reservation_fetch.php — adjusts require path for subfolder
ini_set('display_errors', 0); error_reporting(0); ob_start();
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) { ob_end_clean(); echo json_encode([]); exit(); }

require_once dirname(__DIR__) . '/config.php';

try {
    $conn   = db_connect();
    $role   = $_SESSION['user']['role']      ?? 'student';
    $id_num = $_SESSION['user']['id_number'] ?? '';

    // ── POST actions ──────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data   = json_decode(file_get_contents('php://input'), true) ?: [];
        $action = $data['action'] ?? '';
        $res_id = (int)($data['id'] ?? 0);

        if (!$res_id) { ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Invalid ID']); exit(); }

        if ($action === 'approve' && $role === 'admin') {
            $s = $conn->prepare("UPDATE reservations SET status='Approved' WHERE id=? LIMIT 1");
            $s->bind_param('i', $res_id); $s->execute();
            ob_end_clean(); echo json_encode(['success'=>true]); exit();
        }
        if ($action === 'reject' && $role === 'admin') {
            $s = $conn->prepare("UPDATE reservations SET status='Rejected' WHERE id=? LIMIT 1");
            $s->bind_param('i', $res_id); $s->execute();
            ob_end_clean(); echo json_encode(['success'=>true]); exit();
        }
        if ($action === 'cancel') {
            $is_admin = ($role === 'admin') ? 1 : 0;
            $s = $conn->prepare("UPDATE reservations SET status='Cancelled' WHERE id=? AND (id_number=? OR ?) LIMIT 1");
            $s->bind_param('isi', $res_id, $id_num, $is_admin); $s->execute();
            ob_end_clean(); echo json_encode(['success'=>true]); exit();
        }
        ob_end_clean(); echo json_encode(['success'=>false,'message'=>'Unknown action']); exit();
    }

    // ── GET ───────────────────────────────────────
    $is_admin = isset($_GET['admin']) && $role === 'admin';
    if ($is_admin) {
        $s = $conn->prepare(
            "SELECT r.*, CONCAT(u.first_name,' ',u.last_name) AS student_name
             FROM reservations r
             LEFT JOIN users u ON u.id_number = r.id_number
             ORDER BY r.date DESC, r.time_in DESC LIMIT 200"
        );
        $s->execute();
    } else {
        $s = $conn->prepare("SELECT * FROM reservations WHERE id_number=? ORDER BY date DESC, time_in DESC LIMIT 50");
        $s->bind_param('s', $id_num);
        $s->execute();
    }
    $rows = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close(); $conn->close();
    ob_end_clean(); echo json_encode($rows);

} catch (Throwable $e) {
    ob_end_clean(); echo json_encode([]);
}