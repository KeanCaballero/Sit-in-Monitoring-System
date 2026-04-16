<?php
// api/feedback_submit.php
ini_set('display_errors', 0);
error_reporting(0);
ob_start();
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    ob_end_clean(); echo json_encode(['success'=>false]); exit();
}

require_once '../config.php';

try {
    $conn      = db_connect();
    $data      = json_decode(file_get_contents('php://input'), true) ?: [];
    $id_number = $_SESSION['user']['id_number'] ?? '';
    $sit_in_id = (int)($data['sit_in_id'] ?? 0);
    $rating    = max(1, min(5, (int)($data['rating'] ?? 3)));
    $message   = trim($data['message'] ?? '');

    $stmt = $conn->prepare("INSERT INTO feedback (sit_in_id, id_number, rating, message) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('isis', $sit_in_id, $id_number, $rating, $message);
    $ok = $stmt->execute();
    $stmt->close();
    $conn->close();

    ob_end_clean();
    echo json_encode(['success' => $ok]);
} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode(['success' => false]);
}