<?php
// db_test.php
// Visit: http://localhost/sit-in%20monitoring%20system/db_test.php
// DELETE this file after fixing the issue!

$host = 'localhost';
$db   = 'sit_in_monitoring';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die('<b style="color:red">Connection FAILED:</b> ' . $conn->connect_error);
}

echo '<b style="color:green">✓ Connected to database: ' . $db . '</b><br><br>';

// Show tables
$res = $conn->query("SHOW TABLES");
echo '<b>Tables found:</b><ul>';
while ($row = $res->fetch_row()) {
    echo '<li>' . $row[0] . '</li>';
}
echo '</ul>';

// Show students columns
$res2 = $conn->query("SHOW COLUMNS FROM students");
if ($res2) {
    echo '<b>students table columns:</b><ul>';
    while ($col = $res2->fetch_assoc()) {
        echo '<li>' . $col['Field'] . ' (' . $col['Type'] . ')</li>';
    }
    echo '</ul>';
} else {
    echo '<b style="color:red">No "students" table found!</b><br>';
}

$conn->close();
echo '<br><b style="color:orange">DELETE db_test.php after debugging!</b>';