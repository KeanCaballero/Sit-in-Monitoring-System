<?php
// register.php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);
ob_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit();
}

try {
    // ── DB ────────────────────────────────────────────────────
    $conn = new mysqli('localhost', 'root', '', 'sit_in_monitoring');
    if ($conn->connect_errno) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->connect_error]);
        exit();
    }
    $conn->set_charset('utf8mb4');

    // ── Read fields — exact names from your register.html ─────
    $idNumber   = trim($_POST['idNumber']    ?? '');
    $email      = trim($_POST['email']       ?? '');
    $firstName  = trim($_POST['firstName']   ?? '');
    $lastName   = trim($_POST['lastName']    ?? '');
    $middleName = trim($_POST['middleName']  ?? '');
    $address    = trim($_POST['address']     ?? '');
    $course     = trim($_POST['course']      ?? '');
    $yearLevel  = trim($_POST['courseLevel'] ?? ''); // your form uses courseLevel
    $password   = $_POST['password']         ?? '';
    $repeatPw   = $_POST['repeatPassword']   ?? '';

    // ── Validate ──────────────────────────────────────────────
    if (!$idNumber || !$email || !$firstName || !$lastName || !$address || !$course || !$yearLevel || !$password) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit();
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
        exit();
    }
    if (strlen($password) < 6) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
        exit();
    }
    if ($password !== $repeatPw) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
        exit();
    }

    // ── Check duplicate ID ────────────────────────────────────
    $chk = $conn->prepare("SELECT id FROM `users` WHERE `id_number` = ? LIMIT 1");
    $chk->bind_param('s', $idNumber);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        $chk->close(); $conn->close(); ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'An account with that ID number already exists.']);
        exit();
    }
    $chk->close();

    // ── Check duplicate email ─────────────────────────────────
    $chk2 = $conn->prepare("SELECT id FROM `users` WHERE `email` = ? LIMIT 1");
    $chk2->bind_param('s', $email);
    $chk2->execute();
    if ($chk2->get_result()->num_rows > 0) {
        $chk2->close(); $conn->close(); ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'An account with that email already exists.']);
        exit();
    }
    $chk2->close();

    // ── Add missing columns if they don't exist yet ───────────
    // (safe to run every time — IF NOT EXISTS prevents errors)
    $conn->query("ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `role` VARCHAR(10) NOT NULL DEFAULT 'student'");
    $conn->query("ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `remaining_sessions` INT NOT NULL DEFAULT 30");
    $conn->query("ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `profile_photo` VARCHAR(255) DEFAULT NULL");

    // ── Insert ────────────────────────────────────────────────
    $hashed = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare(
        "INSERT INTO `users`
            (id_number, email, first_name, last_name, middle_name,
             address, course, year_level, password, role, remaining_sessions)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'student', 30)"
    );
    $stmt->bind_param('sssssssss',
        $idNumber, $email, $firstName, $lastName, $middleName,
        $address, $course, $yearLevel, $hashed
    );

    if ($stmt->execute()) {
        $stmt->close(); $conn->close(); ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Account created! Please login.']);
    } else {
        $err = $stmt->error;
        $stmt->close(); $conn->close(); ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Registration failed: ' . $err]);
    }

} catch (Throwable $e) {
    ob_end_clean();
    http_response_code(200);
    echo json_encode([
        'success' => false,
        'message' => 'PHP error: ' . $e->getMessage() . ' (line ' . $e->getLine() . ')',
    ]);
}