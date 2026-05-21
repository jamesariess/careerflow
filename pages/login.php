<?php
require_once '../includes/auth.php';
require_once '../includes/security.php';
session_init();

// Redirect if already logged in
if (auth_user()) {
    header('Location: ' . APP_URL . '/pages/dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { $error = 'Invalid request. Please try again.'; }
    else {
        $email = trim($_POST['email'] ?? '');
        $pw    = $_POST['password'] ?? '';
        $remember = !empty($_POST['remember']);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pw) < 6) {
            $error = 'Please enter a valid email and password.';
        } else {
            // Rate limiting: max 10 attempts per IP per 15 min
            $ip = Security::clientIp();
            if (!Security::checkRateLimit('login', $ip, 10, 900)) {
                $error = 'Too many login attempts. Please wait 15 minutes and try again.';
            } else {
                $user = DB::one('SELECT * FROM users WHERE email = ?', [$email]);
                if ($user && verify_pw($pw, $user['password'])) {
                    Security::clearAttempts('login', $ip);
                    login_user($user);
                    if ($remember) {
                        $token = bin2hex(random_bytes(32));
                        DB::run('UPDATE users SET remember_token=? WHERE id=?', [$token, $user['id']]);
                        setcookie('cf_remember', $token, time() + SESSION_LIFETIME, '/', '', isset($_SERVER['HTTPS']), true);
                    }
                    log_activity($user['id'], 'login', 'User logged in');
                    header('Location: ' . APP_URL . '/pages/dashboard.php');
                    exit;
                } else {
                    Security::recordAttempt('login', $ip);
                    // Timing-safe: always same delay whether user exists or not
                    usleep(300000);
                    $error = 'Invalid email or password.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Sign In – CareerFlow</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  :root {
    --accent: #6C63FF;
    --accent2: #4ECDC4;
    --glass: rgba(255,255,255,0.04);
    --border: rgba(255,255,255,0.08);
  }
  * { font-family: 'DM Sans', sans-serif; box-sizing: border-box; }
  h1,h2,h3,.brand { font-family: 'Syne', sans-serif; }
  body {
    background: #0D0D14;
    background-image:
      radial-gradient(ellipse 80% 60% at 20% 10%, rgba(108,99,255,0.18) 0%, transparent 60%),
      radial-gradient(ellipse 60% 50% at 80% 80%, rgba(78,205,196,0.10) 0%, transparent 60%);
    min-height: 100vh;
    display: flex; align-items: center; justify-content: center;
  }
  .glass-card {
    background: var(--glass);
    border: 1px solid var(--border);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-radius: 20px;
  }
  .input-field {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    color: #fff;
    border-radius: 10px;
    padding: 12px 16px;
    width: 100%;
    font-size: 14px;
    transition: all .2s;
    outline: none;
  }
  .input-field:focus {
    border-color: var(--accent);
    background: rgba(108,99,255,0.08);
    box-shadow: 0 0 0 3px rgba(108,99,255,0.15);
  }
  .input-field::placeholder { color: rgba(255,255,255,0.3); }
  .btn-primary {
    background: linear-gradient(135deg, var(--accent), #9B5DE5);
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 13px;
    width: 100%;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all .25s;
    letter-spacing: 0.3px;
  }
  .btn-primary:hover { opacity: .88; transform: translateY(-1px); box-shadow: 0 8px 24px rgba(108,99,255,0.4); }
  .orb {
    position: fixed;
    border-radius: 50%;
    filter: blur(80px);
    opacity: .25;
    pointer-events: none;
    z-index: 0;
  }
  label { font-size:13px; color:rgba(255,255,255,0.55); font-weight:500; margin-bottom:6px; display:block; }
  .logo-dot { width:10px; height:10px; background: var(--accent); border-radius:50%; display:inline-block; margin-right:6px; }
  /* Loading bar */
  #cf-bar{position:fixed;top:0;left:0;height:3px;width:0;background:linear-gradient(90deg,#6C63FF,#4ECDC4);z-index:9999;transition:width .25s ease,opacity .3s ease;box-shadow:0 0 10px #6C63FF;}
  .btn-primary.loading{opacity:.75;pointer-events:none;}
  .btn-primary.loading::after{content:'';display:inline-block;width:13px;height:13px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .65s linear infinite;margin-left:8px;vertical-align:middle;}
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
  <div id="cf-bar"></div>
  <script>
  // Progress bar
  var bar=document.getElementById('cf-bar');
  var prog=0,timer=null;
  function startLoad(){
    bar.style.opacity='1';bar.style.width='15%';prog=15;
    timer=setInterval(function(){if(prog<80){prog+=Math.random()*4;bar.style.width=prog+'%';}},120);
  }
  function doneLoad(){
    clearInterval(timer);bar.style.width='100%';
    setTimeout(function(){bar.style.opacity='0';setTimeout(function(){bar.style.width='0';},300);},200);
  }
  // On form submit
  document.addEventListener('DOMContentLoaded',function(){
    document.querySelectorAll('form').forEach(function(f){
      f.addEventListener('submit',function(){
        startLoad();
        var btn=f.querySelector('button[type=submit]');
        if(btn){btn.classList.add('loading');btn.textContent=btn.textContent.trim();}
      });
    });
    // Links
    document.querySelectorAll('a[href]').forEach(function(a){
      a.addEventListener('click',function(e){
        if(a.href&&!a.href.includes('#'))startLoad();
      });
    });
    window.addEventListener('pageshow',function(){doneLoad();});
    window.addEventListener('load',function(){doneLoad();});
    window.addEventListener('beforeunload',function(){startLoad();});
  });
  </script>

  <div class="orb" style="width:400px;height:400px;background:#6C63FF;top:-100px;left:-80px;"></div>
  <div class="orb" style="width:300px;height:300px;background:#4ECDC4;bottom:-60px;right:-60px;"></div>

  <div class="glass-card w-full max-w-md mx-4 p-10 relative z-10">
    <!-- Logo -->
    <div class="text-center mb-8">
      <div class="inline-flex items-center gap-2 mb-3">
        <div style="width:36px;height:36px;background:linear-gradient(135deg,#6C63FF,#9B5DE5);border-radius:10px;display:flex;align-items:center;justify-content:center;">
          <svg width="18" height="18" fill="none" viewBox="0 0 24 24"><path d="M9 12h6M9 16h6M9 8h2M6 2h12a2 2 0 012 2v16a2 2 0 01-2 2H6a2 2 0 01-2-2V4a2 2 0 012-2z" stroke="#fff" stroke-width="2" stroke-linecap="round"/></svg>
        </div>
        <span class="brand text-white text-xl font-bold tracking-tight">CareerFlow</span>
      </div>
      <h2 class="text-white text-2xl font-bold mb-1">Welcome back</h2>
      <p style="color:rgba(255,255,255,0.4);font-size:14px;">Sign in to your workspace</p>
    </div>

    <?php if ($error): ?>
    <div style="background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.25);border-radius:10px;padding:12px 16px;margin-bottom:20px;color:#f87171;font-size:13px;">
      <?= sanitize($error) ?>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div style="background:rgba(34,197,94,0.12);border:1px solid rgba(34,197,94,0.25);border-radius:10px;padding:12px 16px;margin-bottom:20px;color:#4ade80;font-size:13px;">
      <?= sanitize($success) ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="">
      <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

      <div class="mb-5">
        <label>Email address</label>
        <input type="email" name="email" class="input-field" placeholder="you@example.com" required autocomplete="email">
      </div>

      <div class="mb-5">
        <label>Password</label>
        <input type="password" name="password" class="input-field" placeholder="••••••••" required autocomplete="current-password">
      </div>

      <div class="flex items-center justify-between mb-6">
        <label class="flex items-center gap-2 cursor-pointer" style="margin:0;color:rgba(255,255,255,0.5)">
          <input type="checkbox" name="remember" style="accent-color:var(--accent)"> Remember me
        </label>
        <a href="forgot.php" style="color:var(--accent);font-size:13px;text-decoration:none;" onmouseover="this.style.opacity='.7'" onmouseout="this.style.opacity='1'">Forgot password?</a>
      </div>

      <button type="submit" class="btn-primary">Sign In</button>
    </form>

    <p style="text-align:center;margin-top:24px;font-size:13px;color:rgba(255,255,255,0.4)">
      Don't have an account?
      <a href="register.php" style="color:var(--accent);text-decoration:none;font-weight:600;"> Create one free</a>
    </p>
  </div>
</body>
</html>
