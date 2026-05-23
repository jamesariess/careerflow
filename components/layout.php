<?php
// ============================================================
// CareerFlow – Shared Layout (sidebar + topbar + globals)
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
session_init();
require_auth();

$user = auth_user();

// ── Currency preference ───────────────────────────────────────
$_CF_CURRENCY        = 'USD';
$_CF_CURRENCY_SYMBOL = '$';
try {
    $__cu = DB::one('SELECT currency FROM users WHERE id=?', [$user['id'] ?? 0]);
    if (!empty($__cu['currency'])) {
        $_CF_CURRENCY = $__cu['currency'];
        $__symbols = [
            'USD'=>'$','PHP'=>'₱','EUR'=>'€','GBP'=>'£','JPY'=>'¥',
            'AUD'=>'A$','CAD'=>'CA$','SGD'=>'S$','INR'=>'₹','KRW'=>'₩',
            'MYR'=>'RM','IDR'=>'Rp','THB'=>'฿','VND'=>'₫','HKD'=>'HK$',
            'CNY'=>'¥','BRL'=>'R$','MXN'=>'Mex$','ZAR'=>'R','AED'=>'د.إ',
            'SAR'=>'﷼','NZD'=>'NZ$','TWD'=>'NT$','PKR'=>'Rs','BDT'=>'৳',
            'CHF'=>'Fr',
        ];
        $_CF_CURRENCY_SYMBOL = $__symbols[$_CF_CURRENCY] ?? $_CF_CURRENCY;
    }
} catch (Exception $e) {}

function cf_currency(float $amount, bool $compact = false): string {
    global $_CF_CURRENCY, $_CF_CURRENCY_SYMBOL;
    if ($compact && $amount >= 1000) {
        return $_CF_CURRENCY_SYMBOL . number_format($amount / 1000, 0) . 'k';
    }
    return $_CF_CURRENCY_SYMBOL . number_format($amount, 0);
}

// ── Page <head> ───────────────────────────────────────────────
function cf_layout_head(string $title = 'CareerFlow'): void {
    global $_CF_CURRENCY, $_CF_CURRENCY_SYMBOL;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title><?= htmlspecialchars($title) ?> – CareerFlow</title>
<meta name="theme-color" content="#0D0D14">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="mobile-web-app-capable" content="yes">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@500;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
window.CF_CURRENCY        = '<?= $_CF_CURRENCY ?>';
window.CF_CURRENCY_SYMBOL = '<?= $_CF_CURRENCY_SYMBOL ?>';
function cfMoney(amount, compact) {
    if (!amount && amount !== 0) return window.CF_CURRENCY_SYMBOL + '0';
    if (compact && amount >= 1000) return window.CF_CURRENCY_SYMBOL + Math.round(amount/1000) + 'k';
    return window.CF_CURRENCY_SYMBOL + Number(amount).toLocaleString();
}
// Chart.js global defaults — prevents infinite-grow bug
document.addEventListener('DOMContentLoaded', function() {
    if (window.Chart) {
        Chart.defaults.responsive              = true;
        Chart.defaults.maintainAspectRatio     = false;
        Chart.defaults.animation               = { duration: 600 };
        Chart.defaults.plugins.legend.labels.color = 'rgba(255,255,255,0.5)';
        Chart.defaults.plugins.legend.labels.font  = { size: 11, family: "'DM Sans', sans-serif" };
        Chart.defaults.scale.grid.color        = 'rgba(255,255,255,0.04)';
        Chart.defaults.scale.ticks.color       = 'rgba(255,255,255,0.45)';
        Chart.defaults.scale.ticks.font        = { size: 11 };
    }
});
</script>
<style>
/* ── CSS Variables ─────────────────────────────────────────── */
:root {
  --bg:        #0D0D14;
  --bg2:       #121219;
  --sidebar:   #0F0F18;
  --card:      rgba(255,255,255,0.035);
  --border:    rgba(255,255,255,0.07);
  --accent:    #6C63FF;
  --accent2:   #4ECDC4;
  --accent3:   #FF6B9D;
  --text:      #E8E8F0;
  --muted:     rgba(255,255,255,0.42);
  --success:   #4ade80;
  --warning:   #fbbf24;
  --danger:    #f87171;
  --sidebar-w: 240px;
  --topbar-h:  64px;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; overflow-x: hidden; }
h1,h2,h3,h4,.brand { font-family: 'Syne', sans-serif; }

/* ── Scrollbar ─────────────────────────────────────────────── */
::-webkit-scrollbar { width: 4px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: rgba(255,255,255,.12); border-radius: 4px; }

/* ══════════════════════════════════════════════════════════════
   PAGE LOADING SYSTEM
   ══════════════════════════════════════════════════════════════ */

/* Top progress bar */
#cf-progress {
  position: fixed;
  top: 0; left: 0;
  height: 3px;
  width: 0%;
  background: linear-gradient(90deg, var(--accent), var(--accent2), var(--accent3));
  z-index: 99999;
  transition: width .25s ease, opacity .3s ease;
  border-radius: 0 3px 3px 0;
  box-shadow: 0 0 10px var(--accent), 0 0 20px rgba(108,99,255,.4);
  pointer-events: none;
}
#cf-progress.done {
  width: 100% !important;
  opacity: 0;
}

/* Full-screen page transition overlay */
#cf-page-overlay {
  position: fixed;
  inset: 0;
  background: var(--bg);
  z-index: 99998;
  opacity: 0;
  pointer-events: none;
  transition: opacity .15s ease;
}
#cf-page-overlay.visible {
  opacity: .55;
  pointer-events: all;
}

/* Spinner inside topbar — shows on navigation */
#cf-nav-spinner {
  display: none;
  width: 18px; height: 18px;
  border: 2px solid rgba(108,99,255,.25);
  border-top-color: var(--accent);
  border-radius: 50%;
  animation: cf-spin .65s linear infinite;
  flex-shrink: 0;
}
@keyframes cf-spin { to { transform: rotate(360deg); } }

/* Button loading state */
.cf-btn-loading {
  position: relative;
  pointer-events: none !important;
  opacity: .75 !important;
}
.cf-btn-loading::after {
  content: '';
  position: absolute;
  right: 10px; top: 50%;
  transform: translateY(-50%);
  width: 13px; height: 13px;
  border: 2px solid rgba(255,255,255,.25);
  border-top-color: #fff;
  border-radius: 50%;
  animation: cf-spin .65s linear infinite;
}
.cf-btn-loading .btn-label { margin-right: 18px; }

