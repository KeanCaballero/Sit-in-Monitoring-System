<?php
// api/reports.php
// GET ?type=summary         → registered / total_sitin / active counts
// GET ?type=daily           → last 14 days sit-in counts
// GET ?type=weekly          → last 12 weeks sit-in counts
// GET ?type=monthly         → last 12 months sit-in counts
// GET ?type=by_purpose      → sit-ins grouped by purpose
// GET ?type=by_lab          → sit-ins grouped by lab
// GET ?type=sitin_list      → full sit-in list (supports ?limit=N &date_from= &date_to=)
// GET ?type=feedback        → all feedback with student + sit-in info
ini_set('display_errors', 0); error_reporting(0); ob_start();
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['user']['role'] ?? '') !== 'admin') { ob_end_clean(); echo json_encode([]); exit(); }
require_once '../config.php';

try {
    $conn = db_connect();
    $type = trim($_GET['type'] ?? 'summary');

    /* ── summary ──────────────────────────────────── */
    if ($type === 'summary') {
        $reg    = (int)$conn->query("SELECT COUNT(*) c FROM users WHERE role='student'")->fetch_assoc()['c'];
        $total  = (int)$conn->query("SELECT COUNT(*) c FROM sit_ins")->fetch_assoc()['c'];
        $active = (int)$conn->query("SELECT COUNT(*) c FROM sit_ins WHERE status='Active'")->fetch_assoc()['c'];
        ob_end_clean();
        echo json_encode(['registered' => $reg, 'total_sitin' => $total, 'active' => $active]);
        exit();
    }

    /* ── daily ────────────────────────────────────── */
    if ($type === 'daily') {
        $res = $conn->query(
            "SELECT DATE(created_at) AS day, COUNT(*) AS count
             FROM sit_ins
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
             GROUP BY DATE(created_at)
             ORDER BY day ASC"
        );
        ob_end_clean(); echo json_encode($res->fetch_all(MYSQLI_ASSOC)); exit();
    }

    /* ── weekly ───────────────────────────────────── */
    if ($type === 'weekly') {
        $res = $conn->query(
            "SELECT DATE_FORMAT(MIN(created_at),'%b %d') AS week, COUNT(*) AS count
             FROM sit_ins
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 WEEK)
             GROUP BY YEARWEEK(created_at,1)
             ORDER BY YEARWEEK(created_at,1) ASC"
        );
        ob_end_clean(); echo json_encode($res->fetch_all(MYSQLI_ASSOC)); exit();
    }

    /* ── monthly ──────────────────────────────────── */
    if ($type === 'monthly') {
        $res = $conn->query(
            "SELECT DATE_FORMAT(created_at,'%b %Y') AS month, COUNT(*) AS count
             FROM sit_ins
             GROUP BY DATE_FORMAT(created_at,'%Y-%m')
             ORDER BY MIN(created_at) ASC
             LIMIT 12"
        );
        ob_end_clean(); echo json_encode($res->fetch_all(MYSQLI_ASSOC)); exit();
    }

    /* ── by purpose ───────────────────────────────── */
    if ($type === 'by_purpose') {
        $res = $conn->query(
            "SELECT purpose, COUNT(*) AS count
             FROM sit_ins
             GROUP BY purpose
             ORDER BY count DESC
             LIMIT 10"
        );
        ob_end_clean(); echo json_encode($res->fetch_all(MYSQLI_ASSOC)); exit();
    }

    /* ── by lab ───────────────────────────────────── */
    if ($type === 'by_lab') {
        $res = $conn->query(
            "SELECT lab, COUNT(*) AS count
             FROM sit_ins
             GROUP BY lab
             ORDER BY count DESC"
        );
        ob_end_clean(); echo json_encode($res->fetch_all(MYSQLI_ASSOC)); exit();
    }

    /* ── sitin_list ───────────────────────────────── */
    if ($type === 'sitin_list') {
        $limit     = (int)($_GET['limit']     ?? 0);   // 0 = all
        $date_from = trim($_GET['date_from']  ?? '');
        $date_to   = trim($_GET['date_to']    ?? '');

        $where  = "WHERE 1=1";
        $params = [];
        $types  = '';

        if ($date_from) { $where .= " AND DATE(s.created_at) >= ?"; $params[] = $date_from; $types .= 's'; }
        if ($date_to)   { $where .= " AND DATE(s.created_at) <= ?"; $params[] = $date_to;   $types .= 's'; }

        $sql = "SELECT s.id, s.id_number,
                       CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) AS name,
                       u.course, s.purpose, s.lab, s.pc_number,
                       s.created_at, s.timed_out_at, s.status, s.session_at_entry
                FROM sit_ins s
                LEFT JOIN users u ON u.id_number = s.id_number
                $where
                ORDER BY s.created_at DESC";

        if ($limit > 0) $sql .= " LIMIT " . (int)$limit;

        if ($params) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        } else {
            $rows = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
        }

        ob_end_clean(); echo json_encode($rows); exit();
    }

    /* ── feedback ─────────────────────────────────── */
    if ($type === 'feedback') {
        $check = $conn->query("SHOW TABLES LIKE 'feedback'");
        if (!$check || $check->num_rows === 0) {
            ob_end_clean(); echo json_encode([]); exit();
        }
        $res = $conn->query(
            "SELECT f.id, f.sit_in_id, f.id_number, f.rating, f.message, f.created_at,
                    CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) AS name,
                    u.course, s.lab, s.purpose
             FROM feedback f
             LEFT JOIN users u   ON u.id_number = f.id_number
             LEFT JOIN sit_ins s ON s.id = f.sit_in_id
             ORDER BY f.created_at DESC
             LIMIT 200"
        );
        ob_end_clean();
        echo json_encode($res ? $res->fetch_all(MYSQLI_ASSOC) : []);
        exit();
    }

    ob_end_clean();
    echo json_encode([]);

} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode([]);
}