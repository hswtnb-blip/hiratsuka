<?php
/**
 * ログアウト（顧客）
 */
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
unset($_SESSION[USER_SESSION_NAME]);
session_destroy();

header('Location: index.php');
exit;
