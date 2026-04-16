<?php
// admin_students.php
ini_set('display_errors', 0);
error_reporting(0);
ob_start();
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
    ob_end_clean(); echo json_encode([]); exit();
}

try {
    require_once 'config.php';
    $conn = db_connect();

    $res  = $conn->query(
        "SELECT id, id_number, first_name, last_name, middle_name,
                course, year_level, email, remaining_sessions
         FROM `users`
         ORDER BY last_name, first_name"
    );

    $rows = [];
    if ($res && $res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = [
                'id'                 => $row['id'],
                'id_number'          => $row['id_number'],
                'first_name'         => $row['first_name'],
                'last_name'          => $row['last_name'],
                'middle_name'        => $row['middle_name'] ?? '',
                'course'             => $row['course'],
                'year_level'         => $row['year_level'],
                'email'              => $row['email'],
                'remaining_sessions' => $row['remaining_sessions'] ?? 30,
            ];
        }
    }
    $conn->close();
    ob_end_clean();
    echo json_encode($rows);

} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode([]);
}
