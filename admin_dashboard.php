<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
    header("Location: index.html");
    exit();
}
$admin = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>CCS Admin – Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Bebas+Neue&display=swap" rel="stylesheet"/>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    :root {
      --navy:      #0a1628;
      --navy2:     #0f2044;
      --navy3:     #162d5e;
      --navy4:     #1e3a7a;
      --gold:      #c9a84c;
      --gold2:     #e8c96a;
      --gold3:     #f5e0a0;
      --blue:      #3b82f6;
      --blue2:     #60a5fa;
      --green:     #10b981;
      --red:       #ef4444;
      --amber:     #f59e0b;
      --bg:        #060e1f;
      --surface:   #0d1b35;
      --surface2:  #112040;
      --surface3:  #162a52;
      --border:    rgba(255,255,255,0.07);
      --border2:   rgba(201,168,76,0.25);
      --text1:     #f0f4ff;
      --text2:     #94a8cc;
      --text3:     #4a6080;
      --radius:    12px;
      --radius-lg: 18px;
      --shadow:    0 4px 24px rgba(0,0,0,0.5);
      --shadow-lg: 0 12px 48px rgba(0,0,0,0.65);
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      background: var(--bg);
      color: var(--text1);
      min-height: 100vh;
      overflow-x: hidden;
    }

    ::-webkit-scrollbar { width: 5px; height: 5px; }
    ::-webkit-scrollbar-track { background: var(--navy); }
    ::-webkit-scrollbar-thumb { background: var(--navy4); border-radius: 4px; }
    ::-webkit-scrollbar-thumb:hover { background: var(--gold); }

    /* ── NAVBAR ── */
    .admin-nav {
      background: var(--surface);
      border-bottom: 1px solid var(--border2);
      padding: 0 1.5rem;
      display: flex; align-items: center; gap: 0;
      height: 58px;
      position: sticky; top: 0; z-index: 200;
      box-shadow: 0 2px 20px rgba(0,0,0,.5);
    }
    .admin-nav .brand {
      color: var(--text1); font-weight: 800; font-size: .85rem;
      margin-right: 1.2rem; white-space: nowrap; text-decoration: none;
      letter-spacing: -.2px; display: flex; align-items: center; gap: .5rem;
    }
    .brand-icon {
      width: 32px; height: 32px;
      background: linear-gradient(135deg, var(--gold), var(--gold2));
      border-radius: 8px; display: flex; align-items: center; justify-content: center;
      font-family: 'Bebas Neue', cursive; font-size: 15px;
      color: var(--navy); flex-shrink: 0;
    }
    .admin-nav .nav-links {
      display: flex; align-items: center;
      gap: 0; flex-wrap: nowrap; flex: 1;
      overflow-x: auto;
    }
    .admin-nav .nav-links::-webkit-scrollbar { display: none; }
    .admin-nav .nav-links a {
      color: var(--text2); text-decoration: none; font-size: .775rem; font-weight: 500;
      padding: .45rem .68rem; border-radius: 7px; transition: all .15s;
      cursor: pointer; white-space: nowrap; display: flex; align-items: center; gap: .3rem;
    }
    .admin-nav .nav-links a i { font-size: .68rem; opacity: .7; }
    .admin-nav .nav-links a:hover { background: var(--surface3); color: var(--text1); }
    .admin-nav .nav-links a.active {
      background: rgba(201,168,76,.15); color: var(--gold2); font-weight: 600;
    }
    .admin-nav .nav-links a.active i { opacity: 1; }
    .btn-logout {
      background: linear-gradient(135deg, var(--gold), var(--gold2));
      color: var(--navy); font-weight: 700; font-size: .775rem;
      border: none; border-radius: 8px; padding: .42rem 1rem;
      cursor: pointer; margin-left: auto; flex-shrink: 0;
      transition: all .15s; display: flex; align-items: center; gap: .4rem;
      box-shadow: 0 2px 12px rgba(201,168,76,.3);
    }
    .btn-logout:hover { filter: brightness(1.1); transform: translateY(-1px); }

    /* ── WRAP / VIEWS ── */
    .admin-wrap { padding: 1.5rem 2rem; max-width: 1400px; margin: 0 auto; }
    .view { display: none; animation: fadeUp .25s ease; }
    .view.active { display: block; }
    @keyframes fadeUp { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; } }

    /* ── PAGE TITLE ── */
    .page-title {
      font-size: 1.3rem; font-weight: 800; color: var(--text1);
      margin-bottom: 1.2rem; display: flex; align-items: center; gap: .6rem; letter-spacing: -.3px;
    }
    .page-title i { color: var(--gold); font-size: 1.1rem; }

    /* ── STAT CARDS ── */
    .stat-cards-row { display: grid; grid-template-columns: repeat(3,1fr); gap: 14px; margin-bottom: 1.25rem; }
    .stat-card {
      background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
      padding: 16px 18px; display: flex; align-items: center; gap: 14px;
      transition: transform .2s, box-shadow .2s; position: relative; overflow: hidden;
    }
    .stat-card::after { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px; }
    .stat-card.c1::after { background: linear-gradient(90deg, var(--blue), var(--blue2)); }
    .stat-card.c2::after { background: linear-gradient(90deg, var(--gold), var(--gold2)); }
    .stat-card.c3::after { background: linear-gradient(90deg, var(--green), #34d399); }
    .stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow); }
    .stat-icon { width: 44px; height: 44px; border-radius: 11px; display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0; }
    .ic-blue  { background: rgba(59,130,246,.15); color: var(--blue2); }
    .ic-gold  { background: rgba(201,168,76,.15);  color: var(--gold2); }
    .ic-green { background: rgba(16,185,129,.15);  color: #34d399; }
    .stat-info label { display: block; font-size: 11px; font-weight: 600; color: var(--text3); text-transform: uppercase; letter-spacing: .8px; margin-bottom: 3px; }
    .stat-value { font-family: 'Bebas Neue', cursive; font-size: 32px; letter-spacing: .5px; line-height: 1; color: var(--text1); }

    /* ── CARD ── */
    .a-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); overflow: hidden; box-shadow: 0 2px 16px rgba(0,0,0,.3); }
    .a-card-header { background: var(--surface2); border-bottom: 1px solid var(--border); padding: .7rem 1.1rem; font-size: .82rem; font-weight: 700; display: flex; align-items: center; gap: .5rem; color: var(--text1); }
    .a-card-header i { color: var(--gold); }
    .a-card-body { padding: 1.1rem; }

    /* ── TABLE ── */
    .a-table { width: 100%; border-collapse: collapse; font-size: .8rem; }
    .a-table th { background: var(--surface2); color: var(--text2); padding: .6rem .85rem; text-align: left; font-weight: 700; font-size: .72rem; text-transform: uppercase; letter-spacing: .6px; white-space: nowrap; }
    .a-table th:first-child { border-radius: 8px 0 0 8px; }
    .a-table th:last-child  { border-radius: 0 8px 8px 0; }
    .a-table td { padding: .6rem .85rem; border-bottom: 1px solid var(--border); color: var(--text1); vertical-align: middle; }
    .a-table tr:last-child td { border-bottom: none; }
    .a-table tr:hover td { background: rgba(255,255,255,.03); }
    .a-table .no-data { text-align: center; color: var(--text3); font-style: italic; padding: 2.5rem; }

    /* ── BUTTONS ── */
    .btn-a-primary { background: linear-gradient(135deg, #2563eb, #3b82f6); color: #fff; border: none; border-radius: 7px; padding: .32rem .8rem; font-size: .75rem; font-weight: 600; cursor: pointer; transition: all .15s; display: inline-flex; align-items: center; gap: .3rem; box-shadow: 0 2px 8px rgba(59,130,246,.25); }
    .btn-a-primary:hover { filter: brightness(1.1); transform: translateY(-1px); }
    .btn-a-danger  { background: linear-gradient(135deg, #dc2626, #ef4444); color: #fff; border: none; border-radius: 7px; padding: .32rem .8rem; font-size: .75rem; font-weight: 600; cursor: pointer; transition: all .15s; display: inline-flex; align-items: center; gap: .3rem; box-shadow: 0 2px 8px rgba(239,68,68,.25); }
    .btn-a-danger:hover  { filter: brightness(1.1); transform: translateY(-1px); }
    .btn-a-success { background: linear-gradient(135deg, #059669, #10b981); color: #fff; border: none; border-radius: 7px; padding: .32rem .8rem; font-size: .75rem; font-weight: 600; cursor: pointer; transition: all .15s; display: inline-flex; align-items: center; gap: .3rem; box-shadow: 0 2px 8px rgba(16,185,129,.25); }
    .btn-a-success:hover { filter: brightness(1.1); transform: translateY(-1px); }

    /* ── ANNOUNCEMENT ── */
    .ann-item { padding: .7rem .85rem; border-radius: 9px; background: var(--surface2); border-left: 3px solid var(--gold); margin-bottom: .6rem; animation: fadeUp .2s ease; }
    .ann-meta { font-size: .72rem; color: var(--text3); font-weight: 600; margin-bottom: .25rem; display: flex; align-items: center; gap: .5rem; }
    .ann-badge { background: rgba(201,168,76,.15); color: var(--gold2); padding: 1px 7px; border-radius: 20px; font-size: .68rem; font-weight: 700; }
    .ann-text { font-size: .82rem; color: var(--text1); line-height: 1.5; }
    .ann-empty { color: var(--text3) !important; font-style: italic; }
    .ann-textarea { width: 100%; background: var(--surface2); border: 1px solid var(--border); border-radius: 9px; color: var(--text1); font-family: 'Plus Jakarta Sans', sans-serif; font-size: .82rem; padding: .7rem .9rem; resize: vertical; min-height: 75px; outline: none; transition: border-color .2s; }
    .ann-textarea::placeholder { color: var(--text3); }
    .ann-textarea:focus { border-color: var(--gold); }

    /* ── BADGE ── */
    .badge-active { background: rgba(16,185,129,.15); color: #34d399; font-size: .7rem; padding: .2rem .6rem; border-radius: 20px; font-weight: 700; }
    .badge-done   { background: var(--surface3); color: var(--text3); font-size: .7rem; padding: .2rem .6rem; border-radius: 20px; font-weight: 600; }

    /* ── TABLE TOP ── */
    .tbl-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: .85rem; flex-wrap: wrap; gap: .5rem; }
    .tbl-search-wrap { position: relative; }
    .tbl-search-wrap input { background: var(--surface2); border: 1px solid var(--border); border-radius: 8px; color: var(--text1); font-family: 'Plus Jakarta Sans', sans-serif; font-size: .8rem; padding: .38rem .7rem .38rem 1.9rem; width: 190px; outline: none; transition: border-color .2s; }
    .tbl-search-wrap input::placeholder { color: var(--text3); }
    .tbl-search-wrap input:focus { border-color: var(--gold); }
    .tbl-search-wrap i { position: absolute; left: .6rem; top: 50%; transform: translateY(-50%); color: var(--text3); font-size: .72rem; }
    .entries-wrap { display: flex; align-items: center; gap: .4rem; font-size: .78rem; color: var(--text2); }
    .entries-wrap select { background: var(--surface2); border: 1px solid var(--border); border-radius: 6px; color: var(--text1); padding: .25rem .5rem; font-size: .78rem; outline: none; }

    /* ── PAGINATION ── */
    .pg-wrap { display: flex; align-items: center; justify-content: space-between; font-size: .75rem; color: var(--text2); margin-top: .85rem; flex-wrap: wrap; gap: .5rem; }
    .pg-btn { border: 1px solid var(--border); background: var(--surface2); color: var(--text2); padding: .2rem .55rem; border-radius: 5px; cursor: pointer; font-size: .75rem; transition: all .15s; }
    .pg-btn:hover { border-color: var(--gold); color: var(--gold2); }
    .pg-btn.active { background: var(--gold); color: var(--navy); border-color: var(--gold); font-weight: 700; }

    /* ── MODAL ── */
    .modal-content { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); }
    .modal-header { background: var(--surface2); border-bottom: 1px solid var(--border); border-radius: var(--radius-lg) var(--radius-lg) 0 0; padding: .9rem 1.2rem; }
    .modal-title { font-size: .88rem; font-weight: 700; color: var(--text1); }
    .modal-title i { color: var(--gold); }
    .modal-header .btn-close { filter: invert(1) opacity(.5); }
    .modal-header .btn-close:hover { filter: invert(1) opacity(1); }
    .modal-footer { background: var(--surface2); border-top: 1px solid var(--border); border-radius: 0 0 var(--radius-lg) var(--radius-lg); }
    .modal-body { padding: 1.2rem; }
    .form-label { font-size: .78rem; font-weight: 600; color: var(--text2); margin-bottom: .3rem; }
    .form-control, .form-select { background: var(--surface2) !important; border: 1px solid var(--border) !important; border-radius: 8px !important; color: var(--text1) !important; font-family: 'Plus Jakarta Sans', sans-serif !important; font-size: .83rem !important; padding: .45rem .75rem !important; outline: none !important; transition: border-color .2s !important; }
    .form-control::placeholder { color: var(--text3) !important; }
    .form-control:focus, .form-select:focus { border-color: var(--gold) !important; box-shadow: 0 0 0 3px rgba(201,168,76,.12) !important; }
    .form-select option { background: var(--surface2); color: var(--text1); }
    .modal .btn-primary { background: linear-gradient(135deg, #2563eb, #3b82f6) !important; border: none !important; border-radius: 8px !important; font-size: .82rem; font-weight: 700 !important; }
    .modal .btn-secondary { background: var(--surface3) !important; border: 1px solid var(--border) !important; color: var(--text2) !important; border-radius: 8px !important; font-size: .82rem; font-weight: 600 !important; }
    .modal .btn-danger { background: linear-gradient(135deg, #dc2626, #ef4444) !important; border: none !important; border-radius: 8px !important; font-size: .82rem; font-weight: 700 !important; }
    .modal .btn-outline-secondary { background: transparent !important; border: 1px solid var(--border) !important; color: var(--text2) !important; border-radius: 8px !important; font-size: .82rem; font-weight: 600 !important; }

    /* ── SEARCH MODAL ── */
    .search-input-wrap { position: relative; }
    .search-input-wrap i { position: absolute; left: .75rem; top: 50%; transform: translateY(-50%); color: var(--text3); font-size: .8rem; }
    .search-input-wrap input { width: 100%; background: var(--surface2); border: 1px solid var(--border); border-radius: 10px; color: var(--text1); font-family: 'Plus Jakarta Sans', sans-serif; font-size: .85rem; padding: .55rem .75rem .55rem 2.1rem; outline: none; transition: border-color .2s; }
    .search-input-wrap input::placeholder { color: var(--text3); }
    .search-input-wrap input:focus { border-color: var(--gold); box-shadow: 0 0 0 3px rgba(201,168,76,.1); }

    .search-result-card { display: flex; align-items: center; gap: 12px; padding: .65rem .85rem; border-radius: 9px; background: var(--surface2); border: 1px solid var(--border); margin-bottom: .5rem; transition: border-color .15s; animation: fadeUp .15s ease; }
    .search-result-card:hover { border-color: var(--gold); }
    .src-avatar { width: 38px; height: 38px; border-radius: 50%; background: linear-gradient(135deg, var(--navy4), var(--blue)); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px; flex-shrink: 0; text-transform: uppercase; color: #fff; }
    .src-info { flex: 1; min-width: 0; }
    .src-name { font-weight: 700; font-size: .82rem; color: var(--text1); }
    .src-meta { font-size: .72rem; color: var(--text3); display: flex; gap: 10px; margin-top: 2px; flex-wrap: wrap; }
    .src-session { font-size: .72rem; font-weight: 700; color: var(--gold2); white-space: nowrap; }

    .search-hint { text-align: center; color: var(--text3); font-size: .82rem; padding: 1.5rem 0; }
    .search-hint i { font-size: 1.5rem; display: block; margin-bottom: .5rem; opacity: .35; }
    .loading-row { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 1.5rem; color: var(--text3); font-size: .82rem; }
    .spinner { width: 16px; height: 16px; border: 2px solid var(--border); border-top-color: var(--gold); border-radius: 50%; animation: spin .6s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* ── MISC ── */
    .id-badge { font-family: 'Bebas Neue', cursive; font-size: .85rem; letter-spacing: .5px; background: var(--surface3); color: var(--gold2); padding: 2px 8px; border-radius: 5px; }
    .empty-state { text-align: center; color: var(--text3); padding: 3rem 1rem; }
    .empty-state i { font-size: 2.5rem; display: block; margin-bottom: .75rem; opacity: .3; }
    .empty-state p { font-size: .85rem; }
    .chart-outer { display: flex; justify-content: center; align-items: center; padding: .5rem 0; max-height: 240px; }
    #purposeChart { max-height: 230px; }

    @media (max-width: 900px) { .stat-cards-row { grid-template-columns: 1fr 1fr; } .admin-wrap { padding: 1rem; } }
    @media (max-width: 600px) { .stat-cards-row { grid-template-columns: 1fr; } .nav-links { display: none !important; } }

    /* ── PC MAP (admin) ── */
    .lab-btn-admin {
      border: 1.5px solid var(--border2); background: transparent;
      color: var(--text2); padding: .3rem .75rem; border-radius: 7px;
      font-size: .78rem; font-weight: 600; cursor: pointer; transition: all .15s;
    }
    .lab-btn-admin.active, .lab-btn-admin:hover {
      background: var(--gold); color: var(--navy); border-color: var(--gold);
    }
    .admin-pc-grid {
      display: grid;
      grid-template-columns: repeat(8, 1fr);
      gap: 6px; padding: .75rem;
      background: var(--surface2);
      border-radius: 12px;
      border: 1px solid var(--border);
    }
    .admin-teacher-desk {
      grid-column: 1/-1; background: var(--navy3); color: var(--gold2);
      border-radius: 8px; padding: .4rem; text-align: center;
      font-size: .75rem; font-weight: 700; letter-spacing: 1px; margin-bottom: 4px;
    }
    .admin-pc-item {
      aspect-ratio: 1; border-radius: 7px; display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      font-size: .6rem; font-weight: 700; transition: all .12s;
      border: 1.5px solid transparent; position: relative;
    }
    .admin-pc-item i { font-size: .85rem; margin-bottom: 1px; }
    .admin-pc-item.available { background: rgba(34,197,94,.18);  color:#16a34a; border-color:rgba(34,197,94,.35); }
    .admin-pc-item.occupied  { background: rgba(239,68,68,.18);  color:#991b1b; border-color:rgba(239,68,68,.35); }
    .admin-pc-item.reserved  { background: rgba(245,158,11,.18); color:#92400e; border-color:rgba(245,158,11,.35); }
    .admin-pc-item.pending   { background: rgba(139,92,246,.18); color:#5b21b6; border-color:rgba(139,92,246,.35); }
    .admin-pc-item .pc-tip {
      display: none; position: absolute; bottom: 110%; left: 50%;
      transform: translateX(-50%); background: var(--navy);
      color: var(--text1); font-size: .65rem; padding: 3px 7px;
      border-radius: 4px; white-space: nowrap; z-index: 10;
      border: 1px solid var(--border2); pointer-events: none;
    }
    .admin-pc-item:hover .pc-tip { display: block; }
    .pc-legend { display: flex; gap: 1rem; flex-wrap: wrap; font-size: .74rem; color: var(--text2); margin-bottom: .85rem; }
    .pc-legend span { display: flex; align-items: center; gap: .35rem; }
    .pc-legend .dot { width: 11px; height: 11px; border-radius: 3px; flex-shrink: 0; }

    /* ── leaderboard medals ── */
    .lb-rank-1 td { background: rgba(201,168,76,.07); }
    .lb-rank-2 td { background: rgba(255,255,255,.02); }
    .lb-rank-3 td { background: rgba(245,158,11,.05); }

  </style>
</head>
<body>

<!-- ── NAVBAR ── -->
<nav class="admin-nav">
  <a class="brand" href="#">
    <div class="brand-icon">CCS</div>
    College of Computer Studies Sit-in Monitoring System Admin
  </a>
  <div class="nav-links">
    <a onclick="showView('home')"           id="nav-home"        class="active"><i class="fa-solid fa-house-chimney"></i>Home</a>
    <a onclick="openSearch()"              id="nav-search"                      ><i class="fa-solid fa-magnifying-glass"></i>Search</a>
    <a onclick="showView('students')"      id="nav-students"                    ><i class="fa-solid fa-users"></i>Students</a>
    <a onclick="showView('current-sitin')"     id="nav-sitin"                       ><i class="fa-solid fa-chair"></i>Sit-in</a>
    <a onclick="showView('sitin-records')" id="nav-records"                     ><i class="fa-solid fa-table-list"></i>Sit-in Records</a>
    <a onclick="showView('reports')"       id="nav-reports"                     ><i class="fa-solid fa-chart-bar"></i>Sit-in Reports</a>
    <a onclick="showView('feedback')"      id="nav-feedback"                    ><i class="fa-solid fa-comment-dots"></i>Feedback Reports</a>
    <a onclick="showView('reservation')"   id="nav-reservation"                 ><i class="fa-solid fa-calendar-check"></i>Reservation</a>
    <a onclick="showView('leaderboard')"   id="nav-leaderboard"                 ><i class="fa-solid fa-trophy"></i>Leaderboard</a>
    <a onclick="showView('analytics')"     id="nav-analytics"                   ><i class="fa-solid fa-chart-line"></i>Analytics</a>
    <a onclick="showView('announcement')"  id="nav-announcement"                ><i class="fa-solid fa-bullhorn"></i>Announce</a>
    <a onclick="showView('rewards')"       id="nav-rewards"                     ><i class="fa-solid fa-gift"></i>Rewards</a>
  </div>
  <button class="btn-logout" onclick="confirmLogout()">
    <i class="fa-solid fa-right-from-bracket"></i> Log out
  </button>
</nav>

<div class="admin-wrap">

  <!-- ████ HOME ████ -->
  <div class="view active" id="view-home">
    <div class="stat-cards-row">
      <div class="stat-card c1">
        <div class="stat-icon ic-blue"><i class="fa-solid fa-users"></i></div>
        <div class="stat-info"><label>Students Registered</label><div class="stat-value" id="statRegistered">—</div></div>
      </div>
      <div class="stat-card c2">
        <div class="stat-icon ic-gold"><i class="fa-solid fa-chair"></i></div>
        <div class="stat-info"><label>Currently Sit-in</label><div class="stat-value" id="statCurrent">—</div></div>
      </div>
      <div class="stat-card c3">
        <div class="stat-icon ic-green"><i class="fa-solid fa-circle-check"></i></div>
        <div class="stat-info"><label>Total Sit-in</label><div class="stat-value" id="statTotal">—</div></div>
      </div>
    </div>
    <div class="row g-3">
      <div class="col-lg-6">
        <div class="a-card h-100">
          <div class="a-card-header"><i class="fa-solid fa-chart-pie"></i> Language / Purpose Distribution</div>
          <div class="a-card-body">
            <div class="chart-outer"><canvas id="purposeChart"></canvas></div>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="a-card h-100">
          <div class="a-card-header"><i class="fa-solid fa-bullhorn"></i> Announcement</div>
          <div class="a-card-body d-flex flex-column gap-3">
            <div>
              <textarea class="ann-textarea" id="annText" placeholder="Write a new announcement…"></textarea>
              <button class="btn-a-success mt-2" onclick="postAnnouncement()">
                <i class="fa-solid fa-paper-plane"></i> Post Announcement
              </button>
            </div>
            <div>
              <div style="font-size:.75rem;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.7px;margin-bottom:.65rem;">Posted Announcements</div>
              <div id="annList">
                <div class="ann-item">
                  <div class="ann-meta"><span class="ann-badge">CCS Admin</span> 2026-Feb-11</div>
                  <div class="ann-text ann-empty">No message content for this announcement.</div>
                </div>
                <div class="ann-item">
                  <div class="ann-meta"><span class="ann-badge">CCS Admin</span> 2024-May-08</div>
                  <div class="ann-text">🎉 <strong>Important Announcement</strong> — We are excited to announce the launch of our new website! Explore our latest products and services now!</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ████ STUDENTS ████ -->
  <div class="view" id="view-students">
    <div class="page-title"><i class="fa-solid fa-users"></i> Students Information</div>
    <div class="a-card">
      <div class="a-card-body">
        <div class="d-flex gap-2 mb-3 flex-wrap">
          <button class="btn-a-primary" onclick="openAddStudent()"><i class="fa-solid fa-user-plus"></i> Add Student</button>
          <button class="btn-a-danger"  onclick="confirmResetAll()"><i class="fa-solid fa-rotate"></i> Reset All Sessions</button>
        </div>
        <div class="tbl-top">
          <div class="entries-wrap">Show <select id="stuEntries" onchange="renderStudents()"><option>10</option><option>25</option><option>50</option></select> entries</div>
          <div class="tbl-search-wrap"><i class="fa-solid fa-magnifying-glass"></i><input type="text" id="stuSearch" placeholder="Search…" oninput="renderStudents()"/></div>
        </div>
        <div class="table-responsive">
          <table class="a-table">
            <thead><tr><th>ID Number</th><th>Name</th><th>Year</th><th>Course</th><th>Sessions Left</th><th>Actions</th></tr></thead>
            <tbody id="stuBody"></tbody>
          </table>
        </div>
        <div class="pg-wrap">
          <span id="stuInfo" style="color:var(--text3);">Showing 0 entries</span>
          <div id="stuPagination" style="display:flex;gap:.25rem;flex-wrap:wrap;"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- ████ CURRENT SIT-IN ████ -->
  <div class="view" id="view-current-sitin">
    <div class="page-title"><i class="fa-solid fa-chair"></i> Current Sit-in
      <button class="btn-a-primary ms-auto" onclick="openSitInModal()" style="font-size:.75rem;"><i class="fa-solid fa-plus"></i> New Sit-in</button>
    </div>
    <div class="a-card">
      <div class="a-card-body">
        <div class="tbl-top">
          <div class="entries-wrap">Show <select id="curEntries" onchange="renderCurrentSitIn()"><option>10</option><option>25</option><option>50</option></select> entries</div>
          <div class="tbl-search-wrap"><i class="fa-solid fa-magnifying-glass"></i><input type="text" id="curSearch" placeholder="Search…" oninput="renderCurrentSitIn()"/></div>
        </div>
        <div class="table-responsive">
          <table class="a-table">
            <thead><tr><th>Sit ID</th><th>ID Number</th><th>Name</th><th>Purpose</th><th>Lab</th><th>Session</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody id="curBody"></tbody>
          </table>
        </div>
        <div class="pg-wrap">
          <span id="curInfo" style="color:var(--text3);">Showing 0 entries</span>
          <div id="curPagination" style="display:flex;gap:.25rem;flex-wrap:wrap;"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- ████ SIT-IN RECORDS ████ -->
  <div class="view" id="view-sitin-records">
    <div class="page-title"><i class="fa-solid fa-table-list"></i> All Sit-in Records</div>
    <div class="a-card">
      <div class="a-card-body">
        <div class="tbl-top">
          <div class="entries-wrap">Show <select id="recEntries" onchange="renderRecords()"><option>10</option><option>25</option><option>50</option></select> entries</div>
          <div class="tbl-search-wrap"><i class="fa-solid fa-magnifying-glass"></i><input type="text" id="recSearch" placeholder="Search…" oninput="renderRecords()"/></div>
        </div>
        <div class="table-responsive">
          <table class="a-table">
            <thead><tr><th>Sit ID</th><th>ID Number</th><th>Name</th><th>Purpose</th><th>Lab</th><th>Session</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody id="recBody"></tbody>
          </table>
        </div>
        <div class="pg-wrap">
          <span id="recInfo" style="color:var(--text3);">Showing 0 entries</span>
          <div id="recPagination" style="display:flex;gap:.25rem;flex-wrap:wrap;"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- ████ REPORTS ████ -->
  <div class="view" id="view-reports">
    <div class="page-title"><i class="fa-solid fa-chart-bar"></i> Sit-in Reports</div>
    <div class="a-card mb-3">
      <div class="a-card-header"><i class="fa-solid fa-filter"></i> Filter Reports
        <div style="margin-left:auto;display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
          <span style="font-size:.74rem;color:var(--text3);">Show:</span>
          <select id="rptLimit" style="background:var(--surface2);border:1px solid var(--border);border-radius:6px;color:var(--text1);padding:.25rem .5rem;font-size:.78rem;outline:none;">
            <option value="5">5 records</option>
            <option value="10" selected>10 records</option>
            <option value="25">25 records</option>
            <option value="50">50 records</option>
            <option value="0">All records</option>
          </select>
          <span style="font-size:.74rem;color:var(--text3);">From:</span>
          <input type="date" id="rptFrom" style="background:var(--surface2);border:1px solid var(--border);border-radius:6px;color:var(--text1);padding:.28rem .5rem;font-size:.78rem;outline:none;"/>
          <span style="font-size:.74rem;color:var(--text3);">To:</span>
          <input type="date" id="rptTo" style="background:var(--surface2);border:1px solid var(--border);border-radius:6px;color:var(--text1);padding:.28rem .5rem;font-size:.78rem;outline:none;"/>
          <button class="btn-a-primary" onclick="loadReportsList()"><i class="fa-solid fa-filter"></i> Apply</button>
          <button class="btn-a-success" onclick="openPrintReport()"><i class="fa-solid fa-print"></i> Print Report</button>
        </div>
      </div>
    </div>
    <div class="a-card">
      <div class="a-card-body">
        <div class="table-responsive">
          <table class="a-table">
            <thead><tr><th>#</th><th>ID Number</th><th>Name</th><th>Course</th><th>Purpose</th><th>Lab</th><th>PC</th><th>Date</th><th>Time In</th><th>Time Out</th><th>Duration</th><th>Status</th></tr></thead>
            <tbody id="rptBody"><tr><td colspan="12" class="no-data">Click Apply to load reports.</td></tr></tbody>
          </table>
        </div>
        <div style="font-size:.75rem;color:var(--text3);margin-top:.6rem;" id="rptInfo"></div>
      </div>
    </div>
  </div>

  <!-- ████ FEEDBACK ████ -->
  <div class="view" id="view-feedback">
    <div class="page-title"><i class="fa-solid fa-comments"></i> Feedback Reports</div>
    <div class="a-card">
      <div class="a-card-header"><i class="fa-solid fa-star"></i> Student Feedback
        <div class="tbl-search-wrap ms-auto"><i class="fa-solid fa-magnifying-glass"></i><input type="text" id="fbSearch" placeholder="Search…" oninput="filterFeedback()"/></div>
      </div>
      <div class="a-card-body">
        <div class="table-responsive">
          <table class="a-table">
            <thead><tr><th>#</th><th>Student</th><th>Course</th><th>Lab</th><th>Purpose</th><th>Rating</th><th>Feedback Message</th><th>Date</th></tr></thead>
            <tbody id="fbBody"><tr><td colspan="8" class="no-data">Loading feedback…</td></tr></tbody>
          </table>
        </div>
        <div style="font-size:.75rem;color:var(--text3);margin-top:.6rem;" id="fbInfo"></div>
      </div>
    </div>
  </div>

  <!-- ████ RESERVATION ████ -->
  <div class="view" id="view-reservation">
    <div class="page-title"><i class="fa-solid fa-calendar-check"></i> Reservation Management</div>

    <!-- Live PC Map -->
    <div class="a-card mb-3">
      <div class="a-card-header"><i class="fa-solid fa-desktop"></i> Live Lab PC Map
        <div style="margin-left:auto;display:flex;gap:.4rem;align-items:center;">
          <button class="lab-btn-admin active" onclick="loadAdminPcMap('524',this)">Lab 524</button>
          <button class="lab-btn-admin"        onclick="loadAdminPcMap('526',this)">Lab 526</button>
          <button class="lab-btn-admin"        onclick="loadAdminPcMap('528',this)">Lab 528</button>
          <button class="lab-btn-admin"        onclick="loadAdminPcMap('530',this)">Lab 530</button>
          <input type="date" id="adminMapDate" style="background:var(--surface2);border:1px solid var(--border);border-radius:7px;color:var(--text1);padding:.28rem .55rem;font-size:.78rem;outline:none;" onchange="loadAdminPcMap(currentAdminLab)"/>
          <button onclick="loadAdminPcMap(currentAdminLab)" style="background:rgba(201,168,76,.15);color:var(--gold2);border:none;border-radius:7px;padding:.3rem .7rem;font-size:.75rem;font-weight:700;cursor:pointer;"><i class="fa-solid fa-arrows-rotate"></i></button>
        </div>
      </div>
      <div class="a-card-body">
        <div class="pc-legend">
          <span><span class="dot" style="background:rgba(34,197,94,.5);border:1px solid #16a34a"></span>Available</span>
          <span><span class="dot" style="background:rgba(239,68,68,.5);border:1px solid #991b1b"></span>Occupied (Sit-in)</span>
          <span><span class="dot" style="background:rgba(245,158,11,.5);border:1px solid #92400e"></span>Reserved (Approved)</span>
          <span><span class="dot" style="background:rgba(139,92,246,.5);border:1px solid #5b21b6"></span>Pending Approval</span>
        </div>
        <div id="adminPcMapLoading" style="text-align:center;padding:2.5rem;color:var(--text3);">
          <i class="fa-solid fa-spinner fa-spin fa-2x"></i><br><br>Loading PC map…
        </div>
        <div id="adminPcGrid" class="admin-pc-grid" style="display:none;"></div>
        <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:.85rem;" id="adminLabStats"></div>
      </div>
    </div>

    <!-- Reservation Requests Table -->
    <div class="a-card">
      <div class="a-card-header"><i class="fa-solid fa-list-check"></i> Reservation Requests
        <div class="tbl-search-wrap ms-auto"><i class="fa-solid fa-magnifying-glass"></i><input type="text" id="resSearch" placeholder="Search…" oninput="renderAdminReservations()"/></div>
      </div>
      <div class="a-card-body">
        <div class="table-responsive">
          <table class="a-table">
            <thead><tr><th>#</th><th>Student</th><th>Lab</th><th>PC</th><th>Date</th><th>Time</th><th>Purpose</th><th>Status</th><th>Action</th></tr></thead>
            <tbody id="resBody"><tr><td colspan="9" class="no-data">Loading…</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- ████ LEADERBOARD ████ -->
  <div class="view" id="view-leaderboard">
    <div class="page-title"><i class="fa-solid fa-trophy"></i> Leaderboard</div>
    <div class="a-card">
      <div class="a-card-header"><i class="fa-solid fa-ranking-star"></i> Top Students by Points</div>
      <div class="a-card-body">
        <div class="table-responsive">
          <table class="a-table">
            <thead><tr><th>Rank</th><th>Student</th><th>Course</th><th>Sit-ins</th><th>Points</th><th>Action</th></tr></thead>
            <tbody id="adminLbBody"><tr><td colspan="6" class="no-data">Loading…</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- ████ ANALYTICS ████ -->
  <div class="view" id="view-analytics">
    <div class="page-title"><i class="fa-solid fa-chart-line"></i> Analytics &amp; Reports</div>
    <div class="stat-cards-row mb-4">
      <div class="stat-card c1"><div class="stat-icon ic-blue"><i class="fa-solid fa-users"></i></div><div class="stat-info"><label>Registered</label><div class="stat-value" id="anaRegistered">—</div></div></div>
      <div class="stat-card c2"><div class="stat-icon ic-gold"><i class="fa-solid fa-chair"></i></div><div class="stat-info"><label>Total Sit-ins</label><div class="stat-value" id="anaTotalSitins">—</div></div></div>
      <div class="stat-card c3"><div class="stat-icon ic-green"><i class="fa-solid fa-circle-check"></i></div><div class="stat-info"><label>Active Now</label><div class="stat-value" id="anaActive">—</div></div></div>
    </div>
    <div class="row g-3">
      <div class="col-lg-6">
        <div class="a-card">
          <div class="a-card-header"><i class="fa-solid fa-chart-bar"></i> Sit-ins Over Time
            <div style="margin-left:auto;display:flex;gap:.3rem;">
              <button class="pg-btn" onclick="loadReport('daily')">Daily</button>
              <button class="pg-btn" onclick="loadReport('weekly')">Weekly</button>
              <button class="pg-btn" onclick="loadReport('monthly')">Monthly</button>
            </div>
          </div>
          <div class="a-card-body"><canvas id="reportChart" height="200"></canvas></div>
        </div>
      </div>
      <div class="col-lg-3">
        <div class="a-card">
          <div class="a-card-header"><i class="fa-solid fa-chart-pie"></i> By Purpose</div>
          <div class="a-card-body"><canvas id="purposeChart2" height="200"></canvas></div>
        </div>
      </div>
      <div class="col-lg-3">
        <div class="a-card">
          <div class="a-card-header"><i class="fa-solid fa-flask"></i> By Lab</div>
          <div class="a-card-body"><canvas id="labChart" height="200"></canvas></div>
        </div>
      </div>
    </div>
  </div>

  <!-- ████ ANNOUNCEMENT ████ -->
  <div class="view" id="view-announcement">
    <div class="page-title"><i class="fa-solid fa-bullhorn"></i> Create Announcement</div>
    <div class="row g-4">
      <div class="col-lg-5">
        <div class="a-card">
          <div class="a-card-header"><i class="fa-solid fa-pen-to-square"></i> Post New Announcement</div>
          <div class="a-card-body">
            <div style="margin-bottom:.75rem;">
              <div style="font-size:.78rem;font-weight:600;color:var(--text2);margin-bottom:.35rem;">Title (optional)</div>
              <input type="text" id="annTitleNew" placeholder="Announcement title…" style="width:100%;background:var(--surface2);border:1px solid var(--border);border-radius:9px;color:var(--text1);padding:.5rem .85rem;font-size:.82rem;outline:none;font-family:'Plus Jakarta Sans',sans-serif;transition:border-color .2s;" onfocus="this.style.borderColor='var(--gold)'" onblur="this.style.borderColor='var(--border)'"/>
            </div>
            <div style="margin-bottom:.75rem;">
              <div style="font-size:.78rem;font-weight:600;color:var(--text2);margin-bottom:.35rem;">Message</div>
              <textarea id="annTextNew" class="ann-textarea" placeholder="Write your announcement…" rows="5"></textarea>
            </div>
            <button class="btn-a-success" onclick="postAnnouncementDB()"><i class="fa-solid fa-paper-plane"></i> Post Announcement</button>
          </div>
        </div>
      </div>
      <div class="col-lg-7">
        <div class="a-card">
          <div class="a-card-header"><i class="fa-solid fa-list"></i> Posted Announcements</div>
          <div class="a-card-body" id="annListNew" style="max-height:360px;overflow-y:auto;">
            <div class="ann-item"><div class="ann-meta"><span class="ann-badge">CCS Admin</span></div><div class="ann-text ann-empty">No announcements yet.</div></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ████ REWARDS ████ -->
  <div class="view" id="view-rewards">
    <div class="page-title"><i class="fa-solid fa-gift"></i> Add Reward / Points</div>
    <div class="row g-4">
      <div class="col-lg-4">
        <div class="a-card">
          <div class="a-card-header"><i class="fa-solid fa-star"></i> Award Points to Student</div>
          <div class="a-card-body">
            <div style="margin-bottom:.7rem;">
              <div style="font-size:.78rem;font-weight:600;color:var(--text2);margin-bottom:.35rem;">Student ID Number</div>
              <input type="text" id="rwIdNum" placeholder="e.g. 20-1234-567" style="width:100%;background:var(--surface2);border:1px solid var(--border);border-radius:9px;color:var(--text1);padding:.5rem .85rem;font-size:.82rem;outline:none;font-family:'Plus Jakarta Sans',sans-serif;"/>
            </div>
            <div style="margin-bottom:.7rem;">
              <div style="font-size:.78rem;font-weight:600;color:var(--text2);margin-bottom:.35rem;">Points to Add</div>
              <input type="number" id="rwPoints" min="1" max="100" value="10" style="width:100%;background:var(--surface2);border:1px solid var(--border);border-radius:9px;color:var(--text1);padding:.5rem .85rem;font-size:.82rem;outline:none;font-family:'Plus Jakarta Sans',sans-serif;"/>
            </div>
            <div style="margin-bottom:.85rem;">
              <div style="font-size:.78rem;font-weight:600;color:var(--text2);margin-bottom:.35rem;">Reason</div>
              <input type="text" id="rwReason" placeholder="e.g. Perfect attendance…" style="width:100%;background:var(--surface2);border:1px solid var(--border);border-radius:9px;color:var(--text1);padding:.5rem .85rem;font-size:.82rem;outline:none;font-family:'Plus Jakarta Sans',sans-serif;"/>
            </div>
            <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
              <button class="btn-a-success" onclick="awardPoints()"><i class="fa-solid fa-star"></i> Award Points</button>
              <button class="btn-a-primary" onclick="resetAllSessionsDB()"><i class="fa-solid fa-arrows-rotate"></i> Reset All Sessions</button>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-8">
        <div class="a-card">
          <div class="a-card-header"><i class="fa-solid fa-ranking-star"></i> Points Leaderboard</div>
          <div class="table-responsive">
            <table class="a-table">
              <thead><tr><th>Rank</th><th>Student</th><th>Course</th><th>Sit-ins</th><th>Points</th></tr></thead>
              <tbody id="rwLbBody"><tr><td colspan="5" class="no-data">Loading…</td></tr></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

</div><!-- /admin-wrap -->


<!-- ══ MODAL: SEARCH STUDENT ══ -->
<div class="modal fade" id="modalSearch" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-magnifying-glass me-2"></i>Search Student</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="search-input-wrap">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input type="text" id="searchInput"
            placeholder="Search by ID or Name…"
            oninput="liveSearch()"
            onkeydown="if(event.key==='Enter') liveSearch()"/>
        </div>
        <div id="searchResults" class="mt-3">
          <div class="search-hint">
            <i class="fa-solid fa-user-magnifying-glass"></i>
            Type to search students from the database
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ══ MODAL: SIT-IN FORM ══ -->
<div class="modal fade" id="modalSitIn" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-chair me-2"></i>Sit In Form</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-12"><label class="form-label">ID Number</label><input type="text" class="form-control" id="siIdNum" placeholder="Enter student ID…" oninput="lookupStudent()"/></div>
          <div class="col-12"><label class="form-label">Student Name</label><input type="text" class="form-control" id="siName" readonly placeholder="Auto-filled…"/></div>
          <div class="col-12">
            <label class="form-label">Purpose / Language</label>
            <select class="form-select" id="siPurpose">
              <option value="">Select purpose / language…</option>
              <optgroup label="Programming Languages">
                <option>C Programming</option>
                <option>C++ Programming</option>
                <option>Java</option>
                <option>Python</option>
                <option>PHP</option>
                <option>JavaScript</option>
                <option>ASP.Net</option>
                <option>C# (.NET)</option>
                <option>Visual Basic</option>
                <option>SQL / Database</option>
              </optgroup>
              <optgroup label="Academic Work">
                <option>Thesis / Capstone</option>
                <option>Research Paper</option>
                <option>Assignment</option>
                <option>Laboratory Exercise</option>
                <option>Online Class</option>
              </optgroup>
              <optgroup label="Other">
                <option>Personal Project</option>
                <option>Browsing / Research</option>
                <option>Other</option>
              </optgroup>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Laboratory</label>
            <select class="form-select" id="siLab"><option value="">Select Lab…</option><option>524</option><option>526</option><option>528</option><option>530</option></select>
          </div>
          <div class="col-12">
            <label class="form-label">Remaining Sessions</label>
            <select class="form-select" id="siSession">
              <option value="">Auto-filled…</option>
              <option value="30">30</option>
              <option value="29">29</option>
              <option value="28">28</option>
              <option value="27">27</option>
              <option value="26">26</option>
              <option value="25">25</option>
              <option value="24">24</option>
              <option value="23">23</option>
              <option value="22">22</option>
              <option value="21">21</option>
              <option value="20">20</option>
              <option value="19">19</option>
              <option value="18">18</option>
              <option value="17">17</option>
              <option value="16">16</option>
              <option value="15">15</option>
              <option value="14">14</option>
              <option value="13">13</option>
              <option value="12">12</option>
              <option value="11">11</option>
              <option value="10">10</option>
              <option value="9">9</option>
              <option value="8">8</option>
              <option value="7">7</option>
              <option value="6">6</option>
              <option value="5">5</option>
              <option value="4">4</option>
              <option value="3">3</option>
              <option value="2">2</option>
              <option value="1">1</option>
              <option value="0">0</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button class="btn btn-primary" onclick="submitSitIn()"><i class="fa-solid fa-check me-1"></i>Sit In</button>
      </div>
    </div>
  </div>
</div>

<!-- ══ MODAL: ADD STUDENT ══ -->
<div class="modal fade" id="modalAddStudent" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-user-plus me-2"></i>Add Student</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">ID Number</label><input type="text" class="form-control" id="asId"/></div>
          <div class="col-md-6"><label class="form-label">First Name</label><input type="text" class="form-control" id="asFn"/></div>
          <div class="col-md-6"><label class="form-label">Last Name</label><input type="text" class="form-control" id="asLn"/></div>
          <div class="col-md-6"><label class="form-label">Middle Name</label><input type="text" class="form-control" id="asMn"/></div>
          <div class="col-md-6"><label class="form-label">Course</label><select class="form-select" id="asCo"><option>BSIT</option><option>BSCS</option><option>BSIS</option><option>ACT</option></select></div>
          <div class="col-md-6"><label class="form-label">Year Level</label><select class="form-select" id="asYr"><option value="1">1st Year</option><option value="2">2nd Year</option><option value="3">3rd Year</option><option value="4">4th Year</option></select></div>
          <div class="col-12"><label class="form-label">Email</label><input type="email" class="form-control" id="asEm"/></div>
          <div class="col-12"><label class="form-label">Password</label><input type="password" class="form-control" id="asPw"/></div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" onclick="submitAddStudent()">Add Student</button>
      </div>
    </div>
  </div>
</div>

<!-- ══ MODAL: EDIT STUDENT ══ -->
<div class="modal fade" id="modalEditStudent" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-user-pen me-2"></i>Edit Student</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="editId"/>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">First Name</label><input type="text" class="form-control" id="editFn"/></div>
          <div class="col-md-6"><label class="form-label">Last Name</label><input type="text" class="form-control" id="editLn"/></div>
          <div class="col-md-6"><label class="form-label">Course</label><select class="form-select" id="editCo"><option>BSIT</option><option>BSCS</option><option>BSIS</option><option>ACT</option></select></div>
          <div class="col-md-6"><label class="form-label">Year Level</label><select class="form-select" id="editYr"><option value="1">1st Year</option><option value="2">2nd Year</option><option value="3">3rd Year</option><option value="4">4th Year</option></select></div>
          <div class="col-md-6"><label class="form-label">Remaining Sessions</label><input type="number" class="form-control" id="editSess" min="0" max="30"/></div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" onclick="submitEditStudent()">Save Changes</button>
      </div>
    </div>
  </div>
</div>

<!-- ══ MODAL: LOGOUT ══ -->
<div class="modal fade" id="modalLogout" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-body text-center py-4">
        <i class="fa-solid fa-right-from-bracket" style="font-size:2rem;color:var(--gold);display:block;margin-bottom:.75rem;"></i>
        <div class="fw-bold mb-1" style="color:var(--text1);">Log Out?</div>
        <p style="color:var(--text2);font-size:.82rem;margin-top:.35rem;">Are you sure you want to end your session?</p>
      </div>
      <div class="modal-footer justify-content-center gap-2 border-0 pt-0">
        <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-danger btn-sm" onclick="doLogout()">Yes, Log Out</button>
      </div>
    </div>
  </div>
</div>

<!-- ══ TOAST ══ -->
<div class="position-fixed top-0 end-0 p-3" style="z-index:9999;margin-top:62px;">
  <div id="adminToast" class="toast align-items-center text-white border-0" role="alert" style="border-radius:10px;font-size:.82rem;font-weight:600;">
    <div class="d-flex">
      <div class="toast-body" id="adminToastMsg">Done!</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── STATE ──────────────────────────────────────────────────
let students    = [];
let sitInRecs   = [];
let stuPage     = 1;
let recPage     = 1;
let curPage     = 1;
let searchTimer = null;

// ── TOAST ──────────────────────────────────────────────────
function toast(msg, type = 'success') {
  const el = document.getElementById('adminToast');
  const colors = { success:'#10b981', danger:'#ef4444', warning:'#f59e0b', info:'#3b82f6' };
  el.style.background = colors[type] || colors.success;
  document.getElementById('adminToastMsg').textContent = msg;
  bootstrap.Toast.getOrCreateInstance(el, { delay: 2800 }).show();
}

// ── VIEWS ──────────────────────────────────────────────────
function showView(name) {
  document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
  document.querySelectorAll('.admin-nav .nav-links a').forEach(a => a.classList.remove('active'));
  document.getElementById('view-' + name).classList.add('active');
  const navMap = {
    'home':'home','students':'students','sitin-records':'records',
    'current-sitin':'sitin','reports':'reports','feedback':'feedback',
    'reservation':'reservation','leaderboard':'leaderboard',
    'analytics':'analytics','announcement':'announcement','rewards':'rewards'
  };
  const navEl = document.getElementById('nav-' + (navMap[name] || name));
  if (navEl) navEl.classList.add('active');
  if (name === 'students')      renderStudents();
  if (name === 'sitin-records') renderRecords();
  if (name === 'current-sitin') renderCurrentSitIn();
  if (name === 'home')          loadStats();
  if (name === 'reservation')   { loadAdminPcMap('524'); loadAdminReservations(); }
  if (name === 'leaderboard')   loadAdminLeaderboard();
  if (name === 'analytics')     loadAnalytics();
  if (name === 'announcement')  loadAnnouncementsDB();
  if (name === 'rewards')       loadAdminLeaderboard();
  if (name === 'reports')       loadReportsList();
  if (name === 'feedback')      loadFeedback();
}

// ── STATS ──────────────────────────────────────────────────
function loadStats() {
  fetch('admin_stats.php')
    .then(r => r.json())
    .then(d => {
      document.getElementById('statRegistered').textContent = d.registered ?? students.length;
      document.getElementById('statCurrent').textContent    = d.current    ?? sitInRecs.filter(r => r.status === 'Active').length;
      document.getElementById('statTotal').textContent      = d.total      ?? sitInRecs.length;
      renderChart(d.purposes ?? {});
    })
    .catch(() => {
      document.getElementById('statRegistered').textContent = students.length;
      document.getElementById('statCurrent').textContent    = sitInRecs.filter(r => r.status === 'Active').length;
      document.getElementById('statTotal').textContent      = sitInRecs.length;
      renderChart({});
    });
}

let chartInst = null;
function renderChart(purposes) {
  const labels = Object.keys(purposes).length ? Object.keys(purposes) : ['C#','C','Java','ASP.Net','PHP'];
  const vals   = Object.values(purposes).length ? Object.values(purposes) : [30,20,20,15,15];
  const colors = ['#3b82f6','#ef4444','#f97316','#eab308','#22c55e','#8b5cf6','#06b6d4'];
  const ctx = document.getElementById('purposeChart').getContext('2d');
  if (chartInst) chartInst.destroy();
  chartInst = new Chart(ctx, {
    type: 'doughnut',
    data: { labels, datasets: [{ data: vals, backgroundColor: colors.slice(0,labels.length), borderWidth: 3, borderColor: '#0d1b35', hoverOffset: 6 }] },
    options: {
      cutout: '58%', responsive: true,
      plugins: {
        legend: { position: 'right', labels: { color: '#94a8cc', font: { size: 11, family: 'Plus Jakarta Sans' }, padding: 14, boxWidth: 10, boxHeight: 10, usePointStyle: true } },
        tooltip: { backgroundColor: '#112040', borderColor: 'rgba(201,168,76,.3)', borderWidth: 1, titleColor: '#f0f4ff', bodyColor: '#94a8cc', padding: 10 }
      }
    }
  });
}

// ── FETCH STUDENTS ─────────────────────────────────────────
function fetchStudents() {
  fetch('admin_students.php')
    .then(r => r.json())
    .then(d => { students = d; renderStudents(); })
    .catch(() => renderStudents());
}

// ── RENDER STUDENTS ────────────────────────────────────────
function renderStudents() {
  const q   = (document.getElementById('stuSearch').value || '').toLowerCase();
  const pp  = parseInt(document.getElementById('stuEntries').value || 10);
  const data = students.filter(s => (s.id_number+' '+s.first_name+' '+s.last_name+' '+s.course).toLowerCase().includes(q));
  const total = data.length, pages = Math.max(1, Math.ceil(total/pp));
  if (stuPage > pages) stuPage = pages;
  const slice = data.slice((stuPage-1)*pp, stuPage*pp);
  const tbody = document.getElementById('stuBody');
  if (!total) {
    tbody.innerHTML = `<tr><td colspan="6" class="no-data"><i class="fa-solid fa-user-slash" style="margin-right:.4rem;opacity:.4;"></i>No students found.</td></tr>`;
  } else {
    tbody.innerHTML = slice.map(s => {
      const sess = s.remaining_sessions ?? 30;
      const sessColor = sess <= 5 ? '#ef4444' : sess <= 10 ? '#f59e0b' : '#10b981';
      return `<tr>
        <td><span class="id-badge">${s.id_number}</span></td>
        <td style="font-weight:600;">${s.first_name} ${s.middle_name ? s.middle_name[0]+'.' : ''} ${s.last_name}</td>
        <td style="color:var(--text2);">${s.year_level}</td>
        <td><span style="background:rgba(59,130,246,.12);color:#60a5fa;padding:2px 8px;border-radius:5px;font-size:.72rem;font-weight:700;">${s.course}</span></td>
        <td><span style="font-family:'Bebas Neue',cursive;font-size:1rem;color:${sessColor};">${sess}</span></td>
        <td style="display:flex;gap:.35rem;">
          <button class="btn-a-primary" onclick="openEditStudent(${JSON.stringify(s).replace(/"/g,'&quot;')})"><i class="fa-solid fa-pen"></i> Edit</button>
          <button class="btn-a-danger"  onclick="deleteStudent(${s.id})"><i class="fa-solid fa-trash"></i> Delete</button>
        </td>
      </tr>`;
    }).join('');
  }
  document.getElementById('stuInfo').textContent = total
    ? `Showing ${(stuPage-1)*pp+1}–${Math.min(stuPage*pp,total)} of ${total} entries` : 'Showing 0 entries';
  renderPagination('stuPagination', stuPage, pages, p => { stuPage=p; renderStudents(); });
}

// ── RENDER RECORDS ─────────────────────────────────────────
function renderRecords() {
  const q   = (document.getElementById('recSearch').value || '').toLowerCase();
  const pp  = parseInt(document.getElementById('recEntries').value || 10);
  const data = sitInRecs.filter(r => (r.id_number+' '+r.name+' '+r.purpose+' '+r.lab).toLowerCase().includes(q));
  const total = data.length, pages = Math.max(1, Math.ceil(total/pp));
  if (recPage > pages) recPage = pages;
  const slice = data.slice((recPage-1)*pp, recPage*pp);
  const tbody = document.getElementById('recBody');
  if (!total) {
    tbody.innerHTML = `<tr><td colspan="8" class="no-data"><i class="fa-solid fa-circle-info" style="margin-right:.4rem;opacity:.4;"></i>No sit-in data available.</td></tr>`;
  } else {
    tbody.innerHTML = slice.map(r => `
      <tr>
        <td><span class="id-badge">${r.sit_id}</span></td>
        <td><span class="id-badge">${r.id_number}</span></td>
        <td style="font-weight:600;">${r.name}</td>
        <td style="color:var(--text2);">${r.purpose}</td>
        <td><span style="background:rgba(201,168,76,.12);color:var(--gold2);padding:2px 8px;border-radius:5px;font-size:.72rem;font-weight:700;">${r.lab}</span></td>
        <td style="color:var(--text2);">${r.session}</td>
        <td><span class="badge-${r.status==='Active'?'active':'done'}">${r.status}</span></td>
        <td><button class="btn-a-danger" onclick="timeOut(${r.sit_id})"><i class="fa-solid fa-clock"></i> Time Out</button></td>
      </tr>`).join('');
  }
  document.getElementById('recInfo').textContent = total
    ? `Showing ${(recPage-1)*pp+1}–${Math.min(recPage*pp,total)} of ${total} entries` : 'Showing 0 entries';
  renderPagination('recPagination', recPage, pages, p => { recPage=p; renderRecords(); });
}


// ── RENDER CURRENT SIT-IN (Active only) ────────────────────
function renderCurrentSitIn() {
  const q   = (document.getElementById('curSearch').value || '').toLowerCase();
  const pp  = parseInt(document.getElementById('curEntries').value || 10);
  const data = sitInRecs.filter(r =>
    r.status === 'Active' &&
    (r.id_number+' '+r.name+' '+r.purpose+' '+r.lab).toLowerCase().includes(q)
  );
  const total = data.length, pages = Math.max(1, Math.ceil(total/pp));
  if (curPage > pages) curPage = pages;
  const slice = data.slice((curPage-1)*pp, curPage*pp);
  const tbody = document.getElementById('curBody');
  if (!total) {
    tbody.innerHTML = `<tr><td colspan="8" class="no-data"><i class="fa-solid fa-circle-info" style="margin-right:.4rem;opacity:.4;"></i>No active sit-in sessions right now.</td></tr>`;
  } else {
    tbody.innerHTML = slice.map(r => `
      <tr>
        <td><span class="id-badge">${r.sit_id}</span></td>
        <td><span class="id-badge">${r.id_number}</span></td>
        <td style="font-weight:600;">${r.name}</td>
        <td style="color:var(--text2);">${r.purpose}</td>
        <td><span style="background:rgba(201,168,76,.12);color:var(--gold2);padding:2px 8px;border-radius:5px;font-size:.72rem;font-weight:700;">${r.lab}</span></td>
        <td style="color:var(--text2);">${r.session}</td>
        <td><span class="badge-active">Active</span></td>
        <td><button class="btn-a-danger" onclick="timeOutAndRefresh(${r.sit_id})"><i class="fa-solid fa-clock"></i> Time Out</button></td>
      </tr>`).join('');
  }
  document.getElementById('curInfo').textContent = total
    ? `Showing ${(curPage-1)*pp+1}–${Math.min(curPage*pp,total)} of ${total} active sessions` : 'No active sessions';
  renderPagination('curPagination', curPage, pages, p => { curPage=p; renderCurrentSitIn(); });
}
function timeOutAndRefresh(sitId) {
  fetch('admin_sitin_timeout.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ sit_id: sitId })
  })
  .then(r => r.json())
  .then(d => {
    if (!d.success) { toast('Could not time out.', 'danger'); return; }
    toast('Student timed out.', 'warning');
    fetchSitInRecords();
  })
  .catch(() => {
    const rec = sitInRecs.find(r => r.sit_id === sitId);
    if (rec) rec.status = 'Done';
    renderCurrentSitIn();
    renderRecords();
    loadStats();
    toast('Student timed out.', 'warning');
  });
}

// ── PAGINATION ─────────────────────────────────────────────
function renderPagination(id, cur, total, cb) {
  const el = document.getElementById(id);
  let h = `<button class="pg-btn" ${cur===1?'disabled':''} onclick="(${cb})(${cur-1})">‹</button>`;
  const start = Math.max(1, cur-2), end = Math.min(total, cur+2);
  if (start > 1) h += `<button class="pg-btn" onclick="(${cb})(1)">1</button><span style="color:var(--text3);padding:0 2px;">…</span>`;
  for (let i=start;i<=end;i++) h += `<button class="pg-btn${i===cur?' active':''}" onclick="(${cb})(${i})">${i}</button>`;
  if (end < total) h += `<span style="color:var(--text3);padding:0 2px;">…</span><button class="pg-btn" onclick="(${cb})(${total})">${total}</button>`;
  h += `<button class="pg-btn" ${cur===total?'disabled':''} onclick="(${cb})(${cur+1})">›</button>`;
  el.innerHTML = h;
}

// ── SEARCH — fetches from search_student.php ───────────────
function openSearch() {
  document.getElementById('searchInput').value = '';
  document.getElementById('searchResults').innerHTML = `
    <div class="search-hint"><i class="fa-solid fa-user-magnifying-glass"></i>Type to search students from the database</div>`;
  new bootstrap.Modal(document.getElementById('modalSearch')).show();
  setTimeout(() => document.getElementById('searchInput').focus(), 300);
}

function liveSearch() {
  const q = document.getElementById('searchInput').value.trim();
  const res = document.getElementById('searchResults');
  if (!q) {
    res.innerHTML = `<div class="search-hint"><i class="fa-solid fa-user-magnifying-glass"></i>Type to search students from the database</div>`;
    return;
  }
  clearTimeout(searchTimer);
  searchTimer = setTimeout(async () => {
    res.innerHTML = `<div class="loading-row"><div class="spinner"></div> Searching database…</div>`;
    try {
      const r    = await fetch(`api/search_student.php?q=${encodeURIComponent(q)}`);
      const data = await r.json();
      if (!data.length) {
        res.innerHTML = `<div class="search-hint"><i class="fa-solid fa-user-slash"></i>No student found for "<strong style="color:var(--text1);">${q}</strong>"</div>`;
        return;
      }
      res.innerHTML = data.map(s => {
        const initials = ((s.firstname||'?')[0] + (s.lastname||'?')[0]).toUpperCase();
        const safeData = encodeURIComponent(JSON.stringify(s));
        return `<div class="search-result-card" style="cursor:pointer;" onclick="selectStudentForSitIn(JSON.parse(decodeURIComponent('${safeData}')))">
          <div class="src-avatar">${initials}</div>
          <div class="src-info">
            <div class="src-name">${s.firstname} ${s.lastname}</div>
            <div class="src-meta">
              <span><i class="fa-solid fa-id-card" style="margin-right:3px;"></i>${s.id}</span>
              <span><i class="fa-solid fa-book" style="margin-right:3px;"></i>${s.course}</span>
              <span><i class="fa-solid fa-layer-group" style="margin-right:3px;"></i>Year ${s.year}</span>
            </div>
          </div>
          <div class="src-session"><i class="fa-solid fa-rotate" style="margin-right:3px;"></i>${s.remaining_sessions ?? 30} sess.</div>
          <div style="margin-left:8px;color:var(--gold2);font-size:.7rem;font-weight:700;white-space:nowrap;"><i class="fa-solid fa-chair"></i> Sit In</div>
        </div>`;
      }).join('');
    } catch (e) {
      // Fallback: filter local students array
      const found = students.filter(s =>
        (s.id_number+' '+s.first_name+' '+s.last_name).toLowerCase().includes(q.toLowerCase())
      );
      if (!found.length) {
        res.innerHTML = `<div class="search-hint"><i class="fa-solid fa-user-slash"></i>No results found.</div>`;
        return;
      }
      res.innerHTML = found.map(s => {
        const initials = ((s.first_name||'?')[0]+(s.last_name||'?')[0]).toUpperCase();
        const mapped = { id: s.id_number, firstname: s.first_name, lastname: s.last_name, course: s.course, year: s.year_level, remaining_sessions: s.remaining_sessions ?? 30 };
        const safeData = encodeURIComponent(JSON.stringify(mapped));
        return `<div class="search-result-card" style="cursor:pointer;" onclick="selectStudentForSitIn(JSON.parse(decodeURIComponent('${safeData}')))">
          <div class="src-avatar">${initials}</div>
          <div class="src-info">
            <div class="src-name">${s.first_name} ${s.last_name}</div>
            <div class="src-meta">
              <span><i class="fa-solid fa-id-card" style="margin-right:3px;"></i>${s.id_number}</span>
              <span><i class="fa-solid fa-book" style="margin-right:3px;"></i>${s.course}</span>
              <span><i class="fa-solid fa-layer-group" style="margin-right:3px;"></i>Year ${s.year_level}</span>
            </div>
          </div>
          <div class="src-session"><i class="fa-solid fa-rotate" style="margin-right:3px;"></i>${s.remaining_sessions ?? 30} sess.</div>
          <div style="margin-left:8px;color:var(--gold2);font-size:.7rem;font-weight:700;white-space:nowrap;"><i class="fa-solid fa-chair"></i> Sit In</div>
        </div>`;
      }).join('');
    }
  }, 300);
}

// ── FETCH SIT-IN RECORDS FROM DB ───────────────────────────
function fetchSitInRecords() {
  fetch('admin_sitin_fetch.php?filter=all')
    .then(r => r.json())
    .then(d => {
      sitInRecs = d.map(r => ({
        sit_id:    r.sit_id,
        id_number: r.id_number,
        name:      r.name,
        purpose:   r.purpose,
        lab:       r.lab,
        session:   r.session,
        status:    r.status,
        created_at: r.created_at
      }));
      renderRecords();
      renderCurrentSitIn();
      loadStats();
    })
    .catch(() => { /* keep existing sitInRecs if offline */ });
}

// ── SIT-IN ─────────────────────────────────────────────────
function openSitInModal() {
  ['siIdNum','siName','siSession'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('siPurpose').value = '';
  document.getElementById('siLab').value = '';
  new bootstrap.Modal(document.getElementById('modalSitIn')).show();
}

// Called when a student card is clicked in the Search modal
function selectStudentForSitIn(s) {
  const searchModalEl = document.getElementById('modalSearch');
  const searchModal   = bootstrap.Modal.getInstance(searchModalEl);
  if (searchModal) searchModal.hide();
  searchModalEl.addEventListener('hidden.bs.modal', function handler() {
    searchModalEl.removeEventListener('hidden.bs.modal', handler);
    document.getElementById('siIdNum').value   = s.id            ?? '';
    document.getElementById('siName').value    = (s.firstname + ' ' + s.lastname).trim();
    document.getElementById('siSession').value = s.remaining_sessions ?? 30;
    document.getElementById('siPurpose').value = '';
    document.getElementById('siLab').value     = '';
    new bootstrap.Modal(document.getElementById('modalSitIn')).show();
  });
}
function lookupStudent() {
  const id  = document.getElementById('siIdNum').value.trim();
  const stu = students.find(s => s.id_number === id);
  if (stu) {
    document.getElementById('siName').value    = stu.first_name + ' ' + stu.last_name;
    document.getElementById('siSession').value = stu.remaining_sessions ?? 30;
  } else {
    document.getElementById('siName').value    = '';
    document.getElementById('siSession').value = '';
  }
}
function submitSitIn() {
  const id      = document.getElementById('siIdNum').value.trim();
  const name    = document.getElementById('siName').value;
  const purpose = document.getElementById('siPurpose').value.trim();
  const lab     = document.getElementById('siLab').value;
  if (!id||!name||!purpose||!lab) { alert('Please fill in all fields.'); return; }

  fetch('admin_sitin_submit.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id_number: id, purpose, lab, override_sessions: document.getElementById('siSession').value })
  })
  .then(r => r.json())
  .then(d => {
    if (!d.success) { alert(d.message || 'Could not save sit-in.'); return; }
    bootstrap.Modal.getInstance(document.getElementById('modalSitIn')).hide();
    toast('Student sat in successfully!');
    fetchSitInRecords();   // reload from DB so data is fresh
    showView('current-sitin');
    // Refresh student session count
    fetchStudents();
  })
  .catch(() => {
    // Offline fallback – save in memory only
    sitInRecs.push({ sit_id: sitInRecs.length+1, id_number:id, name, purpose, lab,
                     session: document.getElementById('siSession').value, status:'Active' });
    bootstrap.Modal.getInstance(document.getElementById('modalSitIn')).hide();
    toast('Saved locally (DB unavailable).', 'warning');
    renderCurrentSitIn();
    showView('current-sitin');
  });
}
function timeOut(sitId) {
  timeOutAndRefresh(sitId);
}

// ── ADD STUDENT ────────────────────────────────────────────
function openAddStudent() {
  ['asId','asFn','asLn','asMn','asEm','asPw'].forEach(id => document.getElementById(id).value = '');
  new bootstrap.Modal(document.getElementById('modalAddStudent')).show();
}
function submitAddStudent() {
  const id=document.getElementById('asId').value.trim(), fn=document.getElementById('asFn').value.trim();
  const ln=document.getElementById('asLn').value.trim(), mn=document.getElementById('asMn').value.trim();
  const co=document.getElementById('asCo').value, yr=document.getElementById('asYr').value;
  const em=document.getElementById('asEm').value.trim(), pw=document.getElementById('asPw').value;
  if (!id||!fn||!ln||!em||!pw) { alert('Please fill in all required fields.'); return; }
  fetch('admin_add_student.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id_number:id,first_name:fn,last_name:ln,middle_name:mn,course:co,year_level:yr,email:em,password:pw})})
    .then(r=>r.json()).then(d => {
      if (d.success) { bootstrap.Modal.getInstance(document.getElementById('modalAddStudent')).hide(); toast('Student added!'); fetchStudents(); }
      else { alert(d.message); }
    }).catch(() => {
      students.push({id:Date.now(),id_number:id,first_name:fn,last_name:ln,middle_name:mn,course:co,year_level:yr,email:em,remaining_sessions:30});
      bootstrap.Modal.getInstance(document.getElementById('modalAddStudent')).hide();
      toast('Student added!'); renderStudents();
    });
}

// ── EDIT STUDENT ───────────────────────────────────────────
function openEditStudent(s) {
  document.getElementById('editId').value  =s.id;   document.getElementById('editFn').value  =s.first_name;
  document.getElementById('editLn').value  =s.last_name; document.getElementById('editCo').value  =s.course;
  document.getElementById('editYr').value  =s.year_level; document.getElementById('editSess').value=s.remaining_sessions??30;
  new bootstrap.Modal(document.getElementById('modalEditStudent')).show();
}
function submitEditStudent() {
  const id=document.getElementById('editId').value, fn=document.getElementById('editFn').value.trim();
  const ln=document.getElementById('editLn').value.trim(), co=document.getElementById('editCo').value;
  const yr=document.getElementById('editYr').value, sess=document.getElementById('editSess').value;
  fetch('admin_edit_student.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id,first_name:fn,last_name:ln,course:co,year_level:yr,remaining_sessions:sess})})
    .then(r=>r.json()).then(d=>{ if(d.success){toast('Student updated!');fetchStudents();}else{alert(d.message);}})
    .catch(()=>{ const s=students.find(x=>x.id==id); if(s){s.first_name=fn;s.last_name=ln;s.course=co;s.year_level=yr;s.remaining_sessions=sess;} toast('Student updated!'); renderStudents(); });
  bootstrap.Modal.getInstance(document.getElementById('modalEditStudent')).hide();
}

// ── DELETE ─────────────────────────────────────────────────
function deleteStudent(id) {
  if (!confirm('Delete this student? This cannot be undone.')) return;
  fetch('admin_delete_student.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})})
    .then(r=>r.json()).then(()=>{toast('Student deleted.','danger');fetchStudents();})
    .catch(()=>{students=students.filter(s=>s.id!=id);toast('Student deleted.','danger');renderStudents();});
}

// ── RESET SESSIONS ─────────────────────────────────────────
function confirmResetAll() {
  if (!confirm('Reset all student sessions to 30?')) return;
  students.forEach(s => s.remaining_sessions = 30);
  toast('All sessions reset to 30.'); renderStudents();
}

// ── ANNOUNCEMENT ───────────────────────────────────────────
async function postAnnouncement() {
  const text = document.getElementById('annText').value.trim();
  if (!text) { alert('Please enter an announcement.'); return; }
  try {
    const d = await fetch('api/announcement_post.php', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ title:'', message: text })
    }).then(r => r.json());
    if (!d.success) { toast(d.message || 'Failed.', 'danger'); return; }
  } catch(e) { /* offline — add visually */ }
  const now = new Date();
  const label = `${now.getFullYear()}-${now.toLocaleString('en',{month:'short'})}-${String(now.getDate()).padStart(2,'0')}`;
  const item = document.createElement('div');
  item.className = 'ann-item';
  item.innerHTML = `<div class="ann-meta"><span class="ann-badge">CCS Admin</span> ${label}</div><div class="ann-text">${text}</div>`;
  document.getElementById('annList').prepend(item);
  document.getElementById('annText').value = '';
  toast('Announcement posted!');
  loadAnnouncementsDB();  // refresh the dedicated announcement view too
}

// ── LOGOUT ─────────────────────────────────────────────────
function confirmLogout() { new bootstrap.Modal(document.getElementById('modalLogout')).show(); }
function doLogout() { window.location.href = 'logout.php'; }

// ── INIT ───────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  fetchStudents();
  fetchSitInRecords();
  loadStats();
  // Set today's date on the map date picker
  const mapDate = document.getElementById('adminMapDate');
  if (mapDate) mapDate.valueAsDate = new Date();
});

// ══════════════════════════════════════════════════════════
// ── ADMIN PC MAP ──────────────────────────────────────────
// ══════════════════════════════════════════════════════════
let currentAdminLab = '524';
let adminPcData = null;
let adminResData = [];

async function loadAdminPcMap(lab, btn) {
  if (!lab) lab = currentAdminLab;
  currentAdminLab = lab;
  const date = document.getElementById('adminMapDate')?.value || new Date().toISOString().slice(0,10);

  // Update active button style
  if (btn) {
    document.querySelectorAll('.lab-btn-admin').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
  }

  const grid    = document.getElementById('adminPcGrid');
  const loading = document.getElementById('adminPcMapLoading');
  if (grid)    grid.style.display    = 'none';
  if (loading) loading.style.display = 'block';

  try {
    const r = await fetch(`api/lab_pc_status.php?lab=${encodeURIComponent(lab)}&date=${encodeURIComponent(date)}`);
    adminPcData = await r.json();
  } catch(e) {
    // Offline fallback — show all as available
    adminPcData = { lab, total_pcs:40, pc_map:{}, available_count:40, occupied_count:0, reserved_count:0 };
    for (let i=1;i<=40;i++) adminPcData.pc_map[i] = 'available';
  }

  renderAdminPcGrid();
  if (loading) loading.style.display = 'none';
  if (grid)    grid.style.display    = 'grid';
}

function renderAdminPcGrid() {
  const map   = adminPcData?.pc_map || {};
  const total = adminPcData?.total_pcs || 40;
  const grid  = document.getElementById('adminPcGrid');
  if (!grid) return;

  const labels = {
    available: 'Available',
    occupied:  'Occupied — In Use',
    reserved:  'Reserved (Approved)',
    pending:   'Pending Reservation'
  };

  let html = `<div class="admin-teacher-desk"><i class="fa-solid fa-chalkboard-user"></i>&nbsp; INSTRUCTOR'S DESK</div>`;
  for (let i = 1; i <= total; i++) {
    const st = map[i] || 'available';
    html += `<div class="admin-pc-item ${st}">
      <i class="fa-solid fa-desktop"></i>
      <span>PC${i}</span>
      <div class="pc-tip">${labels[st] || st}</div>
    </div>`;
  }
  grid.innerHTML = html;

  // Stats strip
  const statsEl = document.getElementById('adminLabStats');
  if (statsEl) {
    statsEl.innerHTML = `
      <div class="stat-card c3" style="padding:10px 14px;flex:0 0 auto;">
        <div class="stat-icon ic-green" style="width:34px;height:34px;font-size:13px"><i class="fa-solid fa-check"></i></div>
        <div class="stat-info"><label>Available</label><div class="stat-value" style="font-size:22px">${adminPcData?.available_count||0}</div></div>
      </div>
      <div class="stat-card" style="padding:10px 14px;flex:0 0 auto;border-color:rgba(239,68,68,.25)">
        <div class="stat-icon" style="width:34px;height:34px;font-size:13px;background:rgba(239,68,68,.15);color:#fca5a5"><i class="fa-solid fa-xmark"></i></div>
        <div class="stat-info"><label>Occupied</label><div class="stat-value" style="font-size:22px;color:#ef4444">${adminPcData?.occupied_count||0}</div></div>
      </div>
      <div class="stat-card" style="padding:10px 14px;flex:0 0 auto;border-color:rgba(245,158,11,.25)">
        <div class="stat-icon ic-gold" style="width:34px;height:34px;font-size:13px"><i class="fa-solid fa-calendar-check"></i></div>
        <div class="stat-info"><label>Reserved</label><div class="stat-value" style="font-size:22px;color:var(--gold)">${adminPcData?.reserved_count||0}</div></div>
      </div>`;
  }
}

// ══════════════════════════════════════════════════════════
// ── RESERVATION TABLE ─────────────────────────────────────
// ══════════════════════════════════════════════════════════
async function loadAdminReservations() {
  try {
    const r = await fetch('api/reservation_fetch.php?admin=1');
    adminResData = await r.json();
  } catch(e) { adminResData = []; }
  renderAdminReservations();
}

function renderAdminReservations() {
  const q    = (document.getElementById('resSearch')?.value || '').toLowerCase();
  const data = adminResData.filter(r =>
    (r.id_number + ' ' + (r.student_name||'') + ' ' + r.lab + ' ' + r.purpose + ' ' + r.status)
      .toLowerCase().includes(q)
  );
  const statusColors = {
    Pending:'#8b5cf6', Approved:'#10b981',
    Rejected:'#ef4444', Cancelled:'#64748b', Done:'#94a3b8'
  };
  const tbody = document.getElementById('resBody');
  if (!tbody) return;
  tbody.innerHTML = data.length ? data.map(r => `
    <tr>
      <td><span class="id-badge">#${r.id}</span></td>
      <td style="font-weight:600">${r.student_name || r.id_number}</td>
      <td><span style="background:rgba(201,168,76,.12);color:var(--gold2);padding:2px 8px;border-radius:5px;font-size:.72rem;font-weight:700;">Lab ${r.lab}</span></td>
      <td style="color:var(--text2)">PC ${r.pc_number}</td>
      <td style="color:var(--text2)">${r.date}</td>
      <td style="color:var(--text2)">${r.time_in}</td>
      <td style="color:var(--text2);max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${r.purpose}</td>
      <td><span style="font-size:.7rem;font-weight:700;padding:2px 8px;border-radius:20px;background:rgba(100,116,139,.1);color:${statusColors[r.status]||'#94a3b8'}">${r.status}</span></td>
      <td style="display:flex;gap:.3rem;flex-wrap:wrap;">
        ${r.status === 'Pending' ? `
          <button class="btn-a-success" onclick="approveRes(${r.id})"><i class="fa-solid fa-check"></i> Approve</button>
          <button class="btn-a-danger"  onclick="rejectRes(${r.id})"><i class="fa-solid fa-xmark"></i> Reject</button>
        ` : `<span style="color:var(--text3);font-size:.75rem">${r.status}</span>`}
      </td>
    </tr>`).join('')
  : '<tr><td colspan="9" class="no-data">No reservation requests yet.</td></tr>';
}

async function approveRes(id) {
  try {
    await fetch('api/reservation_fetch.php', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ action:'approve', id })
    });
    toast('Reservation approved!');
  } catch(e) { toast('Could not connect.','danger'); }
  loadAdminReservations();
  loadAdminPcMap(currentAdminLab);
}

async function rejectRes(id) {
  try {
    await fetch('api/reservation_fetch.php', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ action:'reject', id })
    });
    toast('Reservation rejected.', 'warning');
  } catch(e) { toast('Could not connect.','danger'); }
  loadAdminReservations();
  loadAdminPcMap(currentAdminLab);
}

// ══════════════════════════════════════════════════════════
// ── LEADERBOARD ───────────────────────────────────────────
// ══════════════════════════════════════════════════════════
async function loadAdminLeaderboard() {
  const lbBody = document.getElementById('adminLbBody');
  const rwBody = document.getElementById('rwLbBody');
  try {
    const data = await fetch('api/leaderboard.php').then(r => r.json());
    const medals = ['🥇','🥈','🥉'];

    if (lbBody) {
      lbBody.innerHTML = data.length ? data.map((s, i) => `
        <tr class="${i < 3 ? 'lb-rank-' + (i+1) : ''}">
          <td style="font-size:1.1rem">${medals[i] || '#'+(i+1)}</td>
          <td style="font-weight:600">${s.first_name} ${s.last_name}
            <span style="font-size:.7rem;color:var(--text3);margin-left:4px">${s.id_number}</span>
          </td>
          <td style="color:var(--text2)">${s.course} Y${s.year_level}</td>
          <td style="color:var(--text2)">${s.total_sitins}</td>
          <td style="font-weight:800;color:var(--gold2)">${s.points}</td>
          <td>
            <button class="btn-a-success" style="font-size:.7rem"
              onclick="document.getElementById('rwIdNum').value='${s.id_number}';showView('rewards')">
              <i class="fa-solid fa-plus"></i> Award
            </button>
          </td>
        </tr>`).join('')
      : '<tr><td colspan="6" class="no-data">No data yet.</td></tr>';
    }

    if (rwBody) {
      rwBody.innerHTML = data.slice(0, 10).map((s, i) => `
        <tr class="${i < 3 ? 'lb-rank-' + (i+1) : ''}">
          <td>${medals[i] || '#'+(i+1)}</td>
          <td style="font-weight:600">${s.first_name} ${s.last_name}</td>
          <td style="color:var(--text2)">${s.course}</td>
          <td style="color:var(--text2)">${s.total_sitins}</td>
          <td style="font-weight:800;color:var(--gold2)">${s.points}</td>
        </tr>`).join('') || '<tr><td colspan="5" class="no-data">No data yet.</td></tr>';
    }
  } catch(e) {
    if (lbBody) lbBody.innerHTML = '<tr><td colspan="6" class="no-data">Could not load leaderboard.</td></tr>';
    if (rwBody) rwBody.innerHTML = '<tr><td colspan="5" class="no-data">Could not load.</td></tr>';
  }
}

// ══════════════════════════════════════════════════════════
// ── ANALYTICS ─────────────────────────────────────────────
// ══════════════════════════════════════════════════════════
let reportChartInst = null, purposeChart2Inst = null, labChartInst = null;

async function loadAnalytics() {
  try {
    const [summary, purposeData, labData, dailyData] = await Promise.all([
      fetch('api/reports.php?type=summary').then(r => r.json()),
      fetch('api/reports.php?type=by_purpose').then(r => r.json()),
      fetch('api/reports.php?type=by_lab').then(r => r.json()),
      fetch('api/reports.php?type=daily').then(r => r.json()),
    ]);
    const rEl = document.getElementById('anaRegistered');
    const tEl = document.getElementById('anaTotalSitins');
    const aEl = document.getElementById('anaActive');
    if (rEl) rEl.textContent = summary.registered  ?? '—';
    if (tEl) tEl.textContent = summary.total_sitin  ?? '—';
    if (aEl) aEl.textContent = summary.active       ?? '—';
    drawReportChart(dailyData, 'daily');
    drawPurposeChart2(purposeData);
    drawLabChart(labData);
  } catch(e) { /* DB not ready yet — charts stay empty */ }
}

async function loadReport(type) {
  try {
    const data = await fetch(`api/reports.php?type=${type}`).then(r => r.json());
    drawReportChart(data, type);
  } catch(e) {}
}

function drawReportChart(data, type) {
  const el = document.getElementById('reportChart'); if (!el) return;
  const labels = data.map(d => d.day || d.week || d.month || '');
  const vals   = data.map(d => d.count || 0);
  if (reportChartInst) reportChartInst.destroy();
  reportChartInst = new Chart(el.getContext('2d'), {
    type: 'bar',
    data: { labels, datasets: [{ label:'Sit-ins', data:vals, backgroundColor:'rgba(59,130,246,.7)', borderRadius:5 }] },
    options: { responsive:true, plugins:{legend:{display:false}},
      scales: { x:{ticks:{color:'#94a8cc',font:{size:10}}}, y:{ticks:{color:'#94a8cc'},beginAtZero:true} } }
  });
}

function drawPurposeChart2(data) {
  const el = document.getElementById('purposeChart2'); if (!el) return;
  if (purposeChart2Inst) purposeChart2Inst.destroy();
  const colors = ['#3b82f6','#ef4444','#f97316','#eab308','#22c55e','#8b5cf6','#06b6d4'];
  purposeChart2Inst = new Chart(el.getContext('2d'), {
    type: 'doughnut',
    data: { labels: data.map(d=>d.purpose), datasets:[{ data:data.map(d=>d.count), backgroundColor:colors, borderWidth:2, borderColor:'#0d1b35' }] },
    options: { responsive:true, cutout:'55%',
      plugins:{ legend:{ position:'bottom', labels:{color:'#94a8cc',font:{size:9},padding:8,boxWidth:9} } } }
  });
}

function drawLabChart(data) {
  const el = document.getElementById('labChart'); if (!el) return;
  if (labChartInst) labChartInst.destroy();
  labChartInst = new Chart(el.getContext('2d'), {
    type: 'bar',
    data: { labels: data.map(d=>'Lab '+d.lab), datasets:[{ label:'Sit-ins', data:data.map(d=>d.count), backgroundColor:['rgba(201,168,76,.7)','rgba(59,130,246,.7)','rgba(16,185,129,.7)','rgba(245,158,11,.7)'], borderRadius:5 }] },
    options: { indexAxis:'y', responsive:true, plugins:{legend:{display:false}},
      scales:{ x:{ticks:{color:'#94a8cc'}}, y:{ticks:{color:'#94a8cc'}} } }
  });
}

// ══════════════════════════════════════════════════════════
// ── ANNOUNCEMENT (saves to DB) ────────────────────────────
// ══════════════════════════════════════════════════════════
async function postAnnouncementDB() {
  const title = document.getElementById('annTitleNew')?.value.trim() || '';
  const text  = document.getElementById('annTextNew')?.value.trim() || '';
  if (!text) { alert('Please enter a message.'); return; }
  try {
    const d = await fetch('api/announcement_post.php', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ title, message: text })
    }).then(r => r.json());
    if (d.success) {
      toast('Announcement posted!');
      if (document.getElementById('annTitleNew')) document.getElementById('annTitleNew').value = '';
      if (document.getElementById('annTextNew'))  document.getElementById('annTextNew').value  = '';
      loadAnnouncementsDB();
    } else { toast(d.message || 'Failed to post.', 'danger'); }
  } catch(e) {
    // Offline fallback: add visually
    const now = new Date();
    const item = document.createElement('div');
    item.className = 'ann-item';
    item.innerHTML = `<div class="ann-meta"><span class="ann-badge">CCS Admin</span> ${now.toLocaleDateString()}</div><div class="ann-text">${text}</div>`;
    document.getElementById('annListNew')?.prepend(item);
    if (document.getElementById('annTextNew')) document.getElementById('annTextNew').value = '';
    toast('Announcement posted (offline mode)!');
  }
}

async function loadAnnouncementsDB() {
  const el = document.getElementById('annListNew');
  if (!el) return;
  try {
    const data = await fetch('api/announcement_post.php').then(r => r.json());
    el.innerHTML = data.length
      ? data.map(a => `
          <div class="ann-item">
            <div class="ann-meta"><span class="ann-badge">CCS Admin</span> ${(a.created_at||'').slice(0,10)}</div>
            ${a.title ? `<div style="font-size:.8rem;font-weight:700;color:var(--text1);margin-bottom:.2rem;">${a.title}</div>` : ''}
            <div class="ann-text">${a.message}</div>
          </div>`).join('')
      : '<div class="ann-item"><div class="ann-text ann-empty">No announcements yet.</div></div>';
  } catch(e) { /* leave as-is */ }
}

// ══════════════════════════════════════════════════════════
// ── REWARDS / POINTS ──────────────────────────────────────
// ══════════════════════════════════════════════════════════
async function awardPoints() {
  const id_number = (document.getElementById('rwIdNum')?.value || '').trim();
  const points    = parseInt(document.getElementById('rwPoints')?.value || 0);
  const reason    = (document.getElementById('rwReason')?.value || '').trim();
  if (!id_number || !points) { alert('Please fill in Student ID and Points.'); return; }
  try {
    const d = await fetch('api/leaderboard.php', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ action:'add_points', id_number, points, reason })
    }).then(r => r.json());
    if (d.success) {
      toast(`+${points} points awarded to ${id_number}!`);
      loadAdminLeaderboard();
    } else { toast(d.message || 'Failed.', 'danger'); }
  } catch(e) { toast('Could not connect to DB.', 'danger'); }
}

