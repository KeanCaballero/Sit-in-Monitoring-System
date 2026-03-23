/* ============================================================
   CCS Sit-in Portal – Login / Register + Dashboard
   script.js  (clean combined version)
   ============================================================ */

/* ── LOGIN & REGISTER (runs on login/register pages) ──────── */
document.addEventListener('DOMContentLoaded', function () {

  // Password strength (register page only)
  const pw   = document.getElementById('password');
  const bars = ['s1','s2','s3','s4'].map(id => document.getElementById(id)).filter(Boolean);
  if (pw && bars.length === 4) {
    pw.addEventListener('input', () => {
      const v = pw.value; let score = 0;
      if (v.length >= 8) score++;
      if (/[A-Z]/.test(v)) score++;
      if (/[0-9]/.test(v)) score++;
      if (/[^A-Za-z0-9]/.test(v)) score++;
      bars.forEach((b, i) => { b.style.background = i < score ? ['#ef4444','#f97316','#eab308','#22c55e'][score-1] : '#e5e7eb'; });
    });
  }

  // ── LOGIN FORM ─────────────────────────────────────────────
  const loginForm = document.getElementById('login-form');
  if (loginForm) {
    loginForm.addEventListener('submit', async function (e) {
      e.preventDefault();

      const id_number = (document.getElementById('idNumber')?.value || '').trim();
      const password  = (document.getElementById('password')?.value  || '');
      const btn       = loginForm.querySelector('button[type="submit"]');
      const errorBox  = document.getElementById('loginError');

      if (errorBox) { errorBox.style.display = 'none'; errorBox.textContent = ''; }

      if (!id_number || !password) {
        showLoginError('Please enter your ID number and password.'); return;
      }

      if (btn) { btn.disabled = true; btn.textContent = 'Signing in…'; }

      try {
        const res = await fetch('login.php', {
          method:  'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id_number, password }),
        });

        // Read the raw text first so we never lose the response
        const raw = await res.text();

        // Try to parse as JSON
        let data;
        try {
          data = JSON.parse(raw);
        } catch (_) {
          // PHP returned HTML — strip tags and show the actual PHP error
          const stripped = raw.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
          console.error('login.php returned non-JSON:\n', raw);
          showLoginError('PHP error: ' + (stripped.substring(0, 200) || 'Unknown error. Check XAMPP error log.'));
          return;
        }

        if (data.success) {
          window.location.href = data.redirect ||
            (data.role === 'admin' ? 'admin_dashboard.php' : 'dashboard.php');
        } else {
          showLoginError(data.message || 'Login failed. Please try again.');
        }

      } catch (err) {
        console.error('Login fetch error:', err);
        showLoginError('Cannot reach login.php — is XAMPP running?');
      } finally {
        if (btn) { btn.disabled = false; btn.textContent = 'Login'; }
      }

      function showLoginError(msg) {
        if (errorBox) {
          errorBox.textContent   = msg;
          errorBox.style.display = 'block';
        } else {
          alert(msg);
        }
      }
    });
  }

  // ── REGISTER FORM ──────────────────────────────────────────
  const registerForm = document.getElementById('register-form');
  if (registerForm) {
    registerForm.addEventListener('submit', function (e) {
      e.preventDefault();
      const p1 = document.getElementById('password').value;
      const p2 = document.getElementById('repeatPassword').value;
      if (p1 !== p2) { alert('Passwords do not match!'); return; }
      fetch('register.php', { method: 'POST', body: new FormData(this) })
        .then(r => r.json())
        .then(data => {
          if (data.success) { alert('Account created! Please login.'); window.location.href = 'index.html'; }
          else { alert(data.message); }
        })
        .catch(() => alert('Server error. Check XAMPP.'));
    });
  }
});

/* ============================================================
   DASHBOARD CODE  (only runs when dashboard elements exist)
   ============================================================ */

/* ── STATE ─────────────────────────────────────────────────── */
const _u    = (typeof SESSION_USER !== 'undefined') ? SESSION_USER : {};
const _yr   = parseInt(_u.year_level) || 0;
const _yrSuffix = _yr === 1 ? 'st' : _yr === 2 ? 'nd' : _yr === 3 ? 'rd' : 'th';
const _yrStr = _yr ? _yr + _yrSuffix + ' Year' : '—';

const S = {
  first:   _u.first_name  || 'Student',
  middle:  _u.middle_name || '',
  last:    _u.last_name   || '',
  id:      _u.id_number   || '—',
  email:   _u.email       || '—',
  address: _u.address     || '—',
  course:  _u.course      || '—',
  year:    _yrStr,
  session: parseInt(_u.remaining_sessions) || 30, totalSession: 30,
};

