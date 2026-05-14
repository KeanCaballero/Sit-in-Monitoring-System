<?php
header('Content-Type: text/html; charset=utf-8');
// dashboard.php  (drop-in replacement - put in root of project)
ini_set('display_errors', 0);
error_reporting(0);
session_start();

// Redirect if not logged in or if admin
$isStudent = isset($_SESSION['user_id']) && isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'student';
if (!$isStudent) {
    header("Location: index.html");
    exit();
}

require_once 'config.php';

try {
    $conn = db_connect();
    $uid  = (int)$_SESSION['user_id'];
    $res  = $conn->query("SELECT * FROM `users` WHERE id = $uid LIMIT 1");
    $user = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : [];
    if ($user) $_SESSION['user'] = array_merge($_SESSION['user'] ?? [], $user);

    $announcements = [];
    $ac = $conn->query("SHOW TABLES LIKE 'announcements'");
    if ($ac && $ac->num_rows > 0) {
        $ar = $conn->query("SELECT * FROM `announcements` ORDER BY created_at DESC LIMIT 10");
        if ($ar) $announcements = $ar->fetch_all(MYSQLI_ASSOC);
    }

    $history      = [];
    $active_sitin = null;
    $tc = $conn->query("SHOW TABLES LIKE 'sit_ins'");
    if ($tc && $tc->num_rows > 0) {
        $id_num = $conn->real_escape_string($user['id_number'] ?? '');
        // Fetch ALL sit-ins (no LIMIT) so My Summary stats are accurate
        $hr = $conn->query("SELECT * FROM `sit_ins` WHERE id_number='$id_num' ORDER BY id DESC");
        if ($hr) {
            $history = $hr->fetch_all(MYSQLI_ASSOC);
            foreach ($history as $h) {
                if (($h['status'] ?? '') === 'Active') { $active_sitin = $h; break; }
            }
        }
    }
    $conn->close();
} catch (Throwable $e) {
    $user = $_SESSION['user'] ?? [];
    $announcements = []; $history = []; $active_sitin = null;
}

$su = [
    'id_number'          => $user['id_number']          ?? '',
    'first_name'         => $user['first_name']          ?? '',
    'last_name'          => $user['last_name']           ?? '',
    'middle_name'        => $user['middle_name']         ?? '',
    'email'              => $user['email']               ?? '',
    'address'            => $user['address']             ?? '',
    'course'             => $user['course']              ?? '',
    'year_level'         => $user['year_level']          ?? '',
    'remaining_sessions' => $user['remaining_sessions']  ?? 30,
    'profile_photo'      => $user['profile_photo']       ?? '',
    'points'             => $user['points']              ?? 0,
];

$fullname   = trim($su['first_name'] . ' ' . $su['last_name']);
$avatar_src = !empty($su['profile_photo'])
    ? htmlspecialchars($su['profile_photo'])
    : 'https://api.dicebear.com/8.x/adventurer/svg?seed=' . urlencode($fullname ?: 'Student') . '&backgroundColor=b6e3f4';