async function resetAllSessionsDB() {
  if (!confirm('Reset ALL student sessions back to 30? This cannot be undone.')) return;
  try {
    const d = await fetch('api/leaderboard.php', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ action:'reset_sessions' })
    }).then(r => r.json());
    if (d.success) {
      toast('All sessions reset to 30!');
      fetchStudents();
    } else { toast('Failed to reset.', 'danger'); }
  } catch(e) {
    students.forEach(s => s.remaining_sessions = 30);
    toast('Sessions reset (offline mode).'); renderStudents();
  }
}

// ══════════════════════════════════════════════════
// ── REPORTS LIST ─────────────────────────────────
// ══════════════════════════════════════════════════
async function loadReportsList() {
  const limit     = document.getElementById('rptLimit').value || '10';
  const date_from = document.getElementById('rptFrom').value || '';
  const date_to   = document.getElementById('rptTo').value   || '';
  const tbody     = document.getElementById('rptBody');
  const info      = document.getElementById('rptInfo');
  tbody.innerHTML = '<tr><td colspan="12" class="no-data"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</td></tr>';
  try {
    const params = new URLSearchParams({ type:'sitin_list', limit, date_from, date_to });
    const data   = await fetch(`api/reports.php?${params}`).then(r => r.json());
    if (!data.length) {
      tbody.innerHTML = '<tr><td colspan="12" class="no-data">No records found for the selected filter.</td></tr>';
      info.textContent = 'No records found.';
      return;
    }
    const fmtTime = dt => { if (!dt) return '—'; const d=new Date(dt); return d.toLocaleTimeString('en',{hour:'2-digit',minute:'2-digit'}); };
    const fmtDate = dt => { if (!dt) return '—'; const d=new Date(dt); return d.toLocaleDateString('en',{month:'short',day:'numeric',year:'numeric'}); };
    const duration = (s,e) => {
      if (!s||!e) return '—';
      const diff = (new Date(e)-new Date(s))/1000;
      if (diff<0) return '—';
      const h=Math.floor(diff/3600), m=Math.floor((diff%3600)/60);
      return (h>0?h+'h ':'')+m+'m';
    };
    tbody.innerHTML = data.map((r,i) => `
      <tr>
        <td style="color:var(--text3);">${i+1}</td>
        <td><span class="id-badge">${r.id_number||'—'}</span></td>
        <td style="font-weight:600;">${(r.name||'—').trim()}</td>
        <td style="color:var(--text2);font-size:.72rem;">${r.course||'—'} ${r.year_level?'Yr'+r.year_level:''}</td>
        <td>${r.purpose||'—'}</td>
        <td><span style="background:rgba(201,168,76,.12);color:var(--gold2);padding:2px 8px;border-radius:5px;font-size:.72rem;font-weight:700;">${r.lab||'—'}</span></td>
        <td>${r.pc_number||'—'}</td>
        <td>${fmtDate(r.created_at)}</td>
        <td>${fmtTime(r.created_at)}</td>
        <td>${fmtTime(r.timed_out_at)}</td>
        <td>${duration(r.created_at,r.timed_out_at)}</td>
        <td><span class="badge-${r.status==='Active'?'active':'done'}">${r.status}</span></td>
      </tr>`).join('');
    info.textContent = `Showing ${data.length} record${data.length!==1?'s':''}.`;
  } catch(e) {
    tbody.innerHTML = '<tr><td colspan="12" class="no-data">Could not load report data.</td></tr>';
  }
}

