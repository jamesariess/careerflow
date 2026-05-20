<?php
require_once '../components/layout.php';
require_once '../includes/ai.php';
require_once '../includes/security.php';
cf_layout_head('Settings');
cf_layout_sidebar('settings');

$uid    = (int)$user['id'];
$msg    = ''; $msgType = '';
$dbUser = DB::one('SELECT * FROM users WHERE id=?', [$uid]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) die('CSRF error');
    $action = $_POST['_action'] ?? '';

    if ($action === 'update_profile') {
        $name     = trim($_POST['name']           ?? '');
        $fullName = trim($_POST['full_name']      ?? '');
        $phone    = trim($_POST['phone']          ?? '');
        $linkedin = trim($_POST['linkedin_url']   ?? '');
        $jobPref  = trim($_POST['job_title_pref'] ?? '');
        $skills   = trim($_POST['skills_summary'] ?? '');
        $yearsExp = (int)($_POST['years_exp']     ?? 0);
        $timezone = trim($_POST['timezone']       ?? 'UTC');
        $theme    = in_array($_POST['theme'] ?? '', ['light','dark']) ? $_POST['theme'] : 'dark';
        $currency = strtoupper(trim($_POST['currency'] ?? 'USD'));
        if (strlen($name) < 2) { $msg = 'Name must be at least 2 characters.'; $msgType = 'error'; }
        else {
            DB::run('UPDATE users SET name=?, full_name=?, phone=?, linkedin_url=?, job_title_pref=?,
                     skills_summary=?, years_exp=?, timezone=?, theme=?, currency=? WHERE id=?',
                [$name,$fullName,$phone,$linkedin,$jobPref,$skills,$yearsExp,$timezone,$theme,$currency,$uid]);
            $_SESSION['user']['name']  = $name;
            $_SESSION['user']['theme'] = $theme;
            $dbUser  = DB::one('SELECT * FROM users WHERE id=?', [$uid]);
            log_activity($uid,'update_profile','Profile updated');
            $msg = 'Profile saved!'; $msgType = 'success';
        }
    }
    if ($action === 'update_ai') {
        $apiKey = trim($_POST['ai_api_key'] ?? '');
        $model  = trim($_POST['ai_model']   ?? 'mistralai/mistral-7b-instruct:free');
        if ($apiKey) {
            // New key provided — encrypt and save
            $encKey = Security::encrypt($apiKey);
            DB::run('UPDATE users SET ai_api_key=?, ai_model=? WHERE id=?', [$encKey,$model,$uid]);
        } else {
            // No new key — keep existing, just update model
            DB::run('UPDATE users SET ai_model=? WHERE id=?', [$model,$uid]);
        }
        $dbUser = DB::one('SELECT * FROM users WHERE id=?', [$uid]);
        log_activity($uid,'update_ai','AI settings updated');
        $msg = 'AI settings saved!'; $msgType = 'success';
    }
    /* ── AJAX API key test (returns JSON) ── */
    if ($action === 'test_ai_ajax') {
        header('Content-Type: application/json');
        $apiKey = trim($_POST['ai_api_key'] ?? $dbUser['ai_api_key'] ?? '');
        $model  = trim($_POST['ai_model']   ?? $dbUser['ai_model']   ?? '');
        if (!$apiKey) {
            echo json_encode(['ok'=>false,'message'=>'Enter an API key first.']); exit;
        }
        // Decrypt if stored encrypted
        $decrypted = Security::decrypt($apiKey);
        if ($decrypted && $decrypted !== $apiKey) $apiKey = $decrypted;
        $result = AI::testKey($apiKey, $model ?: 'mistralai/mistral-7b-instruct:free');
        echo json_encode($result); exit;
    }

    if ($action === 'test_ai') {
        $apiKey = trim($_POST['ai_api_key'] ?? $dbUser['ai_api_key'] ?? '');
        $model  = trim($_POST['ai_model']   ?? $dbUser['ai_model']   ?? '');
        if (!$apiKey) { $msg = 'Enter an API key first.'; $msgType = 'error'; }
        else {
            $result  = AI::testKey($apiKey, $model ?: 'mistralai/mistral-7b-instruct:free');
            $msg     = $result['message'];
            $msgType = $result['ok'] ? 'success' : 'error';
        }
    }
    if ($action === 'update_gmail') {
        $gmailAddr = trim($_POST['gmail_address']      ?? '');
        $gmailPass = trim($_POST['gmail_app_password'] ?? '');
        if ($gmailAddr && !filter_var($gmailAddr, FILTER_VALIDATE_EMAIL)) {
            $msg = 'Invalid Gmail address.'; $msgType = 'error';
        } else {
            if ($gmailPass) {
                $encPass = Security::encrypt($gmailPass);
                DB::run('UPDATE users SET gmail_address=?, gmail_app_password=? WHERE id=?', [$gmailAddr,$encPass,$uid]);
            } else {
                DB::run('UPDATE users SET gmail_address=? WHERE id=?', [$gmailAddr,$uid]);
            }
            $dbUser = DB::one('SELECT * FROM users WHERE id=?', [$uid]);
            log_activity($uid,'update_gmail','Gmail settings updated');
            $msg = 'Gmail settings saved!'; $msgType = 'success';
        }
    }
    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (!verify_pw($current, $dbUser['password']))   { $msg='Current password incorrect.'; $msgType='error'; }
        elseif (strlen($new) < 8)                        { $msg='Min 8 characters.';           $msgType='error'; }
        elseif ($new !== $confirm)                       { $msg='Passwords do not match.';      $msgType='error'; }
        else {
            DB::run('UPDATE users SET password=? WHERE id=?', [hash_pw($new),$uid]);
            log_activity($uid,'change_password','Password changed');
            $msg = 'Password changed!'; $msgType = 'success';
        }
    }
    if ($action === 'delete_account') {
        if (($_POST['confirm_delete'] ?? '') === 'DELETE') {
            $res = DB::all('SELECT filename FROM resumes WHERE user_id=?', [$uid]);
            foreach ($res as $r) { $p=CF_UPLOAD_DIR.$r['filename']; if(file_exists($p)) unlink($p); }
            DB::run('DELETE FROM users WHERE id=?', [$uid]);
            logout_user();
            header('Location: '.APP_URL.'/pages/login.php'); exit;
        } else { $msg='Type DELETE to confirm.'; $msgType='error'; }
    }
}