$sess     = (int)($su['remaining_sessions'] ?? 30);
$sess_pct = min(100, ($sess / 30) * 100);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>CCS Sit-in Portal  Student Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet"/>
  <link href="style.css" rel="stylesheet"/>
  <style>
    /*  PC LAB MAP  */
    .lab-map-wrap { padding: 1rem; }
    .lab-selector { display: flex; gap: .6rem; flex-wrap: wrap; margin-bottom: 1rem; }
    .lab-btn {
      border: 2px solid var(--border); background: transparent;
      padding: .35rem .9rem; border-radius: 8px; font-size: .82rem; font-weight: 600;
      cursor: pointer; transition: all .15s; color: var(--text2);
    }
    .lab-btn.active {
      border-color: var(--blue); background: var(--blue); color: #fff;
    }
    .lab-legend { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; font-size: .78rem; }
    .leg-item { display: flex; align-items: center; gap: .35rem; color: var(--text2); }
    .leg-dot { width: 14px; height: 14px; border-radius: 4px; }
    .leg-dot.available { background: #22c55e; }
    .leg-dot.occupied  { background: #ef4444; }
    .leg-dot.reserved  { background: #f59e0b; }
    .leg-dot.pending   { background: #8b5cf6; }
    .leg-dot.selected  { background: #3b82f6; border: 2px solid #1d4ed8; }

    .pc-grid {
      display: grid;
      grid-template-columns: repeat(8, 1fr);
      gap: 6px; padding: .5rem;
      background: var(--bg2);
      border-radius: 10px;
      border: 1px solid var(--border);
    }
    .pc-item {
      aspect-ratio: 1; border-radius: 6px;
      display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      cursor: pointer; font-size: .62rem; font-weight: 700;
      transition: all .12s; border: 2px solid transparent; position: relative;
    }
    .pc-item i { font-size: .9rem; margin-bottom: 1px; }
    .pc-item.available { background: var(--green-bg); color: var(--green); border-color: var(--green-border); }
    .pc-item.available:hover { filter: brightness(1.07); transform: scale(1.08); }
    .pc-item.occupied  { background: var(--red-bg);   color: var(--red);   border-color: var(--red-bg);   cursor: not-allowed; }
    .pc-item.reserved  { background: var(--amber-bg); color: var(--amber); border-color: var(--amber-bg); cursor: not-allowed; }
    .pc-item.pending   { background: var(--blue-bg);  color: var(--blue);  border-color: var(--blue-bg);  cursor: not-allowed; }
    .pc-item.selected  { background: var(--blue); color: #fff; border-color: var(--blue); transform: scale(1.08); }
    .pc-item .pc-tooltip {
      display: none; position: absolute; bottom: 110%; left: 50%; transform: translateX(-50%);
      background: var(--card); color: var(--text); border: 1px solid var(--border);
      font-size: .68rem; padding: 3px 7px;
      border-radius: 4px; white-space: nowrap; z-index: 10; pointer-events: none;
      box-shadow: var(--shadow);
    }
    .pc-item:hover .pc-tooltip { display: block; }

    /* teacher desk */
    .teacher-desk {
      grid-column: 1 / -1;
      background: var(--header-bg); color: var(--header-text);
      border-radius: 8px; padding: .4rem;
      text-align: center; font-size: .75rem; font-weight: 700;
      letter-spacing: 1px; margin-bottom: 4px;
    }
    .lab-stats-row { display: flex; gap: .75rem; flex-wrap: wrap; margin-top: .85rem; }
    .lab-stat {
      background: var(--bg2); border: 1px solid var(--border); border-radius: 8px;
      padding: .4rem .8rem; font-size: .78rem; display: flex; align-items: center; gap: .4rem;
      color: var(--text2);
    }
    .lab-stat .count { font-weight: 800; font-size: 1rem; }
    .lab-stat.green .count { color: var(--green); }
    .lab-stat.red   .count { color: var(--red);   }
    .lab-stat.amber .count { color: var(--amber);  }

    /* Reservation confirm strip */
    .res-pc-confirm {
      display: none; background: var(--blue-bg); border: 1.5px solid var(--blue);
      border-radius: 9px; padding: .65rem 1rem; margin-top: .75rem;
      font-size: .83rem; color: var(--blue); font-weight: 600;
    }
    .res-pc-confirm.show { display: flex; align-items: center; gap: .5rem; }

    /* Feedback stars */
    .star-row { display: flex; gap: .3rem; cursor: pointer; }
    .star-row .star { font-size: 1.4rem; color: var(--text4); transition: color .1s; }
    .star-row .star.on { color: var(--gold); }

    /* Leaderboard */
    /* PC Toggle Button inside PC # column */
    .pc-toggle-btn {
      font-size: .68rem; font-weight: 700; border: none; border-radius: 12px;
      padding: 2px 9px; cursor: pointer; transition: all .15s;
      display: inline-flex; align-items: center; gap: 3px;
      white-space: nowrap;
    }
    .pc-toggle-btn.enabled  { background: rgba(16,185,129,.15); color: #065f46; }
    .pc-toggle-btn.enabled:hover  { background: rgba(16,185,129,.3); }
    .pc-toggle-btn.disabled { background: rgba(239,68,68,.15);  color: #991b1b; }
    .pc-toggle-btn.disabled:hover { background: rgba(239,68,68,.3); }
  </style>
</head>
<body>

<!-- TOAST -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="margin-top:66px">
  <div id="liveToast" class="toast" role="alert">
    <div class="toast-body">
      <i class="fa-solid fa-circle-check" id="toastIcon"></i>
      <span id="toastMsg">Done!</span>
    </div>
  </div>
</div>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg ccs-navbar sticky-top">
  <div class="container-fluid px-0">
    <a class="navbar-brand" href="#">
      <img src="images/UC_LOGO.png" alt="UC Logo" class="nav-logo me-2"
           onerror="this.src='images/UC_LOGO.jpg'; this.onerror=null;"/>
      College of Computer Studies Sit-in Monitoring System
    </a>
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" style="color:#fff">
      <i class="fa-solid fa-bars"></i>
    </button>
    <div class="collapse navbar-collapse" id="navbarMain">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-1">
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="notifToggle" data-bs-toggle="dropdown">
            <i class="fa-regular fa-bell"></i> Notification
            <span class="notif-badge" id="notifBadge">3</span>
          </a>
          <div class="dropdown-menu ccs-dropdown-menu p-0" style="width:290px">
            <div class="notif-header">Notifications <span class="notif-clear" onclick="clearNotifs()">Clear all</span></div>
            <div id="notifItems">
              <div class="notif-item">
                <div class="notif-icon green"><i class="fa-solid fa-circle-check"></i></div>
                <div><div class="notif-title">Reservation Approved</div><div class="notif-time">Lab 524 * Today</div></div>
              </div>
              <div class="notif-item">
                <div class="notif-icon blue"><i class="fa-solid fa-envelope"></i></div>
                <div><div class="notif-title">New Announcement</div><div class="notif-time">From Admin</div></div>
              </div>
              <div class="notif-item">
                <div class="notif-icon gold"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div><div class="notif-title">Session reminder: <?= $sess ?> remaining</div><div class="notif-time">Yesterday</div></div>
              </div>
            </div>
          </div>
        </li>
        <li class="nav-item"><a class="nav-link active" data-tab="home" onclick="switchTab('home')"><i class="fa-solid fa-house"></i> Home</a></li>
        <li class="nav-item"><a class="nav-link" data-tab="profile" onclick="switchTab('profile')"><i class="fa-solid fa-user-pen"></i> Edit Profile</a></li>
        <li class="nav-item"><a class="nav-link" data-tab="history" onclick="switchTab('history')"><i class="fa-solid fa-clock-rotate-left"></i> History</a></li>
        <li class="nav-item"><a class="nav-link" data-tab="reservation" onclick="switchTab('reservation')"><i class="fa-solid fa-calendar-plus"></i> Reservation</a></li>
        <li class="nav-item"><a class="nav-link" data-tab="leaderboard" onclick="switchTab('leaderboard')"><i class="fa-solid fa-trophy"></i> Leaderboard</a></li>
        <li class="nav-item"><a class="nav-link" data-tab="sitin-summary" onclick="switchTab('sitin-summary')"><i class="fa-solid fa-chart-simple"></i> My Summary</a></li>
        <li class="nav-item"><a class="nav-link" data-tab="lab-availability" onclick="switchTab('lab-availability')"><i class="fa-solid fa-desktop"></i> Lab Status</a></li>
        <li class="nav-item">
          <button class="dm-toggle nav-link" onclick="toggleDarkMode()" id="dmBtn" title="Toggle Dark/Light Mode">
            <i class="fa-solid fa-moon" id="dmIcon"></i>
          </button>
        </li>
        <li class="nav-item"><a class="nav-link btn-logout ms-1" onclick="confirmLogout()"><i class="fa-solid fa-right-from-bracket"></i> Log out</a></li>
      </ul>
    </div>
  </div>
</nav>

<div class="page-wrap">

<!--  HOME  -->
<div class="view active" id="view-home">
  <div class="row g-4">
    <!-- Student Info -->
    <div class="col-lg-3">
      <div class="ccs-card">
        <div class="ccs-card-header"><i class="fa-solid fa-id-card"></i> Student Information</div>
        <div class="stu-avatar-wrap">
          <img id="mainAvatar" src="<?= $avatar_src ?>" alt="Avatar"
               onerror="this.src='https://api.dicebear.com/8.x/adventurer/svg?seed=Student&backgroundColor=b6e3f4'"/>
          <div class="stu-name" id="dName"><?= htmlspecialchars($fullname ?: 'Student') ?></div>
          <span class="stu-badge" id="dBadge"><?= htmlspecialchars(trim($su['course'] . ($su['year_level'] ? ' * Year '.$su['year_level'] : ''))) ?></span>
        </div>
        <ul class="info-list">
          <li><span class="info-icon"><i class="fa-solid fa-hashtag"></i></span><div><div class="info-label">ID Number</div><div class="info-value" id="dId"><?= htmlspecialchars($su['id_number'] ?: '-') ?></div></div></li>
          <li><span class="info-icon"><i class="fa-solid fa-graduation-cap"></i></span><div><div class="info-label">Course</div><div class="info-value" id="dCourse"><?= htmlspecialchars($su['course'] ?: '-') ?></div></div></li>
          <li><span class="info-icon"><i class="fa-solid fa-layer-group"></i></span><div><div class="info-label">Year Level</div><div class="info-value" id="dYear"><?= htmlspecialchars($su['year_level'] ? $su['year_level'].' Year' : '-') ?></div></div></li>
          <li><span class="info-icon"><i class="fa-solid fa-envelope"></i></span><div><div class="info-label">Email</div><div class="info-value" id="dEmail"><?= htmlspecialchars($su['email'] ?: '-') ?></div></div></li>
          <li><span class="info-icon"><i class="fa-solid fa-star"></i></span><div><div class="info-label">Points</div><div class="info-value" id="dPoints" style="color:#f59e0b;font-weight:700;"><?= (int)$su['points'] ?> pts</div></div></li>
        </ul>
        <div class="session-block">
          <div class="s-label"><i class="fa-regular fa-hourglass"></i>&nbsp; Remaining Sessions</div>
          <div class="s-num" id="sNum"><?= $sess ?></div>
          <div class="s-sub">out of 30 total sessions</div>
          <div class="session-bar"><div class="session-bar-fill" id="sessFill" style="width:<?= $sess_pct ?>%"></div></div>
        </div>
      </div>
    </div>

    <!-- Middle -->
    <div class="col-lg-6 d-flex flex-column gap-3">
      <div class="status-strip <?= $active_sitin ? 'on' : 'off' ?>" id="statusStrip">
        <span class="pulse-dot <?= $active_sitin ? 'on' : 'off' ?>" id="pulseD"></span>
        <span id="statusMsg">
          <?php if ($active_sitin): ?>
            You are <strong>currently sitting in</strong> - Lab <?= htmlspecialchars($active_sitin['lab']) ?>.
          <?php else: ?>
            You are <strong>not currently sitting in.</strong> Use <strong>Reservation</strong> to book a lab session.
          <?php endif; ?>
        </span>
      </div>

      <div class="ccs-card">
        <div class="ccs-card-header"><i class="fa-solid fa-bolt"></i> Quick Actions</div>
        <div class="ccs-card-body">
          <div class="row g-3">
            <div class="col-6"><button class="qa-btn primary" onclick="switchTab('reservation')"><i class="fa-solid fa-calendar-plus"></i> Reserve a Lab</button></div>
            <div class="col-6"><button class="qa-btn" onclick="switchTab('history')"><i class="fa-solid fa-clock-rotate-left"></i> View History</button></div>
            <div class="col-6"><button class="qa-btn" onclick="switchTab('profile')"><i class="fa-solid fa-user-pen"></i> Edit Profile</button></div>
            <div class="col-6"><button class="qa-btn" onclick="switchTab('leaderboard')"><i class="fa-solid fa-trophy"></i> Leaderboard</button></div>
          </div>
        </div>
      </div>

      <div class="ccs-card">
        <div class="ccs-card-header"><i class="fa-solid fa-bullhorn"></i> Announcements</div>
        <div class="ccs-card-body">
          <?php if (!empty($announcements)): ?>
            <?php foreach ($announcements as $ann): ?>
              <div class="ann-item">
                <div class="ann-meta"><span class="ann-tag">CCS Admin</span><span class="ann-date"><i class="fa-regular fa-calendar"></i> <?= htmlspecialchars(date('Y M d', strtotime($ann['created_at'] ?? 'now'))) ?></span></div>
                <div class="ann-text"><?= empty($ann['message']) ? '<em style="color:#999">No message content.</em>' : nl2br(htmlspecialchars($ann['message'])) ?></div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="ann-item"><div class="ann-meta"><span class="ann-tag">CCS Admin</span><span class="ann-date"><i class="fa-regular fa-calendar"></i> Today</span></div><div class="ann-text ann-empty">No announcements yet.</div></div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Recent History mini -->
      <div class="ccs-card">
        <div class="ccs-card-header"><i class="fa-solid fa-table-list"></i> Recent Sit-in History</div>
        <div class="table-responsive">
          <table class="ccs-table">
            <thead><tr><th>Purpose</th><th>Lab</th><th>PC</th><th>Login</th><th>Status</th></tr></thead>
            <tbody>
              <?php if (!empty($history)): ?>
                <?php foreach (array_slice($history,0,3) as $h): ?>
                  <tr>
                    <td><?= htmlspecialchars($h['purpose'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($h['lab'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($h['pc_number'] ?? '-') ?></td>
                    <td><?= !empty($h['created_at']) ? date('M d h:i A', strtotime($h['created_at'])) : '-' ?></td>
                    <td><span style="font-size:.72rem;font-weight:700;padding:2px 8px;border-radius:20px;background:<?= $h['status']==='Active'?'rgba(16,185,129,.12)':'rgba(100,116,139,.12)' ?>;color:<?= $h['status']==='Active'?'#10b981':'#64748b' ?>"><?= htmlspecialchars($h['status']) ?></span></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr class="no-data-row"><td colspan="5"><i class="fa-regular fa-folder-open" style="font-size:1.3rem;display:block;margin-bottom:8px;opacity:.35"></i>No records yet</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Right: Rules -->
    <div class="col-lg-3 d-none d-lg-block">
      <div class="ccs-card">
        <div class="ccs-card-header"><i class="fa-solid fa-shield-halved"></i> Rules &amp; Regulations</div>
        <div class="rules-scroll">
          <div class="rules-uni">University of Cebu</div>
          <div class="rules-dept">College of Information &amp; Computer Studies</div>
          <div class="rules-sec">Laboratory Rules and Regulations</div>
          <p style="font-size:.78rem;line-height:1.55;margin-bottom:.6rem;">To avoid embarrassment and maintain camaraderie with your friends and superiors at our laboratories, please observe the following:</p>
          <ol style="font-size:.78rem;line-height:1.55;padding-left:1.1rem;margin-bottom:.75rem;">
            <li style="margin-bottom:.45rem;">Maintain silence, proper decorum, and discipline inside the laboratory. Mobile phones and other personal equipment must be switched off.</li>
            <li style="margin-bottom:.45rem;">Games are not allowed inside the lab.</li>
            <li style="margin-bottom:.45rem;">Surfing the Internet is allowed only with the permission of the instructor. Downloading and installing software are strictly prohibited.</li>
            <li style="margin-bottom:.45rem;">Deleting computer files and changing computer settings is a major offense.</li>
            <li style="margin-bottom:.45rem;">A fifteen-minute allowance is given for each use; the unit will be given to those who wish to sit-in after.</li>
            <li style="margin-bottom:.45rem;">All bags and belongings must be deposited at the counter.</li>
            <li style="margin-bottom:.45rem;">Chewing gum, eating, drinking, and smoking are prohibited inside the lab.</li>
          </ol>
          <div class="rules-sec">Disciplinary Action</div>
          <p style="font-size:.78rem;line-height:1.55;"><strong>First Offense</strong> - Suspension recommended by the Head or Dean.<br><strong>Second Offense</strong> - A heavier sanction endorsed to the Guidance Center.</p>
        </div>
      </div>
    </div>
  </div>
</div><!-- /home -->


<!--  HISTORY  -->
<div class="view" id="view-history">
  <div class="view-header">
    <div class="view-title"><i class="fa-solid fa-clock-rotate-left"></i> Sit-in History &amp; Feedback</div>
    <button class="btn-ccs-export" onclick="exportCSV()"><i class="fa-solid fa-download"></i> Export CSV</button>
  </div>
  <div class="ccs-card">
    <div class="ccs-card-body p-4">
      <div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
        <div class="tbl-entries d-flex align-items-center gap-2" style="font-size:.82rem">
          Show <select id="histEntries" onchange="renderHistory()"><option value="5">5</option><option value="10" selected>10</option><option value="25">25</option></select> entries
        </div>
        <div class="tbl-search"><i class="fa-solid fa-magnifying-glass"></i><input type="text" id="histSearch" placeholder="Search" oninput="renderHistory()"/></div>
      </div>
      <div class="table-responsive">
        <table class="ccs-table">
          <thead><tr><th>Purpose</th><th>Laboratory</th><th>PC #</th><th>Login</th><th>Logout</th><th>Date</th><th>Status</th><th>Feedback</th></tr></thead>
          <tbody id="histBody"></tbody>
        </table>
      </div>
      <div class="tbl-footer mt-3"><span id="histInfo">Showing 0 entries</span><div class="d-flex gap-1" id="histPagination"></div></div>
    </div>
  </div>
</div>


<!--  RESERVATION  -->
<div class="view" id="view-reservation">
  <div class="view-header">
    <div class="view-title"><i class="fa-solid fa-calendar-plus"></i> Lab Reservation</div>
  </div>
  <div class="row g-4">
    <!-- Form + PC Map -->
    <div class="col-lg-8">
      <div class="ccs-card">
        <div class="ccs-card-header"><i class="fa-solid fa-pen-to-square"></i> New Reservation</div>
        <div class="ccs-card-body p-4">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label-ccs">ID Number</label>
              <input class="form-control-ccs" type="text" id="rId" value="<?= htmlspecialchars($su['id_number']) ?>" readonly/>
            </div>
            <div class="col-md-6">
              <label class="form-label-ccs">Student Name</label>
              <input class="form-control-ccs" type="text" value="<?= htmlspecialchars($fullname) ?>" readonly/>
            </div>
            <div class="col-12">
              <label class="form-label-ccs">Purpose <span style="color:var(--red)">*</span></label>
              <input class="form-control-ccs" type="text" id="rPurpose" placeholder="e.g. C Programming, Thesis, Research"/>
            </div>
            <div class="col-md-4">
              <label class="form-label-ccs">Laboratory <span style="color:var(--red)">*</span></label>
              <select class="form-select-ccs" id="rLab" onchange="loadLabMap()">
                <option value="">Select lab</option>
                <option>524</option><option>526</option><option>528</option><option>530</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label-ccs">Date <span style="color:var(--red)">*</span></label>
              <input class="form-control-ccs" type="date" id="rDate" onchange="loadLabMap()"/>
            </div>
            <div class="col-md-4">
              <label class="form-label-ccs">Time In <span style="color:var(--red)">*</span></label>
              <input class="form-control-ccs" type="time" id="rTime"/>
            </div>
          </div>

          <!-- PC MAP -->
          <div id="pcMapSection" style="display:none;margin-top:1.2rem;">
            <hr style="margin-bottom:1rem;"/>
            <div style="font-weight:700;font-size:.9rem;margin-bottom:.6rem;"><i class="fa-solid fa-desktop" style="color:#1e3a7a;margin-right:.4rem;"></i>Select a PC - click an available (green) PC</div>

            <div class="lab-legend">
              <div class="leg-item"><div class="leg-dot available"></div> Available</div>
              <div class="leg-item"><div class="leg-dot occupied"></div> Occupied</div>
              <div class="leg-item"><div class="leg-dot reserved"></div> Reserved</div>
              <div class="leg-item"><div class="leg-dot pending"></div> Pending</div>
              <div class="leg-item"><div class="leg-dot selected"></div> Your Selection</div>
            </div>

            <div id="pcGrid" class="pc-grid">
              <div class="teacher-desk"><i class="fa-solid fa-chalkboard-user"></i> INSTRUCTOR'S DESK</div>
            </div>

            <div class="lab-stats-row" id="labStatsRow"></div>

            <div class="res-pc-confirm" id="pcConfirmStrip">
              <i class="fa-solid fa-desktop"></i>
              PC <strong id="selectedPcNum">-</strong> selected for Lab <strong id="selectedLab">-</strong>
              <button onclick="clearPcSelection()" style="margin-left:auto;background:none;border:none;cursor:pointer;color:#1e40af;font-size:.8rem;"> Clear</button>
            </div>
          </div>

          <div id="pcMapLoading" style="display:none;text-align:center;padding:2rem;color:#64748b;">
            <i class="fa-solid fa-spinner fa-spin fa-lg"></i> Loading PC availability
          </div>

          <div class="col-12 mt-3">
            <button class="btn-ccs-primary" onclick="submitReservation()">
              <i class="fa-solid fa-calendar-check"></i> Submit Reservation
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Guidelines + My Reservations -->
    <div class="col-lg-4 d-flex flex-column gap-3">
      <div class="ccs-card">
        <div class="ccs-card-header"><i class="fa-solid fa-circle-info"></i> Guidelines</div>
        <div class="ccs-card-body">
          <div class="res-tip"><i class="fa-solid fa-clock"></i><p><strong>Arrive on time.</strong> Reservation cancelled if not checked in within 15 minutes.</p></div>
          <div class="res-tip"><i class="fa-solid fa-laptop"></i><p><strong>Each session costs 1 session point.</strong> You have <strong id="tipSess"><?= $sess ?></strong> remaining.</p></div>
          <div class="res-tip"><i class="fa-solid fa-desktop"></i><p><strong>Select your preferred PC</strong> from the lab map after choosing a lab and date.</p></div>
          <div class="res-tip"><i class="fa-solid fa-id-card"></i><p>Bring your <strong>valid school ID</strong> when claiming your reservation.</p></div>
        </div>
      </div>
      <div class="ccs-card">
        <div class="ccs-card-header"><i class="fa-solid fa-list-check"></i> My Reservations</div>
        <div class="ccs-card-body" id="myResList">
          <p style="text-align:center;font-size:.82rem;color:var(--text3);font-style:italic;">Loading</p>
        </div>
      </div>
    </div>
  </div>
</div><!-- /reservation -->


<!--  EDIT PROFILE  -->
<div class="view" id="view-profile">
  <div class="view-header"><div class="view-title"><i class="fa-solid fa-user-pen"></i> Edit Profile</div></div>
  <div class="row g-4">
    <div class="col-lg-3">
      <div class="ccs-card">
        <div class="profile-av-card">
          <img id="profAvatar" src="<?= $avatar_src ?>" alt="Avatar" onerror="this.src='https://api.dicebear.com/8.x/adventurer/svg?seed=Student&backgroundColor=b6e3f4'"/>
          <div class="profile-name" id="profName"><?= htmlspecialchars($fullname) ?></div>
          <div class="profile-role" id="profRole"><?= htmlspecialchars(trim($su['course'] . ($su['year_level'] ? ' * '.$su['year_level'].' Year' : ''))) ?></div>
          <button class="btn-photo" onclick="triggerPhotoInput()"><i class="fa-solid fa-camera"></i> Change Photo</button>
          <input type="file" id="photoInput" accept="image/*" style="display:none" onchange="previewPhoto(event)"/>
          <div class="profile-sess-stat">
            <div class="sml">Remaining Sessions</div>
            <div class="big" id="profSessNum"><?= $sess ?></div>
            <div class="sml">out of 30</div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-9">
      <div class="ccs-card">
        <div class="ccs-card-header"><i class="fa-solid fa-pen-to-square"></i> Personal Information</div>
        <div class="ccs-card-body p-4">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label-ccs">First Name</label><input class="form-control-ccs" type="text" id="pFn" value="<?= htmlspecialchars($su['first_name']) ?>"/></div>
            <div class="col-md-6"><label class="form-label-ccs">Last Name</label><input class="form-control-ccs" type="text" id="pLn" value="<?= htmlspecialchars($su['last_name']) ?>"/></div>
            <div class="col-md-6"><label class="form-label-ccs">Middle Name</label><input class="form-control-ccs" type="text" id="pMn" value="<?= htmlspecialchars($su['middle_name'] ?? '') ?>"/></div>
            <div class="col-md-6"><label class="form-label-ccs">ID Number</label><input class="form-control-ccs" type="text" value="<?= htmlspecialchars($su['id_number']) ?>" readonly/></div>
            <div class="col-md-6"><label class="form-label-ccs">Email Address</label><input class="form-control-ccs" type="email" id="pEm" value="<?= htmlspecialchars($su['email']) ?>"/></div>
            <div class="col-md-6"><label class="form-label-ccs">Address</label><input class="form-control-ccs" type="text" id="pAd" value="<?= htmlspecialchars($su['address'] ?? '') ?>"/></div>
            <div class="col-md-6">
              <label class="form-label-ccs">Course</label>
              <select class="form-select-ccs" id="pCo">
                <?php foreach (['BSIT','BSCS','BSIS','ACT'] as $c): ?><option <?= $su['course']===$c?'selected':'' ?>><?= $c ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label-ccs">Year Level</label>
              <select class="form-select-ccs" id="pYr">
                <?php foreach (['1','2','3','4'] as $y): ?><option value="<?= $y ?>" <?= $su['year_level']==$y?'selected':'' ?>><?= $y==='1'?'1st':($y==='2'?'2nd':($y==='3'?'3rd':'4th')) ?> Year</option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-12"><hr class="divider"/></div>
            <div class="col-md-6"><label class="form-label-ccs">New Password</label><input class="form-control-ccs" type="password" id="pPw" placeholder="Leave blank to keep current" autocomplete="new-password"/></div>
            <div class="col-md-6"><label class="form-label-ccs">Confirm Password</label><input class="form-control-ccs" type="password" id="pPw2" placeholder="Repeat new password" autocomplete="new-password"/></div>
            <div class="col-12 mt-1"><button class="btn-ccs-primary" onclick="saveProfile()"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div><!-- /profile -->


<!--  LEADERBOARD  -->
<div class="view" id="view-leaderboard">
  <div class="view-header"><div class="view-title"><i class="fa-solid fa-trophy"></i> Leaderboard</div></div>
  <div class="row g-4">
    <div class="col-lg-8">
      <div class="ccs-card">
        <div class="ccs-card-header"><i class="fa-solid fa-ranking-star"></i> Top Students</div>
        <div class="table-responsive">
          <table class="ccs-table" id="lbTable">
            <thead><tr><th>Rank</th><th>Student</th><th>Course</th><th>Sit-ins</th><th>Points</th></tr></thead>
            <tbody id="lbBody"><tr class="no-data-row"><td colspan="5">Loading</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="ccs-card">
        <div class="ccs-card-header"><i class="fa-solid fa-medal"></i> Your Ranking</div>
        <div class="ccs-card-body" id="myRankCard" style="text-align:center;padding:2rem 1rem;">
          <div style="font-size:3rem;"></div>
          <div id="myRankNum" style="font-size:2rem;font-weight:800;color:var(--navy,#1e3a7a)">-</div>
          <div style="font-size:.85rem;color:var(--text2,#555)">Your current rank</div>
          <div style="margin-top:1rem;">
            <div style="font-size:1.5rem;font-weight:800;color:#f59e0b;" id="myPtsNum"><?= (int)$su['points'] ?></div>
            <div style="font-size:.78rem;color:var(--text3,#888)">Total Points</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div><!-- /leaderboard -->


<!--  SIT-IN SUMMARY  -->
<div class="view" id="view-sitin-summary">
  <div class="view-header">
    <div class="view-title"><i class="fa-solid fa-chart-simple"></i> My Sit-in Summary</div>
    <button class="btn-ccs-export" onclick="exportSummaryCSV()"><i class="fa-solid fa-download"></i> Export CSV</button>
  </div>

  <!-- Stats row -->
  <div class="sitin-summary-stats" id="summaryStatsRow">
    <div class="ss-card c1">
      <span class="ss-icon"></span>
      <div class="ss-val" id="sumTotalHrs">-</div>
      <div class="ss-lbl">Total Sit-in Hours</div>
    </div>
    <div class="ss-card c2">
      <span class="ss-icon"></span>
      <div class="ss-val" id="sumTotalSessions">-</div>
      <div class="ss-lbl">Total Sessions</div>
    </div>
    <div class="ss-card c3">
      <span class="ss-icon"></span>
      <div class="ss-val" id="sumAvgDuration">-</div>
      <div class="ss-lbl">Avg Session Duration</div>
    </div>
    <div class="ss-card c4">
      <span class="ss-icon"></span>
      <div class="ss-val" id="sumLongest">-</div>
      <div class="ss-lbl">Longest Session</div>
    </div>
  </div>

  <!-- Session Table -->
  <div class="ccs-card">
    <div class="ccs-card-header"><i class="fa-solid fa-table-list"></i> Session History</div>
    <div class="ccs-card-body p-4">
      <div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
        <div class="tbl-entries d-flex align-items-center gap-2" style="font-size:.82rem;">
          Show <select id="sumEntries" onchange="renderSummaryTable()"><option value="5">5</option><option value="10" selected>10</option><option value="25">25</option><option value="0">All</option></select> entries
        </div>
        <div class="tbl-search"><i class="fa-solid fa-magnifying-glass"></i><input type="text" id="sumSearch" placeholder="Search" oninput="renderSummaryTable()"/></div>
      </div>
      <div class="table-responsive">
        <table class="ccs-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Time In</th>
              <th>Time Out</th>
              <th>Duration</th>
              <th>Lab</th>
              <th>PC # / Reservation</th>
              <th>Purpose</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody id="sumBody"></tbody>
        </table>
      </div>
      <div class="tbl-footer mt-3">
        <span id="sumInfo">Showing 0 entries</span>
        <div class="d-flex gap-1" id="sumPagination"></div>
      </div>
    </div>
  </div>
</div><!-- /sitin-summary -->


<!--  LAB AVAILABILITY  -->
<div class="view" id="view-lab-availability">
  <div class="view-header">
    <div class="view-title"><i class="fa-solid fa-desktop"></i> Software &amp; Lab Availability</div>
    <button class="btn-ccs-export" onclick="loadLabAvailability()" style="background:var(--blue,#2563eb);color:#fff;border:none;border-radius:8px;padding:.4rem 1rem;font-size:.8rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;">
      <i class="fa-solid fa-arrows-rotate"></i> Refresh
    </button>
  </div>

  <!-- Lab cards -->
  <div class="ccs-card mb-3">
    <div class="ccs-card-header"><i class="fa-solid fa-flask"></i> Laboratory Status</div>
    <div class="ccs-card-body p-3">
      <div class="sw-lab-grid" id="labAvailGrid">
        <div style="color:#64748b;font-size:.83rem;padding:1rem;">Loading lab status</div>
      </div>
    </div>
  </div>

  <!-- Software list -->
  <div class="ccs-card">
    <div class="ccs-card-header"><i class="fa-solid fa-laptop-code"></i> Available Software
      <span id="swLoadingSpinner" style="margin-left:.5rem;font-size:.72rem;color:var(--text3);"><i class="fa-solid fa-spinner fa-spin"></i> Loading...</span>
    </div>
    <div class="ccs-card-body p-3">
      <div class="row g-3" id="softwareGrid">
        <!-- loaded dynamically from api/software.php -->
      </div>
    </div>
  </div>
</div><!-- /lab-availability -->


</div><!-- /page-wrap -->


<!-- MODALS -->
<div class="modal fade ccs-modal" id="modalLogin" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-body"><div class="m-icon"><i class="fa-solid fa-check"></i></div><div class="m-title">Successful Login!</div><p class="m-sub">Welcome back, <strong><?= htmlspecialchars($fullname) ?></strong>! </p></div>
    <div class="modal-footer"><button class="btn-m-ok" data-bs-dismiss="modal">OK</button></div>
  </div></div>
</div>

<div class="modal fade ccs-modal" id="modalLogout" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-body"><div class="m-icon warn"><i class="fa-solid fa-right-from-bracket"></i></div><div class="m-title">Log Out?</div><p class="m-sub">Are you sure you want to end your session?</p></div>
    <div class="modal-footer"><button class="btn-m-cancel" data-bs-dismiss="modal">Cancel</button><button class="btn-m-ok" onclick="doLogout()">Yes, Log Out</button></div>
  </div></div>
</div>

<div class="modal fade ccs-modal" id="modalSuccess" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-body"><div class="m-icon"><i class="fa-solid fa-check"></i></div><div class="m-title" id="successTitle">Done!</div><p class="m-sub" id="successSub">Action completed.</p></div>
    <div class="modal-footer"><button class="btn-m-ok" data-bs-dismiss="modal">OK</button></div>
  </div></div>
</div>

<!-- Feedback Modal -->
<div class="modal fade ccs-modal" id="modalFeedback" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-body" style="padding:1.8rem 1.5rem 1rem;">
      <div class="m-icon" style="background:rgba(245,158,11,.1)"><i class="fa-solid fa-star" style="color:#f59e0b"></i></div>
      <div class="m-title">Leave Feedback</div>
      <p class="m-sub">How was your sit-in experience?</p>

      <!-- Star Rating -->
      <div id="starRow" style="display:flex;justify-content:center;gap:.4rem;margin:1rem 0 .4rem;">
        <i class="fa-solid fa-star fb-star" data-v="1" onclick="setRating(1)" style="font-size:2rem;cursor:pointer;transition:transform .12s,color .1s;color:#d1d5db;"></i>
        <i class="fa-solid fa-star fb-star" data-v="2" onclick="setRating(2)" style="font-size:2rem;cursor:pointer;transition:transform .12s,color .1s;color:#d1d5db;"></i>
        <i class="fa-solid fa-star fb-star" data-v="3" onclick="setRating(3)" style="font-size:2rem;cursor:pointer;transition:transform .12s,color .1s;color:#d1d5db;"></i>
        <i class="fa-solid fa-star fb-star" data-v="4" onclick="setRating(4)" style="font-size:2rem;cursor:pointer;transition:transform .12s,color .1s;color:#d1d5db;"></i>
        <i class="fa-solid fa-star fb-star" data-v="5" onclick="setRating(5)" style="font-size:2rem;cursor:pointer;transition:transform .12s,color .1s;color:#d1d5db;"></i>
      </div>
      <div id="fbRatingLabel" style="text-align:center;font-size:.78rem;font-weight:700;margin-bottom:.85rem;color:#f59e0b;letter-spacing:.3px;">⭐ Good (3/5)</div>

      <textarea id="fbMsg" rows="3" style="width:100%;border-radius:8px;border:1px solid #ddd;padding:.5rem;font-size:.83rem;resize:none;" placeholder="Optional message"></textarea>
      <input type="hidden" id="fbSitInId"/>
    </div>
    <div class="modal-footer"><button class="btn-m-cancel" data-bs-dismiss="modal">Skip</button><button class="btn-m-ok" onclick="submitFeedback()">Submit</button></div>
  </div></div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const SESSION_USER = <?= json_encode([
    'id_number'          => $su['id_number'],
    'first_name'         => $su['first_name'],
    'last_name'          => $su['last_name'],
    'middle_name'        => $su['middle_name'] ?? '',
    'email'              => $su['email'],
    'address'            => $su['address'] ?? '',
    'course'             => $su['course'],
    'year_level'         => $su['year_level'],
    'remaining_sessions' => $sess,
    'profile_photo'      => $su['profile_photo'] ?? '',
    'points'             => (int)$su['points'],
]) ?>;

// -- TABS --
function switchTab(tab) {
  document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
  document.querySelectorAll('.nav-link[data-tab]').forEach(a => a.classList.remove('active'));
  document.getElementById('view-' + tab).classList.add('active');
  document.querySelector(`.nav-link[data-tab="${tab}"]`)?.classList.add('active');
  if (tab === 'history')          { loadHistory(); }
  if (tab === 'reservation')      { loadMyReservations(); }
  if (tab === 'leaderboard')      { loadLeaderboard(); }
  if (tab === 'sitin-summary')    { loadSitinSummary(); }
  if (tab === 'lab-availability') { loadLabAvailability(); }
}

// -- TOAST --
function showToast(msg, type = 'success') {
  const colors = { success:'#10b981', danger:'#ef4444', warning:'#f59e0b', info:'#3b82f6' };
  document.getElementById('toastIcon').style.color = colors[type] || colors.success;
  document.getElementById('toastMsg').textContent  = msg;
  bootstrap.Toast.getOrCreateInstance(document.getElementById('liveToast'), { delay: 2800 }).show();
}

// -- HISTORY --
let histData = [], histPage = 1;
async function loadHistory() {
  try {
    const r = await fetch('api/reservation_fetch.php');
    // fallback: use PHP-injected sit_ins as well
  } catch(e) {}
  // Use embedded PHP data
  histData = <?= json_encode(array_map(fn($h) => [
    'purpose'    => $h['purpose']    ?? '-',
    'lab'        => $h['lab']        ?? '-',
    'pc_number'  => $h['pc_number']  ?? '-',
    'login'      => !empty($h['created_at'])    ? date('h:i A', strtotime($h['created_at']))    : '-',
    'logout'     => !empty($h['timed_out_at'])  ? date('h:i A', strtotime($h['timed_out_at']))  : '-',
    'date'       => !empty($h['created_at'])    ? date('Y-m-d', strtotime($h['created_at']))    : '-',
    'status'     => $h['status']     ?? 'Done',
    'id'         => $h['id']         ?? 0,
  ], $history)) ?>;
  renderHistory();
}
function renderHistory() {
  const q   = (document.getElementById('histSearch').value||'').toLowerCase();
  const pp  = parseInt(document.getElementById('histEntries').value||10);
  const data = histData.filter(h => (h.purpose+h.lab+h.status).toLowerCase().includes(q));
  const pages = Math.max(1, Math.ceil(data.length/pp));
  if (histPage > pages) histPage = pages;
  const slice = data.slice((histPage-1)*pp, histPage*pp);
  const tbody = document.getElementById('histBody');
  if (!data.length) {
    tbody.innerHTML = `<tr class="no-data-row"><td colspan="8">No records yet.</td></tr>`;
  } else {
    tbody.innerHTML = slice.map(h => `
      <tr>
        <td>${h.purpose}</td><td>${h.lab}</td><td>${h.pc_number}</td>
        <td>${h.login}</td><td>${h.logout}</td><td>${h.date}</td>
        <td><span style="font-size:.72rem;font-weight:700;padding:2px 8px;border-radius:20px;background:${h.status==='Active'?'rgba(16,185,129,.12)':'rgba(100,116,139,.12)'};color:${h.status==='Active'?'#10b981':'#64748b'}">${h.status}</span></td>
        <td><button onclick="openFeedback(${h.id})" style="background:rgba(245,158,11,.12);color:#92400e;border:none;border-radius:6px;padding:3px 10px;font-size:.72rem;font-weight:700;cursor:pointer;"><i class="fa-solid fa-star"></i> Feedback</button></td>
      </tr>`).join('');
  }
  document.getElementById('histInfo').textContent = data.length
    ? `Showing ${(histPage-1)*pp+1}${Math.min(histPage*pp,data.length)} of ${data.length}` : 'No entries';
}

// -- FEEDBACK --
let curRating = 3;
const ratingLabels = {1:'😞 Poor (1/5)', 2:'😐 Fair (2/5)', 3:'⭐ Good (3/5)', 4:'😊 Great (4/5)', 5:'🌟 Excellent (5/5)'};
function setRating(v) {
  curRating = v;
  document.querySelectorAll('#starRow .fb-star').forEach(s => {
    const sv = +s.dataset.v;
    s.style.color = sv <= v ? '#f59e0b' : '#d1d5db';
    s.style.transform = sv <= v ? 'scale(1.15)' : 'scale(1)';
  });
  const lbl = document.getElementById('fbRatingLabel');
  if (lbl) lbl.textContent = ratingLabels[v] || '';
}
// hover preview
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('#starRow .fb-star').forEach(s => {
    s.addEventListener('mouseover', () => {
      const hv = +s.dataset.v;
      document.querySelectorAll('#starRow .fb-star').forEach(st => {
        st.style.color = +st.dataset.v <= hv ? '#fbbf24' : '#d1d5db';
      });
    });
    s.addEventListener('mouseout', () => setRating(curRating));
  });
});
function openFeedback(id) {
  document.getElementById('fbSitInId').value = id;
  document.getElementById('fbMsg').value = '';
  setRating(3);
  new bootstrap.Modal(document.getElementById('modalFeedback')).show();
}
function submitFeedback() {
  const id  = document.getElementById('fbSitInId').value;
  const msg = document.getElementById('fbMsg').value;
  fetch('api/feedback_submit.php', { method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ sit_in_id:id, rating:curRating, message:msg }) });
  bootstrap.Modal.getInstance(document.getElementById('modalFeedback')).hide();
  showToast('Thank you for your feedback! ');
}

// -- PC MAP --
let selectedPc = null;
let pcMapData  = null;

async function loadLabMap() {
  const lab  = document.getElementById('rLab').value;
  const date = document.getElementById('rDate').value;
  if (!lab || !date) { document.getElementById('pcMapSection').style.display='none'; return; }

  document.getElementById('pcMapSection').style.display = 'none';
  document.getElementById('pcMapLoading').style.display = 'block';
  selectedPc = null;
  document.getElementById('pcConfirmStrip').classList.remove('show');

  try {
    const r    = await fetch(`api/lab_pc_status.php?lab=${encodeURIComponent(lab)}&date=${encodeURIComponent(date)}`);
    pcMapData  = await r.json();
  } catch(e) {
    // Offline fallback: all available
    pcMapData = { lab, total_pcs:40, pc_map:{}, available_count:40, occupied_count:0, reserved_count:0 };
    for (let i=1;i<=40;i++) pcMapData.pc_map[i] = 'available';
  }

  renderPcGrid(lab);
  document.getElementById('pcMapLoading').style.display = 'none';
  document.getElementById('pcMapSection').style.display = 'block';
}

function renderPcGrid(lab) {
  const grid  = document.getElementById('pcGrid');
  const map   = pcMapData?.pc_map || {};
  const total = pcMapData?.total_pcs || 40;

  const statusLabel = { available:'Available', occupied:'Occupied (In-use)', reserved:'Reserved', pending:'Pending Approval' };
  const statusTitle = { available:'Click to select', occupied:'Currently in use', reserved:'Already reserved', pending:'Pending reservation' };

  let html = `<div class="teacher-desk"><i class="fa-solid fa-chalkboard-user"></i> INSTRUCTOR'S DESK</div>`;
  for (let i=1; i<=total; i++) {
    const st = selectedPc === i ? 'selected' : (map[i] || 'available');
    const clickable = (map[i] || 'available') === 'available';
    html += `
      <div class="pc-item ${st}" onclick="${clickable ? `selectPc(${i},'${lab}')` : ''}" title="${statusTitle[map[i]||'available'] || ''}">
        <i class="fa-solid fa-desktop"></i>
        <span>PC${i}</span>
        <div class="pc-tooltip">${statusLabel[map[i]||'available'] || ''}</div>
      </div>`;
  }
  grid.innerHTML = html;

  // Stats
  document.getElementById('labStatsRow').innerHTML = `
    <div class="lab-stat green"><span class="count">${pcMapData?.available_count||0}</span> Available</div>
    <div class="lab-stat red"><span class="count">${pcMapData?.occupied_count||0}</span> Occupied</div>
    <div class="lab-stat amber"><span class="count">${pcMapData?.reserved_count||0}</span> Reserved</div>`;
}

function selectPc(pc, lab) {
  const map = pcMapData?.pc_map || {};
  if ((map[pc] || 'available') !== 'available') return;
  selectedPc = pc;
  renderPcGrid(lab);
  document.getElementById('selectedPcNum').textContent = pc;
  document.getElementById('selectedLab').textContent   = 'Lab ' + lab;
  document.getElementById('pcConfirmStrip').classList.add('show');
}
function clearPcSelection() {
  selectedPc = null;
  const lab = document.getElementById('rLab').value;
  if (lab) renderPcGrid(lab);
  document.getElementById('pcConfirmStrip').classList.remove('show');
}

async function submitReservation() {
  const purpose = document.getElementById('rPurpose').value.trim();
  const lab     = document.getElementById('rLab').value;
  const date    = document.getElementById('rDate').value;
  const time    = document.getElementById('rTime').value;
  if (!purpose || !lab || !date || !time) { alert('Please fill in all fields.'); return; }
  if (!selectedPc) { alert('Please select a PC from the lab map.'); return; }

  try {
    const r = await fetch('api/reservation_submit.php', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ purpose, lab, pc_number: selectedPc, date, time_in: time })
    });
    const d = await r.json();
    if (d.success) {
      showToast('Reservation submitted! Waiting for admin approval.');
      clearPcSelection(); loadMyReservations(); loadLabMap();
      document.getElementById('rPurpose').value = '';
    } else {
      alert(d.message || 'Could not submit reservation.');
    }
  } catch(e) {
    showToast('Reservation saved locally (DB unavailable).', 'warning');
  }
}

// -- MY RESERVATIONS --
async function loadMyReservations() {
  const el = document.getElementById('myResList');
  el.innerHTML = '<p style="font-size:.82rem;color:#888;text-align:center;">Loading</p>';
  try {
    const r = await fetch('api/reservation_fetch.php');
    const data = await r.json();
    if (!data.length) { el.innerHTML = '<p style="font-size:.82rem;color:#888;text-align:center;font-style:italic;">No reservations yet.</p>'; return; }
    const statusColors = { Pending:'#8b5cf6', Approved:'#10b981', Rejected:'#ef4444', Cancelled:'#64748b', Done:'#64748b' };
    el.innerHTML = data.map(r => `
      <div style="background:#f8fafc;border-radius:9px;padding:.6rem .85rem;margin-bottom:.5rem;border:1px solid #e2e8f0;">
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <span style="font-weight:700;font-size:.82rem;">Lab ${r.lab} - PC ${r.pc_number}</span>
          <span style="font-size:.7rem;font-weight:700;padding:2px 8px;border-radius:20px;background:rgba(100,116,139,.1);color:${statusColors[r.status]||'#64748b'}">${r.status}</span>
        </div>
        <div style="font-size:.75rem;color:#64748b;margin-top:.2rem;"><i class="fa-regular fa-calendar"></i> ${r.date} ${r.time_in} &nbsp;|&nbsp; ${r.purpose}</div>
        ${r.status === 'Pending' ? `<button onclick="cancelReservation(${r.id})" style="margin-top:.4rem;background:none;border:none;color:#ef4444;font-size:.72rem;cursor:pointer;font-weight:700;padding:0;"> Cancel</button>` : ''}
      </div>`).join('');
  } catch(e) {
    el.innerHTML = '<p style="font-size:.82rem;color:#888;text-align:center;font-style:italic;">No reservations yet.</p>';
  }
}
async function cancelReservation(id) {
  if (!confirm('Cancel this reservation?')) return;
  try {
    await fetch('api/reservation_fetch.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'cancel', id }) });
    showToast('Reservation cancelled.', 'warning');
    loadMyReservations();
  } catch(e) { showToast('Could not cancel.', 'danger'); }
}

// -- LEADERBOARD --
async function loadLeaderboard() {
  const tbody = document.getElementById('lbBody');
  tbody.innerHTML = '<tr class="no-data-row"><td colspan="5">Loading</td></tr>';
  try {
    const r    = await fetch('api/leaderboard.php');
    const data = await r.json();
    const medals = ['','',''];
    tbody.innerHTML = data.map((s, i) => `
      <tr class="${i<3?'lb-rank-'+(i+1):''}">
        <td><span class="rank-medal">${medals[i] || '#'+(i+1)}</span></td>
        <td style="font-weight:600;">${s.first_name} ${s.last_name}</td>
        <td style="font-size:.78rem;color:#64748b">${s.course}</td>
        <td>${s.total_sitins}</td>
        <td style="font-weight:800;color:#f59e0b">${s.points}</td>
      </tr>`).join('') || '<tr class="no-data-row"><td colspan="5">No data yet.</td></tr>';

    const myRank = data.findIndex(s => s.id_number === SESSION_USER.id_number);
    document.getElementById('myRankNum').textContent = myRank >= 0 ? '#' + (myRank+1) : 'N/A';
  } catch(e) {
    tbody.innerHTML = '<tr class="no-data-row"><td colspan="5">Could not load leaderboard.</td></tr>';
  }
}

// -- PROFILE SAVE --
function saveProfile() {
  const data = {
    first_name:  document.getElementById('pFn').value.trim(),
    last_name:   document.getElementById('pLn').value.trim(),
    middle_name: document.getElementById('pMn').value.trim(),
    email:       document.getElementById('pEm').value.trim(),
    address:     document.getElementById('pAd').value.trim(),
    course:      document.getElementById('pCo').value,
    year_level:  document.getElementById('pYr').value,
    password:    document.getElementById('pPw').value,
    password2:   document.getElementById('pPw2').value,
  };
  if (data.password && data.password !== data.password2) { alert('Passwords do not match.'); return; }
  // FIX: post to update_profile.php (the API), NOT edit_profile.php (which is a full page)
  fetch('update_profile.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(data) })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        showToast('Profile updated!');
        document.getElementById('dName').textContent   = data.first_name + ' ' + data.last_name;
        document.getElementById('profName').textContent = data.first_name + ' ' + data.last_name;
        document.getElementById('dCourse').textContent  = data.course;
        document.getElementById('dYear').textContent    = data.year_level + ' Year';
        // Clear password fields so user doesn't accidentally re-submit
        document.getElementById('pPw').value  = '';
        document.getElementById('pPw2').value = '';
      } else { alert(d.message || 'Could not save profile.'); }
    })
    .catch(() => showToast('Profile saved locally.', 'warning'));
}

function triggerPhotoInput() { document.getElementById('photoInput').click(); }
function previewPhoto(e) {
  const file = e.target.files[0]; if (!file) return;
  const url  = URL.createObjectURL(file);
  document.getElementById('profAvatar').src = url;
  document.getElementById('mainAvatar').src  = url;
}

// -- EXPORT CSV --
function exportCSV() {
  const h = ['Purpose','Lab','PC','Login','Logout','Date','Status'];
  const rows = histData.map(r => [r.purpose,r.lab,r.pc_number,r.login,r.logout,r.date,r.status]);
  const csv = [h, ...rows].map(r => r.map(c => `"${c}"`).join(',')).join('\n');
  const a = document.createElement('a');
  a.href = 'data:text/csv,' + encodeURIComponent(csv);
  a.download = 'sit_in_history.csv';
  a.click();
}

// -- NOTIFS --
async function loadNotifications() {
  try {
    const d = await fetch('api/notifications.php').then(r => r.json());
    const items = d.notifications || [];
    const unread = d.unread || 0;
    const badge  = document.getElementById('notifBadge');
    const el     = document.getElementById('notifItems');
    if (!el) return;

    if (badge) {
      badge.textContent = unread;
      badge.style.display = unread > 0 ? 'inline-flex' : 'none';
    }

    const typeIcon = {
      success:'fa-circle-check', danger:'fa-circle-xmark',
      announcement:'fa-bullhorn', warning:'fa-triangle-exclamation',
      info:'fa-envelope'
    };
    const typeColor = {
      success:'green', danger:'red', announcement:'blue',
      warning:'gold',  info:'blue'
    };
    const relTime = dt => {
      if (!dt) return '';
      const diff = Math.floor((Date.now() - new Date(dt)) / 1000);
      if (diff < 60)   return 'Just now';
      if (diff < 3600) return Math.floor(diff/60)+'m ago';
      if (diff < 86400) return Math.floor(diff/3600)+'h ago';
      return Math.floor(diff/86400)+'d ago';
    };

    el.innerHTML = items.length
      ? items.map(n => `
          <div class="notif-item${n.is_read?'':' unread'}" onclick="markRead(${n.id}, this)" style="${!n.is_read?'background:rgba(59,130,246,.06);':''};cursor:pointer;">
            <div class="notif-icon ${typeColor[n.type]||'blue'}"><i class="fa-solid ${typeIcon[n.type]||'fa-bell'}"></i></div>
            <div>
              <div class="notif-title">${n.title||'Notification'}${!n.is_read?'<span style="width:6px;height:6px;background:#3b82f6;border-radius:50%;display:inline-block;margin-left:5px;vertical-align:middle;"></span>':''}</div>
              <div class="notif-time">${(n.message||'').substring(0,80)}${(n.message||'').length>80?'':''}</div>
              <div class="notif-time" style="color:#aaa;">${relTime(n.created_at)}</div>
            </div>
          </div>`).join('')
      : '<div style="padding:1.4rem;text-align:center;color:#999;font-size:.82rem;">No notifications yet</div>';
  } catch(e) {
    const el = document.getElementById('notifItems');
    if (el) el.innerHTML = '<div style="padding:1.2rem;text-align:center;color:#999;font-size:.82rem;">Could not load notifications</div>';
  }
}

async function markRead(id, el) {
  try {
    await fetch('api/notifications.php', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ action:'mark_read', id })
    });
    if (el) {
      el.style.background = '';
      el.classList.remove('unread');
      const dot = el.querySelector('span[style*="border-radius:50%"]');
      if (dot) dot.remove();
    }
    loadNotifications();
  } catch(e) {}
}

async function clearNotifs() {
  try {
    await fetch('api/notifications.php', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ action:'mark_all_read' })
    });
  } catch(e) {}
  document.getElementById('notifItems').innerHTML = '<div style="padding:1.2rem;text-align:center;color:#999;font-size:.82rem;">No notifications</div>';
  const badge = document.getElementById('notifBadge');
  if (badge) badge.style.display = 'none';
}

