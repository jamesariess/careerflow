<?php
require_once '../includes/auth.php';
require_once '../includes/security.php';
session_init();
if (auth_user()) { header('Location: ' . APP_URL . '/pages/dashboard.php'); exit; }

$error = ''; $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { $error = 'Invalid request.'; }
    else {
        $name  = trim($_POST['name']  ?? '');
        $email = trim($_POST['email'] ?? '');
        $pw    = $_POST['password']  ?? '';
        $pw2   = $_POST['password2'] ?? '';

        if (strlen($name) < 2)                            $error = 'Name must be at least 2 characters.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Invalid email address.';
        elseif (strlen($pw) < 8)                           $error = 'Password must be at least 8 characters.';
        elseif ($pw !== $pw2)                              $error = 'Passwords do not match.';
        else {
            $ip = Security::clientIp();
            if (!Security::checkRateLimit('register', $ip, 10, 3600)) {
                $error = 'Too many registrations from this IP. Please try again later.';
            } else {
            Security::recordAttempt('register', $ip);
            $exists = DB::one('SELECT id FROM users WHERE email=?', [$email]);
            if ($exists) { $error = 'An account with this email already exists.'; }
            else {
                DB::run('INSERT INTO users (name,email,password) VALUES (?,?,?)',
                    [$name, $email, hash_pw($pw)]);
                $uid = (int)DB::lastId();
                log_activity($uid, 'register', 'Account created');
                $success = 'Account created! You can now sign in.';
            }
            } // rate limit else
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Account – CareerFlow</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  :root{--accent:#6C63FF;--accent2:#4ECDC4;}
  *{font-family:'DM Sans',sans-serif;box-sizing:border-box;}
  h1,h2,h3,.brand{font-family:'Syne',sans-serif;}
  body{background:#0D0D14;background-image:radial-gradient(ellipse 80% 60% at 20% 10%,rgba(108,99,255,.18) 0%,transparent 60%),radial-gradient(ellipse 60% 50% at 80% 80%,rgba(78,205,196,.10) 0%,transparent 60%);min-height:100vh;display:flex;align-items:center;justify-content:center;}
  .glass-card{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);backdrop-filter:blur(20px);border-radius:20px;}
  .input-field{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);color:#fff;border-radius:10px;padding:12px 16px;width:100%;font-size:14px;transition:all .2s;outline:none;}
  .input-field:focus{border-color:var(--accent);background:rgba(108,99,255,.08);box-shadow:0 0 0 3px rgba(108,99,255,.15);}
  .input-field::placeholder{color:rgba(255,255,255,.3);}
  .btn-primary{background:linear-gradient(135deg,var(--accent),#9B5DE5);color:#fff;border:none;border-radius:10px;padding:13px;width:100%;font-size:15px;font-weight:600;cursor:pointer;transition:all .25s;}
  .btn-primary:hover{opacity:.88;transform:translateY(-1px);box-shadow:0 8px 24px rgba(108,99,255,.4);}
  label{font-size:13px;color:rgba(255,255,255,.55);font-weight:500;margin-bottom:6px;display:block;}
  .orb{position:fixed;border-radius:50%;filter:blur(80px);opacity:.25;pointer-events:none;z-index:0;}
  #cf-bar{position:fixed;top:0;left:0;height:3px;width:0;background:linear-gradient(90deg,#6C63FF,#4ECDC4);z-index:9999;transition:width .25s ease,opacity .3s ease;box-shadow:0 0 10px #6C63FF;}
  .btn-primary.loading{opacity:.75;pointer-events:none;}
  .btn-primary.loading::after{content:"";display:inline-block;width:13px;height:13px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .65s linear infinite;margin-left:8px;vertical-align:middle;}
  @keyframes spin{to{transform:rotate(360deg)}}
</style>
</head>
<body>
  <div class="orb" style="width:400px;height:400px;background:#6C63FF;top:-100px;left:-80px;"></div>
  <div class="orb" style="width:300px;height:300px;background:#4ECDC4;bottom:-60px;right:-60px;"></div>

  <div class="glass-card w-full max-w-md mx-4 p-10 relative z-10">
    <div class="text-center mb-8">
      <div class="inline-flex items-center gap-2 mb-3">
        <div style="width:36px;height:36px;background:linear-gradient(135deg,#6C63FF,#9B5DE5);border-radius:10px;display:flex;align-items:center;justify-content:center;">
          <svg width="18" height="18" fill="none" viewBox="0 0 24 24"><path d="M9 12h6M9 16h6M9 8h2M6 2h12a2 2 0 012 2v16a2 2 0 01-2 2H6a2 2 0 01-2-2V4a2 2 0 012-2z" stroke="#fff" stroke-width="2" stroke-linecap="round"/></svg>
        </div>
        <span class="brand text-white text-xl font-bold tracking-tight">CareerFlow</span>
      </div>
      <h2 class="text-white text-2xl font-bold mb-1">Create your account</h2>
      <p style="color:rgba(255,255,255,.4);font-size:14px;">Free forever. No credit card needed.</p>
    </div>

    <?php if ($error): ?>
    <div style="background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.25);border-radius:10px;padding:12px 16px;margin-bottom:20px;color:#f87171;font-size:13px;"><?= sanitize($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div style="background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.25);border-radius:10px;padding:12px 16px;margin-bottom:20px;color:#4ade80;font-size:13px;"><?= sanitize($success) ?> <a href="login.php" style="color:var(--accent);font-weight:600;">Sign in →</a></div>
    <?php endif; ?>

    <form method="POST">
      <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
      <div class="mb-4">
        <label>Full name</label>
        <input type="text" name="name" class="input-field" placeholder="Alex Johnson" required>
      </div>
      <div class="mb-4">
        <label>Email address</label>
        <input type="email" name="email" class="input-field" placeholder="you@example.com" required>
      </div>
      <div class="mb-4">
        <label>Password <span style="color:rgba(255,255,255,.3)">(min 8 chars)</span></label>
        <input type="password" name="password" class="input-field" placeholder="••••••••" required>
      </div>
      <div class="mb-6">
        <label>Confirm password</label>
        <input type="password" name="password2" class="input-field" placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn-primary">Create Account</button>
    </form>
    <p style="text-align:center;margin-top:24px;font-size:13px;color:rgba(255,255,255,.4)">
      Already have an account? <a href="login.php" style="color:var(--accent);text-decoration:none;font-weight:600;">Sign in</a>
    </p>
  </div>
</body>
</html>