$dbUser = DB::one('SELECT * FROM users WHERE id=?', [$uid]);
$stats  = DB::one("SELECT COUNT(*) AS total, SUM(status='Hired') AS hired FROM applications WHERE user_id=?", [$uid]);
$resumeCount = (int)(DB::one('SELECT COUNT(*) AS n FROM resumes WHERE user_id=?',[$uid])['n']??0);
$noteCount   = (int)(DB::one('SELECT COUNT(*) AS n FROM notes   WHERE user_id=?',[$uid])['n']??0);

$timezones = ['UTC','America/New_York','America/Chicago','America/Denver','America/Los_Angeles',
    'America/Toronto','America/Vancouver','Europe/London','Europe/Paris','Europe/Berlin',
    'Europe/Moscow','Asia/Dubai','Asia/Kolkata','Asia/Singapore','Asia/Manila',
    'Asia/Tokyo','Asia/Shanghai','Australia/Sydney','Pacific/Auckland'];

$currencies = [
    'USD'=>['name'=>'US Dollar','symbol'=>'$'],       'EUR'=>['name'=>'Euro','symbol'=>'€'],
    'GBP'=>['name'=>'British Pound','symbol'=>'£'],   'PHP'=>['name'=>'Philippine Peso','symbol'=>'₱'],
    'JPY'=>['name'=>'Japanese Yen','symbol'=>'¥'],    'CNY'=>['name'=>'Chinese Yuan','symbol'=>'¥'],
    'INR'=>['name'=>'Indian Rupee','symbol'=>'₹'],    'KRW'=>['name'=>'South Korean Won','symbol'=>'₩'],
    'SGD'=>['name'=>'Singapore Dollar','symbol'=>'S$'],'AUD'=>['name'=>'Australian Dollar','symbol'=>'A$'],
    'CAD'=>['name'=>'Canadian Dollar','symbol'=>'CA$'],'CHF'=>['name'=>'Swiss Franc','symbol'=>'Fr'],
    'MYR'=>['name'=>'Malaysian Ringgit','symbol'=>'RM'],'THB'=>['name'=>'Thai Baht','symbol'=>'฿'],
    'IDR'=>['name'=>'Indonesian Rupiah','symbol'=>'Rp'],'VND'=>['name'=>'Vietnamese Dong','symbol'=>'₫'],
    'BRL'=>['name'=>'Brazilian Real','symbol'=>'R$'], 'MXN'=>['name'=>'Mexican Peso','symbol'=>'Mex$'],
    'ZAR'=>['name'=>'South African Rand','symbol'=>'R'],'AED'=>['name'=>'UAE Dirham','symbol'=>'د.إ'],
    'SAR'=>['name'=>'Saudi Riyal','symbol'=>'﷼'],     'NZD'=>['name'=>'New Zealand Dollar','symbol'=>'NZ$'],
    'HKD'=>['name'=>'Hong Kong Dollar','symbol'=>'HK$'],'TWD'=>['name'=>'Taiwan Dollar','symbol'=>'NT$'],
    'PKR'=>['name'=>'Pakistani Rupee','symbol'=>'Rs'],'BDT'=>['name'=>'Bangladeshi Taka','symbol'=>'৳'],
];