// -- DARK MODE --
function toggleDarkMode() {
  const isDark = document.body.classList.toggle('dark-mode');
  localStorage.setItem('ccs_dark_mode', isDark ? '1' : '0');
  const icon = document.getElementById('dmIcon');
  if (icon) { icon.className = isDark ? 'fa-solid fa-sun' : 'fa-solid fa-moon'; }
}
function applyDarkMode() {
  if (localStorage.getItem('ccs_dark_mode') === '1') {
    document.body.classList.add('dark-mode');
    const icon = document.getElementById('dmIcon');
    if (icon) icon.className = 'fa-solid fa-sun';
  }
}

// -- SIT-IN SUMMARY --
let summaryData = [];
let sumPage = 1;

function calcDuration(t1, t2) {
  if (!t1 || !t2) return null;
  const ms = new Date(t2) - new Date(t1);
  if (ms <= 0) return null;
  return ms / 60000; // minutes
}
function fmtDur(mins) {
  if (mins === null || mins === undefined) return '-';
  const h = Math.floor(mins / 60);
  const m = Math.round(mins % 60);
  return h > 0 ? `${h}h ${m}m` : `${m}m`;
}

// PC toggle state (in-memory per session)
// Load persisted PC disabled state from localStorage
const pcDisabled = (() => {
  try { return JSON.parse(localStorage.getItem('pcDisabled') || '{}'); } catch(e) { return {}; }
})();

