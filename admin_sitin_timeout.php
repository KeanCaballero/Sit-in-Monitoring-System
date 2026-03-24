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
    $conn->close();

    ob_end_clean();
    echo json_encode(['success' => $affected > 0]);

} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}