$freeModels = [
    'mistralai/mistral-7b-instruct:free'     => 'Mistral 7B Instruct (Fast · recommended)',
    'meta-llama/llama-3.1-8b-instruct:free'  => 'Llama 3.1 8B (Meta)',
    'google/gemma-2-9b-it:free'              => 'Gemma 2 9B (Google)',
    'microsoft/phi-3-mini-128k-instruct:free' => 'Phi-3 Mini 128K (Microsoft)',
    'openchat/openchat-7b:free'              => 'OpenChat 7B',
    'qwen/qwen-2-7b-instruct:free'           => 'Qwen 2 7B (Alibaba)',
    'mistralai/mistral-nemo:free'             => 'Mistral Nemo (Latest)',
];

$currCode = $dbUser['currency'] ?? 'USD';
$currSym  = $currencies[$currCode]['symbol'] ?? '$';
$aiLogs   = DB::all('SELECT * FROM ai_logs WHERE user_id=? ORDER BY created_at DESC LIMIT 5', [$uid]);
$emailCount = (int)(DB::one('SELECT COUNT(*) AS n FROM gmail_emails WHERE user_id=?',[$uid])['n']??0);
?>
<?php if ($msg): ?>
<script>document.addEventListener('DOMContentLoaded',()=>showToast(<?= json_encode($msg) ?>,'<?= $msgType ?>'));</script>
<?php endif; ?>
<style>
.tab-btn{padding:10px 18px;background:none;border:none;border-bottom:2px solid transparent;cursor:pointer;font-size:13px;font-weight:600;color:var(--muted);transition:all .2s;white-space:nowrap;font-family:'DM Sans',sans-serif;}
.tab-btn.active{color:#fff;border-color:var(--accent);}
.tab-btn:hover:not(.active){color:rgba(255,255,255,.7);}
.ss{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:24px;margin-bottom:16px;}
.ss h2{font-size:15px;font-weight:700;color:#fff;margin-bottom:4px;}
.ss .sd{font-size:13px;color:var(--muted);margin-bottom:20px;line-height:1.6;}
.key-wrap{position:relative;}
.key-wrap input{padding-right:44px;}
.key-eye{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);}
.model-card{border:1px solid var(--border);border-radius:9px;padding:12px 14px;cursor:pointer;transition:all .18s;display:flex;align-items:center;gap:10px;}
.model-card:hover{border-color:var(--accent);background:rgba(108,99,255,.06);}
.model-card.selected{border-color:var(--accent);background:rgba(108,99,255,.12);}
.curr-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(152px,1fr));gap:8px;}
.curr-card{border:1px solid var(--border);border-radius:8px;padding:10px 12px;cursor:pointer;transition:all .18s;display:flex;align-items:center;gap:8px;}
.curr-card:hover{border-color:var(--accent);background:rgba(108,99,255,.06);}
.curr-card.selected{border-color:var(--accent);background:rgba(108,99,255,.14);}
.curr-sym{width:30px;height:30px;border-radius:7px;background:rgba(108,99,255,.15);display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:var(--accent);flex-shrink:0;}
.info-box{background:rgba(108,99,255,.08);border:1px solid rgba(108,99,255,.2);border-radius:10px;padding:14px 16px;font-size:13px;color:rgba(255,255,255,.7);line-height:1.6;}
.sb{width:22px;height:22px;border-radius:50%;background:var(--accent);color:#fff;font-size:11px;font-weight:700;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;}
</style>

<div style="max-width:880px">

<!-- Profile header -->
<div class="ss" style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;margin-bottom:20px">
  <div style="width:66px;height:66px;border-radius:50%;background:linear-gradient(135deg,var(--accent),#9B5DE5);display:flex;align-items:center;justify-content:center;font-size:26px;font-weight:700;color:#fff;font-family:'Syne',sans-serif;flex-shrink:0">
    <?= strtoupper(mb_substr($dbUser['name'],0,1)) ?>
  </div>
  <div style="flex:1">
    <div style="font-size:18px;font-weight:700;color:#fff;font-family:'Syne',sans-serif"><?= htmlspecialchars($dbUser['name']) ?></div>
    <div style="font-size:13px;color:var(--muted)"><?= htmlspecialchars($dbUser['email']) ?></div>
    <div style="font-size:11px;color:var(--muted);margin-top:2px">
      Member since <?= date('F Y',strtotime($dbUser['created_at'])) ?> &nbsp;·&nbsp;
      Currency: <strong style="color:var(--accent2)"><?= htmlspecialchars($currCode) ?> <?= $currSym ?></strong>
    </div>
  </div>
  <div style="display:flex;gap:20px;text-align:center">
    <?php foreach([['Apps',$stats['total']],['Hired',$stats['hired']],['Resumes',$resumeCount],['Notes',$noteCount]] as [$l,$v]): ?>
    <div>
      <div style="font-size:22px;font-weight:700;color:#fff;font-family:'Syne',sans-serif"><?= (int)$v ?></div>
      <div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.4px"><?= $l ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Tabs -->
<div style="display:flex;border-bottom:1px solid var(--border);margin-bottom:22px;overflow-x:auto">
  <?php foreach(['profile'=>'👤 Profile','currency'=>'💱 Currency','ai'=>'🤖 AI Settings','gmail'=>'📧 Gmail','security'=>'🔒 Security','danger'=>'⚠️ Danger'] as $t=>$l): ?>
  <button onclick="switchTab('<?= $t ?>')" id="tab-<?= $t ?>" class="tab-btn"><?= $l ?></button>
  <?php endforeach; ?>
</div>

<!-- ═══ PROFILE ═══════════════════════════════════════════════ -->
<div id="panel-profile" class="tab-panel" style="display:none">
  <form method="POST">
    <input type="hidden" name="_csrf"    value="<?= csrf_token() ?>">
    <input type="hidden" name="_action" value="update_profile">
    <input type="hidden" name="currency" value="<?= htmlspecialchars($currCode) ?>">
    <div class="ss">
      <h2>Basic Information</h2>
      <p class="sd">Used to personalize your experience and auto-fill cover letters with AI.</p>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <div><label class="cf-label">Display Name *</label><input type="text" name="name" class="cf-input" required value="<?= htmlspecialchars($dbUser['name']) ?>"></div>
        <div><label class="cf-label">Full Name (for cover letters)</label><input type="text" name="full_name" class="cf-input" value="<?= htmlspecialchars($dbUser['full_name']??'') ?>" placeholder="Juan dela Cruz"></div>
        <div><label class="cf-label">Phone</label><input type="text" name="phone" class="cf-input" value="<?= htmlspecialchars($dbUser['phone']??'') ?>" placeholder="+63 912 345 6789"></div>
        <div><label class="cf-label">LinkedIn URL</label><input type="url" name="linkedin_url" class="cf-input" value="<?= htmlspecialchars($dbUser['linkedin_url']??'') ?>" placeholder="https://linkedin.com/in/name"></div>
        <div><label class="cf-label">Target Job Title</label><input type="text" name="job_title_pref" class="cf-input" value="<?= htmlspecialchars($dbUser['job_title_pref']??'') ?>" placeholder="Senior Software Engineer"></div>
        <div><label class="cf-label">Years of Experience</label><input type="number" name="years_exp" class="cf-input" value="<?= (int)($dbUser['years_exp']??0) ?>" min="0" max="50"></div>
        <div style="grid-column:1/-1"><label class="cf-label">Skills &amp; Background <span style="color:var(--muted);font-size:10px;text-transform:none">(AI uses this for cover letters)</span></label><textarea name="skills_summary" class="cf-input" rows="3" placeholder="Full-stack dev, 5 yrs PHP/React, led teams of 4..."><?= htmlspecialchars($dbUser['skills_summary']??'') ?></textarea></div>
        <div><label class="cf-label">Timezone</label><select name="timezone" class="cf-input"><?php foreach($timezones as $tz): ?><option value="<?= $tz ?>" <?= ($dbUser['timezone']??'UTC')===$tz?'selected':'' ?>><?= $tz ?></option><?php endforeach; ?></select></div>
        <div><label class="cf-label">Theme</label><select name="theme" class="cf-input"><option value="dark" <?= ($dbUser['theme']??'dark')==='dark'?'selected':'' ?>>🌙 Dark</option><option value="light" <?= ($dbUser['theme']??'dark')==='light'?'selected':'' ?>>☀️ Light</option></select></div>
      </div>
      <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border);display:flex;gap:10px;justify-content:flex-end">
        <a href="applications.php?export=csv" class="btn btn-secondary">⬇️ Export CSV</a>
        <button type="submit" class="btn btn-primary" onclick="cfBtnLoad(this,true)"><span class="btn-label">Save Profile</span></button>
      </div>
    </div>
  </form>
