<?php
// dashboard.php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit();
}

require_once 'config.php';

try {
    $conn = db_connect();
    $uid  = (int) $_SESSION['user_id'];

    // Re-fetch fresh user data from DB every load
    $res  = $conn->query("SELECT * FROM `users` WHERE id = $uid LIMIT 1");
    $user = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : [];
    if ($user) $_SESSION['user'] = array_merge($_SESSION['user'] ?? [], $user);

    // Announcements (safe — only if table exists)
    $announcements = [];
    $ac = $conn->query("SHOW TABLES LIKE 'announcements'");
    if ($ac && $ac->num_rows > 0) {
        $ar = $conn->query("SELECT * FROM `announcements` ORDER BY created_at DESC LIMIT 10");
        if ($ar) $announcements = $ar->fetch_all(MYSQLI_ASSOC);
    }

    // Sit-in history (safe — only if table exists)
    $history      = [];
    $active_sitin = null;
    foreach (['sit_in','sitin','sit_ins','sit_in_records'] as $tbl) {
        $tc = $conn->query("SHOW TABLES LIKE '$tbl'");
        if ($tc && $tc->num_rows > 0) {
            $fk  = 'student_id';
            $fkc = $conn->query("SHOW COLUMNS FROM `$tbl` LIKE 'user_id'");
            if ($fkc && $fkc->num_rows > 0) $fk = 'user_id';
            $hr = $conn->query("SELECT * FROM `$tbl` WHERE `$fk`=$uid ORDER BY id DESC LIMIT 10");
            if ($hr) {
                $history = $hr->fetch_all(MYSQLI_ASSOC);
                foreach ($history as $h) {
                    if (($h['status'] ?? '') === 'Active') { $active_sitin = $h; break; }
                }
            }
            break;
        }
    }

    $conn->close();
} catch (Throwable $e) {
    $user = $_SESSION['user'] ?? [];
    $announcements = [];
    $history = [];
    $active_sitin = null;
}

// Build the SESSION_USER object that script.js reads
$su = [
    'id_number'           => $user['id_number']           ?? '',
    'first_name'          => $user['first_name']          ?? '',
    'last_name'           => $user['last_name']           ?? '',
    'middle_name'         => $user['middle_name']         ?? '',
    'email'               => $user['email']               ?? '',
    'address'             => $user['address']             ?? '',
    'course'              => $user['course']              ?? '',
    'year_level'          => $user['year_level']          ?? '',
    'remaining_sessions'  => $user['remaining_sessions']  ?? 30,
    'profile_photo'       => $user['profile_photo']       ?? '',
];

$fullname    = trim(($su['first_name'] ?? '') . ' ' . ($su['last_name'] ?? ''));
$initials    = strtoupper(substr($su['first_name'],0,1) . substr($su['last_name'],0,1)) ?: 'S';
$avatar_src  = !empty($su['profile_photo'])
    ? htmlspecialchars($su['profile_photo'])
    : 'https://api.dicebear.com/8.x/adventurer/svg?seed=' . urlencode($fullname ?: 'Student') . '&backgroundColor=b6e3f4';
$year_label  = $su['year_level'] ? $su['year_level'] . ' Year' : '';
$sess        = (int)($su['remaining_sessions'] ?? 30);
$sess_pct    = min(100, ($sess / 30) * 100);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>CCS Sit-in Portal – Student Dashboard</title>

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
  <!-- Custom Styles -->
  <link href="style.css" rel="stylesheet" />
</head>
<body>

<!-- ══════════════════════════════════════════════════════════
     TOAST
══════════════════════════════════════════════════════════ -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="margin-top:66px">
  <div id="liveToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="toast-body">
      <i class="fa-solid fa-circle-check" id="toastIcon"></i>
      <span id="toastMsg">Done!</span>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     NAVBAR
