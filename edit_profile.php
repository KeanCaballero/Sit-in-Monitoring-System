<?php
// edit_profile.php
ini_set('display_errors', 0);
error_reporting(0);
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit();
}

require_once 'config.php';
$conn = db_connect();
$uid  = (int) $_SESSION['user_id'];

// Always fetch fresh from DB
$res  = $conn->query("SELECT * FROM `users` WHERE id = $uid LIMIT 1");
$user = $res ? $res->fetch_assoc() : [];
$conn->close();

if ($user) $_SESSION['user'] = array_merge($_SESSION['user'], $user);

$first_name  = $user['first_name']  ?? '';
$last_name   = $user['last_name']   ?? '';
$middle_name = $user['middle_name'] ?? '';
$email       = $user['email']       ?? '';
$address     = $user['address']     ?? '';
$course      = $user['course']      ?? '';
$year_level  = $user['year_level']  ?? '';
$id_number   = $user['id_number']   ?? '';
$sess        = $user['remaining_sessions'] ?? 30;
$sess_pct    = min(100, ($sess / 30) * 100);
$fullname    = trim($first_name . ' ' . $last_name);
$initials    = strtoupper(substr($first_name,0,1) . substr($last_name,0,1));
$photo       = !empty($user['profile_photo'])
    ? htmlspecialchars($user['profile_photo'])
    : 'https://ui-avatars.com/api/?name=' . urlencode($fullname) . '&background=0d2255&color=fff&size=85';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>CCS Sit-in Portal – Edit Profile</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet"/>
  <style>
    :root{
      --navy:#0d2255;--navy2:#14306e;--navy3:#1a3a82;
      --gold:#c9a84c;--gold2:#e6c06a;
      --bg:#eef1f8;--card:#fff;
      --text1:#0f172a;--text2:#475569;--text3:#94a3b8;
      --border:#e2e8f0;--red:#dc2626;
    }
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'Segoe UI',sans-serif;background:var(--bg);color:var(--text1);min-height:100vh;}

    /* NAV — same as dashboard */
    .top-nav{background:var(--navy);height:56px;display:flex;align-items:center;
      padding:0 1.25rem;gap:.25rem;position:sticky;top:0;z-index:200;
      box-shadow:0 2px 12px rgba(0,0,0,.25);}
    .nav-brand{display:flex;align-items:center;gap:.5rem;color:#fff;font-weight:700;
      font-size:.88rem;text-decoration:none;white-space:nowrap;margin-right:.5rem;}
    .brand-circle{width:30px;height:30px;border-radius:50%;background:rgba(255,255,255,.15);
      display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;color:#fff;flex-shrink:0;}
    .nav-links{display:flex;align-items:center;gap:2px;margin-left:auto;flex-wrap:nowrap;overflow-x:auto;}
    .nav-links::-webkit-scrollbar{display:none;}
    .nav-links a{color:rgba(255,255,255,.82);text-decoration:none;font-size:.82rem;font-weight:500;
      padding:.4rem .7rem;border-radius:5px;transition:background .15s,color .15s;
      display:flex;align-items:center;gap:.35rem;white-space:nowrap;}
    .nav-links a:hover{background:rgba(255,255,255,.12);color:#fff;}
    .nav-links a.active{background:rgba(255,255,255,.15);color:#fff;font-weight:600;}
    .btn-logout{background:var(--gold);color:var(--navy);font-weight:700;font-size:.82rem;
      border:none;border-radius:6px;padding:.38rem .9rem;cursor:pointer;margin-left:.25rem;
      display:flex;align-items:center;gap:.35rem;transition:background .15s;
      text-decoration:none;white-space:nowrap;}
    .btn-logout:hover{background:var(--gold2);}

    /* MAIN */
    .main-wrap{padding:1.75rem 2rem;max-width:1100px;margin:0 auto;}
    .page-title{font-size:1.2rem;font-weight:700;color:var(--navy);
      display:flex;align-items:center;gap:.5rem;margin-bottom:1.25rem;}

    /* LAYOUT */
    .profile-layout{display:grid;grid-template-columns:240px 1fr;gap:1.25rem;align-items:start;}

    /* SIDEBAR */
    .sidebar-card{background:var(--card);border-radius:10px;
      box-shadow:0 1px 6px rgba(0,0,0,.08);overflow:hidden;position:sticky;top:70px;}
    .sidebar-top{padding:1.5rem 1rem;text-align:center;border-bottom:1px solid var(--border);}
    .avatar-wrap{position:relative;width:85px;height:85px;margin:0 auto .75rem;}
    .avatar-wrap img{width:85px;height:85px;border-radius:50%;border:3px solid var(--navy2);
      object-fit:cover;background:#dde6f5;}
    .avatar-edit-btn{position:absolute;bottom:1px;right:1px;width:26px;height:26px;
      background:var(--navy);color:#fff;border:2px solid #fff;border-radius:50%;
      cursor:pointer;font-size:10px;display:flex;align-items:center;justify-content:center;
      transition:background .15s;}
    .avatar-edit-btn:hover{background:var(--navy3);}
    #photoInput{display:none;}
    .sidebar-name{font-weight:700;font-size:.95rem;color:var(--text1);}
    .sidebar-sub{font-size:.75rem;color:var(--text2);margin-top:.25rem;}
    .session-box{padding:1rem;text-align:center;}
    .session-label{font-size:.72rem;color:var(--text2);margin-bottom:.2rem;}
    .session-num{font-size:2rem;font-weight:700;color:var(--navy);line-height:1;}
    .session-of{font-size:.72rem;color:var(--text3);margin-bottom:.5rem;}
    .session-bar-wrap{background:var(--border);border-radius:4px;height:6px;overflow:hidden;}
    .session-bar{height:100%;border-radius:4px;background:var(--gold);transition:width .4s;}

    /* FORM CARD */
    .form-card{background:var(--card);border-radius:10px;
      box-shadow:0 1px 6px rgba(0,0,0,.08);overflow:hidden;}
    .form-card-header{background:var(--navy);color:#fff;padding:.6rem 1rem;font-size:.82rem;
      font-weight:600;display:flex;align-items:center;gap:.4rem;}
    .form-card-body{padding:1.25rem 1.5rem;}

    .f-label{display:block;font-size:.75rem;font-weight:600;color:var(--text2);
      margin-bottom:.3rem;text-transform:uppercase;letter-spacing:.3px;}
    .f-note{font-size:.68rem;color:var(--text3);margin-top:.2rem;}

    .form-control,.form-select{border:1px solid var(--border);border-radius:6px;
      font-size:.85rem;padding:.45rem .75rem;color:var(--text1);background:#fff;width:100%;
      outline:none;transition:border-color .2s,box-shadow .2s;font-family:'Segoe UI',sans-serif;}
    .form-control::placeholder{color:var(--text3);}
    .form-control:focus,.form-select:focus{border-color:var(--navy2);
      box-shadow:0 0 0 3px rgba(20,48,110,.1);}
    .form-control[readonly]{background:#f8fafc;color:var(--text3);cursor:not-allowed;}

    .pw-wrap{position:relative;}
    .pw-wrap .form-control{padding-right:2.4rem;}
    .pw-toggle{position:absolute;right:.65rem;top:50%;transform:translateY(-50%);
      background:none;border:none;color:var(--text3);cursor:pointer;font-size:.82rem;padding:0;}
    .pw-toggle:hover{color:var(--navy);}

    .section-divider{border:none;border-top:1px solid var(--border);margin:1.25rem 0;}
    .section-sub-label{font-size:.72rem;font-weight:700;color:var(--text2);
      text-transform:uppercase;letter-spacing:.5px;margin-bottom:.9rem;}

    .btn-save{width:100%;background:var(--navy);color:#fff;border:none;border-radius:6px;
      font-size:.88rem;font-weight:600;padding:.65rem;cursor:pointer;margin-top:1rem;
      display:flex;align-items:center;justify-content:center;gap:.45rem;transition:background .15s;}
    .btn-save:hover{background:var(--navy3);}
    .btn-save:disabled{opacity:.6;cursor:not-allowed;}
    .btn-spinner{width:15px;height:15px;border:2px solid rgba(255,255,255,.3);
      border-top-color:#fff;border-radius:50%;animation:spin .6s linear infinite;display:none;}
    @keyframes spin{to{transform:rotate(360deg);}}

    .alert-msg{border-radius:7px;padding:.65rem 1rem;font-size:.82rem;font-weight:500;
      display:none;align-items:center;gap:.5rem;margin-bottom:1rem;}
    .alert-msg.success{background:#dcfce7;border:1px solid #86efac;color:#166534;display:flex;align-items:center;}
    .alert-msg.error  {background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;display:flex;align-items:center;}
    .alert-msg.info   {background:#dbeafe;border:1px solid #93c5fd;color:#1e40af;display:flex;align-items:center;}

    @media(max-width:768px){.profile-layout{grid-template-columns:1fr;}
      .sidebar-card{position:static;}.main-wrap{padding:1rem;}}
  </style>
</head>
<body>

<nav class="top-nav">
  <a class="nav-brand" href="dashboard.php">
    <div class="brand-circle"><?= $initials ?></div>
    CCS Sit-in Monitoring Portal
  </a>
  <div class="nav-links">
    <a href="dashboard.php"><i class="fa-solid fa-house-chimney"></i> Home</a>
    <a href="edit_profile.php" class="active"><i class="fa-solid fa-user-pen"></i> Edit Profile</a>
    <a href="dashboard.php#history" onclick="sessionStorage.setItem('tab','history')"><i class="fa-solid fa-clock-rotate-left"></i> History</a>
    <a href="dashboard.php#reservation" onclick="sessionStorage.setItem('tab','reservation')"><i class="fa-solid fa-calendar-check"></i> Reservation</a>
    <a href="logout.php" class="btn-logout"><i class="fa-solid fa-right-from-bracket"></i> Log out</a>
  </div>
</nav>

<div class="main-wrap">
  <div class="page-title"><i class="fa-solid fa-user-pen"></i> Edit Profile</div>

  <div class="alert-msg" id="alertMsg"></div>

  <div class="profile-layout">

    <!-- SIDEBAR -->
    <div class="sidebar-card">
      <div class="sidebar-top">
        <div class="avatar-wrap">
          <img src="<?= $photo ?>" id="avatarPreview" alt="Avatar"
            onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($fullname) ?>&background=0d2255&color=fff&size=85'"/>
          <button class="avatar-edit-btn" type="button"
            onclick="document.getElementById('photoInput').click()" title="Change photo">
            <i class="fa-solid fa-camera"></i>
          </button>
          <input type="file" id="photoInput" accept="image/*" onchange="previewPhoto(this)"/>
        </div>
        <div class="sidebar-name" id="sidebarName"><?= htmlspecialchars($fullname) ?></div>
        <div class="sidebar-sub"  id="sidebarSub"><?= htmlspecialchars($course . ' · ' . $year_level) ?></div>
      </div>
      <div class="session-box">
        <div class="session-label">Remaining Sessions</div>
        <div class="session-num"><?= $sess ?></div>
        <div class="session-of">out of 30 total sessions</div>
        <div class="session-bar-wrap">
          <div class="session-bar" style="width:<?= $sess_pct ?>%;"></div>
        </div>
      </div>
    </div>

    <!-- FORM -->
    <div class="form-card">
      <div class="form-card-header"><i class="fa-solid fa-id-card"></i> PERSONAL INFORMATION</div>
      <div class="form-card-body">

        <div class="row g-3">
          <div class="col-md-6">
            <label class="f-label">First Name</label>
            <input type="text" class="form-control" id="firstname"
              value="<?= htmlspecialchars($first_name) ?>" required/>
          </div>
          <div class="col-md-6">
            <label class="f-label">Last Name</label>
            <input type="text" class="form-control" id="lastname"
              value="<?= htmlspecialchars($last_name) ?>" required/>
          </div>
          <div class="col-md-6">
            <label class="f-label">Middle Name <span style="font-weight:400;text-transform:none;font-style:italic;color:var(--text3);">(optional)</span></label>
            <input type="text" class="form-control" id="middlename"
              value="<?= htmlspecialchars($middle_name) ?>"/>
          </div>
          <div class="col-md-6">
            <label class="f-label">ID Number</label>
            <input type="text" class="form-control" readonly
              value="<?= htmlspecialchars($id_number) ?>"/>
            <div class="f-note"><i class="fa-solid fa-lock" style="font-size:.6rem;"></i> Cannot be changed</div>
          </div>
          <div class="col-md-6">
            <label class="f-label">Email Address</label>
            <input type="email" class="form-control" id="email"
              value="<?= htmlspecialchars($email) ?>" required/>
          </div>
          <div class="col-md-6">
            <label class="f-label">Address</label>
            <input type="text" class="form-control" id="address"
              value="<?= htmlspecialchars($address) ?>"/>
          </div>
          <div class="col-md-6">
            <label class="f-label">Course</label>
            <select class="form-select" id="course">
              <?php foreach (['BSIT','BSCS','BSIS','ACT'] as $c): ?>
                <option value="<?= $c ?>" <?= $course===$c?'selected':'' ?>><?= $c ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="f-label">Year Level</label>
            <select class="form-select" id="year_level">
              <?php foreach (['1','2','3','4'] as $y): ?>
                <option value="<?= $y ?>" <?= $year_level==$y?'selected':'' ?><?= $year_level==="$y"?'selected':'' ?>><?= $y ?>st/nd/rd/th Year</option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <hr class="section-divider"/>
        <div class="section-sub-label">
          <i class="fa-solid fa-key" style="color:var(--navy2);margin-right:4px;"></i>
          Change Password
          <span style="font-weight:400;text-transform:none;font-style:italic;color:var(--text3);">— leave blank to keep current</span>
        </div>

        <div class="row g-3">
          <div class="col-md-6">
            <label class="f-label">New Password</label>
            <div class="pw-wrap">
              <input type="password" class="form-control" id="new_password" placeholder="Leave blank to keep current" autocomplete="new-password"/>
              <button type="button" class="pw-toggle" onclick="togglePw('new_password',this)"><i class="fa-solid fa-eye"></i></button>
            </div>
          </div>
          <div class="col-md-6">
            <label class="f-label">Confirm Password</label>
            <div class="pw-wrap">
              <input type="password" class="form-control" id="confirm_password" placeholder="Repeat new password" autocomplete="new-password"/>
              <button type="button" class="pw-toggle" onclick="togglePw('confirm_password',this)"><i class="fa-solid fa-eye"></i></button>
            </div>
          </div>
        </div>

        <button class="btn-save" id="saveBtn" onclick="saveProfile()">
          <div class="btn-spinner" id="btnSpinner"></div>
          <i class="fa-solid fa-floppy-disk" id="btnIcon"></i>
          <span id="btnText">Save Changes</span>
        </button>

      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ── SAVE PROFILE ── */
async function saveProfile() {
  const btn     = document.getElementById('saveBtn');
  const spinner = document.getElementById('btnSpinner');
  const icon    = document.getElementById('btnIcon');
  const btnText = document.getElementById('btnText');

  const firstname  = document.getElementById('firstname').value.trim();
  const lastname   = document.getElementById('lastname').value.trim();
  const middlename = document.getElementById('middlename').value.trim();
  const email      = document.getElementById('email').value.trim();
  const address    = document.getElementById('address').value.trim();
  const course     = document.getElementById('course').value;
  const year_level = document.getElementById('year_level').value;
  const newpw      = document.getElementById('new_password').value;
  const confirmpw  = document.getElementById('confirm_password').value;

  if (!firstname || !lastname || !email) {
    showAlert('Please fill in First Name, Last Name, and Email.', 'error'); return;
  }
  if (!email.includes('@')) {
    showAlert('Please enter a valid email address.', 'error'); return;
  }
  if (newpw && newpw !== confirmpw) {
    showAlert('Passwords do not match.', 'error'); return;
  }
  if (newpw && newpw.length < 6) {
    showAlert('Password must be at least 6 characters.', 'error'); return;
  }

  btn.disabled = true;
  spinner.style.display = 'block';
  icon.style.display = 'none';
  btnText.textContent = 'Saving…';

  try {
    const res  = await fetch('update_profile.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        firstname, lastname, middlename,
        email, address, course, year_level,
        new_password:     newpw,
        confirm_password: confirmpw,
      })
    });

    // Read raw text first to catch PHP errors
    const raw = await res.text();
    let data;
    try { data = JSON.parse(raw); }
    catch (_) {
      showAlert('Server error — check XAMPP logs.', 'error'); return;
    }

    if (data.success) {
      showAlert('\u2713 ' + data.message, 'success');
      // Update sidebar instantly
      document.getElementById('sidebarName').textContent = (data.firstname || firstname) + ' ' + (data.lastname || lastname);
      document.getElementById('sidebarSub').textContent  = (data.course || course) + ' \u00b7 Year ' + (data.year_level || year_level);
      // Clear password fields
      document.getElementById('new_password').value    = '';
      document.getElementById('confirm_password').value = '';
    } else {
      showAlert('\u2715 ' + (data.message || 'Update failed.'), 'error');
    }
  } catch (e) {
    showAlert('\u2715 Could not reach the server.', 'error');
  } finally {
    btn.disabled = false;
    spinner.style.display = 'none';
    icon.style.display = 'inline';
    btnText.textContent = 'Save Changes';
  }
}

/* ── ALERT — fixed: clears inline style each call ── */
function showAlert(msg, type) {
  const el = document.getElementById('alertMsg');
  el.style.display = '';          // clear any previous inline display:none
  el.className = 'alert-msg ' + type;
  el.innerHTML = (type === 'success')
    ? '<i class="fa-solid fa-circle-check" style="margin-right:6px"></i>' + msg
    : '<i class="fa-solid fa-circle-xmark" style="margin-right:6px"></i>' + msg;
  el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  clearTimeout(el._hideTimer);
  el._hideTimer = setTimeout(() => {
    el.style.display = 'none';
    el.className = 'alert-msg';
  }, 5000);
}

/* ── PASSWORD TOGGLE ── */
function togglePw(id, btn) {
  const input = document.getElementById(id);
  const icon  = btn.querySelector('i');
  if (input.type === 'password') {
    input.type = 'text';
    icon.className = 'fa-solid fa-eye-slash';
  } else {
    input.type = 'password';
    icon.className = 'fa-solid fa-eye';
  }
}

/* ── PHOTO UPLOAD ── */
function previewPhoto(input) {
  if (!input.files || !input.files[0]) return;

  const file = input.files[0];

  // Instant local preview
  const reader = new FileReader();
  reader.onload = e => {
    document.getElementById('avatarPreview').src = e.target.result;
  };
  reader.readAsDataURL(file);

  // Show uploading state
  const camBtn = document.querySelector('.avatar-edit-btn');
  if (camBtn) { camBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>'; camBtn.disabled = true; }
  showAlert('Uploading photo…', 'info');

  const fd = new FormData();
  fd.append('profile_photo', file);

  fetch('upload_photo.php', { method: 'POST', body: fd })
    .then(r => r.text())
    .then(raw => {
      let d;
      try { d = JSON.parse(raw); } catch (_) { d = { success: false, message: 'Server error' }; }
      if (d.success) {
        showAlert('\u2713 Profile photo updated!', 'success');
      } else {
        showAlert('\u2715 ' + (d.message || 'Upload failed.'), 'error');
      }
    })
    .catch(() => showAlert('Photo saved locally only \u2014 could not reach server.', 'error'))
    .finally(() => {
      if (camBtn) { camBtn.innerHTML = '<i class="fa-solid fa-camera"></i>'; camBtn.disabled = false; }
    });
}
</script>
</body>
</html>