let notifCount   = 3;
let historyData  = [];
let reservations = [];
let histPage     = 1;
let pendingDelIdx = null;

/* ── TOAST ─────────────────────────────────────────────────── */
function showToast(msg, icon = 'fa-circle-check', color = null) {
  const toastEl = document.getElementById('liveToast');
  if (!toastEl) return;
  document.getElementById('toastMsg').textContent = msg;
  const ic = document.getElementById('toastIcon');
  ic.className   = 'fa-solid ' + icon;
  ic.style.color = color || '';
  bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 3200 }).show();
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
function markNotifRead(el) {
  if (el.classList.contains('read')) return;
  el.classList.add('read');
  el.style.opacity = '0.55';
  notifCount = Math.max(0, notifCount - 1);
  const badge = document.getElementById('notifBadge');
  if (badge) {
    if (notifCount === 0) { badge.style.display = 'none'; }
    else { badge.textContent = notifCount; }
  }
}

function openNotifDropdown() {
  const toggle = document.getElementById('notifToggle');
  if (toggle) {
    const dd = bootstrap.Dropdown.getOrCreateInstance(toggle);
    dd.show();
  }
}

function clearNotifs() {
  const items = document.getElementById('notifItems');
  if (items) items.innerHTML = '<div class="notif-empty">No new notifications</div>';
  const badge = document.getElementById('notifBadge');
  if (badge) badge.style.display = 'none';
  notifCount = 0;
  showToast('All notifications cleared');
  const dd = bootstrap.Dropdown.getInstance(document.getElementById('notifToggle'));
  if (dd) dd.hide();
}

/* ── LOGOUT ─────────────────────────────────────────────────── */
function confirmLogout() {
  new bootstrap.Modal(document.getElementById('modalLogout')).show();
}
function doLogout() {
  bootstrap.Modal.getInstance(document.getElementById('modalLogout')).hide();
  showToast('Logging out…', 'fa-right-from-bracket');
  setTimeout(() => { window.location.href = 'logout.php'; }, 1600);
}

/* ── SESSION UI ─────────────────────────────────────────────── */
function updateSessionUI() {
  const pct  = ((S.session / S.totalSession) * 100).toFixed(1);
  ['sNum','profSessNum'].forEach(id => { const el = document.getElementById(id); if (el) el.textContent = S.session; });
  const fill = document.getElementById('sessFill');   if (fill)  fill.style.width = pct + '%';
  const rSess = document.getElementById('rSess');     if (rSess) rSess.value = S.session;
  const tipSess = document.getElementById('tipSess'); if (tipSess) tipSess.textContent = S.session;
}

/* ── PROFILE DISPLAY ────────────────────────────────────────── */
function setEl(id, val)  { const el = document.getElementById(id); if (el) el.textContent = val; }
function setVal(id, val) { const el = document.getElementById(id); if (el) el.value = val; }

function refreshDisplay() {
  const full    = [S.first, S.middle, S.last].filter(Boolean).join(' ');
  const yearNum = S.year.replace(/\D/g, '') || '?';

  setEl('dName',       full);
  setEl('profName',    full);
  setEl('welcomeName', full);
  setEl('dBadge',      `${S.course} · Year ${yearNum}`);
  setEl('profRole',    `${S.course} · ${S.year}`);
  setEl('dId',         S.id);
  setEl('dCourse',     S.course);
  setEl('dYear',       S.year);
  setEl('dEmail',      S.email);
  setEl('dAddr',       S.address);
  setVal('rName',      full);

  const rId = document.getElementById('rId'); if (rId) rId.value = S.id;

  setVal('pFn', S.first);
  setVal('pLn', S.last);
  setVal('pMn', S.middle);
  setVal('pEm', S.email);
  setVal('pAd', S.address);
}