══════════════════════════════════════════════════════════ -->
<nav class="navbar navbar-expand-lg ccs-navbar sticky-top">
  <div class="container-fluid px-0">

    <a class="navbar-brand" href="#">
      <span class="brand-pip"></span> CCS Sit-in Portal
    </a>

    <button class="navbar-toggler border-0" type="button"
            data-bs-toggle="collapse" data-bs-target="#navbarMain"
            style="color:#fff">
      <i class="fa-solid fa-bars"></i>
    </button>

    <div class="collapse navbar-collapse" id="navbarMain">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-1">

        <!-- Notification dropdown -->
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="notifToggle"
             data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fa-regular fa-bell"></i> Notification
            <span class="notif-badge" id="notifBadge">3</span>
          </a>
          <div class="dropdown-menu ccs-dropdown-menu p-0" aria-labelledby="notifToggle" style="width:290px">
            <div class="notif-header">
              Notifications
              <span class="notif-clear" onclick="clearNotifs()">Clear all</span>
            </div>
            <div id="notifItems">
              <div class="notif-item">
                <div class="notif-icon green"><i class="fa-solid fa-circle-check"></i></div>
                <div>
                  <div class="notif-title">Reservation Approved</div>
                  <div class="notif-time">Lab 524 · Today, 9:00 AM</div>
                </div>
              </div>
              <div class="notif-item">
                <div class="notif-icon blue"><i class="fa-solid fa-envelope"></i></div>
                <div>
                  <div class="notif-title">New Announcement from Admin</div>
                  <div class="notif-time">Feb 11, 2026</div>
                </div>
              </div>
              <div class="notif-item">
                <div class="notif-icon gold"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div>
                  <div class="notif-title">Session reminder: <?= $sess ?> remaining</div>
                  <div class="notif-time">Yesterday</div>
                </div>
              </div>
            </div>
          </div>
        </li>

        <li class="nav-item">
          <a class="nav-link active" data-tab="home" onclick="switchTab('home')">
            <i class="fa-solid fa-house"></i> Home
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" data-tab="profile" onclick="switchTab('profile')">
            <i class="fa-solid fa-user-pen"></i> Edit Profile
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" data-tab="history" onclick="switchTab('history')">
            <i class="fa-solid fa-clock-rotate-left"></i> History
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" data-tab="reservation" onclick="switchTab('reservation')">
            <i class="fa-solid fa-calendar-plus"></i> Reservation
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link btn-logout ms-1" onclick="confirmLogout()">
            <i class="fa-solid fa-right-from-bracket"></i> Log out
          </a>
        </li>

      </ul>
    </div>
  </div>
</nav>

<!-- ══════════════════════════════════════════════════════════
     PAGE
