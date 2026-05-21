<?php
require_once '../includes/auth.php';
session_init();
if (auth_user()) { header('Location: ' . APP_URL . '/pages/dashboard.php'); exit; }

$token = trim($_GET['token'] ?? '');
$msg   = ''; $msgType = '';
$valid = false;

if ($token) {
    $userRow = DB::one('SELECT id FROM users WHERE reset_token=? AND reset_expires > NOW()', [$token]);
    $valid = (bool)$userRow;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { $msg = 'Invalid request.'; $msgType = 'error'; }
    else {
        $tkn  = $_POST['token'] ?? '';
        $pw   = $_POST['password']  ?? '';
        $pw2  = $_POST['password2'] ?? '';
        $row  = DB::one('SELECT id FROM users WHERE reset_token=? AND reset_expires > NOW()', [$tkn]);

        if (!$row)          { $msg = 'Reset link is invalid or expired.'; $msgType = 'error'; }
        elseif (strlen($pw) < 8) { $msg = 'Password must be at least 8 characters.'; $msgType = 'error'; }
        elseif ($pw !== $pw2)    { $msg = 'Passwords do not match.'; $msgType = 'error'; }
        else {
            DB::run('UPDATE users SET password=?, reset_token=NULL, reset_expires=NULL WHERE id=?',
                [hash_pw($pw), $row['id']]);
            log_activity($row['id'], 'password_reset', 'Password reset via email token');
            $msg = 'Password reset! You can now sign in.'; $msgType = 'success'; $valid = false;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Reset Password – CareerFlow</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  *{font-family:'DM Sans',sans-serif;box-sizing:border-box;}
  .brand{font-family:'Syne',sans-serif;}
  body{background:#0D0D14;background-image:radial-gradient(ellipse 80% 60% at 20% 10%,rgba(108,99,255,.18) 0%,transparent 60%);min-height:100vh;display:flex;align-items:center;justify-content:center;}
  .glass-card{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);backdrop-filter:blur(20px);border-radius:20px;}
  .input-field{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);color:#fff;border-radius:10px;padding:12px 16px;width:100%;font-size:14px;transition:all .2s;outline:none;}
  .input-field:focus{border-color:#6C63FF;background:rgba(108,99,255,.08);}
  .input-field::placeholder{color:rgba(255,255,255,.3);}
  .btn-primary{background:linear-gradient(135deg,#6C63FF,#9B5DE5);color:#fff;border:none;border-radius:10px;padding:13px;width:100%;font-size:15px;font-weight:600;cursor:pointer;transition:all .25s;}
  .btn-primary:hover{opacity:.88;}
  label{font-size:13px;color:rgba(255,255,255,.55);font-weight:500;margin-bottom:6px;display:block;}
  #cf-bar{position:fixed;top:0;left:0;height:3px;width:0;background:linear-gradient(90deg,#6C63FF,#4ECDC4);z-index:9999;transition:width .25s ease,opacity .3s ease;box-shadow:0 0 10px #6C63FF;}
  .btn-primary.loading{opacity:.75;pointer-events:none;}
  .btn-primary.loading::after{content:"";display:inline-block;width:13px;height:13px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .65s linear infinite;margin-left:8px;vertical-align:middle;}
  @keyframes spin{to{transform:rotate(360deg)}}

  /* Mobile responsive */
  @media (max-width: 480px) {
    .glass-card { padding: 28px 20px !important; margin: 12px !important; }
    h2 { font-size: 20px !important; }
    .orb { display: none; }
  }

</style>
</head>
<body>
  <div class="glass-card w-full max-w-md mx-4 p-10">
    <div class="text-center mb-8">
      <div class="inline-flex items-center gap-2 mb-3">
        <div style="width:36px;height:36px;background:linear-gradient(135deg,#6C63FF,#9B5DE5);border-radius:10px;display:flex;align-items:center;justify-content:center;">
          <svg width="18" height="18" fill="none" viewBox="0 0 24 24"><path d="M9 12h6M9 16h6M9 8h2M6 2h12a2 2 0 012 2v16a2 2 0 01-2 2H6a2 2 0 01-2-2V4a2 2 0 012-2z" stroke="#fff" stroke-width="2" stroke-linecap="round"/></svg>
        </div>
        <span class="brand text-white text-xl font-bold">CareerFlow</span>
      </div>
      <h2 class="text-white text-2xl font-bold mb-1">Set new password</h2>
    </div>

    <?php if ($msg): ?>
    <div style="background:rgba(<?= $msgType==='success'?'34,197,94':'239,68,68' ?>,.12);border:1px solid rgba(<?= $msgType==='success'?'34,197,94':'239,68,68' ?>,.25);border-radius:10px;padding:12px 16px;margin-bottom:20px;color:<?= $msgType==='success'?'#4ade80':'#f87171' ?>;font-size:13px;">
      <?= $msg ?>
      <?php if ($msgType==='success'): ?>
        <a href="login.php" style="color:#6C63FF;font-weight:600;"> Sign in →</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($valid): ?>
    <form method="POST">
      <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
      <div class="mb-5">
        <label>New Password</label>
        <input type="password" name="password" class="input-field" placeholder="••••••••" required>
      </div>
      <div class="mb-6">
        <label>Confirm Password</label>
        <input type="password" name="password2" class="input-field" placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn-primary">Reset Password</button>
    </form>
    <?php elseif (!$msg): ?>
    <p style="text-align:center;color:rgba(255,255,255,.5);font-size:14px">
      Invalid or expired reset link. <a href="forgot.php" style="color:#6C63FF;">Request a new one</a>.
    </p>
    <?php endif; ?>
  </div>
</body>
</html>
