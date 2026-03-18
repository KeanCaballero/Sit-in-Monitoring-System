<?php
header('Content-Type: application/json');
require_once 'config.php';
echo json_encode(['loggedIn' => isset($_SESSION['user_id'])]);
?>