══════════════════════════════════════════════════════════ -->
<div class="page-wrap">

  <!-- ██████████████  HOME VIEW  ██████████████ -->
  <div class="view active" id="view-home">
    <div class="row g-4">

      <!-- LEFT: Student Info -->
      <div class="col-lg-3">
        <div class="ccs-card">
          <div class="ccs-card-header">
            <i class="fa-solid fa-id-card"></i> Student Information
          </div>

          <div class="stu-avatar-wrap">
            <img id="mainAvatar" src="<?= $avatar_src ?>" alt="Student Avatar"
              onerror="this.src='https://api.dicebear.com/8.x/adventurer/svg?seed=Student&backgroundColor=b6e3f4'"/>
            <div class="stu-name" id="dName"><?= htmlspecialchars($fullname ?: 'Student') ?></div>
            <span class="stu-badge" id="dBadge"><?= htmlspecialchars(trim($su['course'] . ($su['year_level'] ? ' · Year '.$su['year_level'] : ''))) ?></span>
          </div>

          <ul class="info-list">
            <li>
              <span class="info-icon"><i class="fa-solid fa-hashtag"></i></span>
              <div>
                <div class="info-label">ID Number</div>
                <div class="info-value" id="dId"><?= htmlspecialchars($su['id_number'] ?: '—') ?></div>
              </div>
            </li>
            <li>
              <span class="info-icon"><i class="fa-solid fa-graduation-cap"></i></span>
              <div>
                <div class="info-label">Course</div>
                <div class="info-value" id="dCourse"><?= htmlspecialchars($su['course'] ?: '—') ?></div>
              </div>
            </li>
            <li>
              <span class="info-icon"><i class="fa-solid fa-layer-group"></i></span>
              <div>
                <div class="info-label">Year Level</div>
                <div class="info-value" id="dYear"><?= htmlspecialchars($su['year_level'] ? $su['year_level'].' Year' : '—') ?></div>
              </div>
            </li>
            <li>
              <span class="info-icon"><i class="fa-solid fa-envelope"></i></span>
              <div>
                <div class="info-label">Email</div>
                <div class="info-value" id="dEmail"><?= htmlspecialchars($su['email'] ?: '—') ?></div>
              </div>
            </li>
            <li>
              <span class="info-icon"><i class="fa-solid fa-location-dot"></i></span>
              <div>
                <div class="info-label">Address</div>
                <div class="info-value" id="dAddr"><?= htmlspecialchars($su['address'] ?: '—') ?></div>
              </div>
            </li>
          </ul>

          <div class="session-block">
            <div class="s-label"><i class="fa-regular fa-hourglass"></i>&nbsp; Remaining Sessions</div>
            <div class="s-num" id="sNum"><?= $sess ?></div>
            <div class="s-sub">out of 30 total sessions</div>
            <div class="session-bar">
              <div class="session-bar-fill" id="sessFill" style="width:<?= $sess_pct ?>%"></div>
            </div>
          </div>
        </div>
      </div><!-- /col -->

      <!-- MIDDLE -->
      <div class="col-lg-6 d-flex flex-column gap-3">

        <!-- Status strip -->
        <div class="status-strip <?= $active_sitin ? 'on' : 'off' ?>" id="statusStrip">
          <span class="pulse-dot <?= $active_sitin ? 'on' : 'off' ?>" id="pulseD"></span>
          <span id="statusMsg">
            <?php if ($active_sitin): ?>
              You are <strong>currently sitting in.</strong>
            <?php else: ?>
              You are <strong>not currently sitting in.</strong>
              Use <strong>Reservation</strong> to book a lab session.
            <?php endif; ?>
          </span>
        </div>

        <!-- Quick Actions -->
        <div class="ccs-card">
          <div class="ccs-card-header"><i class="fa-solid fa-bolt"></i> Quick Actions</div>
          <div class="ccs-card-body">
            <div class="row g-3">
              <div class="col-6">
                <button class="qa-btn primary" onclick="switchTab('reservation')">
                  <i class="fa-solid fa-calendar-plus"></i> Reserve a Lab
                </button>
              </div>
              <div class="col-6">
                <button class="qa-btn" onclick="switchTab('history')">
                  <i class="fa-solid fa-clock-rotate-left"></i> View History
                </button>
              </div>
              <div class="col-6">
                <button class="qa-btn" onclick="switchTab('profile')">
                  <i class="fa-solid fa-user-pen"></i> Edit Profile
                </button>
              </div>
              <div class="col-6">
                <button class="qa-btn" onclick="document.getElementById('notifToggle').click()">
                  <i class="fa-solid fa-bell"></i> Notifications
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Announcements — real data from DB, fallback to static if none -->
        <div class="ccs-card">
          <div class="ccs-card-header"><i class="fa-solid fa-bullhorn"></i> Announcements</div>
          <div class="ccs-card-body">
            <?php if (!empty($announcements)): ?>
              <?php foreach ($announcements as $ann): ?>
                <div class="ann-item">
                  <div class="ann-meta">
                    <span class="ann-tag">CCS Admin</span>
                    <span class="ann-date">
                      <i class="fa-regular fa-calendar"></i>
                      <?= htmlspecialchars(date('Y M d', strtotime($ann['created_at'] ?? 'now'))) ?>
                    </span>
                  </div>
                  <div class="ann-text <?= empty($ann['message']) ? 'ann-empty' : '' ?>">
                    <?= empty($ann['message'])
                        ? 'No message content for this announcement.'
                        : nl2br(htmlspecialchars($ann['message'])) ?>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <!-- Static announcements shown when table doesn't exist yet -->
              <div class="ann-item">
                <div class="ann-meta">
                  <span class="ann-tag">CCS Admin</span>
                  <span class="ann-date"><i class="fa-regular fa-calendar"></i> 2026 Feb 11</span>
                </div>
                <div class="ann-text ann-empty">No message content for this announcement.</div>
              </div>
              <div class="ann-item">
                <div class="ann-meta">
                  <span class="ann-tag">CCS Admin</span>
                  <span class="ann-date"><i class="fa-regular fa-calendar"></i> 2024 May 08</span>
                </div>
                <div class="ann-text">
                  🎉 <strong>Important Announcement</strong> — We are excited to announce the
                  launch of our new website! Explore our latest products and services now!
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Recent Sit-in History (mini) -->
        <div class="ccs-card">
          <div class="ccs-card-header"><i class="fa-solid fa-table-list"></i> Recent Sit-in History</div>
          <div class="table-responsive">
            <table class="ccs-table">
              <thead>
                <tr>
                  <th>Purpose</th><th>Laboratory</th><th>Login</th><th>Logout</th><th>Date</th>
                </tr>
              </thead>
              <tbody id="miniHistBody">
                <?php if (!empty($history)): ?>
                  <?php foreach (array_slice($history, 0, 3) as $h): ?>
                    <tr>
                      <td><?= htmlspecialchars($h['purpose'] ?? '—') ?></td>
                      <td><?= htmlspecialchars($h['lab']     ?? '—') ?></td>
                      <td><?= !empty($h['login_time'])  ? date('h:i A', strtotime($h['login_time']))  : '—' ?></td>
                      <td><?= !empty($h['logout_time']) ? date('h:i A', strtotime($h['logout_time'])) : '—' ?></td>
                      <td><?= !empty($h['login_time'])  ? date('Y-m-d', strtotime($h['login_time']))  : '—' ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr class="no-data-row">
                    <td colspan="5">
                      <i class="fa-regular fa-folder-open" style="font-size:1.3rem;display:block;margin-bottom:8px;opacity:.35"></i>
                      No records yet
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      </div><!-- /col mid -->

      <!-- RIGHT: Rules -->
      <div class="col-lg-3 d-none d-lg-block">
        <div class="ccs-card">
          <div class="ccs-card-header"><i class="fa-solid fa-shield-halved"></i> Rules &amp; Regulations</div>
          <div class="rules-scroll">
            <div class="rules-uni">University of Cebu</div>
            <div class="rules-dept">College of Information &amp; Computer Studies</div>
            <div class="rules-sec">Laboratory Rules and Regulations</div>
            <p class="mb-2" style="font-size:.82rem">
              To avoid embarrassment and maintain camaraderie with your friends and superiors
              at our laboratories, please observe the following:
            </p>
            <ol>
              <li>Maintain silence, proper decorum, and discipline inside the laboratory. Mobile phones and personal equipment must be switched off.</li>
              <li>Games are not allowed inside the lab — computer-related, card games, or anything that may disturb operations.</li>
              <li>Surfing the Internet is allowed only with the instructor's permission. Downloading and installing software are strictly prohibited.</li>
              <li>Deleting computer files and changing computer setup is not allowed.</li>
              <li>Observe proper sitting posture at all times.</li>
              <li>Laboratory users must sign in the logbook before using any computer unit.</li>
              <li>All bags must be deposited at the bag deposit area outside the laboratory.</li>
              <li>Eating and drinking inside the laboratory is strictly prohibited.</li>
              <li>Students must present their valid ID and log in to the sit-in monitoring system.</li>
              <li>Violations will subject the student to disciplinary action.</li>
            </ol>
          </div>
        </div>
      </div><!-- /col right -->

    </div><!-- /row -->
  </div><!-- /home view -->


  <!-- ██████████████  HISTORY VIEW  ██████████████ -->
  <div class="view" id="view-history">

    <div class="view-header">
      <div class="view-title">
        <i class="fa-solid fa-clock-rotate-left"></i> History Information
      </div>
      <button class="btn-ccs-export" onclick="exportCSV()">
        <i class="fa-solid fa-download"></i> Export CSV
      </button>
    </div>

    <div class="ccs-card">
      <div class="ccs-card-body p-4">

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
          <div class="tbl-entries d-flex align-items-center gap-2" style="font-size:.82rem;color:var(--text2)">
            Show
            <select id="histEntries" onchange="renderHistory()">
              <option value="5">5</option>
              <option value="10" selected>10</option>
              <option value="25">25</option>
            </select>
            entries per page
          </div>
          <div class="tbl-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="histSearch" placeholder="Search…" oninput="renderHistory()" />
          </div>
        </div>

        <div class="table-responsive">
          <table class="ccs-table">
            <thead>
              <tr>
                <th>ID Number</th><th>Name</th><th>Purpose</th>
                <th>Laboratory</th><th>Login</th><th>Logout</th>
                <th>Date</th><th>Action</th>
              </tr>
            </thead>
            <tbody id="histBody"></tbody>
          </table>
        </div>

        <div class="tbl-footer mt-3">
          <span id="histInfo">Showing 0 entries</span>
          <div class="d-flex gap-1" id="histPagination"></div>
        </div>

      </div>
    </div>
  </div><!-- /history view -->


  <!-- ██████████████  RESERVATION VIEW  ██████████████ -->
  <div class="view" id="view-reservation">

    <div class="view-header">
      <div class="view-title">
        <i class="fa-solid fa-calendar-plus"></i> Reservation
      </div>
    </div>

    <div class="row g-4">

      <!-- Form -->
      <div class="col-lg-8">
        <div class="ccs-card">
          <div class="ccs-card-header"><i class="fa-solid fa-pen-to-square"></i> New Reservation</div>
          <div class="ccs-card-body p-4">

            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label-ccs">ID Number</label>
                <input class="form-control-ccs" type="text" id="rId"
                  value="<?= htmlspecialchars($su['id_number']) ?>" readonly />
              </div>
              <div class="col-md-6">
                <label class="form-label-ccs">Student Name</label>
                <input class="form-control-ccs" type="text" id="rName"
                  value="<?= htmlspecialchars($fullname) ?>" readonly />
              </div>
              <div class="col-12">
                <label class="form-label-ccs">Purpose <span style="color:var(--red)">*</span></label>
                <input class="form-control-ccs" type="text" id="rPurpose" placeholder="e.g. C Programming, Thesis, Research…" />
              </div>
              <div class="col-md-6">
                <label class="form-label-ccs">Laboratory <span style="color:var(--red)">*</span></label>
                <select class="form-select-ccs" id="rLab">
                  <option value="">Select lab…</option>
                  <option>Lab 524</option>
                  <option>Lab 526</option>
                  <option>Lab 528</option>
                  <option>Lab 530</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label-ccs">Date <span style="color:var(--red)">*</span></label>
                <input class="form-control-ccs" type="date" id="rDate" />
              </div>
              <div class="col-md-6">
                <label class="form-label-ccs">Time In <span style="color:var(--red)">*</span></label>
                <input class="form-control-ccs" type="time" id="rTime" />
              </div>
              <div class="col-md-6">
                <label class="form-label-ccs">Remaining Sessions</label>
                <input class="form-control-ccs" type="text" id="rSess"
                  value="<?= $sess ?>" readonly />
              </div>
              <div class="col-12 mt-1">
                <button class="btn-ccs-primary" onclick="submitReservation()">
                  <i class="fa-solid fa-calendar-check"></i> Submit Reservation
                </button>
              </div>
            </div>

          </div>
        </div>
      </div><!-- /form col -->

      <!-- Guidelines + My Reservations -->
      <div class="col-lg-4 d-flex flex-column gap-3">

        <div class="ccs-card">
          <div class="ccs-card-header"><i class="fa-solid fa-circle-info"></i> Guidelines</div>
          <div class="ccs-card-body">
            <div class="res-tip">
              <i class="fa-solid fa-clock"></i>
              <p><strong>Arrive on time.</strong> Reservation cancelled if you don't check in within 15 minutes.</p>
            </div>
            <div class="res-tip">
              <i class="fa-solid fa-laptop"></i>
              <p><strong>Each session costs 1 session point.</strong> You have <strong id="tipSess"><?= $sess ?></strong> remaining.</p>
            </div>
            <div class="res-tip">
              <i class="fa-solid fa-ban"></i>
              <p><strong>No games or unauthorized software.</strong> Violations may result in forfeiture.</p>
            </div>
            <div class="res-tip">
              <i class="fa-solid fa-id-card"></i>
              <p>Bring your <strong>valid school ID</strong> when claiming your reservation.</p>
            </div>
          </div>
        </div>

        <div class="ccs-card">
          <div class="ccs-card-header"><i class="fa-solid fa-list-check"></i> My Reservations</div>
          <div class="ccs-card-body">
            <div id="myResList">
              <p class="text-center" style="font-size:.82rem;color:var(--text3);font-style:italic;padding:8px 0">
                No reservations yet.
              </p>
            </div>
          </div>
        </div>

      </div>
    </div><!-- /row -->
  </div><!-- /reservation view -->


  <!-- ██████████████  EDIT PROFILE VIEW  ██████████████ -->
  <div class="view" id="view-profile">

    <div class="view-header">
      <div class="view-title"><i class="fa-solid fa-user-pen"></i> Edit Profile</div>
    </div>

    <div class="row g-4">

      <!-- Avatar card -->
      <div class="col-lg-3">
        <div class="ccs-card">
          <div class="profile-av-card">
            <img id="profAvatar" src="<?= $avatar_src ?>" alt="Profile Avatar"
              onerror="this.src='https://api.dicebear.com/8.x/adventurer/svg?seed=Student&backgroundColor=b6e3f4'"/>
            <div class="profile-name" id="profName"><?= htmlspecialchars($fullname) ?></div>
            <div class="profile-role" id="profRole"><?= htmlspecialchars(trim($su['course'] . ($su['year_level'] ? ' · '.$su['year_level'].' Year' : ''))) ?></div>
            <button class="btn-photo" onclick="triggerPhotoInput()">
              <i class="fa-solid fa-camera"></i> Change Photo
            </button>
            <input type="file" id="photoInput" accept="image/*" style="display:none" onchange="previewPhoto(event)" />
            <div class="profile-sess-stat">
              <div class="sml">Remaining Sessions</div>
              <div class="big" id="profSessNum"><?= $sess ?></div>
              <div class="sml">out of 30</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Edit form -->
      <div class="col-lg-9">
        <div class="ccs-card">
          <div class="ccs-card-header"><i class="fa-solid fa-pen-to-square"></i> Personal Information</div>
          <div class="ccs-card-body p-4">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label-ccs">First Name</label>
                <input class="form-control-ccs" type="text" id="pFn"
                  value="<?= htmlspecialchars($su['first_name']) ?>" />
              </div>
              <div class="col-md-6">
                <label class="form-label-ccs">Last Name</label>
                <input class="form-control-ccs" type="text" id="pLn"
                  value="<?= htmlspecialchars($su['last_name']) ?>" />
              </div>
              <div class="col-md-6">
                <label class="form-label-ccs">Middle Name <span style="color:var(--text3)">(optional)</span></label>
                <input class="form-control-ccs" type="text" id="pMn"
                  value="<?= htmlspecialchars($su['middle_name'] ?? '') ?>" />
              </div>
              <div class="col-md-6">
                <label class="form-label-ccs">ID Number</label>
                <input class="form-control-ccs" type="text"
                  value="<?= htmlspecialchars($su['id_number']) ?>" readonly />
              </div>
              <div class="col-md-6">
                <label class="form-label-ccs">Email Address</label>
                <input class="form-control-ccs" type="email" id="pEm"
                  value="<?= htmlspecialchars($su['email']) ?>" />
              </div>
              <div class="col-md-6">
                <label class="form-label-ccs">Address</label>
                <input class="form-control-ccs" type="text" id="pAd"
                  value="<?= htmlspecialchars($su['address'] ?? '') ?>" />
              </div>
              <div class="col-md-6">
                <label class="form-label-ccs">Course</label>
                <select class="form-select-ccs" id="pCo">
                  <?php foreach (['BSIT','BSCS','BSIS','ACT'] as $c): ?>
                    <option <?= $su['course']===$c?'selected':'' ?>><?= $c ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label-ccs">Year Level</label>
                <select class="form-select-ccs" id="pYr">
                  <?php foreach (['1','2','3','4'] as $y): ?>
                    <option value="<?= $y ?>" <?= $su['year_level']==$y?'selected':'' ?>>
                      <?= $y === '1' ? '1st' : ($y === '2' ? '2nd' : ($y === '3' ? '3rd' : '4th')) ?> Year
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-12"><hr class="divider" /></div>

              <div class="col-md-6">
                <label class="form-label-ccs">New Password</label>
                <input class="form-control-ccs" type="password" id="pPw"
                  placeholder="Leave blank to keep current" autocomplete="new-password"/>
              </div>
              <div class="col-md-6">
                <label class="form-label-ccs">Confirm Password</label>
                <input class="form-control-ccs" type="password" id="pPw2"
                  placeholder="Repeat new password" autocomplete="new-password"/>
              </div>

              <div class="col-12 mt-1">
                <button class="btn-ccs-primary" onclick="saveProfile()">
                  <i class="fa-solid fa-floppy-disk"></i> Save Changes
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /row -->
  </div><!-- /profile view -->

