<?php
// api/lab_pc_status.php
// Returns which PCs are occupied in a lab on a given date/time.
// GET params: lab, date (Y-m-d), time (H:i)
ini_set('display_errors', 0);
error_reporting(0);
ob_start();
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once '../config.php';

try {
    $conn = db_connect();

    $lab  = trim($_GET['lab']  ?? '');
    $date = trim($_GET['date'] ?? date('Y-m-d'));
    $time = trim($_GET['time'] ?? date('H:i'));

    if (!$lab) {
        ob_end_clean();
        echo json_encode(['error' => 'Lab required']);
        exit();
    }

    // PC counts per lab
    $pc_counts = ['524' => 40, '526' => 40, '528' => 40, '530' => 40];
    $total_pcs = $pc_counts[$lab] ?? 40;

    // Occupied PCs from active sit-ins in this lab right now
    $occupied_sitin = [];
    $stmt = $conn->prepare(
        "SELECT pc_number FROM sit_ins
         WHERE lab = ? AND status = 'Active' AND pc_number IS NOT NULL"
    );
    $stmt->bind_param('s', $lab);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as $r) {
        if ($r['pc_number']) $occupied_sitin[] = (int)$r['pc_number'];
    }
    $stmt->close();

    // Reserved PCs on this date/time window (Approved/Pending reservations)
    $reserved = [];
    $tbl_check = $conn->query("SHOW TABLES LIKE 'reservations'");
    if ($tbl_check && $tbl_check->num_rows > 0) {
        $stmt2 = $conn->prepare(
            "SELECT pc_number, id_number, status FROM reservations
             WHERE lab = ? AND date = ? AND status IN ('Approved','Pending')
             AND pc_number IS NOT NULL"
        );
        $stmt2->bind_param('ss', $lab, $date);
        $stmt2->execute();
        $res_rows = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($res_rows as $r) {
            $reserved[] = [
                'pc'     => (int)$r['pc_number'],
                'status' => $r['status'],
            ];
        }
        $stmt2->close();
    }

    $conn->close();

    // Build pc_map: pc_number => status
    $pc_map = [];
    for ($i = 1; $i <= $total_pcs; $i++) {
        $pc_map[$i] = 'available';
    }
    foreach ($occupied_sitin as $pc) {
        if (isset($pc_map[$pc])) $pc_map[$pc] = 'occupied';
    }
    foreach ($reserved as $r) {
        if (isset($pc_map[$r['pc']]) && $pc_map[$r['pc']] === 'available') {
            $pc_map[$r['pc']] = $r['status'] === 'Approved' ? 'reserved' : 'pending';
        }
    }

    ob_end_clean();
    echo json_encode([
        'lab'       => $lab,
        'total_pcs' => $total_pcs,
        'date'      => $date,
        'pc_map'    => $pc_map,
        'occupied_count'  => count($occupied_sitin),
        'reserved_count'  => count($reserved),
        'available_count' => count(array_filter($pc_map, fn($s) => $s === 'available')),
    ]);

} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode(['error' => $e->getMessage()]);
}