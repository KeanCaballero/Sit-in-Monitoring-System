<?php
// db_fix.php
// ============================================================
// PUT THIS FILE in: C:\xampp\htdocs\sit-in monitoring system\
// THEN OPEN:  http://localhost/sit-in%20monitoring%20system/db_fix.php
// DELETE THIS FILE after it shows "All done!"
// ============================================================
$host = 'localhost';
$db   = 'sit_in_monitoring';
$user = 'root';
$pass = '';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('<b style="color:red">❌ Cannot connect: ' . $conn->connect_error . '</b>');
}
$conn->set_charset('utf8mb4');
$results = [];

// Helper: run a query, don't die on duplicate column/key errors
function safeQuery($conn, $sql, $label) {
    global $results;
    try {
        $conn->query($sql);
        $results[] = ['ok', "✅ $label"];
    } catch (mysqli_sql_exception $e) {
        $errno = $e->getCode();
        // 1060 = Duplicate column name (already exists — that's fine)
        // 1061 = Duplicate key name
        if ($errno == 1060 || $errno == 1061) {
            $results[] = ['skip', "⏭️ $label — already exists (skipped)"];
        } else {
            $results[] = ['err', "❌ $label — ERROR: " . $e->getMessage()];
        }
    }
}

// ── 1. FIX users TABLE ───────────────────────────────────────
safeQuery($conn,
    "ALTER TABLE `users` ADD COLUMN `middle_name` VARCHAR(100) DEFAULT '' AFTER `last_name`",
    "users: add middle_name"
);
safeQuery($conn,
    "ALTER TABLE `users` ADD COLUMN `address` VARCHAR(255) DEFAULT ''",
    "users: add address"
);
safeQuery($conn,
    "ALTER TABLE `users` ADD COLUMN `profile_photo` VARCHAR(255) DEFAULT ''",
    "users: add profile_photo"
);
safeQuery($conn,
    "ALTER TABLE `users` ADD COLUMN `remaining_sessions` INT DEFAULT 30",
    "users: add remaining_sessions"
);
safeQuery($conn,
    "ALTER TABLE `users` ADD COLUMN `role` VARCHAR(20) DEFAULT 'student'",
    "users: add role"
);
safeQuery($conn,
    "ALTER TABLE `users` ADD COLUMN `points` INT DEFAULT 0",
    "users: add points"
);

// ── 2. FIX sit_ins TABLE ─────────────────────────────────────
$check = $conn->query("SHOW TABLES LIKE 'sit_ins'");
if ($check->num_rows === 0) {
    safeQuery($conn,
        "CREATE TABLE `sit_ins` (
            `id`               INT AUTO_INCREMENT PRIMARY KEY,
            `id_number`        VARCHAR(20) NOT NULL,
            `purpose`          VARCHAR(255) NOT NULL,
            `lab`              VARCHAR(50) NOT NULL,
            `pc_number`        INT DEFAULT NULL,
            `session_at_entry` INT DEFAULT 30,
            `status`           VARCHAR(20) DEFAULT 'Active',
            `created_at`       DATETIME DEFAULT CURRENT_TIMESTAMP,
            `timed_out_at`     DATETIME DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "sit_ins: CREATE table"
    );
} else {
    safeQuery($conn,
        "ALTER TABLE `sit_ins` ADD COLUMN `pc_number` INT DEFAULT NULL AFTER `lab`",
        "sit_ins: add pc_number"
    );
    safeQuery($conn,
        "ALTER TABLE `sit_ins` ADD COLUMN `session_at_entry` INT DEFAULT 30",
        "sit_ins: add session_at_entry"
    );
    safeQuery($conn,
        "ALTER TABLE `sit_ins` ADD COLUMN `timed_out_at` DATETIME DEFAULT NULL",
        "sit_ins: add timed_out_at"
    );
    safeQuery($conn,
        "ALTER TABLE `sit_ins` ADD COLUMN `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP",
        "sit_ins: add created_at"
    );
}

// ── 3. FIX reservations TABLE ────────────────────────────────
$check2 = $conn->query("SHOW TABLES LIKE 'reservations'");
if ($check2->num_rows === 0) {
    safeQuery($conn,
        "CREATE TABLE `reservations` (
            `id`           INT AUTO_INCREMENT PRIMARY KEY,
            `id_number`    VARCHAR(20) NOT NULL,
            `purpose`      VARCHAR(255) NOT NULL,
            `lab`          VARCHAR(50) NOT NULL,
            `pc_number`    INT DEFAULT NULL,
            `date`         DATE NOT NULL,
            `time_in`      TIME DEFAULT NULL,
            `status`       VARCHAR(20) DEFAULT 'Pending',
            `created_at`   DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "reservations: CREATE table"
    );
} else {
    safeQuery($conn,
        "ALTER TABLE `reservations` ADD COLUMN `pc_number` INT DEFAULT NULL",
        "reservations: add pc_number"
    );
    safeQuery($conn,
        "ALTER TABLE `reservations` ADD COLUMN `time_in` TIME DEFAULT NULL",
        "reservations: add time_in"
    );
    safeQuery($conn,
        "ALTER TABLE `reservations` ADD COLUMN `status` VARCHAR(20) DEFAULT 'Pending'",
        "reservations: add status"
    );
    safeQuery($conn,
        "ALTER TABLE `reservations` ADD COLUMN `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP",
        "reservations: add created_at"
    );
    safeQuery($conn,
        "ALTER TABLE `reservations` ADD COLUMN `date` DATE NOT NULL DEFAULT '2025-01-01'",
        "reservations: add date"
    );
    safeQuery($conn,
        "ALTER TABLE `reservations` ADD COLUMN `purpose` VARCHAR(255) DEFAULT ''",
        "reservations: add purpose"
    );
    safeQuery($conn,
        "ALTER TABLE `reservations` ADD COLUMN `id_number` VARCHAR(20) DEFAULT ''",
        "reservations: add id_number"
    );
    safeQuery($conn,
        "ALTER TABLE `reservations` ADD COLUMN `lab` VARCHAR(50) DEFAULT ''",
        "reservations: add lab"
    );
}

