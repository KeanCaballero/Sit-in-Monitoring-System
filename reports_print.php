<?php
// reports_print.php
// Access: http://localhost/sit-in%20monitoring%20system/reports_print.php
// Supports GET params: limit (5|10|25|50|all), date_from, date_to
ini_set('display_errors', 0);
error_reporting(0);
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
    header('Location: index.html'); exit();
}

require_once 'config.php';

$limit     = $_GET['limit']     ?? '10';
$date_from = trim($_GET['date_from'] ?? '');
$date_to   = trim($_GET['date_to']   ?? '');

$limit_int = ($limit === 'all') ? 0 : max(1, (int)$limit);

try {
    $conn  = db_connect();
    $where = "WHERE 1=1";
    $params = []; $types = '';

    if ($date_from) { $where .= " AND DATE(s.created_at) >= ?"; $params[] = $date_from; $types .= 's'; }
    if ($date_to)   { $where .= " AND DATE(s.created_at) <= ?"; $params[] = $date_to;   $types .= 's'; }

    $sql = "SELECT s.id, s.id_number,
                   CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) AS name,
                   u.course, u.year_level, s.purpose, s.lab, s.pc_number,
                   s.created_at, s.timed_out_at, s.status, s.session_at_entry
            FROM sit_ins s
            LEFT JOIN users u ON u.id_number = s.id_number
            $where
            ORDER BY s.created_at DESC";

    if ($limit_int > 0) $sql .= " LIMIT $limit_int";

    if ($params) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        $records = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    $total_count = count($records);
    $conn->close();
} catch (Throwable $e) {
    $records = []; $total_count = 0;
}

