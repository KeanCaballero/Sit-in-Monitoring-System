<?php
/**
 * api/reservation_action.php
 * Handles: approve / reject / cancel reservation requests.
 * When a reservation is APPROVED it automatically inserts an Active sit-in
 * record so it appears immediately in Current Sit-In, Sit-in Records and Reports.
 *
 * Expected POST body (JSON):
 *   { "id": 5, "action": "approve" }   // action: approve | reject | cancel
 */

ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

session_start();

// ── Auth guard ──────────────────────────────────────────────────────────────
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['user']['role']) ||
    $_SESSION['user']['role'] !== 'admin'
) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../config.php';

// ── Parse input ─────────────────────────────────────────────────────────────
$raw    = file_get_contents('php://input');
$data   = json_decode($raw, true);

// Also accept form-encoded for convenience
if (!$data) {
    $data = $_POST;
}

$id     = intval($data['id']   ?? 0);
$action = trim($data['action'] ?? '');

if (!$id || !in_array($action, ['approve', 'reject', 'cancel'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$conn = db_connect();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'DB connection failed']);
    exit();
}

// ── Fetch reservation ────────────────────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT r.*, u.remaining_sessions, u.first_name, u.last_name
     FROM reservations r
     JOIN users u ON u.id_number = r.id_number
     WHERE r.id = ? AND r.status = 'Pending'
     LIMIT 1"
);
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$res) {
    echo json_encode(['success' => false, 'message' => 'Reservation not found or already processed']);
    $conn->close();
    exit();
}

$conn->begin_transaction();

try {
    // ── Map action → DB status ───────────────────────────────────────────────
    $statusMap = [
        'approve' => 'Approved',
        'reject'  => 'Rejected',
        'cancel'  => 'Cancelled',
    ];
    $newStatus = $statusMap[$action];

    // ── 1. Update reservation status ─────────────────────────────────────────
    $upd = $conn->prepare("UPDATE reservations SET status = ? WHERE id = ?");
    $upd->bind_param('si', $newStatus, $id);
    $upd->execute();
    $upd->close();

    // ── 2. If APPROVED → auto-create sit-in record ───────────────────────────
    if ($action === 'approve') {
        $idNumber       = $res['id_number'];
        $purpose        = $res['purpose'];
        $lab            = $res['lab'];
        $pcNumber       = $res['pc_number'];
        $sessAtEntry    = $res['remaining_sessions'];

        // Check: student must not already have an active sit-in
        $chk = $conn->prepare(
            "SELECT id FROM sit_ins WHERE id_number = ? AND status = 'Active' LIMIT 1"
        );
        $chk->bind_param('s', $idNumber);
        $chk->execute();
        $existing = $chk->get_result()->fetch_assoc();
        $chk->close();

        if ($existing) {
            // Student is already sitting in — still approve the reservation
            // but don't double-insert a sit-in.
            // You can change this to reject with an error if you prefer.
        } else {
            // Deduct one session from user
            if ($sessAtEntry > 0) {
                $decr = $conn->prepare(
                    "UPDATE users SET remaining_sessions = remaining_sessions - 1 WHERE id_number = ? AND remaining_sessions > 0"
                );
                $decr->bind_param('s', $idNumber);
                $decr->execute();
                $decr->close();

                // Re-read the updated session count for the sit-in record
                $sr = $conn->prepare("SELECT remaining_sessions FROM users WHERE id_number = ? LIMIT 1");
                $sr->bind_param('s', $idNumber);
                $sr->execute();
                $sessAtEntry = $sr->get_result()->fetch_assoc()['remaining_sessions'];
                $sr->close();
            }

            // Insert sit-in
            $ins = $conn->prepare(
                "INSERT INTO sit_ins (id_number, purpose, lab, pc_number, session_at_entry, status, created_at)
                 VALUES (?, ?, ?, ?, ?, 'Active', NOW())"
            );
            $ins->bind_param('sssii', $idNumber, $purpose, $lab, $pcNumber, $sessAtEntry);
            $ins->execute();
            $sitInId = $ins->insert_id;
            $ins->close();

            // ── 3. Send in-app notification to student ───────────────────────
            $msg   = "Your reservation for Lab {$lab} PC {$pcNumber} on {$res['date']} has been approved.";
            $title = 'Reservation Approved ✅';
            $type  = 'success';
            $notif = $conn->prepare(
                "INSERT INTO notifications (user_id_number, type, title, message) VALUES (?, ?, ?, ?)"
            );
            $notif->bind_param('ssss', $idNumber, $type, $title, $msg);
            $notif->execute();
            $notif->close();
        }
    } else {
        // Rejected / Cancelled → notify student
        $label = $action === 'reject' ? 'rejected ❌' : 'cancelled';
        $msg   = "Your reservation for Lab {$res['lab']} PC {$res['pc_number']} on {$res['date']} has been {$label}.";
        $title = $action === 'reject' ? 'Reservation Rejected ❌' : 'Reservation Cancelled';
        $type  = 'info';
        $notif = $conn->prepare(
            "INSERT INTO notifications (user_id_number, type, title, message) VALUES (?, ?, ?, ?)"
        );
        $idNumber = $res['id_number'];
        $notif->bind_param('ssss', $idNumber, $type, $title, $msg);
        $notif->execute();
        $notif->close();
    }

    $conn->commit();
    echo json_encode([
        'success' => true,
        'message' => "Reservation {$newStatus} successfully.",
        'status'  => $newStatus,
    ]);

} catch (Throwable $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
} finally {
    $conn->close();
}