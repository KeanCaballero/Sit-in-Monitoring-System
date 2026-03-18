<?php
// admin_stats.php
ini_set('display_errors', 0);
error_reporting(0);
ob_start();
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    echo json_encode(['registered'=>0,'current'=>0,'total'=>0,'purposes'=>[]]);
    exit();
}

try {
    require_once 'config.php';
    $conn = db_connect();

    // Total registered students
    $r1 = $conn->query("SELECT COUNT(*) AS cnt FROM `users`");
    $registered = $r1 ? (int)$r1->fetch_assoc()['cnt'] : 0;

    // Current / total sit-ins (check if sit_in table exists)
    $current = 0; $total = 0; $purposes = [];
    $tc = $conn->query("SHOW TABLES LIKE 'sit_in'");
    if ($tc && $tc->num_rows > 0) {
        $r2 = $conn->query("SELECT COUNT(*) AS cnt FROM `sit_in` WHERE status='Active'");
        if ($r2) $current = (int)$r2->fetch_assoc()['cnt'];
        $r3 = $conn->query("SELECT COUNT(*) AS cnt FROM `sit_in`");
        if ($r3) $total = (int)$r3->fetch_assoc()['cnt'];
        $r4 = $conn->query("SELECT purpose, COUNT(*) AS cnt FROM `sit_in` GROUP BY purpose");
        if ($r4) {
            while ($row = $r4->fetch_assoc()) {
                $purposes[$row['purpose']] = (int)$row['cnt'];
            }
        }
    }

    $conn->close();
    ob_end_clean();
    echo json_encode(compact('registered','current','total','purposes'));

} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode(['registered'=>0,'current'=>0,'total'=>0,'purposes'=>[]]);
}