function openPrintReport() {
  const limit     = document.getElementById('rptLimit').value || '10';
  const date_from = document.getElementById('rptFrom').value || '';
  const date_to   = document.getElementById('rptTo').value   || '';
  const params    = new URLSearchParams({ limit, date_from, date_to });
  window.open(`reports_print.php?${params}`, '_blank');
}

// ══════════════════════════════════════════════════
// ── FEEDBACK REPORTS ──────────────────────────────
// ══════════════════════════════════════════════════
let allFeedback = [];

async function loadFeedback() {
  const tbody = document.getElementById('fbBody');
  if (!tbody) return;
  tbody.innerHTML = '<tr><td colspan="8" class="no-data"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</td></tr>';
  try {
    allFeedback = await fetch('api/reports.php?type=feedback').then(r => r.json());
    filterFeedback();
  } catch(e) {
    allFeedback = [];
    tbody.innerHTML = '<tr><td colspan="8" class="no-data">No feedback data available.</td></tr>';
  }
}

function filterFeedback() {
  const q     = (document.getElementById('fbSearch')?.value || '').toLowerCase();
  const tbody = document.getElementById('fbBody');
  const info  = document.getElementById('fbInfo');
  if (!tbody) return;

  const data = allFeedback.filter(f =>
    (f.id_number+' '+(f.name||'')+' '+(f.message||'')+' '+(f.lab||'')).toLowerCase().includes(q)
  );

  if (!data.length) {
    tbody.innerHTML = '<tr><td colspan="8" class="no-data">No feedback data available yet.</td></tr>';
    if (info) info.textContent = '';
    return;
  }

  const stars = n => '★'.repeat(Math.min(5,Math.max(0,n||0)))+'☆'.repeat(5-Math.min(5,Math.max(0,n||0)));
  const starColor = n => n>=4?'#10b981':n>=3?'#f59e0b':'#ef4444';
  tbody.innerHTML = data.map((f,i) => `
    <tr>
      <td style="color:var(--text3);">${i+1}</td>
      <td>
        <div style="font-weight:600;font-size:.8rem;">${(f.name||'Unknown').trim()}</div>
        <div style="font-size:.7rem;color:var(--text3);">${f.id_number||''}</div>
      </td>
      <td style="font-size:.72rem;color:var(--text2);">${f.course||'—'}</td>
      <td><span style="background:rgba(201,168,76,.12);color:var(--gold2);padding:2px 7px;border-radius:5px;font-size:.72rem;font-weight:700;">${f.lab||'—'}</span></td>
      <td style="font-size:.78rem;">${f.purpose||'—'}</td>
      <td style="color:${starColor(f.rating)};font-size:.88rem;letter-spacing:1px;" title="${f.rating}/5 stars">${stars(f.rating)}</td>
      <td style="font-size:.78rem;max-width:260px;">${f.message ? f.message.substring(0,120)+(f.message.length>120?'…':'') : '<em style="color:var(--text3)">No comment</em>'}</td>
      <td style="font-size:.72rem;color:var(--text3);">${f.created_at ? new Date(f.created_at).toLocaleDateString('en',{month:'short',day:'numeric',year:'numeric'}) : '—'}</td>
    </tr>`).join('');
  if (info) info.textContent = `Showing ${data.length} feedback entr${data.length!==1?'ies':'y'}.`;
}