</div>

<!-- ═══ CURRENCY ═══════════════════════════════════════════════ -->
<div id="panel-currency" class="tab-panel" style="display:none">
  <div class="ss">
    <h2>Currency Preference</h2>
    <p class="sd">All salary figures across the entire app — dashboard, analytics, applications, and cover letters — will display in your chosen currency. Changing this does not convert amounts; it only changes the label.</p>
    <div class="info-box" style="margin-bottom:18px">
      Currently: <strong style="color:#fff"><?= htmlspecialchars($currencies[$currCode]['name']??'US Dollar') ?></strong>
      &nbsp;<span style="color:var(--accent2)">(<?= htmlspecialchars($currCode) ?> <?= $currSym ?>)</span>
    </div>
    <form method="POST" id="currencyForm">
      <input type="hidden" name="_csrf"    value="<?= csrf_token() ?>">
      <input type="hidden" name="_action" value="update_profile">
      <!-- Pass all profile fields unchanged -->
      <input type="hidden" name="name"           value="<?= htmlspecialchars($dbUser['name']) ?>">
      <input type="hidden" name="full_name"       value="<?= htmlspecialchars($dbUser['full_name']??'') ?>">
      <input type="hidden" name="phone"           value="<?= htmlspecialchars($dbUser['phone']??'') ?>">
      <input type="hidden" name="linkedin_url"    value="<?= htmlspecialchars($dbUser['linkedin_url']??'') ?>">
      <input type="hidden" name="job_title_pref"  value="<?= htmlspecialchars($dbUser['job_title_pref']??'') ?>">
      <input type="hidden" name="skills_summary"  value="<?= htmlspecialchars($dbUser['skills_summary']??'') ?>">
      <input type="hidden" name="years_exp"       value="<?= (int)($dbUser['years_exp']??0) ?>">
      <input type="hidden" name="timezone"        value="<?= htmlspecialchars($dbUser['timezone']??'UTC') ?>">
      <input type="hidden" name="theme"           value="<?= htmlspecialchars($dbUser['theme']??'dark') ?>">
      <input type="hidden" name="currency" id="selCurr" value="<?= htmlspecialchars($currCode) ?>">

      <input type="text" id="currSearch" oninput="filterCurr(this.value)" placeholder="🔍 Search currency (e.g. peso, dollar, euro)…" class="cf-input" style="margin-bottom:14px">
      <div class="curr-grid" id="currGrid">
        <?php foreach($currencies as $code=>$ci): $sel=($currCode===$code); ?>
        <div class="curr-card <?= $sel?'selected':'' ?>" id="curr-<?= $code ?>" onclick="selCurrency('<?= $code ?>')" data-search="<?= strtolower($ci['name'].' '.$code) ?>">
          <div class="curr-sym"><?= htmlspecialchars($ci['symbol']) ?></div>
          <div style="min-width:0">
            <div style="font-size:12px;font-weight:700;color:<?= $sel?'#fff':'rgba(255,255,255,.8)' ?>"><?= $code ?></div>
            <div style="font-size:10px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($ci['name']) ?></div>
          </div>
          <?php if($sel): ?>
          <svg style="margin-left:auto;flex-shrink:0" width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="var(--accent)" stroke-width="2.5" stroke-linecap="round"/></svg>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <div style="margin-top:18px;padding-top:16px;border-top:1px solid var(--border);display:flex;justify-content:flex-end">
        <button type="submit" class="btn btn-primary" onclick="cfBtnLoad(this,true)"><span class="btn-label">💾 Save Currency</span></button>
      </div>
    </form>
  </div>
