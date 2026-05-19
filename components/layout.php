<?php
// components/layout.php – call cf_layout_head() and cf_layout_sidebar()
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
session_init();
require_auth();

$user = auth_user();

function cf_layout_head(string $title = 'CareerFlow'): void { ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($title) ?> – CareerFlow</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@500;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<style>
:root {
  --bg:       #0D0D14;
  --bg2:      #121219;
  --sidebar:  #0F0F18;
  --card:     rgba(255,255,255,0.035);
  --border:   rgba(255,255,255,0.07);
  --accent:   #6C63FF;
  --accent2:  #4ECDC4;
  --accent3:  #FF6B9D;
  --text:     #E8E8F0;
  --muted:    rgba(255,255,255,0.42);
  --success:  #4ade80;
  --warning:  #fbbf24;
  --danger:   #f87171;
  --sidebar-w: 240px;
  --topbar-h:  64px;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; overflow-x: hidden; }
h1,h2,h3,h4,.brand { font-family: 'Syne', sans-serif; }

/* ── Scrollbar ──────────────────────────────── */
::-webkit-scrollbar { width: 4px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: rgba(255,255,255,.12); border-radius: 4px; }

/* ── Sidebar ────────────────────────────────── */
#sidebar {
  position: fixed; top: 0; left: 0; bottom: 0;
  width: var(--sidebar-w);
  background: var(--sidebar);
  border-right: 1px solid var(--border);
  display: flex; flex-direction: column;
  z-index: 50; transition: transform .3s;
}
.sidebar-logo {
  padding: 20px 20px 16px;
  display: flex; align-items: center; gap: 10px;
  border-bottom: 1px solid var(--border);
}
.logo-icon {
  width: 34px; height: 34px;
  background: linear-gradient(135deg, var(--accent), #9B5DE5);
  border-radius: 9px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.sidebar-nav { flex: 1; overflow-y: auto; padding: 12px 10px; }
.nav-section-label {
  font-size: 10px; font-weight: 600; letter-spacing: 1.2px;
  color: var(--muted); text-transform: uppercase;
  padding: 14px 10px 6px;
}
.nav-link {
  display: flex; align-items: center; gap: 10px;
  padding: 9px 12px; border-radius: 9px;
  color: rgba(255,255,255,.55); font-size: 14px; font-weight: 500;
  text-decoration: none; cursor: pointer;
  transition: all .18s; margin-bottom: 2px;
  white-space: nowrap;
}
.nav-link:hover  { background: rgba(108,99,255,.1); color: #fff; }
.nav-link.active { background: rgba(108,99,255,.18); color: #fff; }
.nav-link.active svg path, .nav-link.active svg circle, .nav-link.active svg rect { stroke: var(--accent); }
.nav-badge {
  margin-left: auto; background: var(--accent);
  color: #fff; font-size: 10px; font-weight: 700;
  padding: 1px 6px; border-radius: 20px;
}
.sidebar-footer {
  padding: 14px 10px;
  border-top: 1px solid var(--border);
}
.user-chip {
  display: flex; align-items: center; gap: 10px;
  padding: 8px 10px; border-radius: 9px;
  cursor: pointer; transition: background .18s;
}
.user-chip:hover { background: rgba(255,255,255,.05); }
.avatar {
  width: 32px; height: 32px; border-radius: 50%;
  background: linear-gradient(135deg, var(--accent), var(--accent2));
  display: flex; align-items: center; justify-content: center;
  font-size: 12px; font-weight: 700; color: #fff; flex-shrink: 0;
}

/* ── Top Bar ────────────────────────────────── */
#topbar {
  position: fixed; top: 0; left: var(--sidebar-w); right: 0;
  height: var(--topbar-h);
  background: rgba(13,13,20,.85); backdrop-filter: blur(12px);
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between;
  padding: 0 24px; z-index: 40;
}
.search-box {
  display: flex; align-items: center; gap: 8px;
  background: rgba(255,255,255,.05); border: 1px solid var(--border);
  border-radius: 9px; padding: 8px 14px; width: 280px;
  transition: all .2s;
}
.search-box:focus-within { border-color: var(--accent); background: rgba(108,99,255,.06); }
.search-box input { background: transparent; border: none; outline: none; color: var(--text); font-size: 14px; width: 100%; }
.search-box input::placeholder { color: var(--muted); }
.icon-btn {
  width: 36px; height: 36px; border-radius: 9px;
  background: rgba(255,255,255,.05); border: 1px solid var(--border);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; transition: all .18s; position: relative;
}
.icon-btn:hover { background: rgba(108,99,255,.15); border-color: var(--accent); }

/* ── Main Content ───────────────────────────── */
#main-content {
  margin-left: var(--sidebar-w);
  margin-top: var(--topbar-h);
  padding: 28px;
  min-height: calc(100vh - var(--topbar-h));
}

/* ── Cards ──────────────────────────────────── */
.card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 14px;
  backdrop-filter: blur(10px);
}
.stat-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 20px 22px;
  transition: transform .2s, box-shadow .2s;
}
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(0,0,0,.3); }