/* Skeleton loader — use on cards while data loads */
.cf-skeleton {
  background: linear-gradient(90deg,
    rgba(255,255,255,.04) 25%,
    rgba(255,255,255,.09) 50%,
    rgba(255,255,255,.04) 75%);
  background-size: 200% 100%;
  animation: cf-shimmer 1.5s infinite;
  border-radius: 8px;
}
@keyframes cf-shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* Nav link ripple */
.nav-link { position: relative; overflow: hidden; }
.nav-link::after {
  content: '';
  position: absolute;
  inset: 0;
  background: rgba(255,255,255,.07);
  opacity: 0;
  transition: opacity .12s;
}
.nav-link:active::after { opacity: 1; }

/* Page-in animation for main content */
@keyframes cf-page-in {
  from { opacity: 0; transform: translateY(8px); }
  to   { opacity: 1; transform: translateY(0); }
}
#main-content {
  animation: cf-page-in .35s ease forwards;
}

/* Refresh pulse on sidebar logo */
@keyframes cf-logo-pulse {
  0%,100% { box-shadow: 0 0 0 0 rgba(108,99,255,0); }
  50%     { box-shadow: 0 0 0 8px rgba(108,99,255,.25); }
}
.logo-icon.loading { animation: cf-logo-pulse .9s ease infinite; }

/* ══════════════════════════════════════════════════════════════
   SIDEBAR
   ══════════════════════════════════════════════════════════════ */