</div>

<!-- ═══ AI SETTINGS ═══════════════════════════════════════════ -->
<div id="panel-ai" class="tab-panel" style="display:none">
  <div class="ss">
    <h2>AI Provider — OpenRouter (100% Free)</h2>
    <p class="sd">OpenRouter gives access to 50+ AI models via a single free API key. No credit card required.</p>
    <div class="info-box" style="margin-bottom:20px">
      <div style="display:flex;flex-direction:column;gap:8px">
        <div style="display:flex;align-items:flex-start;gap:10px"><span class="sb">1</span> Visit <a href="https://openrouter.ai/keys" target="_blank" style="color:var(--accent)">openrouter.ai/keys</a> and create a free account</div>
        <div style="display:flex;align-items:flex-start;gap:10px"><span class="sb">2</span> Click <strong style="color:#fff">Create Key</strong> → copy the key starting with <code style="color:var(--accent2);background:rgba(0,0,0,.3);padding:1px 5px;border-radius:3px">sk-or-v1-…</code></div>
        <div style="display:flex;align-items:flex-start;gap:10px"><span class="sb">3</span> Paste it below, pick a model, click Save &amp; Test</div>
      </div>
    </div>
    <form method="POST" id="aiForm">
      <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="_action" value="update_ai">
      <div style="margin-bottom:16px">
        <label class="cf-label">OpenRouter API Key</label>
        <div class="key-wrap">
          <input type="password" name="ai_api_key" id="aiKeyInp" class="cf-input" value="<?= htmlspecialchars($dbUser['ai_api_key']??'') ?>" placeholder="sk-or-v1-xxxxxxxxxxxxxxxxxxxxxxxx" autocomplete="off">
          <button type="button" class="key-eye" onclick="toggleKey('aiKeyInp')">👁</button>
        </div>
      </div>
      <div style="margin-bottom:20px">
        <label class="cf-label">Free AI Model</label>
        <div style="display:flex;flex-direction:column;gap:8px" id="modelGrid">
          <?php $selModel=$dbUser['ai_model']??'mistralai/mistral-7b-instruct:free';
          foreach($freeModels as $mid=>$mname): $s=$selModel===$mid; ?>
          <div class="model-card <?= $s?'selected':'' ?>" onclick="selModel(<?= json_encode($mid) ?>)" data-id="<?= htmlspecialchars($mid) ?>">
            <div style="width:10px;height:10px;border-radius:50%;border:2px solid <?= $s?'var(--accent)':'var(--border)' ?>;background:<?= $s?'var(--accent)':'transparent' ?>;flex-shrink:0;transition:all .15s"></div>
            <div style="flex:1;min-width:0">
              <div style="font-size:13px;font-weight:<?= $s?'600':'400' ?>;color:<?= $s?'#fff':'rgba(255,255,255,.7)' ?>"><?= htmlspecialchars($mname) ?></div>
              <div style="font-size:10px;color:var(--muted);font-family:monospace"><?= htmlspecialchars($mid) ?></div>
            </div>
            <?php if($s): ?><svg style="flex-shrink:0" width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="var(--accent)" stroke-width="2.5" stroke-linecap="round"/></svg><?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <input type="hidden" name="ai_model" id="selModel" value="<?= htmlspecialchars($selModel) ?>">
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <button type="submit" class="btn btn-primary" onclick="cfBtnLoad(this,true)"><span class="btn-label">Save AI Settings</span></button>
        <button type="button" onclick="testAI()" class="btn btn-secondary" id="testBtn">🧪 Test Connection</button>
        <span id="testResult" style="font-size:13px;display:none;padding:7px 12px;border-radius:8px"></span>
      </div>
    </form>
  </div>
  <?php if(!empty($aiLogs)): ?>
  <div class="ss">
    <h2>Recent AI Usage</h2>
    <p class="sd">Last 5 AI interactions logged from your account.</p>
    <?php foreach($aiLogs as $log): ?>
    <div style="padding:10px 0;border-bottom:1px solid rgba(255,255,255,.05);display:flex;align-items:center;gap:10px">
      <span style="font-size:10px;padding:2px 8px;border-radius:20px;background:rgba(108,99,255,.15);color:#a78bfa;font-weight:600;white-space:nowrap"><?= htmlspecialchars($log['feature']) ?></span>
      <span style="font-size:12px;color:var(--muted);flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars(mb_substr($log['prompt']??'',0,80)) ?></span>
      <span style="font-size:11px;color:var(--muted);white-space:nowrap"><?= date('M j g:i A',strtotime($log['created_at'])) ?></span>
      <span style="font-size:10px;color:var(--accent2);white-space:nowrap"><?= $log['tokens_used'] ?>tok</span>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- ═══ GMAIL ══════════════════════════════════════════════════ -->