/* ── Buttons ────────────────────────────────── */
.btn { display: inline-flex; align-items: center; gap: 7px; padding: 9px 18px; border-radius: 9px; font-size: 14px; font-weight: 600; cursor: pointer; border: none; transition: all .2s; text-decoration: none; }
.btn-primary { background: linear-gradient(135deg, var(--accent), #9B5DE5); color: #fff; }
.btn-primary:hover { opacity: .88; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(108,99,255,.4); }
.btn-secondary { background: rgba(255,255,255,.07); color: var(--text); border: 1px solid var(--border); }
.btn-secondary:hover { background: rgba(255,255,255,.12); }
.btn-danger { background: rgba(248,113,113,.12); color: var(--danger); border: 1px solid rgba(248,113,113,.2); }
.btn-danger:hover { background: rgba(248,113,113,.2); }
.btn-sm { padding: 6px 12px; font-size: 13px; }

/* ── Status badges ──────────────────────────── */
.badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; white-space: nowrap; }
.badge-wishlist   { background:rgba(148,163,184,.12); color:#94a3b8; }
.badge-applied    { background:rgba(96,165,250,.12);  color:#60a5fa; }
.badge-screening  { background:rgba(251,191,36,.12);  color:#fbbf24; }
.badge-assessment { background:rgba(167,139,250,.12); color:#a78bfa; }
.badge-interview  { background:rgba(52,211,153,.12);  color:#34d399; }
.badge-finalinterview { background:rgba(251,113,133,.12); color:#fb7185; }
.badge-offer      { background:rgba(34,197,94,.12);   color:#22c55e; }
.badge-rejected   { background:rgba(248,113,113,.12); color:#f87171; }
.badge-hired      { background:rgba(78,205,196,.15);  color:#4ECDC4; }

/* ── Table ──────────────────────────────────── */
.cf-table { width: 100%; border-collapse: collapse; font-size: 14px; }
.cf-table th { text-align: left; padding: 12px 16px; font-size: 11px; font-weight: 600; letter-spacing: .8px; text-transform: uppercase; color: var(--muted); border-bottom: 1px solid var(--border); }
.cf-table td { padding: 13px 16px; border-bottom: 1px solid rgba(255,255,255,.04); vertical-align: middle; }
.cf-table tr:last-child td { border-bottom: none; }
.cf-table tbody tr:hover { background: rgba(255,255,255,.02); }

/* ── Forms ──────────────────────────────────── */
.cf-input { background: rgba(255,255,255,.05); border: 1px solid var(--border); color: var(--text); border-radius: 9px; padding: 10px 14px; font-size: 14px; width: 100%; outline: none; transition: all .2s; font-family: 'DM Sans', sans-serif; }
.cf-input:focus { border-color: var(--accent); background: rgba(108,99,255,.07); box-shadow: 0 0 0 3px rgba(108,99,255,.12); }
.cf-input::placeholder { color: var(--muted); }
.cf-label { font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; display: block; text-transform: uppercase; letter-spacing: .5px; }
select.cf-input option { background: #1a1a28; }

/* ── Modal ──────────────────────────────────── */
.modal-overlay {
  position: fixed; inset: 0;
  background: rgba(0,0,0,.7); backdrop-filter: blur(6px);
  display: flex; align-items: center; justify-content: center;
  z-index: 200; opacity: 0; pointer-events: none;
  transition: opacity .25s;
}
.modal-overlay.open { opacity: 1; pointer-events: auto; }
.modal-box {
  background: #171724; border: 1px solid var(--border);
  border-radius: 18px; padding: 30px;
  width: 100%; max-width: 620px; max-height: 90vh;
  overflow-y: auto;
  transform: scale(.94); transition: transform .25s;
}
.modal-overlay.open .modal-box { transform: scale(1); }

/* ── Toast ──────────────────────────────────── */
#toast-container { position: fixed; bottom: 24px; right: 24px; z-index: 999; display: flex; flex-direction: column; gap: 8px; }
.toast { display: flex; align-items: center; gap: 10px; padding: 13px 18px; border-radius: 11px; font-size: 13px; font-weight: 500; min-width: 260px; backdrop-filter: blur(10px); border: 1px solid; animation: slideIn .3s ease; }
.toast-success { background: rgba(34,197,94,.15); border-color: rgba(34,197,94,.3); color: #4ade80; }
.toast-error   { background: rgba(239,68,68,.15);  border-color: rgba(239,68,68,.3);  color: #f87171; }
.toast-info    { background: rgba(108,99,255,.15); border-color: rgba(108,99,255,.3); color: #a78bfa; }
@keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

/* ── Kanban ─────────────────────────────────── */
.kanban-col {
  background: rgba(255,255,255,.025);
  border: 1px solid var(--border);
  border-radius: 14px; padding: 12px;
  min-width: 230px; width: 230px; flex-shrink: 0;
}
.kanban-col-header { padding: 6px 4px 12px; display: flex; align-items: center; justify-content: space-between; }
.kanban-card {
  background: rgba(255,255,255,.05); border: 1px solid var(--border);
  border-radius: 10px; padding: 13px; margin-bottom: 8px;
  cursor: grab; transition: all .2s; user-select: none;
}
.kanban-card:hover { border-color: var(--accent); transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,.3); }
.kanban-card.sortable-ghost { opacity: .4; }
.kanban-drop { min-height: 60px; }

/* ── Responsive ─────────────────────────────── */
@media (max-width: 768px) {
  #sidebar { transform: translateX(-100%); }
  #sidebar.open { transform: translateX(0); }
  #topbar { left: 0; }
  #main-content { margin-left: 0; padding: 16px; }
}
</style>
</head>
<?php }

function cf_layout_sidebar(string $active = ''): void {
    global $user;
    $u = $user ?: ['name' => 'User', 'email' => ''];
    $initial = strtoupper(mb_substr($u['name'], 0, 1));

    // Unread notifications count
    $notifCount = 0;
    try {
        $row = DB::one('SELECT COUNT(*) AS n FROM notifications WHERE user_id=? AND is_read=0', [$u['id']]);
        $notifCount = (int)($row['n'] ?? 0);
    } catch (Exception $e) {}

    $nav = [
        'dashboard'    => ['icon' => 'grid',       'label' => 'Dashboard'],
        'applications' => ['icon' => 'briefcase',  'label' => 'Applications'],
        'kanban'       => ['icon' => 'columns',    'label' => 'Kanban Board'],
        'calendar'     => ['icon' => 'calendar',   'label' => 'Calendar'],
        'resumes'      => ['icon' => 'file-text',  'label' => 'Resumes'],
        'analytics'    => ['icon' => 'bar-chart',  'label' => 'Analytics'],
        'notes'        => ['icon' => 'edit',       'label' => 'Notes'],
        'settings'     => ['icon' => 'settings',   'label' => 'Settings'],
    ];
    $icons = [
        'grid'       => '<path d="M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z" stroke="currentColor" stroke-width="1.6" fill="none"/>',
        'briefcase'  => '<path d="M20 7H4a2 2 0 00-2 2v10a2 2 0 002 2h16a2 2 0 002-2V9a2 2 0 00-2-2zM16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2" stroke="currentColor" stroke-width="1.6" fill="none"/>',
        'columns'    => '<path d="M12 3h9v18h-9M3 3h7v18H3" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round"/>',
        'calendar'   => '<rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="1.6" fill="none"/><path d="M16 2v4M8 2v4M3 10h18" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>',
        'file-text'  => '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6z" stroke="currentColor" stroke-width="1.6" fill="none"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>',
        'bar-chart'  => '<path d="M18 20V10M12 20V4M6 20v-6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>',
        'edit'       => '<path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" stroke="currentColor" stroke-width="1.6" fill="none"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" stroke="currentColor" stroke-width="1.6" fill="none"/>',
        'settings'   => '<circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6" fill="none"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z" stroke="currentColor" stroke-width="1.6" fill="none"/>',
    ];
    ?>
    <body>
    <div id="toast-container"></div>

    <!-- Sidebar -->
    <aside id="sidebar">
      <div class="sidebar-logo">
        <div class="logo-icon">
          <svg width="17" height="17" fill="none" viewBox="0 0 24 24"><path d="M9 12h6M9 16h6M9 8h2M6 2h12a2 2 0 012 2v16a2 2 0 01-2 2H6a2 2 0 01-2-2V4a2 2 0 012-2z" stroke="#fff" stroke-width="2" stroke-linecap="round"/></svg>
        </div>
        <span class="brand text-white font-bold" style="font-size:17px;letter-spacing:-.3px">CareerFlow</span>
      </div>
      <nav class="sidebar-nav">
        <span class="nav-section-label">Workspace</span>
        <?php foreach ($nav as $key => $item):
            $isActive = $active === $key;
            $href = APP_URL . '/pages/' . $key . '.php';
        ?>
        <a href="<?= $href ?>" class="nav-link <?= $isActive ? 'active' : '' ?>">
          <svg width="17" height="17" viewBox="0 0 24 24" style="flex-shrink:0;opacity:<?= $isActive ? '1' : '.65' ?>"><?= $icons[$item['icon']] ?></svg>
          <?= htmlspecialchars($item['label']) ?>
          <?php if ($key === 'analytics' && $notifCount > 0): ?>
            <span class="nav-badge"><?= $notifCount ?></span>
          <?php endif; ?>
        </a>
        <?php endforeach; ?>
      </nav>
      <div class="sidebar-footer">
        <div class="user-chip" onclick="window.location='<?= APP_URL ?>/pages/settings.php'">
          <div class="avatar"><?= $initial ?></div>
          <div style="flex:1;min-width:0">
            <div style="font-size:13px;font-weight:600;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($u['name']) ?></div>
            <div style="font-size:11px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($u['email']) ?></div>
          </div>
        </div>
        <a href="<?= APP_URL ?>/pages/logout.php" style="display:flex;align-items:center;gap:8px;padding:8px 10px;border-radius:9px;color:var(--muted);font-size:13px;text-decoration:none;margin-top:4px;transition:all .18s" onmouseover="this.style.color='#f87171';this.style.background='rgba(248,113,113,.08)'" onmouseout="this.style.color='var(--muted)';this.style.background='transparent'">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
          Sign out
        </a>
      </div>
    </aside>

    <!-- Top Bar -->
    <header id="topbar">
      <div class="flex items-center gap-3">
        <button onclick="document.getElementById('sidebar').classList.toggle('open')" class="icon-btn" style="display:none" id="menu-btn">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none"><path d="M3 12h18M3 6h18M3 18h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        </button>
        <div class="search-box">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" style="color:var(--muted);flex-shrink:0"><circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/><path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
          <input type="text" placeholder="Search applications…" id="global-search">
        </div>
      </div>
      <div style="display:flex;align-items:center;gap:10px">
        <a href="<?= APP_URL ?>/pages/applications.php?new=1" class="btn btn-primary btn-sm">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/></svg>
          Add Job
        </a>
        <div class="icon-btn" style="position:relative" onclick="window.location='<?= APP_URL ?>/pages/calendar.php'">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none"><rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="1.8" fill="none"/><path d="M16 2v4M8 2v4M3 10h18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        </div>
        <?php if ($notifCount > 0): ?>
        <div class="icon-btn" style="position:relative">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
          <span style="position:absolute;top:-4px;right:-4px;width:16px;height:16px;background:var(--danger);border-radius:50%;font-size:9px;font-weight:700;display:flex;align-items:center;justify-content:center;color:#fff"><?= $notifCount ?></span>
        </div>
        <?php endif; ?>
      </div>
    </header>

    <main id="main-content">
    <script>
    // Mobile menu
    if (window.innerWidth <= 768) {
      document.getElementById('menu-btn').style.display = 'flex';
    }
    // Toast utility
    function showToast(msg, type='success') {
      const c = document.getElementById('toast-container');
      const t = document.createElement('div');
      t.className = `toast toast-${type}`;
      t.innerHTML = msg;
      c.appendChild(t);
      setTimeout(() => t.remove(), 4000);
    }
    // Global search
    document.getElementById('global-search').addEventListener('keydown', function(e) {
      if (e.key === 'Enter' && this.value.trim()) {
        window.location = '<?= APP_URL ?>/pages/applications.php?q=' + encodeURIComponent(this.value);
      }
    });
    </script>
<?php } ?>
