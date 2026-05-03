<?php
// admin_sitin_submit.php
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

    $data      = json_decode(file_get_contents('php://input'), true);
    $id_number         = trim($data['id_number']         ?? '');
    $purpose           = trim($data['purpose']           ?? '');
    $lab               = trim($data['lab']               ?? '');
    $override_sessions = isset($data['override_sessions']) && $data['override_sessions'] !== '' ? (int)$data['override_sessions'] : null;

    if (!$id_number || !$purpose || !$lab) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        exit();
    }

    // Get the student's DB id and check sessions
    $stmt = $conn->prepare("SELECT id, remaining_sessions FROM users WHERE id_number = ? LIMIT 1");
    $stmt->bind_param('s', $id_number);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Student not found.']);
        exit();
    }
    if ($row['remaining_sessions'] <= 0) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Student has no remaining sessions.']);
        exit();
    }

    // Check if student already has an active sit-in
    $stmt = $conn->prepare("SELECT id FROM sit_ins WHERE id_number = ? AND status = 'Active' LIMIT 1");
    $stmt->bind_param('s', $id_number);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Student is already sitting in.']);
        exit();
    }

    // Insert sit-in record
    $stmt = $conn->prepare(
        "INSERT INTO sit_ins (id_number, purpose, lab, session_at_entry, status, created_at)
         VALUES (?, ?, ?, ?, 'Active', NOW())"
    );
    $session_to_store = ($override_sessions !== null) ? $override_sessions : $row['remaining_sessions'];
    $stmt->bind_param('sssi', $id_number, $purpose, $lab, $session_to_store);
    $stmt->execute();
    $new_sit_id = $conn->insert_id;
    $stmt->close();

    // Decrement remaining sessions
    $stmt = $conn->prepare("UPDATE users SET remaining_sessions = remaining_sessions - 1 WHERE id_number = ?");
    $stmt->bind_param('s', $id_number);
    $stmt->execute();
    $stmt->close();

    $conn->close();

    ob_end_clean();
    echo json_encode(['success' => true, 'sit_id' => $new_sit_id]);

} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}