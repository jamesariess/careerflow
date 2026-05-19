<?php
require_once '../includes/auth.php';
session_init();
$u = auth_user();
if ($u) {
    log_activity($u['id'], 'logout', 'User signed out');
    /* Clear remember-me cookie */
    DB::run('UPDATE users SET remember_token=NULL WHERE id=?', [$u['id']]);
    setcookie('cf_remember', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
}
logout_user();
header('Location: ' . APP_URL . '/pages/login.php');
exit;