<div id="panel-gmail" class="tab-panel" style="display:none">
  <div class="ss">
    <h2>Gmail Integration (IMAP + App Password)</h2>
    <p class="sd">Connect Gmail to auto-detect job emails, update application statuses, and generate AI replies. Uses secure IMAP — your password is never shared.</p>
    <div class="info-box" style="margin-bottom:20px">
      <strong style="color:#fff">How to get a Gmail App Password:</strong>
      <div style="display:flex;flex-direction:column;gap:8px;margin-top:8px">
        <div style="display:flex;align-items:flex-start;gap:10px"><span class="sb">1</span> Enable <strong style="color:#fff">2-Step Verification</strong> on your Google account</div>
        <div style="display:flex;align-items:flex-start;gap:10px"><span class="sb">2</span> Go to <a href="https://myaccount.google.com/apppasswords" target="_blank" style="color:var(--accent)">myaccount.google.com/apppasswords</a></div>
        <div style="display:flex;align-items:flex-start;gap:10px"><span class="sb">3</span> App name: <strong style="color:#fff">CareerFlow</strong> → click Create → copy the 16-char password</div>
        <div style="display:flex;align-items:flex-start;gap:10px"><span class="sb">4</span> Paste it below and click Save</div>
      </div>
      <div style="margin-top:10px;font-size:12px;color:rgba(255,255,255,.4)">⚠️ Requires PHP <code style="background:rgba(0,0,0,.3);padding:1px 4px;border-radius:3px">imap</code> extension: <code style="background:rgba(0,0,0,.3);padding:1px 5px;border-radius:3px">sudo apt install php-imap && sudo service apache2 restart</code></div>
    </div>
    <form method="POST">
      <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="_action" value="update_gmail">
      <div style="display:grid;gap:14px">
        <div><label class="cf-label">Gmail Address</label><input type="email" name="gmail_address" class="cf-input" value="<?= htmlspecialchars($dbUser['gmail_address']??'') ?>" placeholder="yourname@gmail.com"></div>
        <div><label class="cf-label">App Password <span style="color:var(--muted);font-size:10px;text-transform:none">(16 chars)</span></label>
          <div class="key-wrap">
            <input type="password" name="gmail_app_password" id="gmailPass" class="cf-input" value="<?= htmlspecialchars($dbUser['gmail_app_password']??'') ?>" placeholder="xxxx xxxx xxxx xxxx" autocomplete="off">
            <button type="button" class="key-eye" onclick="toggleKey('gmailPass')">👁</button>
          </div>
          <p style="font-size:11px;color:var(--muted);margin-top:5px">Leave blank to keep existing password.</p>
        </div>
      </div>
      <div style="margin-top:18px;padding-top:16px;border-top:1px solid var(--border);display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <button type="submit" class="btn btn-primary" onclick="cfBtnLoad(this,true)"><span class="btn-label">Save Gmail Settings</span></button>
        <?php if(!empty($dbUser['gmail_address'])): ?>
        <a href="gmail.php" class="btn btn-secondary">📧 Open Gmail Inbox</a>
        <span style="font-size:12px;color:var(--success)">✓ <?= htmlspecialchars($dbUser['gmail_address']) ?></span>
        <?php else: ?><span style="font-size:12px;color:var(--muted)">Not connected</span><?php endif; ?>
      </div>
    </form>
  </div>
  <?php if(!empty($dbUser['gmail_sync_at'])): ?>
  <div class="ss">
    <h2>Sync Status</h2>
    <div style="display:flex;align-items:center;gap:12px">
      <div style="width:9px;height:9px;border-radius:50%;background:var(--success)"></div>
      <span style="font-size:13px;color:var(--text)">Last synced: <strong><?= date('M j, Y g:i A',strtotime($dbUser['gmail_sync_at'])) ?></strong></span>
      <a href="gmail.php" class="btn btn-secondary btn-sm" style="margin-left:auto">Sync Now</a>
    </div>
    <div style="font-size:12px;color:var(--muted);margin-top:6px"><?= $emailCount ?> emails synced total</div>
  </div>
  <?php endif; ?>
