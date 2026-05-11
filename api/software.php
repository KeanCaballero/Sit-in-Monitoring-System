<?php
// api/software.php
// GET              → returns all software (students + admin)
// POST action=add  → admin: add software to DB
// POST action=delete → admin: remove software from DB
ini_set('display_errors', 0);
error_reporting(0);
ob_start();
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../config.php';

try {
    $conn = db_connect();
    $role = $_SESSION['user']['role'] ?? 'student';

    // Ensure softwares table exists
    $conn->query("
        CREATE TABLE IF NOT EXISTS `softwares` (
            `id`          INT AUTO_INCREMENT PRIMARY KEY,
            `name`        VARCHAR(120) NOT NULL,
            `category`    VARCHAR(80)  DEFAULT 'Other',
            `description` TEXT         DEFAULT NULL,
            `icon`        VARCHAR(60)  DEFAULT 'fa-box-archive',
            `color`       VARCHAR(20)  DEFAULT '#64748b',
            `labs`        VARCHAR(100) DEFAULT 'All',
            `created_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    /* ── POST ──────────────────────────────────────── */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($role !== 'admin') {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Admin only']);
            exit();
        }

        $data   = json_decode(file_get_contents('php://input'), true) ?: [];
        $action = $data['action'] ?? '';

        /* ADD */
        if ($action === 'add') {
            $name  = trim($data['name']        ?? '');
            $cat   = trim($data['category']    ?? 'Other');
            $desc  = trim($data['description'] ?? '');
            $icon  = trim($data['icon']        ?? 'fa-box-archive');
            $color = trim($data['color']       ?? '#64748b');
            $labs  = trim($data['labs']        ?? 'All'); // comma-separated e.g. "524,526" or "All"

            if (!$name) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Name required']);
                exit();
            }

            $stmt = $conn->prepare(
                "INSERT INTO softwares (name, category, description, icon, color, labs)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param('ssssss', $name, $cat, $desc, $icon, $color, $labs);
            $stmt->execute();
            $new_id = $conn->insert_id;
            $stmt->close();
            $conn->close();

            ob_end_clean();
            echo json_encode(['success' => true, 'id' => $new_id]);
            exit();
        }

        /* DELETE */
        if ($action === 'delete') {
            $id = (int)($data['id'] ?? 0);
            if (!$id) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Invalid ID']);
                exit();
            }
            $stmt = $conn->prepare("DELETE FROM softwares WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            $conn->close();

            ob_end_clean();
            echo json_encode(['success' => $affected > 0]);
            exit();
        }

        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        exit();
    }

    /* ── GET: return all software ──────────────────── */
    $lab_filter = trim($_GET['lab'] ?? '');
    if ($lab_filter) {
        $lf   = $conn->real_escape_string($lab_filter);
        $rows = $conn->query(
            "SELECT * FROM softwares
             WHERE labs = 'All' OR FIND_IN_SET('$lf', labs)
             ORDER BY created_at DESC"
        )->fetch_all(MYSQLI_ASSOC);
    } else {
        $rows = $conn->query("SELECT * FROM softwares ORDER BY created_at DESC")
                     ->fetch_all(MYSQLI_ASSOC);
    }

    $conn->close();
    ob_end_clean();
    echo json_encode($rows);

} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}