async function loadSitinSummary() {
  // Always use PHP-injected data — api/reports.php is admin-only
  // so fetching it from student dashboard returns empty array
  summaryData = <?= json_encode(array_map(fn($h) => [
    'id'           => $h['id'] ?? 0,
    'id_number'    => $h['id_number'] ?? '',
    'purpose'      => $h['purpose'] ?? '-',
    'lab'          => $h['lab'] ?? '-',
    'pc_number'    => $h['pc_number'] ?? null,
    'created_at'   => $h['created_at'] ?? null,
    'timed_out_at' => $h['timed_out_at'] ?? null,
    'status'       => $h['status'] ?? 'Done',
  ], $history)) ?>;
  computeAndRenderSummary();
}

function computeAndRenderSummary() {
  let totalMins = 0, count = 0, longest = 0;
  summaryData.forEach(s => {
    const d = calcDuration(s.created_at, s.timed_out_at);
    if (d !== null) { totalMins += d; count++; if (d > longest) longest = d; }
  });
  const avg = count > 0 ? totalMins / count : 0;
  const totalHrs = (totalMins / 60).toFixed(1);

  document.getElementById('sumTotalHrs').textContent     = totalHrs + 'h';
  document.getElementById('sumTotalSessions').textContent = summaryData.length;
  document.getElementById('sumAvgDuration').textContent   = fmtDur(avg);
  document.getElementById('sumLongest').textContent       = fmtDur(longest);

  sumPage = 1;
  renderSummaryTable();
}