</div>

<!-- ═══ SECURITY ═══════════════════════════════════════════════ -->
<div id="panel-security" class="tab-panel" style="display:none">
  <div class="ss">
    <h2>Change Password</h2>
    <p class="sd">Use a strong password of at least 8 characters.</p>
    <form method="POST">
      <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="_action" value="change_password">
      <div style="display:grid;gap:14px">
        <div><label class="cf-label">Current Password</label><input type="password" name="current_password" class="cf-input" required placeholder="••••••••"></div>
        <div><label class="cf-label">New Password</label><input type="password" name="new_password" class="cf-input" required placeholder="min 8 characters" id="newPw" oninput="checkPw()"></div>
        <div><label class="cf-label">Confirm New Password</label><input type="password" name="confirm_password" class="cf-input" required placeholder="••••••••" id="confPw" oninput="checkPw()"><p id="pwMsg" style="font-size:11px;margin-top:4px"></p></div>
      </div>
      <div style="margin-top:18px;padding-top:16px;border-top:1px solid var(--border);display:flex;justify-content:flex-end"><button type="submit" class="btn btn-primary" onclick="cfBtnLoad(this,true)"><span class="btn-label">Update Password</span></button></div>
    </form>
  </div>
  <div class="ss"><h2>Sessions</h2><p class="sd">Sign out all active sessions across all devices.</p><a href="logout.php" class="btn btn-secondary">Sign Out All Sessions</a></div>
</div>

