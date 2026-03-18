document.addEventListener('DOMContentLoaded', function() {
    // Password strength
    const pw = document.getElementById('password');
    const bars = ['s1','s2','s3','s4'].map(id => document.getElementById(id));
    const colors = ['#ef4444','#f97316','#eab308','#22c55e'];
    
    if (pw && bars.length === 4) {
        pw.addEventListener('input', () => {
            const v = pw.value;
            let score = 0;
            if (v.length >= 8) score++;
            if (/[A-Z]/.test(v)) score++;
            if (/[0-9]/.test(v)) score++;
            if (/[^A-Za-z0-9]/.test(v)) score++;
            bars.forEach((b, i) => {
                b.style.background = i < score ? colors[score-1] : '#e5e7eb';
            });
        });
    }

    // Login form
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            fetch('login.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('Welcome, ' + data.user.name + '!');
                    window.location.href = 'dashboard.php';
                } else {
                    alert(data.message);
                }
            })
            .catch(err => alert('Server error. Check XAMPP.'));
        });
    }

    // Register form
    const registerForm = document.getElementById('register-form');
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const password = document.getElementById('password').value;
            const repeatPassword = document.getElementById('repeatPassword').value;
            
            if (password !== repeatPassword) {
                alert('Passwords do not match!');
                return;
            }
            
            const formData = new FormData(this);
            fetch('register.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('Account created! Please login.');
                    window.location.href = 'index.html';
                } else {
                    alert(data.message);
                }
            })
            .catch(err => alert('Server error. Check XAMPP.'));
        });
    }
});

/* ============================================================
   CCS Sit-in Portal – Student Dashboard
   script.js
   ============================================================ */

/* ── STATE ──────────────────────────────────────────────────── */
const S = {
  first: 'Kimmy', middle: 'D.', last: 'Yammy',
  id: '123123123',
  email: 'Yammy@gmail.com',
  address: 'Secret',
  course: 'BSIT', year: '3rd Year',
  session: 30, totalSession: 30,
};

let historyData = [];   // {id, name, purpose, lab, login, logout, date}
let reservations = [];  // {lab, purpose, date, time, idx}
let histPage     = 1;
let pendingDelIdx = null;

/* ── TOAST ──────────────────────────────────────────────────── */
function showToast(msg, icon = 'fa-circle-check', color = null) {
  const toastEl = document.getElementById('liveToast');
  document.getElementById('toastMsg').textContent  = msg;
  const ic = document.getElementById('toastIcon');
  ic.className = 'fa-solid ' + icon;
  ic.style.color = color || '';
  const toast = bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 3200 });
  toast.show();
}

/* ── TABS ───────────────────────────────────────────────────── */
function switchTab(tab) {
  document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
  document.querySelectorAll('[data-tab]').forEach(a => a.classList.remove('active'));
  const view = document.getElementById('view-' + tab);
  if (view) view.classList.add('active');
  const link = document.querySelector('[data-tab="' + tab + '"]');
  if (link) link.classList.add('active');
  if (tab === 'history') renderHistory();
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

/* ── NOTIFICATIONS ──────────────────────────────────────────── */
function clearNotifs() {
  document.getElementById('notifItems').innerHTML =
    '<div class="notif-empty">No new notifications</div>';
  document.getElementById('notifBadge').style.display = 'none';
  showToast('All notifications cleared');
  // close the dropdown
  const dd = bootstrap.Dropdown.getInstance(document.getElementById('notifToggle'));
  if (dd) dd.hide();
}

/* ── LOGOUT ─────────────────────────────────────────────────── */
function confirmLogout() {
  const modal = new bootstrap.Modal(document.getElementById('modalLogout'));
  modal.show();
}
function doLogout() {
  bootstrap.Modal.getInstance(document.getElementById('modalLogout')).hide();
  showToast('Logging out…', 'fa-right-from-bracket');
  setTimeout(() => {
    document.body.innerHTML = `
      <div style="display:flex;align-items:center;justify-content:center;
        height:100vh;flex-direction:column;gap:16px;
        background:#eef1f8;font-family:'Plus Jakarta Sans',sans-serif;color:#3d4f75">
        <i class="fa-solid fa-circle-check" style="font-size:3rem;color:#16a34a"></i>
        <strong style="font-size:1.1rem">You have been logged out.</strong>
        <a href="javascript:location.reload()" style="color:#0d2255;font-weight:700">← Back to Login</a>
      </div>`;
  }, 1600);
}

/* ── SESSION UI ─────────────────────────────────────────────── */
function updateSessionUI() {
  const pct = ((S.session / S.totalSession) * 100).toFixed(1);
  ['sNum', 'profSessNum'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.textContent = S.session;
  });
  const fill = document.getElementById('sessFill');
  if (fill) fill.style.width = pct + '%';
  const rSess = document.getElementById('rSess');
  if (rSess) rSess.value = S.session;
  const tipSess = document.getElementById('tipSess');
  if (tipSess) tipSess.textContent = S.session;
}

