<?php
require_once '../components/layout.php';
cf_layout_head('Settings');
cf_layout_sidebar('settings');

$uid = (int)$user['id'];
$msg = ''; $msgType = '';

/* Fetch latest user row */
$dbUser = DB::one('SELECT * FROM users WHERE id=?', [$uid]);

/* ── POST handlers ──────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) die('CSRF error');
    $action = $_POST['_action'] ?? '';

    /* Update profile */
    if ($action === 'update_profile') {
        $name     = trim($_POST['name'] ?? '');
        $timezone = trim($_POST['timezone'] ?? 'UTC');
        $theme    = in_array($_POST['theme'] ?? '', ['light','dark']) ? $_POST['theme'] : 'dark';

        if (strlen($name) < 2) {
            $msg = 'Name must be at least 2 characters.'; $msgType = 'error';
        } else {
            DB::run('UPDATE users SET name=?, timezone=?, theme=? WHERE id=?',
                [$name, $timezone, $theme, $uid]);
            /* Refresh session */
            $_SESSION['user']['name']  = $name;
            $_SESSION['user']['theme'] = $theme;
            $dbUser['name']  = $name;
            $dbUser['theme'] = $theme;
            log_activity($uid, 'update_profile', 'Profile updated');
            $msg = 'Profile saved!'; $msgType = 'success';
        }
    }

    /* Change password */
    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!verify_pw($current, $dbUser['password'])) {
            $msg = 'Current password is incorrect.'; $msgType = 'error';
        } elseif (strlen($new) < 8) {
            $msg = 'New password must be at least 8 characters.'; $msgType = 'error';
        } elseif ($new !== $confirm) {
            $msg = 'New passwords do not match.'; $msgType = 'error';
        } else {
            DB::run('UPDATE users SET password=? WHERE id=?', [hash_pw($new), $uid]);
            log_activity($uid, 'change_password', 'Password changed');
            $msg = 'Password changed successfully!'; $msgType = 'success';
        }
    }

    /* Delete account */
    if ($action === 'delete_account') {
        $confirm = $_POST['confirm_delete'] ?? '';
        if ($confirm === 'DELETE') {
            /* Remove upload files */
            $userResumes = DB::all('SELECT filename FROM resumes WHERE user_id=?', [$uid]);
            foreach ($userResumes as $r) {
                $path = CF_UPLOAD_DIR . $r['filename'];
                if (file_exists($path)) unlink($path);
            }
            DB::run('DELETE FROM users WHERE id=?', [$uid]);
            logout_user();
            header('Location: ' . APP_URL . '/pages/login.php');
            exit;
        } else {
            $msg = 'Type DELETE to confirm account deletion.'; $msgType = 'error';
        }
    }
}

/* Timezone list (abbreviated) */
$timezones = ['UTC','America/New_York','America/Chicago','America/Denver','America/Los_Angeles',
              'America/Toronto','America/Vancouver','Europe/London','Europe/Paris','Europe/Berlin',
              'Europe/Moscow','Asia/Dubai','Asia/Kolkata','Asia/Singapore','Asia/Tokyo',
              'Asia/Shanghai','Australia/Sydney','Pacific/Auckland'];

/* Stats summary for settings page */
$stats = DB::one("SELECT COUNT(*) AS total, SUM(status='Hired') AS hired FROM applications WHERE user_id=?", [$uid]);
$resumeCount = DB::one('SELECT COUNT(*) AS n FROM resumes WHERE user_id=?', [$uid])['n'];
$noteCount   = DB::one('SELECT COUNT(*) AS n FROM notes   WHERE user_id=?', [$uid])['n'];
?>
<?php if ($msg): ?>
<script>document.addEventListener('DOMContentLoaded',()=>showToast('<?= addslashes($msg) ?>','<?= $msgType ?>'));</script>
<?php endif; ?>

