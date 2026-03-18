<?php

define('DB_HOST',    'localhost');
define('DB_NAME',    'sit_in_monitoring');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

function db_connect() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false,
            'message' => 'DB connection failed: ' . $conn->connect_error]);
        exit();
    }
    $conn->set_charset(DB_CHARSET);
    return $conn;
}