#sidebar {
  position: fixed; top: 0; left: 0; bottom: 0;
  width: var(--sidebar-w);
  background: var(--sidebar);
  border-right: 1px solid var(--border);
  display: flex; flex-direction: column;
  z-index: 50; transition: transform .3s cubic-bezier(.4,0,.2,1);
}
.sidebar-logo {
  padding: 20px 20px 16px;
  display: flex; align-items: center; gap: 10px;
  border-bottom: 1px solid var(--border);
  flex-shrink: 0;
}
.logo-icon {
  width: 34px; height: 34px;
  background: linear-gradient(135deg, var(--accent), #9B5DE5);
  border-radius: 9px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; transition: transform .2s;
}
.logo-icon:hover { transform: scale(1.08); }
.sidebar-nav { flex: 1; overflow-y: auto; padding: 12px 10px; }
.nav-section-label {
  font-size: 10px; font-weight: 600; letter-spacing: 1.2px;
  color: var(--muted); text-transform: uppercase;
  padding: 14px 10px 6px; display: block;
}
.nav-link {
  display: flex; align-items: center; gap: 10px;
  padding: 9px 12px; border-radius: 9px;
  color: rgba(255,255,255,.55); font-size: 14px; font-weight: 500;
  text-decoration: none; cursor: pointer;
  transition: background .18s, color .18s; margin-bottom: 2px;
  white-space: nowrap; overflow: hidden;
}
.nav-link:hover  { background: rgba(108,99,255,.1); color: #fff; }
.nav-link.active { background: rgba(108,99,255,.18); color: #fff; }
.nav-link.loading-nav {
  pointer-events: none;
  opacity: .7;
}
.nav-link.loading-nav .nav-link-spinner {
  display: flex !important;
}
.nav-link-spinner {
  display: none;
  width: 12px; height: 12px;
  border: 1.5px solid rgba(255,255,255,.2);
  border-top-color: var(--accent);
  border-radius: 50%;
  animation: cf-spin .65s linear infinite;
  margin-left: auto; flex-shrink: 0;
}
.nav-badge {
  margin-left: auto; background: var(--accent);
  color: #fff; font-size: 10px; font-weight: 700;
  padding: 1px 6px; border-radius: 20px;
}
.sidebar-footer {
  padding: 14px 10px;
  border-top: 1px solid var(--border);
  flex-shrink: 0;
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

/* ══════════════════════════════════════════════════════════════
   TOPBAR
   ══════════════════════════════════════════════════════════════ */
#topbar {
  position: fixed; top: 0; left: var(--sidebar-w); right: 0;
  height: var(--topbar-h);
  background: rgba(13,13,20,.92); backdrop-filter: blur(14px);
  -webkit-backdrop-filter: blur(14px);
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

/* ══════════════════════════════════════════════════════════════
   MAIN CONTENT
   ══════════════════════════════════════════════════════════════ */
#main-content {
  margin-left: var(--sidebar-w);
  margin-top: var(--topbar-h);
  padding: 28px;
  min-height: calc(100vh - var(--topbar-h));
}

/* ── Cards ───────────────────────────────────────────────────── */
.card { background: var(--card); border: 1px solid var(--border); border-radius: 14px; }
.stat-card {
  background: var(--card); border: 1px solid var(--border);
  border-radius: 14px; padding: 20px 22px;
  transition: transform .2s, box-shadow .2s;
}
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(0,0,0,.3); }

/* ── Buttons ─────────────────────────────────────────────────── */
.btn {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 9px 18px; border-radius: 9px;
  font-size: 14px; font-weight: 600; cursor: pointer;
  border: none; transition: all .2s; text-decoration: none;
  position: relative;
}
.btn-primary { background: linear-gradient(135deg, var(--accent), #9B5DE5); color: #fff; }
.btn-primary:hover { opacity: .88; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(108,99,255,.4); }
.btn-secondary { background: rgba(255,255,255,.07); color: var(--text); border: 1px solid var(--border); }
.btn-secondary:hover { background: rgba(255,255,255,.12); }
.btn-danger { background: rgba(248,113,113,.12); color: var(--danger); border: 1px solid rgba(248,113,113,.2); }
.btn-danger:hover { background: rgba(248,113,113,.2); }
.btn-sm { padding: 6px 12px; font-size: 13px; }

/* Button click feedback */
.btn:active { transform: scale(.97); }

/* ── Status badges ───────────────────────────────────────────── */
.badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; white-space: nowrap; }
.badge-wishlist        { background:rgba(148,163,184,.12); color:#94a3b8; }
.badge-applied         { background:rgba(96,165,250,.12);  color:#60a5fa; }
.badge-screening       { background:rgba(251,191,36,.12);  color:#fbbf24; }
.badge-assessment      { background:rgba(167,139,250,.12); color:#a78bfa; }
.badge-interview       { background:rgba(52,211,153,.12);  color:#34d399; }
.badge-finalinterview  { background:rgba(251,113,133,.12); color:#fb7185; }
.badge-offer           { background:rgba(34,197,94,.12);   color:#22c55e; }
.badge-rejected        { background:rgba(248,113,113,.12); color:#f87171; }
.badge-hired           { background:rgba(78,205,196,.15);  color:#4ECDC4; }

/* ── Table ───────────────────────────────────────────────────── */
.cf-table { width: 100%; border-collapse: collapse; font-size: 14px; }
.cf-table th { text-align: left; padding: 12px 16px; font-size: 11px; font-weight: 600; letter-spacing: .8px; text-transform: uppercase; color: var(--muted); border-bottom: 1px solid var(--border); }
.cf-table td { padding: 13px 16px; border-bottom: 1px solid rgba(255,255,255,.04); vertical-align: middle; }
.cf-table tr:last-child td { border-bottom: none; }
.cf-table tbody tr { transition: background .12s; }
.cf-table tbody tr:hover { background: rgba(255,255,255,.02); }

/* ── Forms ───────────────────────────────────────────────────── */
.cf-input {
  background: rgba(255,255,255,.05); border: 1px solid var(--border);
  color: var(--text); border-radius: 9px; padding: 10px 14px;
  font-size: 14px; width: 100%; outline: none;
  transition: border-color .2s, background .2s, box-shadow .2s;
  font-family: 'DM Sans', sans-serif;
}
.cf-input:focus { border-color: var(--accent); background: rgba(108,99,255,.07); box-shadow: 0 0 0 3px rgba(108,99,255,.12); }
.cf-input::placeholder { color: var(--muted); }
.cf-input:disabled { opacity: .45; cursor: not-allowed; }
select.cf-input option { background: #1a1a28; }
.cf-label { font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; display: block; text-transform: uppercase; letter-spacing: .5px; }

/* ── Modal ───────────────────────────────────────────────────── */
.modal-overlay {
  position: fixed; inset: 0;
  background: rgba(0,0,0,.75); backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  display: flex; align-items: center; justify-content: center;
  z-index: 500;                /* above FAB(59), bottom-nav(60), topbar(40) */
  opacity: 0; pointer-events: none;
  transition: opacity .22s ease;
  padding: 20px;
}
.modal-overlay.open { opacity: 1; pointer-events: auto; }
.modal-box {
  background: #171724;
  border: 1px solid var(--border);
  border-radius: 18px;
  padding: 28px;
  width: 100%; max-width: 640px;
  max-height: 88vh;
  overflow-y: auto;
  overflow-x: hidden;
  -webkit-overflow-scrolling: touch;
  transform: scale(.95) translateY(16px);
  transition: transform .28s cubic-bezier(.34,1.56,.64,1);
  position: relative;
}
.modal-overlay.open .modal-box { transform: scale(1) translateY(0); }
/* Scrollbar inside modal */
.modal-box::-webkit-scrollbar { width: 4px; }
.modal-box::-webkit-scrollbar-thumb { background: rgba(255,255,255,.1); border-radius: 4px; }

/* ── Modal header/body/footer (structure classes) ─────────────── */
/* Desktop: normal layout, no sticky behaviour needed */
.cf-modal-header {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 20px; flex-shrink: 0;
}
.cf-modal-body {
  flex: 1;
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
}
.cf-modal-footer {
  display: flex; gap: 10px; justify-content: flex-end;
  margin-top: 20px; padding-top: 18px;
  border-top: 1px solid var(--border);
  flex-shrink: 0;
}
.cf-modal-footer .btn { min-width: 100px; justify-content: center; }

/* ── Toast ───────────────────────────────────────────────────── */
#toast-container {
  position: fixed; bottom: 24px; right: 24px;
  z-index: 9999; display: flex; flex-direction: column; gap: 8px;
  pointer-events: none;
}
@media (max-width: 768px) {
  #toast-container {
    bottom: 76px;   /* above bottom nav (64px) */
    right: 12px;
    left: 12px;
    align-items: stretch;
  }
  .toast { min-width: 0; width: 100%; }
}
.toast {
  display: flex; align-items: center; gap: 10px;
  padding: 13px 18px; border-radius: 11px;
  font-size: 13px; font-weight: 500; min-width: 260px;
  backdrop-filter: blur(10px); border: 1px solid;
  pointer-events: all;
  animation: toast-in .3s cubic-bezier(.34,1.56,.64,1);
}
@keyframes toast-in {
  from { transform: translateX(110%); opacity: 0; }
  to   { transform: translateX(0);    opacity: 1; }
}
.toast-success { background: rgba(34,197,94,.15);  border-color: rgba(34,197,94,.3);  color: #4ade80; }
.toast-error   { background: rgba(239,68,68,.15);  border-color: rgba(239,68,68,.3);  color: #f87171; }
.toast-info    { background: rgba(108,99,255,.15); border-color: rgba(108,99,255,.3); color: #a78bfa; }
.toast-warning { background: rgba(251,191,36,.15); border-color: rgba(251,191,36,.3); color: #fbbf24; }

/* ── Kanban ──────────────────────────────────────────────────── */
.kanban-col {
  background: rgba(255,255,255,.025); border: 1px solid var(--border);
  border-radius: 14px; padding: 12px; min-width: 230px; width: 230px; flex-shrink: 0;
}
.kanban-col-header { padding: 6px 4px 12px; display: flex; align-items: center; justify-content: space-between; }
.kanban-card {
  background: rgba(255,255,255,.05); border: 1px solid var(--border);
  border-radius: 10px; padding: 13px; margin-bottom: 8px;
  cursor: grab; transition: border-color .18s, transform .18s, box-shadow .18s;
  user-select: none;
}
.kanban-card:hover { border-color: var(--accent); transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,.3); }
.kanban-card.sortable-ghost { opacity: .35; border: 2px dashed var(--accent); }
.kanban-card.sortable-chosen { box-shadow: 0 12px 40px rgba(0,0,0,.5); transform: scale(1.02) rotate(.5deg); }
.kanban-drop { min-height: 60px; }

/* ── Responsive ──────────────────────────────────────────────── */
/* ══════════════════════════════════════════════════════════════
   RESPONSIVE GRID HELPERS
   ══════════════════════════════════════════════════════════════ */
/* Use these classes on grid wrappers instead of inline styles */
.rg-2   { display:grid; grid-template-columns:repeat(2,1fr); gap:16px; }
.rg-3   { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; }
.rg-4   { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; }
.rg-21  { display:grid; grid-template-columns:2fr 1fr; gap:16px; }
.rg-31  { display:grid; grid-template-columns:3fr 1fr; gap:16px; }
.rg-12  { display:grid; grid-template-columns:1fr 2fr; gap:16px; }
.rg-auto{ display:grid; grid-template-columns:repeat(auto-fill,minmax(175px,1fr)); gap:14px; }
.rg-sidebar { display:grid; grid-template-columns:220px 1fr; gap:16px; align-items:start; }

/* ── Bottom nav (mobile only — hidden on desktop) ─────────────── */
#cf-bottom-nav {
  display: none;
  position: fixed; bottom: 0; left: 0; right: 0;
  height: 64px;
  background: rgba(15,15,24,.97);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border-top: 1px solid var(--border);
  z-index: 60;
  padding: 0 4px;
  padding-bottom: env(safe-area-inset-bottom, 0);
}
.cf-bnav-items {
  display: flex; align-items: center; justify-content: space-around;
  height: 100%;
}
.cf-bnav-item {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 3px; flex: 1; padding: 8px 4px;
  color: rgba(255,255,255,.4); font-size: 9px; font-weight: 600;
  text-decoration: none; text-transform: uppercase; letter-spacing: .4px;
  border-radius: 10px; transition: color .18s, background .18s;
  cursor: pointer; position: relative;
}
.cf-bnav-item.active { color: var(--accent); }
.cf-bnav-item:active { background: rgba(108,99,255,.12); }
.cf-bnav-item svg   { width: 22px; height: 22px; flex-shrink: 0; }
.cf-bnav-dot {
  position: absolute; top: 6px; right: calc(50% - 14px);
  width: 7px; height: 7px; border-radius: 50%;
  background: var(--danger); border: 2px solid var(--bg);
}
/* FAB (Floating Action Button) for Add Job on mobile */
#cf-fab {
  display: none;
  position: fixed; bottom: 80px; right: 20px;
  width: 52px; height: 52px; border-radius: 50%;
  background: linear-gradient(135deg, var(--accent), #9B5DE5);
  box-shadow: 0 4px 20px rgba(108,99,255,.5);
  align-items: center; justify-content: center;
  z-index: 59; cursor: pointer; border: none;
  transition: transform .2s, box-shadow .2s;
  text-decoration: none;
}
#cf-fab:active { transform: scale(.92); box-shadow: 0 2px 12px rgba(108,99,255,.4); }

/* Sidebar overlay (tap outside to close) */
#cf-sidebar-overlay {
  display: none;
  position: fixed; inset: 0;
  background: rgba(0,0,0,.6);
  z-index: 49;
  backdrop-filter: blur(2px);
}

/* ── Utility ─────────────────────────────────────────────────── */
.flex { display: flex; }
.items-center { align-items: center; }
.justify-between { justify-content: space-between; }
.gap-3 { gap: 12px; }
.text-center { text-align: center; }

/* ══════════════════════════════════════════════════════════════
   MOBILE — ≤ 768px
   ══════════════════════════════════════════════════════════════ */
@media (max-width: 768px) {
  :root { --topbar-h: 56px; }

  /* Sidebar slides in from left */
  #sidebar { transform: translateX(-110%); transition: transform .3s cubic-bezier(.4,0,.2,1); }
  #sidebar.open { transform: translateX(0); box-shadow: 6px 0 30px rgba(0,0,0,.6); }
  #cf-sidebar-overlay { display: block; opacity: 0; pointer-events: none; transition: opacity .3s; }
  #cf-sidebar-overlay.open { opacity: 1; pointer-events: all; }

  /* Topbar: full width */
  #topbar { left: 0; padding: 0 14px; }
  /* Hide search box — replaced by search icon */
  .search-box { display: none !important; }
  /* Hide desktop Add Job button in topbar */
  #btn-add-job { display: none !important; }

  /* Main content */
  #main-content {
    margin-left: 0;
    padding: 14px 14px 80px; /* bottom pad for bottom nav */
    animation: cf-page-in .3s ease forwards;
  }

  /* Show bottom nav + FAB */
  #cf-bottom-nav { display: flex; }
  #cf-fab { display: flex; }

  /* ── Grid collapse ── */
  .rg-2, .rg-21, .rg-31, .rg-12 { grid-template-columns: 1fr !important; }
  .rg-3  { grid-template-columns: repeat(2,1fr) !important; }
  .rg-4  { grid-template-columns: repeat(2,1fr) !important; }
  .rg-sidebar { grid-template-columns: 1fr !important; }

  /* Inline style grids — override via class additions where possible */
  /* Catch-all: any 2-column grid inside main content */
  #main-content [style*="grid-template-columns:1fr 1fr"],
  #main-content [style*="grid-template-columns: 1fr 1fr"] {
    grid-template-columns: 1fr !important;
  }
  #main-content [style*="grid-template-columns:2fr 1fr"],
  #main-content [style*="grid-template-columns: 2fr 1fr"] {
    grid-template-columns: 1fr !important;
  }
  #main-content [style*="grid-template-columns:repeat(4,1fr)"],
  #main-content [style*="grid-template-columns: repeat(4,1fr)"] {
    grid-template-columns: repeat(2,1fr) !important;
  }
  #main-content [style*="grid-template-columns:1fr 320px"],
  #main-content [style*="grid-template-columns:1fr 340px"],
  #main-content [style*="grid-template-columns:200px 1fr"],
  #main-content [style*="grid-template-columns:220px 1fr"] {
    grid-template-columns: 1fr !important;
  }
  /* stat card auto-fill: 2 cols on phone */
  #main-content [style*="minmax(175px,1fr)"],
  #main-content [style*="minmax(180px,1fr)"] {
    grid-template-columns: repeat(2,1fr) !important;
  }

  /* Tables — horizontal scroll */
  .cf-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
  .cf-table { min-width: 600px; }
  /* Hide less important columns */
  .cf-table .hide-mobile { display: none !important; }

  /* Cards */
  .stat-card { padding: 14px 16px; }
  .stat-card > div:first-child span { font-size: 9px; }

  /* ══════════════════════════════════════════
     MOBILE MODAL — centered card, proper layout
     ══════════════════════════════════════════ */
  .modal-overlay {
    /* Center the modal on screen, not bottom sheet */
    align-items: center !important;
    justify-content: center !important;
    padding: 12px !important;
    z-index: 500 !important;
    overflow-y: auto !important;
    /* Start hidden, fade in */
    opacity: 0;
    pointer-events: none;
  }
  .modal-overlay.open {
    opacity: 1;
    pointer-events: auto;
  }
  .modal-box {
    /* Proper card — not full screen, not bottom sheet */
    width: calc(100vw - 24px) !important;
    max-width: 100% !important;
    max-height: calc(100vh - 24px) !important;
    margin: auto !important;
    border-radius: 16px !important;
    /* No translateY — just scale in cleanly */
    transform: scale(.92) !important;
    transition: transform .22s cubic-bezier(.34,1.4,.64,1), opacity .2s !important;
    opacity: 0;
    /* CRITICAL: flex column so header/body/footer stack properly */
    display: flex !important;
    flex-direction: column !important;
    padding: 0 !important;
    overflow: hidden !important;
  }
  .modal-overlay.open .modal-box {
    transform: scale(1) !important;
    opacity: 1;
  }
  /* Header: fixed height, never scrolls */
  .cf-modal-header {
    padding: 16px 16px 14px !important;
    border-bottom: 1px solid var(--border) !important;
    background: #1c1c2e !important;
    flex-shrink: 0 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    margin-bottom: 0 !important;
    border-radius: 16px 16px 0 0 !important;
  }
  .cf-modal-header h2 { font-size: 16px !important; }
  /* Body: takes remaining space, scrolls independently */
  .cf-modal-body {
    flex: 1 !important;
    overflow-y: auto !important;
    -webkit-overflow-scrolling: touch !important;
    padding: 16px !important;
    background: #171724 !important;
  }
  /* Footer: fixed height, never scrolls — ALWAYS VISIBLE */
  .cf-modal-footer {
    padding: 12px 16px !important;
    padding-bottom: calc(12px + env(safe-area-inset-bottom, 0px)) !important;
    border-top: 1px solid var(--border) !important;
    background: #1c1c2e !important;
    flex-shrink: 0 !important;
    display: flex !important;
    gap: 8px !important;
    justify-content: stretch !important;
    margin-top: 0 !important;
    border-radius: 0 0 16px 16px !important;
  }
  .cf-modal-footer .btn {
    flex: 1 !important;
    justify-content: center !important;
    padding: 12px !important;
    font-size: 14px !important;
  }
  /* No ::before handle (not a sheet) */
  .modal-box::before { display: none !important; }
  /* Form grids: 2 cols for short rows, full for long */
  .modal-box .rg-2 {
    grid-template-columns: 1fr 1fr !important;
    gap: 10px !important;
  }
  /* Full-width fields stay full width */
  .modal-box [style*="grid-column:1/-1"],
  .modal-box [style*="grid-column: 1 / -1"] {
    grid-column: 1 / -1 !important;
  }
  /* Prevent iOS zoom on focus */
  .modal-box .cf-input,
  .modal-box input,
  .modal-box select,
  .modal-box textarea {
    font-size: 16px !important;
  }
  /* Shorter textareas on mobile */
  .modal-box textarea.cf-input {
    min-height: 60px !important;
    max-height: 90px !important;
    rows: 2 !important;
  }
  /* Hide FAB + bottom nav when modal is open */
  body.modal-open #cf-fab,
  body.modal-open #cf-bottom-nav {
    display: none !important;
  }

  /* Forms: inputs full-width */
  .cf-input { font-size: 16px !important; } /* prevents iOS zoom */
  select.cf-input { font-size: 16px !important; }

  /* Filter bars: stack vertically */
  .filter-bar { flex-direction: column !important; }
  .filter-bar .cf-input { width: 100% !important; }

  /* Kanban: show 1 column at a time, horizontal scroll */
  .kanban-board { overflow-x: auto; -webkit-overflow-scrolling: touch; }
  .kanban-col   { min-width: 260px !important; width: 260px !important; }

  /* Page headers: stack */
  .page-header { flex-direction: column !important; align-items: flex-start !important; gap: 10px !important; }
  .page-header .btn { width: 100%; justify-content: center; }

  /* Timeline: smaller text */
  .timeline-label { font-size: 7px !important; }
  .timeline-dot   { width: 20px !important; height: 20px !important; font-size: 9px !important; }

  /* Topbar right side — show only icons */
  #topbar .topbar-right-text { display: none; }

  /* Detail sidebar: order — main content first */
  .detail-sidebar { order: -1; }

  /* Settings tabs: horizontal scroll */
  #settingsTabs, [id^="tab-"] ~ [id^="tab-"] { overflow-x: auto; }
  .tab-btn { padding: 8px 12px; font-size: 12px; white-space: nowrap; }

  /* Cover letter grid */
  #main-content [style*="grid-template-columns:repeat(auto-fill,minmax(300px"] {
    grid-template-columns: 1fr !important;
  }

  /* Analytics funnel bars */
  .funnel-bar { min-width: 0 !important; }

  /* Chart wrappers: slightly shorter on mobile */
  [style*="height:220px"], [style*="height:240px"] {
    height: 180px !important;
  }
  [style*="height:200px"] {
    height: 160px !important;
  }
}

/* ── Small phones ≤ 380px ─────────────────────────────────────── */
@media (max-width: 380px) {
  #main-content { padding: 10px 10px 80px; }
  .rg-4, .rg-3 { grid-template-columns: 1fr !important; }
  #main-content [style*="grid-template-columns:repeat(4,1fr)"] { grid-template-columns: 1fr !important; }
  #main-content [style*="minmax(175px"] { grid-template-columns: 1fr !important; }
  .stat-card > div:last-child { font-size: 22px !important; }
}

/* ── Tablet ≤ 1024px ─────────────────────────────────────────── */
@media (max-width: 1024px) and (min-width: 769px) {
  :root { --sidebar-w: 200px; }
  #main-content { padding: 20px; }
  .rg-4   { grid-template-columns: repeat(2,1fr) !important; }
  #main-content [style*="grid-template-columns:repeat(4,1fr)"] { grid-template-columns: repeat(2,1fr) !important; }
}
</style>
</head>
<?php }

// ── Sidebar + body open ───────────────────────────────────────
function cf_layout_sidebar(string $active = ''): void {
    global $user, $_CF_CURRENCY_SYMBOL;
    $u       = $user ?: ['name'=>'User','email'=>'','id'=>0];
    $initial = strtoupper(mb_substr($u['name'], 0, 1));

    $notifCount  = 0;
    $gmailUnread = 0;
    try {
        $row = DB::one('SELECT COUNT(*) AS n FROM notifications WHERE user_id=? AND is_read=0', [$u['id']]);
        $notifCount = (int)($row['n'] ?? 0);
        $gr = DB::one('SELECT COUNT(*) AS n FROM gmail_emails WHERE user_id=? AND is_read=0 AND is_job_related=1', [$u['id']]);
        $gmailUnread = (int)($gr['n'] ?? 0);
    } catch (Exception $e) {}

    $nav = [
        'dashboard'    => ['icon'=>'grid',      'label'=>'Dashboard'],
        'applications' => ['icon'=>'briefcase', 'label'=>'Applications'],
        'kanban'       => ['icon'=>'columns',   'label'=>'Kanban Board'],
        'calendar'     => ['icon'=>'calendar',  'label'=>'Calendar'],
        'resumes'      => ['icon'=>'file-text', 'label'=>'Resumes'],
        'analytics'    => ['icon'=>'bar-chart', 'label'=>'Analytics'],
        'gmail'        => ['icon'=>'mail',      'label'=>'Gmail Inbox'],
        'cover_letters' => ['icon'=>'pen',       'label'=>'Cover Letters'],
        'notes'        => ['icon'=>'edit',      'label'=>'Notes'],
        'settings'     => ['icon'=>'settings',  'label'=>'Settings'],
    ];
    $icons = [
        'grid'      => '<path d="M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z" stroke="currentColor" stroke-width="1.6" fill="none"/>',
        'briefcase' => '<path d="M20 7H4a2 2 0 00-2 2v10a2 2 0 002 2h16a2 2 0 002-2V9a2 2 0 00-2-2zM16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2" stroke="currentColor" stroke-width="1.6" fill="none"/>',
        'columns'   => '<path d="M12 3h9v18h-9M3 3h7v18H3" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round"/>',
        'calendar'  => '<rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="1.6" fill="none"/><path d="M16 2v4M8 2v4M3 10h18" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>',
        'file-text' => '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6z" stroke="currentColor" stroke-width="1.6" fill="none"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>',
        'bar-chart' => '<path d="M18 20V10M12 20V4M6 20v-6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>',
        'mail'      => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" stroke="currentColor" stroke-width="1.6" fill="none"/><polyline points="22,6 12,13 2,6" stroke="currentColor" stroke-width="1.6" fill="none"/>',
        'pen'       => '<path d="M12 20h9M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round"/>',
        'edit'      => '<path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" stroke="currentColor" stroke-width="1.6" fill="none"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" stroke="currentColor" stroke-width="1.6" fill="none"/>',
        'settings'  => '<circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6" fill="none"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z" stroke="currentColor" stroke-width="1.6" fill="none"/>',
    ];
    ?>
    <body>

    <!-- ── Loading elements ── -->
    <div id="cf-progress"></div>
    <div id="cf-page-overlay"></div>
    <div id="toast-container"></div>

    <!-- ── Sidebar ── -->
    <aside id="sidebar">
      <div class="sidebar-logo">
        <div class="logo-icon" id="cf-logo-icon">
          <svg width="17" height="17" fill="none" viewBox="0 0 24 24">
            <path d="M9 12h6M9 16h6M9 8h2M6 2h12a2 2 0 012 2v16a2 2 0 01-2 2H6a2 2 0 01-2-2V4a2 2 0 012-2z" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </div>
        <span class="brand" style="color:#fff;font-size:17px;font-weight:700;letter-spacing:-.3px">CareerFlow</span>
      </div>
      <nav class="sidebar-nav">
        <span class="nav-section-label">Workspace</span>
        <?php foreach ($nav as $key => $item):
            $isActive = $active === $key;
            $href     = APP_URL . '/pages/' . $key . '.php';
        ?>
        <a href="<?= $href ?>" class="nav-link <?= $isActive ? 'active' : '' ?>" data-page="<?= $key ?>">
          <svg width="17" height="17" viewBox="0 0 24 24" style="flex-shrink:0;opacity:<?= $isActive?'1':'.65' ?>"><?= $icons[$item['icon']] ?></svg>
          <?= htmlspecialchars($item['label']) ?>
          <?php if ($key==='gmail' && $gmailUnread>0): ?>
            <span class="nav-badge"><?= $gmailUnread ?></span>
          <?php elseif ($key==='analytics' && $notifCount>0): ?>
            <span class="nav-badge"><?= $notifCount ?></span>
          <?php endif; ?>
          <span class="nav-link-spinner"></span>
        </a>
        <?php endforeach; ?>
      </nav>
      <div class="sidebar-footer">
        <div class="user-chip" onclick="cfNavigate('<?= APP_URL ?>/pages/settings.php')">
          <div class="avatar"><?= $initial ?></div>
          <div style="flex:1;min-width:0">
            <div style="font-size:13px;font-weight:600;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($u['name']) ?></div>
            <div style="font-size:11px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($u['email']) ?></div>
          </div>
          <span style="font-size:11px;color:var(--accent);font-weight:700"><?= $_CF_CURRENCY_SYMBOL ?></span>
        </div>
        <a href="<?= APP_URL ?>/pages/logout.php"
           style="display:flex;align-items:center;gap:8px;padding:8px 10px;border-radius:9px;color:var(--muted);font-size:13px;text-decoration:none;margin-top:4px;transition:all .18s"
           onmouseover="this.style.color='#f87171';this.style.background='rgba(248,113,113,.08)'"
           onmouseout="this.style.color='var(--muted)';this.style.background='transparent'">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none">
            <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
          Sign out
        </a>
      </div>
    </aside>

    <!-- ── Topbar ── -->
    <header id="topbar">
      <div style="display:flex;align-items:center;gap:10px">
        <button onclick="toggleSidebar()"
                class="icon-btn" style="display:none" id="menu-btn">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none">
            <path d="M3 12h18M3 6h18M3 18h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </button>
        <div class="search-box">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" style="color:var(--muted);flex-shrink:0">
            <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/>
            <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
          <input type="text" placeholder="Search applications…" id="global-search">
        </div>
        <!-- Navigation spinner (visible during page load) -->
        <div id="cf-nav-spinner"></div>
      </div>
      <div style="display:flex;align-items:center;gap:8px">
        <a href="<?= APP_URL ?>/pages/applications.php?new=1" class="btn btn-primary btn-sm" id="btn-add-job">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
            <path d="M12 5v14M5 12h14" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/>
          </svg>
          <span class="btn-label">Add Job</span>
        </a>
        <a href="<?= APP_URL ?>/pages/gmail.php" class="icon-btn" title="Gmail Inbox">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none">
            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" stroke="currentColor" stroke-width="1.8" fill="none"/>
            <polyline points="22,6 12,13 2,6" stroke="currentColor" stroke-width="1.8" fill="none"/>
          </svg>
          <?php if ($gmailUnread > 0): ?>
          <span style="position:absolute;top:-3px;right:-3px;width:14px;height:14px;background:var(--danger);border-radius:50%;font-size:8px;font-weight:700;display:flex;align-items:center;justify-content:center;color:#fff"><?= min($gmailUnread,9) ?></span>
          <?php endif; ?>
        </a>
        <?php if ($notifCount > 0): ?>
        <div class="icon-btn">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none">
            <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
          <span style="position:absolute;top:-3px;right:-3px;width:14px;height:14px;background:var(--danger);border-radius:50%;font-size:8px;font-weight:700;display:flex;align-items:center;justify-content:center;color:#fff"><?= min($notifCount,9) ?></span>
        </div>
        <?php endif; ?>
      </div>
    </header>

    <!-- Sidebar overlay (mobile tap-outside) -->
    <div id="cf-sidebar-overlay" onclick="closeSidebar()"></div>

    <!-- Bottom navigation (mobile) -->
    <nav id="cf-bottom-nav">
      <div class="cf-bnav-items">
        <a href="<?= APP_URL ?>/pages/dashboard.php" class="cf-bnav-item <?= $active==='dashboard'?'active':'' ?>">
          <svg viewBox="0 0 24 24" fill="none"><path d="M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z" stroke="currentColor" stroke-width="1.6"/></svg>
          Home
        </a>
        <a href="<?= APP_URL ?>/pages/applications.php" class="cf-bnav-item <?= $active==='applications'?'active':'' ?>">
          <svg viewBox="0 0 24 24" fill="none"><path d="M20 7H4a2 2 0 00-2 2v10a2 2 0 002 2h16a2 2 0 002-2V9a2 2 0 00-2-2zM16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2" stroke="currentColor" stroke-width="1.6"/></svg>
          Jobs
        </a>
        <a href="<?= APP_URL ?>/pages/gmail.php" class="cf-bnav-item <?= $active==='gmail'?'active':'' ?>">
          <svg viewBox="0 0 24 24" fill="none"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" stroke="currentColor" stroke-width="1.6"/><polyline points="22,6 12,13 2,6" stroke="currentColor" stroke-width="1.6"/></svg>
          Mail
          <?php if ($gmailUnread > 0): ?><span class="cf-bnav-dot"></span><?php endif; ?>
        </a>
        <a href="<?= APP_URL ?>/pages/kanban.php" class="cf-bnav-item <?= $active==='kanban'?'active':'' ?>">
          <svg viewBox="0 0 24 24" fill="none"><path d="M12 3h9v18h-9M3 3h7v18H3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
          Board
        </a>
        <a href="<?= APP_URL ?>/pages/settings.php" class="cf-bnav-item <?= $active==='settings'?'active':'' ?>">
          <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z" stroke="currentColor" stroke-width="1.6"/></svg>
          More
        </a>
      </div>
    </nav>

    <!-- FAB: Add Job (mobile only) -->
    <a href="<?= APP_URL ?>/pages/applications.php?new=1" id="cf-fab" title="Add Job Application">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
        <path d="M12 5v14M5 12h14" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/>
      </svg>
    </a>

    <main id="main-content">

    <script>
    // ══════════════════════════════════════════════════════════════
    // CareerFlow – Global JS (loading, security, utilities)
    // ══════════════════════════════════════════════════════════════

    // ── Mobile setup ─────────────────────────────────────────────
    const isMobile = window.innerWidth <= 768;
    if (isMobile) {
        document.getElementById('menu-btn').style.display = 'flex';
    }

    function toggleSidebar() {
        const sb  = document.getElementById('sidebar');
        const ov  = document.getElementById('cf-sidebar-overlay');
        const open = sb.classList.toggle('open');
        ov.classList.toggle('open', open);
        // Prevent body scroll when sidebar open
        document.body.style.overflow = open ? 'hidden' : '';
    }
    function closeSidebar() {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('cf-sidebar-overlay').classList.remove('open');
        document.body.style.overflow = '';
    }
    // Close sidebar on nav-link tap (mobile)
    document.querySelectorAll('.nav-link').forEach(a => {
        a.addEventListener('click', function() {
            if (window.innerWidth <= 768) closeSidebar();
        });
    });
    // Close on Escape
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            closeSidebar();
            closeAllModals();
        }
    });

    // ── Modal open/close helpers ──────────────────────────────────
    function openModal(id) {
        const m = document.getElementById(id);
        if (!m) return;
        m.classList.add('open');
        document.body.classList.add('modal-open');
        // Prevent body scroll
        document.body.style.overflow = 'hidden';
    }
    function closeModal(id) {
        const m = document.getElementById(id);
        if (!m) return;
        m.classList.remove('open');
        if (!document.querySelector('.modal-overlay.open')) {
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
        }
    }
    function closeAllModals() {
        document.querySelectorAll('.modal-overlay.open').forEach(m => {
            m.classList.remove('open');
        });
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
    }

    // Auto-wire: clicking overlay background closes modal
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal-overlay') && e.target.classList.contains('open')) {
            e.target.classList.remove('open');
            if (!document.querySelector('.modal-overlay.open')) {
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
            }
        }
    });

    // Auto-track modal open state for CSS body class
    // MutationObserver watches all .modal-overlay for class changes
    const _modalObs = new MutationObserver(() => {
        const anyOpen = !!document.querySelector('.modal-overlay.open');
        document.body.classList.toggle('modal-open', anyOpen);
        if (!anyOpen) document.body.style.overflow = '';
    });
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.modal-overlay').forEach(m => {
            _modalObs.observe(m, { attributes: true, attributeFilter: ['class'] });
        });
    });

    // ── Progress bar engine ───────────────────────────────────────
    const CfProgress = (() => {
        const bar   = document.getElementById('cf-progress');
        const overlay = document.getElementById('cf-page-overlay');
        let timer   = null;
        let current = 0;

        function set(pct) {
            current = pct;
            bar.style.width = pct + '%';
        }

        function start() {
            bar.classList.remove('done');
            bar.style.opacity = '1';
            set(5);
            // Logo pulse
            document.getElementById('cf-logo-icon')?.classList.add('loading');
            // Topbar spinner
            document.getElementById('cf-nav-spinner').style.display = 'flex';
            // Overlay
            overlay.classList.add('visible');
            // Simulate progress
            let target = 80;
            timer = setInterval(() => {
                if (current < target) {
                    const step = (target - current) * 0.15;
                    set(current + Math.max(step, 0.5));
                }
            }, 80);
        }

        function done() {
            clearInterval(timer);
            set(100);
            bar.classList.add('done');
            overlay.classList.remove('visible');
            document.getElementById('cf-logo-icon')?.classList.remove('loading');
            document.getElementById('cf-nav-spinner').style.display = 'none';
            setTimeout(() => { bar.style.width = '0%'; bar.classList.remove('done'); }, 400);
        }

        return { start, done, set };
    })();

    // ── Toast system ──────────────────────────────────────────────
    function showToast(msg, type = 'success', duration = 4000) {
        const container = document.getElementById('toast-container');
        const icons = {
            success: '✓', error: '✕', info: 'ℹ', warning: '⚠'
        };
        const toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        toast.innerHTML = `<span style="font-size:16px;line-height:1">${icons[type]||'•'}</span><span style="flex:1">${msg}</span>`;
        toast.onclick = () => dismissToast(toast);
        container.appendChild(toast);
        // Auto dismiss
        const timer = setTimeout(() => dismissToast(toast), duration);
        toast._timer = timer;
    }
    function dismissToast(toast) {
        clearTimeout(toast._timer);
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(110%)';
        toast.style.transition = 'all .3s ease';
        setTimeout(() => toast.remove(), 320);
    }

    // ── Navigation with loading ───────────────────────────────────
    function cfNavigate(url) {
        CfProgress.start();
        window.location.href = url;
    }

    // Intercept all sidebar nav links
    document.querySelectorAll('.nav-link[data-page]').forEach(link => {
        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            // Skip if already active or modifier key
            if (this.classList.contains('active') || e.ctrlKey || e.metaKey || e.shiftKey) return;
            e.preventDefault();
            // Mark this link as loading
            this.classList.add('loading-nav');
            CfProgress.start();
            setTimeout(() => { window.location.href = href; }, 50);
        });
    });

    // Intercept ALL internal anchor clicks (except modals, #hash, external)
    document.addEventListener('click', function(e) {
        const a = e.target.closest('a[href]');
        if (!a) return;
        const href = a.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:')) return;
        if (a.target === '_blank') return;
        if (a.hasAttribute('download')) return;
        if (a.classList.contains('no-loader')) return;
        // Only intercept same-origin links
        try {
            const url = new URL(href, window.location.href);
            if (url.origin !== window.location.origin) return;
        } catch(err) { return; }
        // Don't re-intercept nav-links (handled above)
        if (a.classList.contains('nav-link')) return;
        // Start loader
        CfProgress.start();
    });

    // Intercept ALL form submissions
    document.addEventListener('submit', function(e) {
        const form = e.target;
        if (form.classList.contains('no-loader')) return;
        // Ajax forms that use fetch don't do full submits, skip them
        if (form.dataset.ajax) return;
        // Show loader on the submit button
        const btn = form.querySelector('[type=submit]');
        if (btn && !btn.classList.contains('no-loader')) {
            const origText = btn.innerHTML;
            btn.classList.add('cf-btn-loading');
            // Restore if user comes back (bfcache)
            window.addEventListener('pageshow', () => {
                btn.classList.remove('cf-btn-loading');
                btn.innerHTML = origText;
            }, { once: true });
        }
        CfProgress.start();
    });

    // Page refresh / back-forward cache
    window.addEventListener('pageshow', function(e) {
        CfProgress.done();
        // Remove all loading states on bfcache restore
        document.querySelectorAll('.cf-btn-loading').forEach(b => b.classList.remove('cf-btn-loading'));
        document.querySelectorAll('.loading-nav').forEach(l => l.classList.remove('loading-nav'));
    });

    // Browser back / forward
    window.addEventListener('popstate', function() {
        CfProgress.start();
    });

    // Before unload (refresh, close)
    window.addEventListener('beforeunload', function() {
        CfProgress.start();
    });

    // When page is fully loaded — complete the bar
    if (document.readyState === 'complete') {
        CfProgress.done();
    } else {
        window.addEventListener('load', () => CfProgress.done());
        // Also complete on DOMContentLoaded for faster perceived finish
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => CfProgress.done(), 200);
        });
    }

    // ── Button loading helper ─────────────────────────────────────
    // Call: cfBtnLoad(btn, true/false)
    function cfBtnLoad(btn, loading) {
        if (loading) {
            btn.classList.add('cf-btn-loading');
            btn.disabled = true;
        } else {
            btn.classList.remove('cf-btn-loading');
            btn.disabled = false;
        }
    }

    // ── API key security: redact keys in DOM ──────────────────────
    // Prevent key values from appearing in page source via accidental echo
    document.addEventListener('DOMContentLoaded', function() {
        // Mask any input[type=password] that look like API keys
        document.querySelectorAll('input[type=password][autocomplete=off]').forEach(inp => {
            inp.addEventListener('focus', () => {
                if (inp.value && inp.value.length > 10) {
                    inp.setAttribute('data-has-value', '1');
                }
            });
        });

        // Prevent right-click inspect on API key inputs
        document.querySelectorAll('#aiKeyInp, #gmailPass').forEach(inp => {
            inp.addEventListener('contextmenu', e => e.preventDefault());
            // Clear clipboard after paste to prevent key leaking
            inp.addEventListener('paste', () => {
                setTimeout(() => {
                    try { navigator.clipboard.writeText(''); } catch(err) {}
                }, 100);
            });
        });
    });

    // ── Global search ─────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function() {
        const searchEl = document.getElementById('global-search');
        if (searchEl) {
            searchEl.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && this.value.trim()) {
                    CfProgress.start();
                    window.location = '<?= APP_URL ?>/pages/applications.php?q=' + encodeURIComponent(this.value);
                }
            });
        }
    });

    // ── Keyboard shortcuts ────────────────────────────────────────
    document.addEventListener('keydown', function(e) {
        // Ignore when typing in inputs
        if (['INPUT','TEXTAREA','SELECT'].includes(document.activeElement?.tagName)) return;

        // g + d = Dashboard
        // g + a = Applications
        // g + k = Kanban
        // g + n = Notes
        // g + g = Gmail
        if (e.key === 'g') {
            window._cfGPressed = true;
            setTimeout(() => { window._cfGPressed = false; }, 800);
            return;
        }
        if (window._cfGPressed) {
            const map = { d: 'dashboard', a: 'applications', k: 'kanban', n: 'notes', g: 'gmail', c: 'cover_letters', r: 'resumes' };
            const page = map[e.key];
            if (page) {
                window._cfGPressed = false;
                cfNavigate('<?= APP_URL ?>/pages/' + page + '.php');
            }
        }

        // ? = show shortcuts
        if (e.key === '?' && !e.shiftKey) {
            showToast('Shortcuts: g+d Dashboard · g+a Apps · g+k Kanban · g+g Gmail · g+c Cover Letters', 'info', 6000);
        }
    });

    // ── Confirm dangerous actions ─────────────────────────────────
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[data-confirm]').forEach(el => {
            el.addEventListener('click', function(e) {
                if (!confirm(this.dataset.confirm)) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                }
            });
        });
    });

    // ── Fetch wrapper with CSRF + loading ─────────────────────────
    // Use: cfFetch(url, formData) for all AJAX calls
    async function cfFetch(url, formData, showLoader = false) {
        if (showLoader) CfProgress.set(30);
        try {
            const r = await fetch(url, { method: 'POST', body: formData });
            if (showLoader) CfProgress.done();
            return r;
        } catch(err) {
            if (showLoader) CfProgress.done();
            showToast('Network error. Please check your connection.', 'error');
            throw err;
        }
    }
    </script>

<?php } ?>