/* ── PROFILE DISPLAY ────────────────────────────────────────── */
function refreshDisplay() {
  const full = [S.first, S.middle, S.last].filter(Boolean).join(' ');
  const yearNum = S.year.replace(/\D/g, '') || '?';

  setEl('dName',        full);
  setEl('profName',     full);
  setEl('welcomeName',  full);
  setEl('dBadge',       `${S.course} · Year ${yearNum}`);
  setEl('profRole',     `${S.course} · ${S.year}`);
  setEl('dId',          S.id);
  setEl('dCourse',      S.course);
  setEl('dYear',        S.year);
  setEl('dEmail',       S.email);
  setEl('dAddr',        S.address);
  setVal('rName',       full);
}

function setEl(id, val) {
  const el = document.getElementById(id);
  if (el) el.textContent = val;
}
function setVal(id, val) {
  const el = document.getElementById(id);
  if (el) el.value = val;
}

/* ── SAVE PROFILE ───────────────────────────────────────────── */
function saveProfile() {
  const pw  = document.getElementById('pPw').value;
  const pw2 = document.getElementById('pPw2').value;
  if (pw && pw !== pw2) {
    showToast('Passwords do not match!', 'fa-circle-xmark', '#ef4444');
    return;
  }
  S.first   = document.getElementById('pFn').value.trim()  || S.first;
  S.last    = document.getElementById('pLn').value.trim()  || S.last;
  S.middle  = document.getElementById('pMn').value.trim();
  S.email   = document.getElementById('pEm').value.trim();
  S.address = document.getElementById('pAd').value.trim();
  S.course  = document.getElementById('pCo').value;
  S.year    = document.getElementById('pYr').value;

  document.getElementById('pPw').value  = '';
  document.getElementById('pPw2').value = '';

  refreshDisplay();
  showToast('Profile updated successfully!');
}

/* ── AVATAR UPLOAD ──────────────────────────────────────────── */
function handleAvatar(input) {
  const file = input.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = ev => {
    const src = ev.target.result;
    ['mainAvatar', 'profAvatar'].forEach(id => {
      const img = document.getElementById(id);
      if (img) img.src = src;
    });
    showToast('Profile photo updated!');
  };
  reader.readAsDataURL(file);
}

/* ── RESERVATION ────────────────────────────────────────────── */
function submitReservation() {
  const purpose = document.getElementById('rPurpose').value.trim();
  const lab     = document.getElementById('rLab').value;
  const date    = document.getElementById('rDate').value;
  const time    = document.getElementById('rTime').value;

  if (!purpose) { showToast('Please enter a purpose', 'fa-circle-xmark', '#ef4444'); return; }
  if (!lab)     { showToast('Please select a laboratory', 'fa-circle-xmark', '#ef4444'); return; }
  if (!date)    { showToast('Please pick a date', 'fa-circle-xmark', '#ef4444'); return; }
  if (!time)    { showToast('Please enter a time', 'fa-circle-xmark', '#ef4444'); return; }

  reservations.push({ lab, purpose, date, time, idx: Date.now() });
  renderMyReservations();

  document.getElementById('rPurpose').value = '';
  document.getElementById('rLab').value     = '';
  document.getElementById('rDate').value    = todayStr();
  document.getElementById('rTime').value    = '';

  showSuccessModal(
    'Reservation Submitted!',
    `${lab} · ${fmtDate(date)} at ${fmtTime(time)}\n\nYour reservation has been submitted for approval.`
  );
}

function renderMyReservations() {
  const el = document.getElementById('myResList');
  if (!el) return;
  if (!reservations.length) {
    el.innerHTML = '<p class="text-center" style="font-size:.82rem;color:var(--text3);font-style:italic;padding:8px 0">No reservations yet.</p>';
    return;
  }
  el.innerHTML = reservations.map((r, i) => `
    <div class="res-row">
      <div>
        <div class="res-row-lab">
          <i class="fa-solid fa-computer me-1" style="color:var(--navy3)"></i>${r.lab}
        </div>
        <div class="res-row-meta">${r.purpose} &nbsp;·&nbsp; ${fmtDate(r.date)} &nbsp;·&nbsp; ${fmtTime(r.time)}</div>
      </div>
      <button class="btn-ccs-outline btn-ccs-danger" onclick="askCancelReservation(${i})">
        <i class="fa-solid fa-xmark"></i> Cancel
      </button>
    </div>
  `).join('');
}

function askCancelReservation(i) {
  pendingDelIdx = i;
  new bootstrap.Modal(document.getElementById('modalCancelRes')).show();
}
function doDeleteReservation() {
  if (pendingDelIdx !== null) { reservations.splice(pendingDelIdx, 1); pendingDelIdx = null; }
  bootstrap.Modal.getInstance(document.getElementById('modalCancelRes')).hide();
  renderMyReservations();
  showToast('Reservation cancelled');
}