// ── 4. CREATE missing tables ─────────────────────────────────
safeQuery($conn,
    "CREATE TABLE IF NOT EXISTS `announcements` (
        `id`         INT AUTO_INCREMENT PRIMARY KEY,
        `title`      VARCHAR(255) DEFAULT '',
        `message`    TEXT NOT NULL,
        `created_by` VARCHAR(50) DEFAULT 'admin',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "announcements: CREATE (if not exists)"
);
safeQuery($conn,
    "CREATE TABLE IF NOT EXISTS `feedback` (
        `id`          INT AUTO_INCREMENT PRIMARY KEY,
        `sit_in_id`   INT DEFAULT NULL,
        `id_number`   VARCHAR(20) NOT NULL,
        `rating`      TINYINT DEFAULT 5,
        `message`     TEXT DEFAULT NULL,
        `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "feedback: CREATE (if not exists)"
);
safeQuery($conn,
    "CREATE TABLE IF NOT EXISTS `points_log` (
        `id`          INT AUTO_INCREMENT PRIMARY KEY,
        `id_number`   VARCHAR(20) NOT NULL,
        `points`      INT NOT NULL,
        `reason`      VARCHAR(255) DEFAULT '',
        `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "points_log: CREATE (if not exists)"
);

// ── 5. Make sure there's an admin account ────────────────────
try {
    $adminCheck = $conn->query("SELECT id FROM `users` WHERE role='admin' LIMIT 1");
    if ($adminCheck && $adminCheck->num_rows === 0) {
        $hashed = password_hash('admin123', PASSWORD_DEFAULT);
        $conn->query("INSERT INTO `users` (id_number, first_name, last_name, email, password, role, remaining_sessions)
                      VALUES ('ADMIN-001', 'CCS', 'Admin', 'admin@uc.edu.ph', '$hashed', 'admin', 30)");
        $results[] = ['ok', '✅ Admin account created — ID: ADMIN-001, Password: admin123'];
    } else {
        $results[] = ['skip', '⏭️ Admin account already exists (skipped)'];
    }
} catch (mysqli_sql_exception $e) {
    $results[] = ['err', '❌ Admin check — ERROR: ' . $e->getMessage()];
}

$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
  <title>DB Fix — CCS Sit-in System</title>
  <style>
    body { font-family: Arial, sans-serif; background: #0f172a; color: #e2e8f0; padding: 2rem; }
    h1   { color: #e8c96a; margin-bottom: 1.5rem; }
    .item { padding: .45rem .75rem; margin-bottom: .35rem; border-radius: 6px; font-size: .9rem; font-family: monospace; }
    .ok   { background: rgba(16,185,129,.15); color: #6ee7b7; }
    .skip { background: rgba(100,116,139,.15); color: #94a3b8; }
    .err  { background: rgba(239,68,68,.15);  color: #fca5a5; }
    .done { background: #10b981; color: #fff; padding: 1rem 1.5rem; border-radius: 10px; font-size: 1.1rem; font-weight: bold; margin-top: 1.5rem; display: inline-block; }
    .warn { background: #f59e0b; color: #000; padding: .6rem 1.2rem; border-radius: 8px; margin-top: 1rem; font-weight: bold; display: inline-block; }
  </style>
</head>
<body>
<h1>🔧 CCS Database Fix</h1>
<p style="color:#94a3b8;margin-bottom:1.2rem;">Running all fixes on <strong style="color:#e8c96a">sit_in_monitoring</strong>…</p>
<?php foreach ($results as [$type, $msg]): ?>
  <div class="item <?= $type ?>"><?= htmlspecialchars($msg) ?></div>
<?php endforeach; ?>
<?php
$errors = array_filter($results, fn($r) => $r[0] === 'err');
if (empty($errors)):
?>
  <div class="done">✅ All done! Your database is ready.</div><br>
  <span class="warn">⚠️ DELETE db_fix.php from your project folder now!</span>
  <br><br>
  <a href="admin_dashboard.php" style="color:#e8c96a">→ Go to Admin Dashboard</a>
<?php else: ?>
  <div style="background:rgba(239,68,68,.2);padding:1rem;border-radius:8px;margin-top:1rem;">
    <strong style="color:#fca5a5">Some errors occurred (shown in red above).</strong><br>
    <span style="color:#94a3b8;font-size:.85rem;">⏭️ Skipped items are fine — they just already existed.</span>
  </div>
<?php endif; ?>
</body>
</html>