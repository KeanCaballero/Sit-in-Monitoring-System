<?php
// admin_sitin_fetch.php
ini_set('display_errors', 0);
error_reporting(0);
ob_start();
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
    ob_end_clean();
    echo json_encode([]);
    exit();
}

try {
    require_once 'config.php';
    $conn = db_connect();

    // Optional filter: ?filter=active  or  ?filter=all  (default all)
    $filter = $_GET['filter'] ?? 'all';

    if ($filter === 'active') {
        $stmt = $conn->prepare(
            "SELECT s.id AS sit_id, s.id_number,
                    CONCAT(u.first_name,' ',u.last_name) AS name,
                    s.purpose, s.lab, s.session_at_entry AS session,
                    s.status, s.created_at
             FROM sit_ins s
             JOIN users u ON u.id_number = s.id_number
             WHERE s.status = 'Active'
             ORDER BY s.created_at DESC"
        );
    } else {
        $stmt = $conn->prepare(
            "SELECT s.id AS sit_id, s.id_number,
                    CONCAT(u.first_name,' ',u.last_name) AS name,
                    s.purpose, s.lab, s.session_at_entry AS session,
                    s.status, s.created_at
             FROM sit_ins s
             JOIN users u ON u.id_number = s.id_number
             ORDER BY s.created_at DESC"
        );
    }

    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();

    ob_end_clean();
    echo json_encode($rows);

} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode([]);
}