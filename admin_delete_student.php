<?php
// admin_delete_student.php
ini_set('display_errors', 0);
error_reporting(0);
ob_start();
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit();
}

try {
    require_once 'config.php';
    $conn = db_connect();

    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (empty($data)) $data = $_POST;

    $id = (int) ($data['id'] ?? 0);

    if (!$id) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid student ID.']);
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM `users` WHERE id = ? AND role = 'student' LIMIT 1");
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Student deleted.']);
    } else {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Delete failed: ' . $stmt->error]);
    }
    $stmt->close();
    $conn->close();

} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