// ══════════════════════════════════════════════════
// ── LEADERBOARD (admin) ───────────────────────────
// ══════════════════════════════════════════════════
async function loadAdminLeaderboard() {
  const lbBody = document.getElementById('adminLbBody');
  const rwBody = document.getElementById('rwLbBody');
  const loading = '<tr><td colspan="6" class="no-data"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</td></tr>';
  if (lbBody) lbBody.innerHTML = loading;
  if (rwBody) rwBody.innerHTML = loading;
  try {
    const data = await fetch('api/leaderboard.php').then(r => r.json());
    const medals = ['🥇','🥈','🥉'];
    const rowsHtml = data.length
      ? data.map((s,i) => `
          <tr class="${i<3?'lb-rank-'+(i+1):''}">
            <td><span style="font-size:1rem;">${medals[i]||'#'+(i+1)}</span></td>
            <td>
              <div style="font-weight:600;">${s.first_name} ${s.last_name}</div>
              <div style="font-size:.7rem;color:var(--text3);">${s.id_number}</div>
            </td>
            <td><span style="background:rgba(59,130,246,.12);color:#60a5fa;padding:2px 7px;border-radius:5px;font-size:.7rem;font-weight:700;">${s.course}</span></td>
            <td style="font-family:'Bebas Neue',cursive;font-size:1.1rem;color:var(--text1);">${s.total_sitins}</td>
            <td style="font-family:'Bebas Neue',cursive;font-size:1.1rem;color:var(--gold2);">${s.points}</td>
            <td>
              <button class="btn-a-success" onclick="quickAwardPoints('${s.id_number}')">
                <i class="fa-solid fa-plus"></i> Points
              </button>
            </td>
          </tr>`).join('')
      : '<tr><td colspan="6" class="no-data">No student data yet.</td></tr>';

    if (lbBody) lbBody.innerHTML = rowsHtml;
    if (rwBody) rwBody.innerHTML = data.length
      ? data.map((s,i) => `
          <tr>
            <td>${medals[i]||'#'+(i+1)}</td>
            <td style="font-weight:600;">${s.first_name} ${s.last_name}</td>
            <td style="font-size:.72rem;color:var(--text2);">${s.course}</td>
            <td>${s.total_sitins}</td>
            <td style="font-weight:800;color:var(--gold2);">${s.points}</td>
          </tr>`).join('')
      : '<tr><td colspan="5" class="no-data">No data yet.</td></tr>';
  } catch(e) {
    if (lbBody) lbBody.innerHTML = '<tr><td colspan="6" class="no-data">Could not load leaderboard.</td></tr>';
    if (rwBody) rwBody.innerHTML = '<tr><td colspan="5" class="no-data">Could not load.</td></tr>';
  }
}