/* ── SAVE PROFILE — POSTs to update_profile.php ─────────────── */
async function saveProfile() {
  const pw  = document.getElementById('pPw').value;
  const pw2 = document.getElementById('pPw2').value;
  if (pw && pw !== pw2) { showToast('Passwords do not match!', 'fa-circle-xmark', '#ef4444'); return; }
  if (pw && pw.length < 6) { showToast('Password must be at least 6 characters!', 'fa-circle-xmark', '#ef4444'); return; }

  // Find and disable the Save button
  const saveBtn = document.querySelector('[onclick="saveProfile()"]');
  if (saveBtn) { saveBtn.disabled = true; saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving…'; }

  try {
    const res = await fetch('update_profile.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        firstname:        document.getElementById('pFn').value.trim(),
        lastname:         document.getElementById('pLn').value.trim(),
        middlename:       document.getElementById('pMn').value.trim(),
        email:            document.getElementById('pEm').value.trim(),
        address:          document.getElementById('pAd').value.trim(),
        course:           document.getElementById('pCo').value,
        year_level:       document.getElementById('pYr').value,
        new_password:     pw,
        confirm_password: pw2,
      })
    });
    const data = await res.json();
    if (data.success) {
      // Update local JS state so UI reflects change immediately
      S.first   = data.firstname  || document.getElementById('pFn').value.trim();
      S.last    = data.lastname   || document.getElementById('pLn').value.trim();
      S.middle  = document.getElementById('pMn').value.trim();
      S.email   = document.getElementById('pEm').value.trim();
      S.address = document.getElementById('pAd').value.trim();
      S.course  = document.getElementById('pCo').value;
      S.year    = document.getElementById('pYr').value;
      document.getElementById('pPw').value  = '';
      document.getElementById('pPw2').value = '';
      refreshDisplay();
      showToast('Profile updated successfully!');
      // Reload after short delay so PHP session reflects new data
      setTimeout(() => location.reload(), 1800);
    } else {
      showToast(data.message || 'Update failed.', 'fa-circle-xmark', '#ef4444');
    }
  } catch (e) {
    showToast('Could not reach server.', 'fa-circle-xmark', '#ef4444');
  } finally {
    if (saveBtn) { saveBtn.disabled = false; saveBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Changes'; }
  }
}