<!-- ═══ DANGER ═════════════════════════════════════════════════ -->
<div id="panel-danger" class="tab-panel" style="display:none">
  <div class="ss" style="border-color:rgba(248,113,113,.25)">
    <h2 style="color:var(--danger)">⚠️ Delete Account</h2>
    <p class="sd">Permanently deletes your account, all applications, resumes, notes, emails, and uploaded files. <strong style="color:var(--danger)">Cannot be undone.</strong></p>
    <div style="background:rgba(248,113,113,.06);border:1px solid rgba(248,113,113,.15);border-radius:10px;padding:18px">
      <form method="POST" onsubmit="return confirm('Are you absolutely sure? EVERYTHING will be permanently deleted.')">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="_action" value="delete_account">
        <label class="cf-label" style="color:var(--danger)">Type DELETE to confirm</label>
        <input type="text" name="confirm_delete" class="cf-input" placeholder="DELETE" pattern="DELETE" required style="border-color:rgba(248,113,113,.3);margin-bottom:14px;max-width:220px">
        <br><button type="submit" class="btn btn-danger">Permanently Delete My Account</button>
      </form>
    </div>
  </div>
</div>

</div>

<script>
const PANELS=['profile','currency','ai','gmail','security','danger'];
function switchTab(t){
  PANELS.forEach(p=>{
    document.getElementById('panel-'+p).style.display=p===t?'block':'none';
    document.getElementById('tab-'+p).classList.toggle('active',p===t);
  });
  history.replaceState(null,'','settings.php#'+t);
}
// Init from hash
(function(){ const h=location.hash.replace('#',''); switchTab(PANELS.includes(h)?h:'profile'); })();

function toggleKey(id){const i=document.getElementById(id);i.type=i.type==='password'?'text':'password';}

// Currency
function selCurrency(code){
  document.querySelectorAll('.curr-card').forEach(c=>c.classList.remove('selected'));
  document.getElementById('curr-'+code)?.classList.add('selected');
  document.getElementById('selCurr').value=code;
}
function filterCurr(q){
  q=q.toLowerCase();
  document.querySelectorAll('.curr-card').forEach(c=>{
    c.style.display=c.dataset.search.includes(q)?'':'none';
  });
}

// AI model
function selModel(mid){
  document.querySelectorAll('#modelGrid .model-card').forEach(c=>{
    const isThis=c.dataset.id===mid;
    c.classList.toggle('selected',isThis);
    const dot=c.querySelector('div[style*="border-radius:50%"]')||c.querySelector('div:first-child');
    if(dot){dot.style.background=isThis?'var(--accent)':'transparent';dot.style.borderColor=isThis?'var(--accent)':'var(--border)';}
  });
  document.getElementById('selModel').value=mid;
}

// Test AI - inline result, no redirect
async function testAI(){
  const btn=document.getElementById('testBtn');
  const res=document.getElementById('testResult');
  btn.disabled=true; btn.textContent='⏳ Testing…';
  res.style.display='none';
  try {
    const fd=new FormData(document.getElementById('aiForm'));
    fd.set('_action','test_ai_ajax');
    const r=await fetch('settings.php',{method:'POST',body:fd});
    const j=await r.json();
    res.style.display='inline-flex';
    res.style.alignItems='center';
    res.style.gap='6px';
    if(j.ok){
      res.style.background='rgba(34,197,94,.12)';
      res.style.border='1px solid rgba(34,197,94,.25)';
      res.style.color='#4ade80';
      res.style.borderRadius='8px';
      res.textContent='✓ ' + j.message;
      showToast('AI connection successful!','success');
    } else {
      res.style.background='rgba(248,113,113,.12)';
      res.style.border='1px solid rgba(248,113,113,.25)';
      res.style.color='#f87171';
      res.style.borderRadius='8px';
      res.textContent='✕ ' + j.message;
      showToast('API test failed: ' + j.message,'error');
    }
  } catch(e){
    res.style.display='inline-flex';
    res.textContent='✕ Network error';
    res.style.color='#f87171';
    showToast('Network error','error');
  }
  btn.disabled=false; btn.textContent='🧪 Test Connection';
}

// Password match
function checkPw(){
  const np=document.getElementById('newPw').value;
  const cp=document.getElementById('confPw').value;
  const msg=document.getElementById('pwMsg');
  if(!cp){msg.textContent='';return;}
  if(cp===np){msg.textContent='✓ Passwords match';msg.style.color='var(--success)';}
  else{msg.textContent='✗ Do not match';msg.style.color='var(--danger)';}
}

// Ctrl+S
document.addEventListener('keydown',e=>{
  if((e.ctrlKey||e.metaKey)&&e.key==='s'){
    e.preventDefault();
    const active=PANELS.find(p=>document.getElementById('panel-'+p).style.display!=='none');
    document.querySelector('#panel-'+active+' form')?.submit();
  }
});
</script>

</main>
</body>
</html>
