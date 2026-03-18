<?php
// login.php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);
ob_start();
header('Content-Type: application/json; charset=utf-8');

try {
    session_start();

    $DB_HOST = 'localhost';
    $DB_NAME = 'sit_in_monitoring';
    $DB_USER = 'root';
    $DB_PASS = '';

    // Already logged in
    if (!empty($_SESSION['user_id'])) {
        $role = $_SESSION['user']['role'] ?? 'student';
        ob_end_clean();
        echo json_encode([
            'success'  => true,
            'role'     => $role,
            'redirect' => $role === 'admin' ? 'admin_dashboard.php' : 'dashboard.php',
        ]);
        exit();
    }

    // Read JSON body
    $raw  = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (empty($body)) $body = $_POST;

    $id_number = trim($body['id_number'] ?? '');
    $password  = trim($body['password']  ?? '');

    if ($id_number === '' || $password === '') {
        ob_end_clean();
        echo json_encode(['success' => false,
            'message' => 'Please enter your ID number and password.']);
        exit();
    }

    // Connect
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    if ($conn->connect_errno) {
        ob_end_clean();
        echo json_encode(['success' => false,
            'message' => 'DB error: ' . $conn->connect_error]);
        exit();
    }
    $conn->set_charset('utf8mb4');

    $user = null;
    $role = 'student';

    // ── STEP 1: Check users table ──────────────────────────────
    $stmt = $conn->prepare("SELECT * FROM `users` WHERE `id_number` = ? LIMIT 1");
    $stmt->bind_param('s', $id_number);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user) {
        // Check if users table has a 'role' column
        $rc = $conn->query("SHOW COLUMNS FROM `users` LIKE 'role'");
        if ($rc && $rc->num_rows > 0) {
            // Use the role column directly
            $role = ($user['role'] ?? 'student') === 'admin' ? 'admin' : 'student';
        } else {
            // No role column — everyone in users table is a student by default
            $role = 'student';
        }
    }

    // ── STEP 2: If not found in users, check admin table ───────
    if (!$user) {
        $admin_tbl = $conn->query("SHOW TABLES LIKE 'admin'");
        if ($admin_tbl && $admin_tbl->num_rows > 0) {
            // Detect which column is the login identifier
            $id_col = 'username'; // default for admin table
            foreach (['id_number', 'username', 'email'] as $try) {
                $cc = $conn->query("SHOW COLUMNS FROM `admin` LIKE '$try'");
                if ($cc && $cc->num_rows > 0) { $id_col = $try; break; }
            }
            $stmt2 = $conn->prepare(
                "SELECT * FROM `admin` WHERE `$id_col` = ? LIMIT 1"
            );
            $stmt2->bind_param('s', $id_number);
            $stmt2->execute();
            $user = $stmt2->get_result()->fetch_assoc();
            $stmt2->close();
            if ($user) $role = 'admin';
        }
    }

    $conn->close();

    if (!$user) {
        ob_end_clean();
        echo json_encode(['success' => false,
            'message' => 'No account found with that ID number.']);
        exit();
    }

    // ── Verify password ────────────────────────────────────────
    $db_pw    = $user['password'] ?? '';
    $verified = password_verify($password, $db_pw)
             || ($password === $db_pw);

    if (!$verified) {
        ob_end_clean();
        echo json_encode(['success' => false,
            'message' => 'Incorrect password. Please try again.']);
        exit();
    }

    // ── Set session ────────────────────────────────────────────
    $_SESSION['user_id']      = $user['id'];
    $_SESSION['user']         = $user;
    $_SESSION['user']['role'] = $role;

    ob_end_clean();
    echo json_encode([
        'success'  => true,
        'role'     => $role,
        'redirect' => $role === 'admin' ? 'admin_dashboard.php' : 'dashboard.php',
    ]);

} catch (Throwable $e) {
    ob_end_clean();
    http_response_code(200);
    echo json_encode([
        'success' => false,
        'message' => 'PHP error: ' . $e->getMessage() . ' (line ' . $e->getLine() . ')',
    ]);
}