function renderSummaryTable() {
  const q   = (document.getElementById('sumSearch').value || '').toLowerCase();
  const pp  = parseInt(document.getElementById('sumEntries').value || 10);
  let data = summaryData.filter(s => (s.purpose + s.lab + s.status).toLowerCase().includes(q));
  const pages = Math.max(1, pp === 0 ? 1 : Math.ceil(data.length / pp));
  if (sumPage > pages) sumPage = pages;
  const slice = pp === 0 ? data : data.slice((sumPage-1)*pp, sumPage*pp);

  const tbody = document.getElementById('sumBody');
  if (!slice.length) {
    tbody.innerHTML = `<tr class="no-data-row"><td colspan="8">No sessions found.</td></tr>`; 
  } else {
    tbody.innerHTML = slice.map(s => {
      const dur   = calcDuration(s.created_at, s.timed_out_at);
      const date  = s.created_at ? new Date(s.created_at).toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'}) : '-';
      const tIn   = s.created_at  ? new Date(s.created_at).toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit'}) : '-';
      const tOut  = s.timed_out_at ? new Date(s.timed_out_at).toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit'}) : '-';
      const pcNum = s.pc_number || '-';
      const isDisabled = pcDisabled[`${s.lab}_${s.pc_number}`];
      const toggleLabel = isDisabled ? 'Disabled' : 'Enabled';
      const toggleClass = isDisabled ? 'disabled' : 'enabled';
      const statusColor = s.status === 'Active' ? '#10b981' : '#64748b';
      const statusBg    = s.status === 'Active' ? 'rgba(16,185,129,.12)' : 'rgba(100,116,139,.12)';
      const pcCell = s.pc_number
        ? `<div style="display:flex;flex-direction:column;align-items:center;gap:4px;"><span style="font-weight:700;font-size:.9rem;">${s.pc_number}</span><button class="pc-toggle-btn ${toggleClass}" onclick="togglePc('${s.lab}',${s.pc_number},this)"><i class="fa-solid fa-${isDisabled ? 'ban' : 'circle-check'}" style="font-size:.6rem;"></i> ${toggleLabel}</button></div>`
        : '-';
      return `<tr>
        <td>${date}</td>
        <td>${tIn}</td>
        <td>${tOut}</td>
        <td>${fmtDur(dur)}</td>
        <td>${s.lab}</td>
        <td style="text-align:center;">${pcCell}</td>
        <td style="max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${s.purpose}</td>
        <td><span style="font-size:.72rem;font-weight:700;padding:2px 8px;border-radius:20px;background:${statusBg};color:${statusColor}">${s.status}</span></td>
      </tr>`;
    }).join('');
  }

  const total = data.length;
  const from  = pp === 0 ? 1 : (sumPage-1)*pp + 1;
  const to    = pp === 0 ? total : Math.min(sumPage*pp, total);
  document.getElementById('sumInfo').textContent = total ? `Showing ${from}${to} of ${total}` : 'No entries';

  // Pagination
  const pgEl = document.getElementById('sumPagination');
  if (pp === 0 || pages <= 1) { pgEl.innerHTML = ''; return; }
  let pg = '';
  for (let i=1; i<=pages; i++) pg += `<button class="btn-sm btn ${i===sumPage?'btn-primary':'btn-outline-secondary'}" style="font-size:.72rem;padding:2px 8px;" onclick="sumPage=${i};renderSummaryTable()">${i}</button>`;
  pgEl.innerHTML = pg;
}

