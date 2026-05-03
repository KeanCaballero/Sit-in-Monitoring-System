<?php
// admin_edit_student.php
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

    $id                = (int) ($data['id']                ?? 0);
    $first_name        = trim($data['first_name']          ?? '');
    $last_name         = trim($data['last_name']           ?? '');
    $course            = trim($data['course']              ?? '');
    $year_level        = trim($data['year_level']          ?? '');
    $remaining_sessions = (int) ($data['remaining_sessions'] ?? 30);

    if (!$id || !$first_name || !$last_name) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        exit();
    }

    $stmt = $conn->prepare(
        "UPDATE `users` SET first_name = ?, last_name = ?, course = ?, year_level = ?, remaining_sessions = ?
         WHERE id = ? LIMIT 1"
    );
    $stmt->bind_param('ssssii', $first_name, $last_name, $course, $year_level, $remaining_sessions, $id);

    if ($stmt->execute()) {
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Student updated successfully.']);
    } else {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Update failed: ' . $stmt->error]);
    }
    $stmt->close();
    $conn->close();

} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
