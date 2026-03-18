<?php
header('Content-Type: application/json');
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idNumber = trim($_POST['idNumber']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id_number = ?");
    $stmt->execute([$idNumber]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user'] = $user;
        echo json_encode(['success' => true, 'message' => 'Login successful', 'user' => ['id_number' => $user['id_number'], 'name' => $user['first_name'] . ' ' . $user['last_name']]]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>