</div><!-- /page-wrap -->


<!-- ══════════════════════════════════════════════════════════
     MODALS
══════════════════════════════════════════════════════════ -->

<!-- Login Success -->
<div class="modal fade ccs-modal" id="modalLogin" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body">
        <div class="m-icon"><i class="fa-solid fa-check"></i></div>
        <div class="m-title">Successful Login!</div>
        <p class="m-sub">Welcome back, <strong id="welcomeName"><?= htmlspecialchars($fullname) ?></strong>! 👋</p>
      </div>
      <div class="modal-footer">
        <button class="btn-m-ok" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>

<!-- Logout Confirm -->
<div class="modal fade ccs-modal" id="modalLogout" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body">
        <div class="m-icon warn"><i class="fa-solid fa-right-from-bracket"></i></div>
        <div class="m-title">Log Out?</div>
        <p class="m-sub">Are you sure you want to end your session?</p>
      </div>
      <div class="modal-footer">
        <button class="btn-m-cancel" data-bs-dismiss="modal">Cancel</button>
        <button class="btn-m-ok" onclick="doLogout()">Yes, Log Out</button>
      </div>
    </div>
  </div>
</div>

<!-- Success Generic -->
<div class="modal fade ccs-modal" id="modalSuccess" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body">
        <div class="m-icon"><i class="fa-solid fa-check"></i></div>
        <div class="m-title" id="successTitle">Done!</div>
        <p class="m-sub" id="successSub">Action completed successfully.</p>
      </div>
      <div class="modal-footer">
        <button class="btn-m-ok" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>

<!-- Cancel Reservation Confirm -->
<div class="modal fade ccs-modal" id="modalCancelRes" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body">
        <div class="m-icon danger"><i class="fa-solid fa-trash"></i></div>
        <div class="m-title">Cancel Reservation?</div>
        <p class="m-sub">This reservation will be permanently removed.</p>
      </div>
      <div class="modal-footer">
        <button class="btn-m-cancel" data-bs-dismiss="modal">Keep it</button>
        <button class="btn-m-ok danger" onclick="doDeleteReservation()">Yes, Cancel</button>
      </div>
    </div>
  </div>
</div>


<!-- ══════════════════════════════════════════════════════════
     SCRIPTS
══════════════════════════════════════════════════════════ -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Pass real DB data to script.js via SESSION_USER -->
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
]) ?>;
</script>

<script src="script.js"></script>

</body>
</html>