function quickAwardPoints(id_number) {
  if (document.getElementById('rwIdNum')) {
    document.getElementById('rwIdNum').value = id_number;
    showView('rewards');
  }
}

// ══════════════════════════════════════════════════
// ── RESERVATION (admin) ───────────────────────────
// ══════════════════════════════════════════════════
let adminReservations  = [];
let currentAdminLab    = '524';

async function loadAdminReservations() {
  try {
    const data = await fetch('api/reservation_fetch.php?admin=1').then(r => r.json());
    adminReservations = Array.isArray(data) ? data : [];
    renderAdminReservations();
  } catch(e) { adminReservations = []; renderAdminReservations(); }
}

function renderAdminReservations() {
  const tbody = document.getElementById('resBody');
  if (!tbody) return;
  const q    = (document.getElementById('resSearch')?.value || '').toLowerCase();
  const data = adminReservations.filter(r =>
    (r.id_number+' '+(r.student_name||'')+' '+r.lab+' '+r.status).toLowerCase().includes(q)
  );
  if (!data.length) {
    tbody.innerHTML = '<tr><td colspan="9" class="no-data">No reservations found.</td></tr>'; return;
  }
  const statusColor = { Pending:'#8b5cf6', Approved:'#10b981', Rejected:'#ef4444', Cancelled:'#64748b', Done:'#64748b' };
  tbody.innerHTML = data.map(r => `
    <tr>
      <td style="color:var(--text3);">${r.id}</td>
      <td>
        <div style="font-weight:600;">${r.student_name || r.id_number}</div>
        <div style="font-size:.7rem;color:var(--text3);">${r.id_number}</div>
      </td>
      <td><span style="background:rgba(201,168,76,.12);color:var(--gold2);padding:2px 7px;border-radius:5px;font-size:.72rem;font-weight:700;">${r.lab}</span></td>
      <td>${r.pc_number||'—'}</td>
      <td>${r.date||'—'}</td>
      <td>${r.time_in||'—'}</td>
      <td style="font-size:.78rem;">${r.purpose||'—'}</td>
      <td><span style="font-size:.72rem;font-weight:700;padding:2px 8px;border-radius:20px;background:rgba(100,116,139,.12);color:${statusColor[r.status]||'#64748b'}">${r.status}</span></td>
      <td style="display:flex;gap:.3rem;">
        ${r.status==='Pending'
          ? `<button class="btn-a-success" onclick="adminResAction(${r.id},'approve')"><i class="fa-solid fa-check"></i> Approve</button>
             <button class="btn-a-danger"  onclick="adminResAction(${r.id},'reject')"><i class="fa-solid fa-xmark"></i> Reject</button>`
          : `<span style="font-size:.72rem;color:var(--text3);">${r.status}</span>`}
      </td>
    </tr>`).join('');
}