/* ── HISTORY ────────────────────────────────────────────────── */
function renderHistory() {
  const q   = (document.getElementById('histSearch').value || '').toLowerCase();
  const pp  = parseInt(document.getElementById('histEntries').value || 10);
  const data = historyData.filter(r =>
    Object.values(r).join(' ').toLowerCase().includes(q)
  );
  const total = data.length;
  const pages = Math.max(1, Math.ceil(total / pp));
  if (histPage > pages) histPage = pages;

  const slice = data.slice((histPage - 1) * pp, histPage * pp);
  const tbody = document.getElementById('histBody');

  if (!total) {
    tbody.innerHTML = `
      <tr class="no-data-row">
        <td colspan="8">
          <i class="fa-regular fa-folder-open" style="font-size:1.4rem;display:block;margin-bottom:8px;opacity:.35"></i>
          No history records yet
        </td>
      </tr>`;
  } else {
    tbody.innerHTML = slice.map(r => `
      <tr>
        <td>${r.id}</td>
        <td>${r.name}</td>
        <td><span class="chip chip-blue">${r.purpose}</span></td>
        <td>${r.lab}</td>
        <td>${r.login}</td>
        <td>${r.logout}</td>
        <td>${r.date}</td>
        <td>
          <button class="btn-ccs-outline" style="color:var(--blue);border-color:#bfdbfe">
            <i class="fa-solid fa-eye"></i> View
          </button>
        </td>
      </tr>`).join('');
  }

  // Info text
  document.getElementById('histInfo').textContent = total
    ? `Showing ${(histPage - 1) * pp + 1}–${Math.min(histPage * pp, total)} of ${total} entr${total === 1 ? 'y' : 'ies'}`
    : 'Showing 0 entries';

  // Pagination
  let pgHtml = `
    <button class="ccs-pgbtn" onclick="goPage(1)">«</button>
    <button class="ccs-pgbtn" onclick="goPage(${histPage - 1})">‹</button>`;
  for (let i = 1; i <= pages; i++) {
    pgHtml += `<button class="ccs-pgbtn${i === histPage ? ' active' : ''}" onclick="goPage(${i})">${i}</button>`;
  }
  pgHtml += `
    <button class="ccs-pgbtn" onclick="goPage(${histPage + 1})">›</button>
    <button class="ccs-pgbtn" onclick="goPage(${pages})">»</button>`;
  document.getElementById('histPagination').innerHTML = pgHtml;
}

function goPage(p) {
  const pp    = parseInt(document.getElementById('histEntries').value || 10);
  const pages = Math.max(1, Math.ceil(historyData.length / pp));
  histPage    = Math.min(Math.max(1, p), pages);
  renderHistory();
}

function exportCSV() {
  if (!historyData.length) { showToast('No data to export', 'fa-circle-xmark', '#ef4444'); return; }
  const headers = ['ID Number','Name','Purpose','Lab','Login','Logout','Date'];
  const rows    = historyData.map(r => [r.id, r.name, r.purpose, r.lab, r.login, r.logout, r.date]);
  const csv     = [headers, ...rows].map(r => r.join(',')).join('\n');
  const a       = document.createElement('a');
  a.href        = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
  a.download    = 'sit-in-history.csv';
  a.click();
  showToast('History exported as CSV');
}

function renderMiniHistory() {
  const el = document.getElementById('miniHistBody');
  if (!el) return;
  if (!historyData.length) {
    el.innerHTML = `<tr class="no-data-row"><td colspan="5">
      <i class="fa-regular fa-folder-open" style="font-size:1.3rem;display:block;margin-bottom:8px;opacity:.35"></i>
      No records yet</td></tr>`;
    return;
  }
  el.innerHTML = historyData.slice(-3).reverse().map(r => `
    <tr>
      <td><span class="chip chip-blue">${r.purpose}</span></td>
      <td>${r.lab}</td>
      <td>${r.login}</td>
      <td>${r.logout}</td>
      <td>${r.date}</td>
    </tr>`).join('');
}

/* ── SUCCESS MODAL ──────────────────────────────────────────── */
function showSuccessModal(title, sub) {
  document.getElementById('successTitle').textContent = title;
  document.getElementById('successSub').textContent   = sub;
  new bootstrap.Modal(document.getElementById('modalSuccess')).show();
}

/* ── FORMAT HELPERS ─────────────────────────────────────────── */
function fmtDate(d) {
  if (!d) return '—';
  const [y, m, dy] = d.split('-');
  const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  return `${months[parseInt(m) - 1]} ${parseInt(dy)}, ${y}`;
}
function fmtTime(t) {
  if (!t) return '—';
  const [h, m]  = t.split(':');
  const hr      = parseInt(h);
  return `${hr % 12 || 12}:${m} ${hr >= 12 ? 'PM' : 'AM'}`;
}
function todayStr() {
  return new Date().toISOString().slice(0, 10);
}

/* ── INIT ───────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  refreshDisplay();
  updateSessionUI();
  renderHistory();
  renderMiniHistory();
  renderMyReservations();

  // Default date in reservation form
  const rDate = document.getElementById('rDate');
  if (rDate) rDate.value = todayStr();

  // Sync profile form course select
  const pCo = document.getElementById('pCo');
  if (pCo) pCo.value = S.course;

  // Show login success modal on load
  const loginModal = new bootstrap.Modal(document.getElementById('modalLogin'));
  loginModal.show();
});