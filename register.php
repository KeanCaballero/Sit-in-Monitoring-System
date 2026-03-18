<?php
header('Content-Type: application/json');
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idNumber = trim($_POST['idNumber']);
    $email = trim($_POST['email']);
    $firstName = trim($_POST['firstName']);
    $lastName = trim($_POST['lastName']);
    $middleName = trim($_POST['middleName'] ?? '');
    $address = trim($_POST['address']);
    $course = $_POST['course'];
    $yearLevel = (int)$_POST['courseLevel'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("INSERT INTO users (id_number, email, first_name, last_name, middle_name, address, course, year_level, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$idNumber, $email, $firstName, $lastName, $middleName, $address, $course, $yearLevel, $password]);
        echo json_encode(['success' => true, 'message' => 'Account created! Please login.']);
    } catch(PDOException $e) {
        $error = $e->getCode() === 23000 ? 'ID/Email already exists' : 'Registration failed';
        echo json_encode(['success' => false, 'message' => $error]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>
