<?php
/**
 * api/leaderboard_public.php
 * Public endpoint — no login required.
 * Returns top students ranked by points for the landing-page leaderboard.
 *
 * Query params:
 *   ?limit=10   (default 10, max 50)
 */

ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config.php';

$limit = min(50, max(1, intval($_GET['limit'] ?? 10)));

$conn = db_connect();
if (!$conn) {
    echo json_encode(['success' => false, 'data' => []]);
    exit();
}

/**
 * Leaderboard logic (mirrors admin dashboard):
 *   points  = stored in users.points  (awarded by admin via Rewards)
 *   sit_ins = count of completed sessions
 *   We also compute total hours and average session length for display.
 */
$sql = "
    SELECT
        u.id_number,
        u.first_name,
        u.last_name,
        u.course,
        u.year_level,
        u.points,
        COUNT(s.id)                                           AS total_sitins,
        COALESCE(
            ROUND(
                SUM(
                    TIMESTAMPDIFF(MINUTE,
                        s.created_at,
                        COALESCE(s.timed_out_at, NOW())
                    )
                ) / 60.0, 1
            ), 0
        )                                                     AS total_hours,
        COALESCE(
            ROUND(
                AVG(
                    TIMESTAMPDIFF(MINUTE,
                        s.created_at,
                        COALESCE(s.timed_out_at, NOW())
                    )
                )
            ), 0
        )                                                     AS avg_minutes
    FROM users u
    LEFT JOIN sit_ins s ON s.id_number = u.id_number AND s.status = 'Done'
    WHERE u.role = 'student'
    GROUP BY u.id_number
    HAVING total_sitins > 0 OR u.points > 0
    ORDER BY u.points DESC, total_sitins DESC, total_hours DESC
    LIMIT ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $limit);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

// Clean output — don't expose id_number fully
$out = [];
foreach ($rows as $i => $row) {
    // Mask the ID: show only last 4 chars, e.g. "****-4567"
    $masked = preg_replace('/\d(?=\d{4})/', '*', $row['id_number']);

    $out[] = [
        'rank'        => $i + 1,
        'name'        => $row['first_name'] . ' ' . mb_substr($row['last_name'], 0, 1) . '.',
        'course'      => $row['course'],
        'year'        => $row['year_level'],
        'points'      => intval($row['points']),
        'total_sitins'=> intval($row['total_sitins']),
        'total_hours' => floatval($row['total_hours']),
        'avg_minutes' => intval($row['avg_minutes']),
        'id_masked'   => $masked,
    ];
}

echo json_encode(['success' => true, 'data' => $out]);