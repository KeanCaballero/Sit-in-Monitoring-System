<?php
// api/pc_toggle_unavailable.php
// Admin endpoint to mark a PC as unavailable (broken) or clear the flag.
ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user']['role']) || $_SESSION['user']['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../config.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) $data = $_POST;

$lab = trim($data['lab'] ?? '');
$pc  = intval($data['pc'] ?? 0);
$action = trim($data['action'] ?? ''); // set | clear

if (!$lab || !$pc || !in_array($action, ['set','clear'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

try {
    $conn = db_connect();
    if (!$conn) throw new Exception('DB connection failed');

    // Ensure table exists
    $conn->query("CREATE TABLE IF NOT EXISTS pc_unavailable (
      id INT AUTO_INCREMENT PRIMARY KEY,
      lab VARCHAR(10) NOT NULL,
      pc_number INT NOT NULL,
      reason TEXT DEFAULT NULL,
      admin_id INT DEFAULT NULL,
      start_date DATE NOT NULL,
      end_date DATE DEFAULT NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY lab_pc_date (lab, pc_number, start_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $today = date('Y-m-d');
    $adminId = $_SESSION['user_id'];

    if ($action === 'set') {
        // Insert a record for today (start_date = today). If exists, ignore.
        $ins = $conn->prepare("INSERT IGNORE INTO pc_unavailable (lab, pc_number, admin_id, start_date) VALUES (?, ?, ?, ?)");
        $ins->bind_param('siss', $lab, $pc, $adminId, $today);
        $ok = $ins->execute();
        $ins->close();
        if ($ok) {
            echo json_encode(['success' => true, 'message' => "PC {$pc} marked unavailable for Lab {$lab}"]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to mark unavailable']);
        }
        $conn->close();
        exit();
    }

    // clear
    $del = $conn->prepare("DELETE FROM pc_unavailable WHERE lab = ? AND pc_number = ? AND start_date = ?");
    $del->bind_param('sis', $lab, $pc, $today);
    $del->execute();
    $affected = $del->affected_rows;
    $del->close();
    $conn->close();

    if ($affected > 0) echo json_encode(['success' => true, 'message' => "Cleared unavailable flag for PC {$pc} in Lab {$lab}"]); else echo json_encode(['success' => false, 'message' => 'No unavailable flag found']);

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