async function adminResAction(id, action) {
  try {
    const d = await fetch('api/reservation_fetch.php', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ action, id })
    }).then(r => r.json());
    if (d.success) {
      toast(action==='approve'?'Reservation approved!':'Reservation rejected.', action==='approve'?'success':'warning');

      // Notify student
      const res = adminReservations.find(r => r.id === id);
      if (res) {
        await fetch('api/notifications.php', {
          method:'POST', headers:{'Content-Type':'application/json'},
          body: JSON.stringify({
            action:'send', target: res.id_number,
            type:  action==='approve'?'success':'danger',
            title: action==='approve'?'Reservation Approved ✅':'Reservation Rejected ❌',
            message: `Your reservation for Lab ${res.lab} PC ${res.pc_number} on ${res.date} has been ${action==='approve'?'approved':'rejected'}.`
          })
        });
      }
      loadAdminReservations();
      loadAdminPcMap(currentAdminLab);
    } else { toast(d.message||'Action failed.','danger'); }
  } catch(e) { toast('DB unavailable.','danger'); }
}

async function loadAdminPcMap(lab, btn) {
  currentAdminLab = lab;
  document.querySelectorAll('.lab-btn-admin').forEach(b => b.classList.remove('active'));
  if (btn) btn.classList.add('active');
  else {
    document.querySelectorAll('.lab-btn-admin').forEach(b => {
      if (b.textContent.trim() === 'Lab '+lab) b.classList.add('active');
    });
  }
  const grid    = document.getElementById('adminPcGrid');
  const loading = document.getElementById('adminPcMapLoading');
  const stats   = document.getElementById('adminLabStats');
  if (!grid) return;
  grid.style.display = 'none';
  if (loading) loading.style.display = 'block';

  const dateEl  = document.getElementById('adminMapDate');
  const date    = dateEl?.value || new Date().toISOString().slice(0,10);
  if (dateEl && !dateEl.value) dateEl.value = date;

  try {
    const d = await fetch(`lab_pc_status.php?lab=${lab}&date=${date}`).then(r => r.json());
    if (d.error) throw new Error(d.error);

    const pcMap   = d.pc_map || {};
    const total   = d.total_pcs || 40;
    const colors  = {
      available:'rgba(34,197,94,.18)', occupied:'rgba(239,68,68,.2)',
      reserved:'rgba(245,158,11,.2)',  pending:'rgba(139,92,246,.2)'
    };
    const borders = {
      available:'rgba(34,197,94,.6)',  occupied:'rgba(239,68,68,.6)',
      reserved:'rgba(245,158,11,.6)',  pending:'rgba(139,92,246,.6)'
    };
    const texts = {
      available:'#22c55e', occupied:'#ef4444', reserved:'#f59e0b', pending:'#8b5cf6'
    };

    let html = `<div class="admin-pc-item" style="background:rgba(59,130,246,.12);border:1px solid rgba(59,130,246,.4);grid-column:1/-1;display:flex;align-items:center;justify-content:center;gap:.5rem;font-size:.78rem;font-weight:700;color:#93c5fd;padding:.6rem;border-radius:8px;margin-bottom:.25rem;">
      <i class="fa-solid fa-chalkboard-user"></i> INSTRUCTOR'S DESK</div>`;
    for (let i=1; i<=total; i++) {
      const st = pcMap[i] || 'available';
      html += `<div class="admin-pc-item" style="background:${colors[st]};border:1.5px solid ${borders[st]};border-radius:8px;padding:.6rem .3rem;text-align:center;cursor:default;">
        <i class="fa-solid fa-desktop" style="font-size:.85rem;color:${texts[st]};display:block;margin-bottom:3px;"></i>
        <div style="font-size:.62rem;font-weight:700;color:${texts[st]};">PC${i}</div>
        <div class="pc-tip">${st.charAt(0).toUpperCase()+st.slice(1)}</div>
      </div>`;
    }
    grid.innerHTML = html;
    grid.style.display = 'grid';
    if (loading) loading.style.display = 'none';
    if (stats) stats.innerHTML = `
      <span style="background:rgba(34,197,94,.1);color:#22c55e;padding:3px 10px;border-radius:6px;font-size:.75rem;font-weight:700;"><i class="fa-solid fa-circle" style="font-size:.45rem;margin-right:4px;"></i>${d.available_count} Available</span>
      <span style="background:rgba(239,68,68,.1);color:#ef4444;padding:3px 10px;border-radius:6px;font-size:.75rem;font-weight:700;"><i class="fa-solid fa-circle" style="font-size:.45rem;margin-right:4px;"></i>${d.occupied_count} Occupied</span>
      <span style="background:rgba(245,158,11,.1);color:#f59e0b;padding:3px 10px;border-radius:6px;font-size:.75rem;font-weight:700;"><i class="fa-solid fa-circle" style="font-size:.45rem;margin-right:4px;"></i>${d.reserved_count} Reserved</span>`;
  } catch(e) {
    if (loading) loading.style.display = 'none';
    grid.style.display = 'grid';
    grid.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--text3);grid-column:1/-1;">Could not load PC map. Is lab_pc_status.php accessible?</div>';
  }
}