function fmt_time($dt) {
    if (!$dt || $dt === '0000-00-00 00:00:00') return '—';
    return date('h:i A', strtotime($dt));
}
function fmt_date($dt) {
    if (!$dt || $dt === '0000-00-00 00:00:00') return '—';
    return date('M d, Y', strtotime($dt));
}
function duration($start, $end) {
    if (!$start || !$end || $end === '0000-00-00 00:00:00') return '—';
    $diff = strtotime($end) - strtotime($start);
    if ($diff < 0) return '—';
    $h = floor($diff / 3600);
    $m = floor(($diff % 3600) / 60);
    return ($h > 0 ? "{$h}h " : '') . "{$m}m";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <title>Sit-in Report — CCS</title>
  <style>
    @page { size: A4 landscape; margin: 14mm 12mm 12mm 12mm; }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 11px; color: #111; background: #fff; }

    /* ── FILTER BAR (screen only) ── */
    @media screen {
      .filter-bar {
        background: #0f2044; color: #e2e8f0; padding: 10px 18px;
        display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
        border-bottom: 2px solid #c9a84c; margin-bottom: 12px;
      }
      .filter-bar label { font-size: 11px; font-weight: 600; }
      .filter-bar select, .filter-bar input[type=date] {
        background: #1e3a7a; color: #e2e8f0; border: 1px solid #3b82f6;
        border-radius: 5px; padding: 3px 7px; font-size: 11px;
      }
      .filter-bar button {
        background: linear-gradient(135deg,#c9a84c,#e8c96a); color: #0f2044;
        font-weight: 700; border: none; border-radius: 6px;
        padding: 5px 14px; cursor: pointer; font-size: 11px;
      }
      .btn-print {
        background: linear-gradient(135deg,#10b981,#34d399); color:#fff;
        font-weight: 700; border: none; border-radius: 6px;
        padding: 5px 16px; cursor: pointer; font-size: 11px; margin-left:auto;
      }
    }
    @media print { .filter-bar { display: none; } }

    /* ── HEADER ── */
    .report-header { text-align: center; margin-bottom: 10px; }
    .report-header .main-title { font-size: 17px; font-weight: 800; text-transform: uppercase; letter-spacing: 1.5px; }
    .report-header .sub-title  { font-size: 12px; font-weight: 600; margin-top: 2px; color: #444; }
    .report-header .meta       { font-size: 10px; color: #666; margin-top: 4px; }
    .divider { border: none; border-top: 2px solid #0f2044; margin: 6px 0; }
    .divider2{ border: none; border-top: 1px solid #ccc;   margin: 4px 0; }

    /* ── SUMMARY ROW ── */
    .summary { display: flex; gap: 20px; margin-bottom: 10px; font-size: 10.5px; }
    .summary span { background: #f0f4ff; border: 1px solid #c7d4ee; border-radius: 4px; padding: 2px 8px; }
    .summary b { color: #0f2044; }

    /* ── TABLE ── */
    table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
    thead tr { background: #0f2044; color: #e8c96a; }
    thead th { padding: 5px 6px; font-size: 10px; font-weight: 700; text-transform: uppercase;
               letter-spacing: .5px; text-align: left; white-space: nowrap; }
    tbody tr:nth-child(even) { background: #f7f9fc; }
    tbody tr:hover { background: #eef2ff; }
    tbody td { padding: 4px 6px; font-size: 10.5px; border-bottom: 1px solid #e5e7eb; vertical-align: middle; }
    .badge-active { background: #d1fae5; color: #065f46; padding: 1px 6px; border-radius: 10px; font-size: 9.5px; font-weight: 700; }
    .badge-done   { background: #f1f5f9; color: #475569; padding: 1px 6px; border-radius: 10px; font-size: 9.5px; font-weight: 700; }
    .no-data { text-align: center; color: #aaa; font-style: italic; padding: 24px; }

    /* ── FOOTER ── */
    .report-footer { margin-top: 14px; display: flex; justify-content: space-between; font-size: 10px; color: #666; border-top: 1px solid #ccc; padding-top: 5px; }
    .sign-line { border-top: 1px solid #333; width: 180px; margin-top: 24px; padding-top: 3px; text-align: center; font-size: 9.5px; }
  </style>
</head>
<body>

<!-- ── FILTER BAR (screen only) ── -->
<div class="filter-bar">
  <form method="GET" style="display:contents;">
    <label>Show:
      <select name="limit">
        <?php foreach(['5','10','25','50','all'] as $opt): ?>
          <option value="<?= $opt ?>" <?= $limit===$opt?'selected':'' ?>><?= $opt==='all'?'All':$opt ?> records</option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>From: <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>"/></label>
    <label>To:   <input type="date" name="date_to"   value="<?= htmlspecialchars($date_to)   ?>"/></label>
    <button type="submit">Apply Filter</button>
    <button type="button" class="btn-print" onclick="window.print()">🖨️ Print / Save PDF</button>
    <a href="admin_dashboard.php" style="color:#94a3b8;font-size:11px;text-decoration:none;margin-left:4px;">← Back to Dashboard</a>
  </form>
</div>

<!-- ── REPORT CONTENT ── -->
<div class="report-header">
  <div class="main-title">SIT-IN MONITORING SYSTEM</div>
  <div class="sub-title">College of Computer Studies — University of Cebu</div>
  <div class="meta">
    Sit-in Activity Report &nbsp;|&nbsp;
    <?php
      if ($date_from && $date_to)      echo "Period: $date_from to $date_to";
      elseif ($date_from)              echo "From: $date_from";
      elseif ($date_to)                echo "Up to: $date_to";
      else                             echo "All Dates";
    ?>
    &nbsp;|&nbsp; Generated: <?= date('F d, Y h:i A') ?>
  </div>
</div>
<hr class="divider"/>

<div class="summary">
  <span>Total Records: <b><?= $total_count ?></b></span>
  <span>Active: <b><?= count(array_filter($records, fn($r) => $r['status']==='Active')) ?></b></span>
  <span>Completed: <b><?= count(array_filter($records, fn($r) => $r['status']==='Done')) ?></b></span>
</div>

<table>
  <thead>
    <tr>
      <th>#</th>
      <th>ID Number</th>
      <th>Student Name</th>
      <th>Course / Yr</th>
      <th>Purpose</th>
      <th>Lab</th>
      <th>PC #</th>
      <th>Date</th>
      <th>Time In</th>
      <th>Time Out</th>
      <th>Duration</th>
      <th>Status</th>
    </tr>
  </thead>
  <tbody>
    <?php if ($records): ?>
      <?php foreach ($records as $i => $r): ?>
        <tr>
          <td style="color:#888;"><?= $i+1 ?></td>
          <td style="font-weight:600;"><?= htmlspecialchars($r['id_number'] ?? '—') ?></td>
          <td><?= htmlspecialchars(trim($r['name']) ?: '—') ?></td>
          <td style="color:#555;"><?= htmlspecialchars(($r['course']??'').' '.($r['year_level']?'Yr'.$r['year_level']:'')) ?></td>
          <td><?= htmlspecialchars($r['purpose'] ?? '—') ?></td>
          <td style="font-weight:700;"><?= htmlspecialchars($r['lab'] ?? '—') ?></td>
          <td><?= $r['pc_number'] ?? '—' ?></td>
          <td><?= fmt_date($r['created_at']) ?></td>
          <td><?= fmt_time($r['created_at']) ?></td>
          <td><?= fmt_time($r['timed_out_at']) ?></td>
          <td><?= duration($r['created_at'], $r['timed_out_at']) ?></td>
          <td><span class="badge-<?= strtolower($r['status']==='Active'?'active':'done') ?>"><?= htmlspecialchars($r['status']) ?></span></td>
        </tr>
      <?php endforeach; ?>
    <?php else: ?>
      <tr><td colspan="12" class="no-data">No sit-in records found for the selected filter.</td></tr>
    <?php endif; ?>
  </tbody>
</table>

<hr class="divider2"/>

<div class="report-footer">
  <span>CCS Sit-in Monitoring System &nbsp;|&nbsp; UC – College of Computer Studies</span>
  <span>Printed: <?= date('M d, Y h:i A') ?></span>
</div>

<div style="display:flex;justify-content:flex-end;gap:48px;margin-top:20px;">
  <div class="sign-line">Prepared by<br>Lab-in-charge</div>
  <div class="sign-line">Noted by<br>CCS Dean / Department Head</div>
</div>

</body>
</html>