/* ── AVATAR ─────────────────────────────────────────────────── */
function triggerPhotoInput() { const el = document.getElementById('photoInput'); if (el) el.click(); }
function previewPhoto(event) { handleAvatar(event.target); }
function handleAvatar(input) {
  const file = input.files[0]; if (!file) return;
  const reader = new FileReader();
  reader.onload = ev => {
    ['mainAvatar','profAvatar'].forEach(id => { const img = document.getElementById(id); if (img) img.src = ev.target.result; });
  };
  reader.readAsDataURL(file);
  // Upload to server
  const fd = new FormData();
  fd.append('profile_photo', file);
  fetch('upload_photo.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => { if (d.success) showToast('Profile photo updated!'); else showToast(d.message, 'fa-circle-xmark', '#ef4444'); })
    .catch(() => showToast('Profile photo updated locally only.'));
}

/* ── RESERVATION ────────────────────────────────────────────── */
function submitReservation() {
  const purpose = document.getElementById('rPurpose').value.trim();
  const lab     = document.getElementById('rLab').value;
  const date    = document.getElementById('rDate').value;
  const time    = document.getElementById('rTime').value;
  if (!purpose) { showToast('Please enter a purpose',      'fa-circle-xmark', '#ef4444'); return; }
  if (!lab)     { showToast('Please select a laboratory',  'fa-circle-xmark', '#ef4444'); return; }
  if (!date)    { showToast('Please pick a date',          'fa-circle-xmark', '#ef4444'); return; }
  if (!time)    { showToast('Please enter a time',         'fa-circle-xmark', '#ef4444'); return; }
  reservations.push({ lab, purpose, date, time, idx: Date.now() });
  renderMyReservations();
  document.getElementById('rPurpose').value = '';
  document.getElementById('rLab').value     = '';
  document.getElementById('rDate').value    = todayStr();
  document.getElementById('rTime').value    = '';
  showSuccessModal('Reservation Submitted!', `${lab} · ${fmtDate(date)} at ${fmtTime(time)}\n\nYour reservation has been submitted for approval.`);
}

function renderMyReservations() {
  const el = document.getElementById('myResList'); if (!el) return;
  if (!reservations.length) {
    el.innerHTML = '<p class="text-center" style="font-size:.82rem;color:var(--text3);font-style:italic;padding:8px 0">No reservations yet.</p>';
    return;
  }
  el.innerHTML = reservations.map((r, i) => `
    <div class="res-row">
      <div>
        <div class="res-row-lab"><i class="fa-solid fa-computer me-1" style="color:var(--navy3)"></i>${r.lab}</div>
        <div class="res-row-meta">${r.purpose} &nbsp;·&nbsp; ${fmtDate(r.date)} &nbsp;·&nbsp; ${fmtTime(r.time)}</div>
      </div>
      <button class="btn-ccs-outline btn-ccs-danger" onclick="askCancelReservation(${i})">
        <i class="fa-solid fa-xmark"></i> Cancel
      </button>
    </div>`).join('');
}

function askCancelReservation(i) { pendingDelIdx = i; new bootstrap.Modal(document.getElementById('modalCancelRes')).show(); }
function doDeleteReservation() {
  if (pendingDelIdx !== null) { reservations.splice(pendingDelIdx, 1); pendingDelIdx = null; }
  bootstrap.Modal.getInstance(document.getElementById('modalCancelRes')).hide();
  renderMyReservations();
  showToast('Reservation cancelled');
}

/* ── HISTORY ────────────────────────────────────────────────── */
function renderHistory() {
  const q    = (document.getElementById('histSearch').value || '').toLowerCase();
  const pp   = parseInt(document.getElementById('histEntries').value || 10);
  const data = historyData.filter(r => Object.values(r).join(' ').toLowerCase().includes(q));
  const total = data.length, pages = Math.max(1, Math.ceil(total / pp));
  if (histPage > pages) histPage = pages;
  const slice = data.slice((histPage - 1) * pp, histPage * pp);
  const tbody = document.getElementById('histBody');

  if (!total) {
    tbody.innerHTML = `<tr class="no-data-row"><td colspan="8">
      <i class="fa-regular fa-folder-open" style="font-size:1.4rem;display:block;margin-bottom:8px;opacity:.35"></i>
      No history records yet</td></tr>`;
  } else {
    tbody.innerHTML = slice.map(r => `
      <tr>
        <td>${r.id}</td><td>${r.name}</td>
        <td><span class="chip chip-blue">${r.purpose}</span></td>
        <td>${r.lab}</td><td>${r.login}</td><td>${r.logout}</td><td>${r.date}</td>
        <td><button class="btn-ccs-outline" style="color:var(--blue);border-color:#bfdbfe">
          <i class="fa-solid fa-eye"></i> View</button></td>
      </tr>`).join('');
  }

  document.getElementById('histInfo').textContent = total
    ? `Showing ${(histPage-1)*pp+1}–${Math.min(histPage*pp,total)} of ${total} entr${total===1?'y':'ies'}`
    : 'Showing 0 entries';

  let pgHtml = `<button class="ccs-pgbtn" onclick="goPage(1)">«</button>
                <button class="ccs-pgbtn" onclick="goPage(${histPage-1})">‹</button>`;
  for (let i = 1; i <= pages; i++) pgHtml += `<button class="ccs-pgbtn${i===histPage?' active':''}" onclick="goPage(${i})">${i}</button>`;
  pgHtml += `<button class="ccs-pgbtn" onclick="goPage(${histPage+1})">›</button>
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
  const rows    = historyData.map(r => [r.id,r.name,r.purpose,r.lab,r.login,r.logout,r.date]);
  const csv     = [headers,...rows].map(r => r.join(',')).join('\n');
  const a = document.createElement('a');
  a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
  a.download = 'sit-in-history.csv'; a.click();
  showToast('History exported as CSV');
}

function renderMiniHistory() {
  const el = document.getElementById('miniHistBody'); if (!el) return;
  if (!historyData.length) {
    el.innerHTML = `<tr class="no-data-row"><td colspan="5">
      <i class="fa-regular fa-folder-open" style="font-size:1.3rem;display:block;margin-bottom:8px;opacity:.35"></i>
      No records yet</td></tr>`;
    return;
  }
  el.innerHTML = historyData.slice(-3).reverse().map(r => `
    <tr>
      <td><span class="chip chip-blue">${r.purpose}</span></td>
      <td>${r.lab}</td><td>${r.login}</td><td>${r.logout}</td><td>${r.date}</td>
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
  const [y,m,dy] = d.split('-');
  return `${['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][parseInt(m)-1]} ${parseInt(dy)}, ${y}`;
}
function fmtTime(t) {
  if (!t) return '—';
  const [h,m] = t.split(':'), hr = parseInt(h);
  return `${hr%12||12}:${m} ${hr>=12?'PM':'AM'}`;
}
function todayStr() { return new Date().toISOString().slice(0,10); }

/* ── DASHBOARD INIT ─────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  if (!document.getElementById('histSearch')) return;

  refreshDisplay();
  updateSessionUI();
  renderHistory();
  renderMiniHistory();
  renderMyReservations();

  const rDate = document.getElementById('rDate'); if (rDate) rDate.value = todayStr();

  const pCo = document.getElementById('pCo');
  if (pCo) {
    const opts = Array.from(pCo.options).map(o => o.value);
    pCo.value = opts.includes(S.course) ? S.course : opts[0];
  }

  // Support redirect from edit_profile.php navbar links
  const pendingTab = sessionStorage.getItem('tab');
  if (pendingTab) { sessionStorage.removeItem('tab'); switchTab(pendingTab); }

  const loginModalEl = document.getElementById('modalLogin');
  if (loginModalEl) new bootstrap.Modal(loginModalEl).show();
});