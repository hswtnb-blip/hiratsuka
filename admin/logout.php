<?php
/**
 * 管理者ログアウト
 */
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
unset($_SESSION[ADMIN_SESSION_NAME]);
session_destroy();

header('Location: login.php');
exit;
