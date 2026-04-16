<?php
// api/reservation_submit.php
ini_set('display_errors', 0);
error_reporting(0);
ob_start();
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../config.php';

try {
    $conn = db_connect();
    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    $id_number = $_SESSION['user']['id_number'] ?? '';
    $purpose   = trim($data['purpose']   ?? '');
    $lab       = trim($data['lab']       ?? '');
    $pc_number = (int)($data['pc_number'] ?? 0);
    $date      = trim($data['date']      ?? '');
    $time_in   = trim($data['time_in']   ?? '');

    if (!$purpose || !$lab || !$pc_number || !$date || !$time_in) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit();
    }

    // Check student has sessions
    $s = $conn->prepare("SELECT remaining_sessions FROM users WHERE id_number = ? LIMIT 1");
    $s->bind_param('s', $id_number);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $s->close();

    if (!$row || $row['remaining_sessions'] <= 0) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'No remaining sessions.']);
        exit();
    }

    // Check that PC is not already reserved/occupied on that date
    $chk = $conn->prepare(
        "SELECT id FROM reservations
         WHERE lab = ? AND pc_number = ? AND date = ? AND status IN ('Approved','Pending')
         LIMIT 1"
    );
    $chk->bind_param('sis', $lab, $pc_number, $date);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'That PC is already reserved on this date.']);
        exit();
    }
    $chk->close();

    // Also check sit_ins — if someone is actively using it
    $chk2 = $conn->prepare(
        "SELECT id FROM sit_ins WHERE lab = ? AND pc_number = ? AND status = 'Active' LIMIT 1"
    );
    $chk2->bind_param('si', $lab, $pc_number);
    $chk2->execute();
    if ($chk2->get_result()->num_rows > 0) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'That PC is currently occupied.']);
        exit();
    }
    $chk2->close();

    // Insert reservation
    $ins = $conn->prepare(
        "INSERT INTO reservations (id_number, purpose, lab, pc_number, date, time_in, status)
         VALUES (?, ?, ?, ?, ?, ?, 'Pending')"
    );
    $ins->bind_param('sssiss', $id_number, $purpose, $lab, $pc_number, $date, $time_in);
    if ($ins->execute()) {
        $new_id = $conn->insert_id;
        ob_end_clean();
        echo json_encode(['success' => true, 'reservation_id' => $new_id]);
    } else {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Insert failed.']);
    }
    $ins->close();
    $conn->close();

} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
