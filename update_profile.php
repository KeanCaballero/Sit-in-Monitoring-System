<?php
// update_profile.php
// Accepts BOTH snake_case (first_name) AND camelCase (firstname) field names
// so it works regardless of which page submits to it.
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

    $user_id = (int) $_SESSION['user_id'];

    // ── ACCEPT BOTH NAMING CONVENTIONS ──
    // snake_case from dashboard.php  OR  camelCase from edit_profile.php
    $first_name  = trim($data['first_name']  ?? $data['firstname']  ?? '');
    $last_name   = trim($data['last_name']   ?? $data['lastname']   ?? '');
    $middle_name = trim($data['middle_name'] ?? $data['middlename'] ?? '');
    $email       = trim($data['email']       ?? '');
    $address     = trim($data['address']     ?? '');
    $course      = trim($data['course']      ?? '');
    $year_level  = trim($data['year_level']  ?? '');
    $new_pw      = trim($data['new_password'] ?? $data['password']  ?? '');
    $confirm_pw  = trim($data['confirm_password'] ?? $data['password2'] ?? $new_pw);

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

    // Email uniqueness check
    $chk = $conn->prepare("SELECT id FROM `users` WHERE email = ? AND id != ? LIMIT 1");
    $chk->bind_param('si', $email, $user_id);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'That email is already used by another account.']);
        exit();
    }
    $chk->close();

    // Password change (only if user typed one)
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
                first_name  = ?, last_name  = ?, middle_name = ?,
                email       = ?, address    = ?, course      = ?,
                year_level  = ?, password   = ?
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
                first_name  = ?, last_name  = ?, middle_name = ?,
                email       = ?, address    = ?, course      = ?,
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
        // Sync session so refresh shows new info
        $_SESSION['user']['first_name']  = $first_name;
        $_SESSION['user']['last_name']   = $last_name;
        $_SESSION['user']['middle_name'] = $middle_name;
        $_SESSION['user']['email']       = $email;
        $_SESSION['user']['address']     = $address;
        $_SESSION['user']['course']      = $course;
        $_SESSION['user']['year_level']  = $year_level;

        // Also fetch current profile_photo from DB and sync into session/response
        $pf = '';
        try {
            $s2 = $conn->prepare("SELECT profile_photo FROM `users` WHERE id = ? LIMIT 1");
            $s2->bind_param('i', $user_id);
            $s2->execute();
            $r2 = $s2->get_result()->fetch_assoc();
            $s2->close();
            if ($r2 && !empty($r2['profile_photo'])) {
                $pf = $r2['profile_photo'];
                $_SESSION['user']['profile_photo'] = $pf;
            }
        } catch (Throwable $ee) {
            // ignore
        }

        ob_end_clean();
        echo json_encode([
            'success'     => true,
            'message'     => 'Profile updated successfully!',
            // Return BOTH naming conventions so any caller works
            'first_name'  => $first_name,
            'last_name'   => $last_name,
            'firstname'   => $first_name,
            'lastname'    => $last_name,
            'middle_name' => $middle_name,
            'middlename'  => $middle_name,
            'email'       => $email,
            'course'      => $course,
            'year_level'  => $year_level,
            'profile_photo' => $pf,
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
    echo json_encode(['success' => false, 'message' => 'PHP error: ' . $e->getMessage()]);
}