function togglePc(lab, pcNum, btn) {
  const key = `${lab}_${pcNum}`;
  pcDisabled[key] = !pcDisabled[key];
  // Persist to localStorage
  try { localStorage.setItem('pcDisabled', JSON.stringify(pcDisabled)); } catch(e) {}
  // Update button text and icon
  const isNowDisabled = pcDisabled[key];
  btn.innerHTML = `<i class="fa-solid fa-${isNowDisabled ? 'ban' : 'circle-check'}" style="font-size:.6rem;"></i> ${isNowDisabled ? 'Disabled' : 'Enabled'}`;
  btn.className = `pc-toggle-btn ${isNowDisabled ? 'disabled' : 'enabled'}`;
  showToast(`PC ${pcNum} in Lab ${lab} is now ${isNowDisabled ? 'DISABLED' : 'ENABLED'} for reservation.`, isNowDisabled ? 'warning' : 'success');
}

function exportSummaryCSV() {
  const h = ['Date','Time In','Time Out','Duration','Lab','PC #','Purpose','Status'];
  const rows = summaryData.map(s => {
    const dur  = calcDuration(s.created_at, s.timed_out_at);
    const date = s.created_at ? new Date(s.created_at).toLocaleDateString() : '';
    const tIn  = s.created_at ? new Date(s.created_at).toLocaleTimeString() : '';
    const tOut = s.timed_out_at ? new Date(s.timed_out_at).toLocaleTimeString() : '';
    return [date, tIn, tOut, fmtDur(dur), s.lab, s.pc_number || '', s.purpose, s.status];
  });
  const csv = [h, ...rows].map(r => r.map(c => `"${c}"`).join(',')).join('\n');
  const a = document.createElement('a');
  a.href = 'data:text/csv,' + encodeURIComponent(csv);
  a.download = 'my_sitin_summary.csv';
  a.click();
}

