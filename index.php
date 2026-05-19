<?php
require_once 'includes/auth.php';
session_init();
if (auth_user()) {
    header('Location: ' . APP_URL . '/pages/dashboard.php');
} else {
    header('Location: ' . APP_URL . '/pages/login.php');
}
exit;