<div style="max-width:820px">

  <div style="margin-bottom:28px">
    <h1 style="font-size:22px;font-weight:700;color:#fff">Settings</h1>
    <p style="color:var(--muted);font-size:13px;margin-top:2px">Manage your account and preferences</p>
  </div>

  <!-- ── Account overview card ── -->
  <div class="card" style="padding:22px;margin-bottom:20px;display:flex;align-items:center;gap:18px">
    <div style="width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,var(--accent),#9B5DE5);display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:700;color:#fff;font-family:'Syne',sans-serif;flex-shrink:0">
      <?= strtoupper(mb_substr($dbUser['name'],0,1)) ?>
    </div>
    <div style="flex:1">
      <div style="font-size:18px;font-weight:700;color:#fff;font-family:'Syne',sans-serif"><?= htmlspecialchars($dbUser['name']) ?></div>
      <div style="font-size:13px;color:var(--muted)"><?= htmlspecialchars($dbUser['email']) ?></div>
      <div style="font-size:11px;color:var(--muted);margin-top:4px">Member since <?= date('F Y', strtotime($dbUser['created_at'])) ?></div>
    </div>
    <div style="display:flex;gap:20px;text-align:center">
      <?php foreach ([['Applications',$stats['total']],['Hired',$stats['hired']],['Resumes',$resumeCount],['Notes',$noteCount]] as [$l,$v]): ?>
      <div>
        <div style="font-size:20px;font-weight:700;color:#fff;font-family:'Syne',sans-serif"><?= (int)$v ?></div>
        <div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px"><?= $l ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ── Tabs ── -->
  <div style="display:flex;gap:4px;border-bottom:1px solid var(--border);margin-bottom:22px" id="settingsTabs">
    <?php foreach (['profile'=>'Profile','security'=>'Security','danger'=>'Danger Zone'] as $tab => $label): ?>
    <button onclick="switchTab('<?= $tab ?>')" id="tab-<?= $tab ?>"
      style="padding:10px 18px;background:none;border:none;cursor:pointer;font-size:13px;font-weight:600;border-bottom:2px solid transparent;transition:all .2s;color:var(--muted)"
      class="settings-tab">
      <?= $label ?>
    </button>
    <?php endforeach; ?>
  </div>

  <!-- ── Profile Tab ── -->
  <div id="panel-profile" class="settings-panel">
    <div class="card" style="padding:24px">
      <h2 style="font-size:15px;font-weight:700;color:#fff;margin-bottom:20px">Profile Information</h2>
      <form method="POST">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="_action" value="update_profile">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
          <div style="grid-column:1/-1">
            <label class="cf-label">Full Name</label>
            <input type="text" name="name" class="cf-input" required value="<?= htmlspecialchars($dbUser['name']) ?>">
          </div>
          <div style="grid-column:1/-1">
            <label class="cf-label">Email Address <span style="color:var(--muted);text-transform:none;font-size:11px">(read-only)</span></label>
            <input type="email" class="cf-input" value="<?= htmlspecialchars($dbUser['email']) ?>" disabled style="opacity:.5;cursor:not-allowed">
          </div>
          <div>
            <label class="cf-label">Timezone</label>
            <select name="timezone" class="cf-input">
              <?php foreach ($timezones as $tz): ?>
              <option value="<?= $tz ?>" <?= $dbUser['timezone']===$tz?'selected':'' ?>><?= $tz ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="cf-label">Theme</label>
            <select name="theme" class="cf-input">
              <option value="dark"  <?= $dbUser['theme']==='dark' ?'selected':'' ?>>🌙 Dark</option>
              <option value="light" <?= $dbUser['theme']==='light'?'selected':'' ?>>☀️ Light</option>
            </select>
          </div>
        </div>
        <div style="margin-top:20px;padding-top:18px;border-top:1px solid var(--border);display:flex;justify-content:flex-end">
          <button type="submit" class="btn btn-primary">Save Profile</button>
        </div>
      </form>
    </div>

    <!-- Export section -->
    <div class="card" style="padding:22px;margin-top:16px">
      <h2 style="font-size:15px;font-weight:700;color:#fff;margin-bottom:6px">Export Your Data</h2>
      <p style="font-size:13px;color:var(--muted);margin-bottom:16px">Download all your application data as CSV.</p>
      <a href="applications.php?export=csv" class="btn btn-secondary">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        Export Applications CSV
      </a>
    </div>
  </div>

  <!-- ── Security Tab ── -->
  <div id="panel-security" class="settings-panel" style="display:none">
    <div class="card" style="padding:24px">
      <h2 style="font-size:15px;font-weight:700;color:#fff;margin-bottom:20px">Change Password</h2>
      <form method="POST">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="_action" value="change_password">
        <div style="display:grid;gap:14px">
          <div>
            <label class="cf-label">Current Password</label>
            <input type="password" name="current_password" class="cf-input" required placeholder="••••••••" autocomplete="current-password">
          </div>
          <div>
            <label class="cf-label">New Password <span style="color:var(--muted);text-transform:none;font-size:11px">(min 8 chars)</span></label>
            <input type="password" name="new_password" class="cf-input" required placeholder="••••••••" autocomplete="new-password">
          </div>
          <div>
            <label class="cf-label">Confirm New Password</label>
            <input type="password" name="confirm_password" class="cf-input" required placeholder="••••••••" autocomplete="new-password">
          </div>
        </div>
        <div style="margin-top:20px;padding-top:18px;border-top:1px solid var(--border);display:flex;justify-content:flex-end">
          <button type="submit" class="btn btn-primary">Update Password</button>
        </div>
      </form>
    </div>

    <div class="card" style="padding:22px;margin-top:16px">
      <h2 style="font-size:15px;font-weight:700;color:#fff;margin-bottom:6px">Active Sessions</h2>
      <p style="font-size:13px;color:var(--muted);margin-bottom:14px">Sign out of all other sessions for security.</p>
      <a href="logout.php?all=1" class="btn btn-secondary">Sign Out All Sessions</a>
    </div>
  </div>

  <!-- ── Danger Tab ── -->
  <div id="panel-danger" class="settings-panel" style="display:none">
    <div class="card" style="padding:24px;border-color:rgba(248,113,113,.2)">
      <h2 style="font-size:15px;font-weight:700;color:var(--danger);margin-bottom:8px">⚠️ Delete Account</h2>
      <p style="font-size:13px;color:var(--muted);margin-bottom:18px;line-height:1.6">
        This will <strong style="color:var(--danger)">permanently delete</strong> your account, all applications, resumes, notes, and data. This cannot be undone.
      </p>
      <div style="background:rgba(248,113,113,.06);border:1px solid rgba(248,113,113,.15);border-radius:10px;padding:18px">
        <form method="POST" onsubmit="return confirm('Are you absolutely sure? This deletes EVERYTHING.')">
          <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="_action" value="delete_account">
          <label class="cf-label" style="color:var(--danger)">Type DELETE to confirm</label>
          <input type="text" name="confirm_delete" class="cf-input" placeholder="DELETE" pattern="DELETE" required
            style="border-color:rgba(248,113,113,.3);margin-bottom:14px">
          <button type="submit" class="btn btn-danger">Permanently Delete My Account</button>
        </form>
      </div>
    </div>
  </div>

</div><!-- /max-width -->

<script>
const tabs = ['profile','security','danger'];
function switchTab(tab) {
  tabs.forEach(t => {
    document.getElementById('panel-' + t).style.display = t === tab ? 'block' : 'none';
    const btn = document.getElementById('tab-' + t);
    btn.style.color       = t === tab ? '#fff' : 'var(--muted)';
    btn.style.borderColor = t === tab ? 'var(--accent)' : 'transparent';
  });
}
// Activate first tab on load
switchTab('profile');

// Keyboard shortcut: Ctrl+S saves profile
document.addEventListener('keydown', e => {
  if ((e.ctrlKey || e.metaKey) && e.key === 's') {
    e.preventDefault();
    const activePanel = document.querySelector('.settings-panel:not([style*="none"])');
    const form = activePanel?.querySelector('form');
    if (form) form.submit();
  }
});
</script>

</main>
</body>
</html>
