<?php
require_once '../includes/auth.php';
session_init();
if (auth_user()) { header('Location: ' . APP_URL . '/pages/dashboard.php'); exit; }

$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { $msg = 'Invalid request.'; $msgType = 'error'; }
    else {
        $email = trim($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg = 'Please enter a valid email address.'; $msgType = 'error';
        } else {
            $user = DB::one('SELECT id FROM users WHERE email=?', [$email]);
            /* Always show success to prevent user enumeration */
            $msg = 'If an account exists for that email, a reset link has been sent.';
            $msgType = 'success';
            if ($user) {
                $token   = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', time() + 3600);
                DB::run('UPDATE users SET reset_token=?, reset_expires=? WHERE id=?',
                    [$token, $expires, $user['id']]);
                /* In a real app: send email with APP_URL/pages/reset.php?token=$token */
                /* For demo purposes the token is shown in the success message */
                $resetLink = APP_URL . '/pages/reset.php?token=' . $token;
                $msg .= ' <span style="opacity:.7;font-size:11px">(Demo: <a href="' . $resetLink . '" style="color:inherit">' . $resetLink . '</a>)</span>';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password – CareerFlow</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  :root{--accent:#6C63FF;}
  *{font-family:'DM Sans',sans-serif;box-sizing:border-box;}
  h1,h2,h3,.brand{font-family:'Syne',sans-serif;}
  body{background:#0D0D14;background-image:radial-gradient(ellipse 80% 60% at 20% 10%,rgba(108,99,255,.18) 0%,transparent 60%);min-height:100vh;display:flex;align-items:center;justify-content:center;}
  .glass-card{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);backdrop-filter:blur(20px);border-radius:20px;}
  .input-field{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);color:#fff;border-radius:10px;padding:12px 16px;width:100%;font-size:14px;transition:all .2s;outline:none;}
  .input-field:focus{border-color:var(--accent);background:rgba(108,99,255,.08);box-shadow:0 0 0 3px rgba(108,99,255,.15);}
  .input-field::placeholder{color:rgba(255,255,255,.3);}
  .btn-primary{background:linear-gradient(135deg,var(--accent),#9B5DE5);color:#fff;border:none;border-radius:10px;padding:13px;width:100%;font-size:15px;font-weight:600;cursor:pointer;transition:all .25s;}
  .btn-primary:hover{opacity:.88;transform:translateY(-1px);}
  label{font-size:13px;color:rgba(255,255,255,.55);font-weight:500;margin-bottom:6px;display:block;}
  .orb{position:fixed;border-radius:50%;filter:blur(80px);opacity:.2;pointer-events:none;z-index:0;}
</style>
</head>
<body>
  <div class="orb" style="width:400px;height:400px;background:#6C63FF;top:-100px;left:-80px;"></div>

  <div class="glass-card w-full max-w-md mx-4 p-10 relative z-10">
    <div class="text-center mb-8">
      <div class="inline-flex items-center gap-2 mb-3">
        <div style="width:36px;height:36px;background:linear-gradient(135deg,#6C63FF,#9B5DE5);border-radius:10px;display:flex;align-items:center;justify-content:center;">
          <svg width="18" height="18" fill="none" viewBox="0 0 24 24"><path d="M9 12h6M9 16h6M9 8h2M6 2h12a2 2 0 012 2v16a2 2 0 01-2 2H6a2 2 0 01-2-2V4a2 2 0 012-2z" stroke="#fff" stroke-width="2" stroke-linecap="round"/></svg>
        </div>
        <span class="brand text-white text-xl font-bold">CareerFlow</span>
      </div>
      <h2 class="text-white text-2xl font-bold mb-1">Reset password</h2>
      <p style="color:rgba(255,255,255,.4);font-size:14px">Enter your email to receive a reset link</p>
    </div>

    <?php if ($msg): ?>
    <div style="background:rgba(<?= $msgType==='success'?'34,197,94':'239,68,68' ?>,.12);border:1px solid rgba(<?= $msgType==='success'?'34,197,94':'239,68,68' ?>,.25);border-radius:10px;padding:12px 16px;margin-bottom:20px;color:<?= $msgType==='success'?'#4ade80':'#f87171' ?>;font-size:13px;">
      <?= $msg ?>
    </div>
    <?php endif; ?>

    <?php if (!($msg && $msgType === 'success')): ?>
    <form method="POST">
      <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
      <div class="mb-6">
        <label>Email address</label>
        <input type="email" name="email" class="input-field" placeholder="you@example.com" required autocomplete="email">
      </div>
      <button type="submit" class="btn-primary">Send Reset Link</button>
    </form>
    <?php endif; ?>

    <p style="text-align:center;margin-top:24px;font-size:13px;color:rgba(255,255,255,.4)">
      Remembered it? <a href="login.php" style="color:var(--accent);text-decoration:none;font-weight:600;">Sign in</a>
    </p>
  </div>
</body>
</html>
