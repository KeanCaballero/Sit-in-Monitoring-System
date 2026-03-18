<?php
// update_profile.php
ini_set('display_errors', 0);
error_reporting(0);
ob_start();
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit();
}

try {
    require_once 'config.php';
    $conn = db_connect();

    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (empty($data)) $data = $_POST;

    $user_id    = (int) $_SESSION['user_id'];
    $first_name  = trim($data['firstname']   ?? '');
    $last_name   = trim($data['lastname']    ?? '');
    $middle_name = trim($data['middlename']  ?? '');
    $email       = trim($data['email']       ?? '');
    $address     = trim($data['address']     ?? '');
    $course      = trim($data['course']      ?? '');
    $year_level  = trim($data['year_level']  ?? '');
    $new_pw      = trim($data['new_password']      ?? '');
    $confirm_pw  = trim($data['confirm_password']  ?? '');

    if (!$first_name || !$last_name || !$email || !$course || !$year_level) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit();
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
        exit();
    }

    // Check email not taken by another user
    $chk = $conn->prepare("SELECT id FROM `users` WHERE email = ? AND id != ? LIMIT 1");
    $chk->bind_param('si', $email, $user_id);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'That email is already used by another account.']);
        exit();
    }
    $chk->close();

    // Password change
    if ($new_pw !== '') {
        if ($new_pw !== $confirm_pw) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
            exit();
        }
        if (strlen($new_pw) < 6) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
            exit();
        }
        $hashed = password_hash($new_pw, PASSWORD_DEFAULT);
        $stmt = $conn->prepare(
            "UPDATE `users` SET
                first_name  = ?, last_name   = ?, middle_name = ?,
                email       = ?, address     = ?, course      = ?,
                year_level  = ?, password    = ?
             WHERE id = ? LIMIT 1"
        );
        $stmt->bind_param('ssssssssi',
            $first_name, $last_name, $middle_name,
            $email, $address, $course, $year_level,
            $hashed, $user_id
        );
    } else {
        $stmt = $conn->prepare(
            "UPDATE `users` SET
                first_name  = ?, last_name   = ?, middle_name = ?,
                email       = ?, address     = ?, course      = ?,
                year_level  = ?
             WHERE id = ? LIMIT 1"
        );
        $stmt->bind_param('sssssssi',
            $first_name, $last_name, $middle_name,
            $email, $address, $course, $year_level,
            $user_id
        );
    }

    if ($stmt->execute()) {
        // Sync session
        $_SESSION['user']['first_name']  = $first_name;
        $_SESSION['user']['last_name']   = $last_name;
        $_SESSION['user']['middle_name'] = $middle_name;
        $_SESSION['user']['email']       = $email;
        $_SESSION['user']['address']     = $address;
        $_SESSION['user']['course']      = $course;
        $_SESSION['user']['year_level']  = $year_level;

        ob_end_clean();
        echo json_encode([
            'success'    => true,
            'message'    => 'Profile updated successfully!',
            'firstname'  => $first_name,
            'lastname'   => $last_name,
            'course'     => $course,
            'year_level' => $year_level,
        ]);
    } else {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Update failed: ' . $stmt->error]);
    }
    $stmt->close();
    $conn->close();

} catch (Throwable $e) {
    ob_end_clean();
    http_response_code(200);
    echo json_encode(['success' => false,
        'message' => 'PHP error: ' . $e->getMessage()]);
}