// ══════════════════════════════════════════════════
// ── NOTIFICATIONS (admin badge) ───────────────────
// ══════════════════════════════════════════════════
async function pollAdminNotifications() {
  // Admin uses leaderboard/student activity as "notifications" — just a badge of pending reservations
  try {
    const data = adminReservations || [];
    const pending = data.filter(r => r.status === 'Pending').length;
    const badge   = document.getElementById('adminNotifBadge');
    if (badge) {
      badge.textContent = pending;
      badge.style.display = pending > 0 ? 'inline-flex' : 'none';
    }
  } catch(e) {}
}

// ══════════════════════════════════════════════════
// ── AUTO INIT ────────────────────────────────────
// ══════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => {
  // Set today's date in admin map date picker
  const adminMapDate = document.getElementById('adminMapDate');
  if (adminMapDate && !adminMapDate.value) adminMapDate.value = new Date().toISOString().slice(0,10);

  // Load initial data
  fetchStudents();
  fetchSitInRecords();
  loadStats();
  loadAnnouncementsDB();

  // Auto-refresh every 30 seconds
  setInterval(() => {
    fetchSitInRecords();
    loadStats();
    if (adminReservations.length > 0 || document.getElementById('view-reservation')?.classList.contains('active')) {
      loadAdminReservations();
    }
    pollAdminNotifications();
  }, 30000);
});

function confirmLogout() {
  if (confirm('Are you sure you want to log out?')) window.location.href='logout.php';
}
</script>
</body>
</html>