<?php
// search_student.php
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
    require_once '../config.php';
    $conn = db_connect();

    $q    = trim($_GET['q'] ?? '');
    if ($q === '') {
        ob_end_clean();
        echo json_encode([]);
        exit();
    }

    $like = '%' . $q . '%';
    $stmt = $conn->prepare(
        "SELECT id, id_number, first_name, last_name, course, year_level,
                remaining_sessions
         FROM `users`
         WHERE id_number  LIKE ?
            OR first_name LIKE ?
            OR last_name  LIKE ?
            OR CONCAT(first_name,' ',last_name) LIKE ?
         ORDER BY last_name, first_name
         LIMIT 20"
    );
    $stmt->bind_param('ssss', $like, $like, $like, $like);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();

    // Rename keys to match what the dashboard JS expects
    $out = array_map(fn($r) => [
        'id'                 => $r['id_number'],
        'firstname'          => $r['first_name'],
        'lastname'           => $r['last_name'],
        'course'             => $r['course'],
        'year'               => $r['year_level'],
        'remaining_sessions' => $r['remaining_sessions'] ?? 30,
    ], $rows);

    ob_end_clean();
    echo json_encode($out);

} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode([]);
}