// -- LAB AVAILABILITY --
async function loadLabAvailability() {
  const grid = document.getElementById('labAvailGrid');
  if (!grid) return;
  grid.innerHTML = '<div style="color:#64748b;font-size:.83rem;padding:1rem;"><i class="fa-solid fa-spinner fa-spin"></i> Loading</div>';

  const labs = ['524','526','528','530'];
  const today = new Date().toISOString().slice(0,10);
  const results = await Promise.all(labs.map(async lab => {
    try {
      const d = await fetch(`api/lab_pc_status.php?lab=${lab}&date=${today}`).then(r => r.json());
      return { lab, ...d };
    } catch(e) {
      return { lab, available_count: '?', occupied_count: '?', reserved_count: '?', total_pcs: 40 };
    }
  }));

  grid.innerHTML = results.map(r => {
    const avail = typeof r.available_count === 'number' ? r.available_count : 0;
    const total = r.total_pcs || 40;
    const pct   = Math.round((avail / total) * 100);
    const cls   = avail === 0 ? 'full' : avail < 5 ? 'low' : 'ok';
    const icCls = avail === 0 ? 'busy' : 'open';
    return `<div class="sw-lab-card">
      <div class="sw-lab-icon ${icCls}">
        <i class="fa-solid fa-desktop"></i>
      </div>
      <div style="flex:1;min-width:0;">
        <div class="sw-lab-name">Laboratory ${r.lab}</div>
        <div class="sw-lab-avail">${r.available_count} / ${total} PCs available</div>
        <div style="margin-top:.35rem;height:5px;background:#e2e8f0;border-radius:3px;">
          <div style="height:100%;width:${pct}%;border-radius:3px;background:${cls==='ok'?'#10b981':cls==='low'?'#f59e0b':'#ef4444'};transition:width .4s;"></div>
        </div>
        <div style="display:flex;gap:.35rem;margin-top:.35rem;flex-wrap:wrap;">
          <span class="sw-pc-badge ${cls}">${avail} free</span>
          ${r.occupied_count ? `<span class="sw-pc-badge" style="background:rgba(239,68,68,.1);color:#dc2626;">${r.occupied_count} in-use</span>` : ''}
          ${r.reserved_count ? `<span class="sw-pc-badge" style="background:rgba(245,158,11,.1);color:#d97706;">${r.reserved_count} reserved</span>` : ''}
        </div>
      </div>
    </div>`;
  }).join('');

  // Also reload software list every time lab tab opens
  loadSoftwareGrid();
}

