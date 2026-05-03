<?php
// admin_add_student.php
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

    $id_number   = trim($data['id_number']   ?? '');
    $first_name  = trim($data['first_name']  ?? '');
    $last_name   = trim($data['last_name']   ?? '');
    $middle_name = trim($data['middle_name'] ?? '');
    $course      = trim($data['course']      ?? '');
    $year_level  = trim($data['year_level']  ?? '');
    $email       = trim($data['email']       ?? '');
    $password    = $data['password']         ?? '';

    if (!$id_number || !$first_name || !$last_name || !$email || !$password || !$course || !$year_level) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit();
    }

    // Check duplicate ID
    $chk = $conn->prepare("SELECT id FROM `users` WHERE id_number = ? LIMIT 1");
    $chk->bind_param('s', $id_number);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'An account with that ID number already exists.']);
        exit();
    }
    $chk->close();

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare(
        "INSERT INTO `users` (id_number, first_name, last_name, middle_name, course, year_level, email, password, role, remaining_sessions)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'student', 30)"
    );
    $stmt->bind_param('ssssssss', $id_number, $first_name, $last_name, $middle_name, $course, $year_level, $email, $hashed);

    if ($stmt->execute()) {
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Student added successfully.']);
    } else {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Insert failed: ' . $stmt->error]);
    }
    $stmt->close();
    $conn->close();

} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}