// -- SOFTWARE GRID (fetched from DB via api/software.php) --
// Hardcoded default software — always shown for a lively look
const DEFAULT_SOFTWARE = [
  { name:'MS Office 365',       category:'Office / Productivity', description:'Word, Excel, PowerPoint',  icon:'fa-file-word',     color:'#2563eb', labs:'All' },
  { name:'Visual Studio Code',  category:'Programming IDE',       description:'Code editor & debugger',   icon:'fa-code',          color:'#0078d4', labs:'All' },
  { name:'XAMPP',               category:'Utility / Tool',        description:'Apache + MySQL + PHP',     icon:'fa-server',        color:'#fb7a24', labs:'All' },
  { name:'MySQL Workbench',     category:'Database Tool',         description:'Database management tool', icon:'fa-database',      color:'#00758f', labs:'524,530' },
  { name:'NetBeans IDE',        category:'Programming IDE',       description:'Java development',         icon:'fa-mug-hot',       color:'#1b6ac6', labs:'524,526' },
  { name:'IntelliJ IDEA',       category:'Programming IDE',       description:'JetBrains Java IDE',       icon:'fa-bolt',          color:'#f0006d', labs:'526,528' },
  { name:'Android Studio',      category:'Programming IDE',       description:'Android app development',  icon:'fa-mobile-screen', color:'#3ddc84', labs:'526,528' },
  { name:'Python 3.x',          category:'Programming IDE',       description:'Python interpreter & pip', icon:'fa-python',        color:'#3776ab', labs:'All' },
  { name:'Git',                 category:'Utility / Tool',        description:'Version control',          icon:'fa-code-branch',   color:'#f05032', labs:'All' },
  { name:'Adobe Photoshop',     category:'Design Software',       description:'Image editing',            icon:'fa-image',         color:'#31a8ff', labs:'528,530' },
  { name:'Figma (Browser)',     category:'Design Software',       description:'UI/UX design tool',        icon:'fa-pen-nib',       color:'#a259ff', labs:'All' },
  { name:'Cisco Packet Tracer', category:'Utility / Tool',        description:'Network simulation',       icon:'fa-network-wired', color:'#1ba0d7', labs:'528' },
];

async function loadSoftwareGrid() {
  const grid    = document.getElementById('softwareGrid');
  const spinner = document.getElementById('swLoadingSpinner');
  if (!grid) return;

  const catColors = {
    'Programming IDE':    '#3b82f6',
    'Database Tool':      '#10b981',
    'Design Software':    '#f59e0b',
    'Utility / Tool':     '#8b5cf6',
    'Reference / Document':'#64748b',
    'Lab Manual':         '#f97316',
    'Office / Productivity':'#2563eb',
    'Other':              '#94a3b8'
  };

  function renderCard(sw) {
    const color = sw.color || catColors[sw.category] || '#64748b';
    const icon  = sw.icon  || 'fa-box-archive';
    const labs  = sw.labs === 'All' ? 'All Labs' : 'Lab ' + sw.labs.split(',').join(', ');
    return `<div class="col-md-4 col-lg-3">
      <div class="sw-lab-card">
        <div class="sw-lab-icon open" style="background:${color}20;color:${color};">
          <i class="fa-solid ${icon}"></i>
        </div>
        <div>
          <div class="sw-lab-name" style="font-size:.8rem;">${sw.name}</div>
          <div class="sw-lab-avail">${sw.description || sw.category}</div>
          <div style="display:flex;gap:.35rem;flex-wrap:wrap;margin-top:.3rem;">
            <span class="sw-pc-badge ok"><i class="fa-solid fa-circle-check" style="font-size:.55rem;"></i> Installed</span>
            <span class="sw-pc-badge" style="background:rgba(99,102,241,.1);color:#6366f1;font-size:.62rem;">${labs}</span>
          </div>
        </div>
      </div>
    </div>`;
  }

  // 1) Render hardcoded defaults immediately
  if (spinner) spinner.style.display = 'none';
  grid.innerHTML = DEFAULT_SOFTWARE.map(renderCard).join('');

  // 2) Append any custom software from the API (admin-added)
  try {
    const data = await fetch('api/software.php').then(r => r.json());
    if (Array.isArray(data) && data.length > 0) {
      grid.innerHTML += data.map(renderCard).join('');
    }
  } catch(e) {
    // API failed — defaults are already showing
  }
}

// -- LOGOUT --
function confirmLogout() { new bootstrap.Modal(document.getElementById('modalLogout')).show(); }
function doLogout() { window.location.href = 'logout.php'; }

// -- INIT --
document.addEventListener('DOMContentLoaded', () => {
  // Apply dark mode preference
  applyDarkMode();

  // Set today's date as default
  document.getElementById('rDate').valueAsDate = new Date();

  // Load notifications immediately + poll every 30s
  loadNotifications();
  setInterval(loadNotifications, 30000);

  // Load reservations and leaderboard
  loadMyReservations();
  loadLeaderboard();

  // Show login modal if first load
  if (sessionStorage.getItem('justLoggedIn') === '1') {
    sessionStorage.removeItem('justLoggedIn');
    new bootstrap.Modal(document.getElementById('modalLogin')).show();
  }
});
</script>
</body>
</html>