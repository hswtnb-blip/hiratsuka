<?php
/**
 * Google OAuth コールバック
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/google_calendar.php';

startSecureSession();
requireAdminLogin();

$code  = $_GET['code']  ?? '';
$error = $_GET['error'] ?? '';

if ($error) {
    setFlash('error', 'Google認証がキャンセルされました。');
    header('Location: google_auth.php');
    exit;
}

if (!$code) {
    setFlash('error', '認証コードが取得できませんでした。');
    header('Location: google_auth.php');
    exit;
}

if (GoogleCalendar::exchangeCodeForToken($code)) {
    setFlash('success', 'Googleカレンダーの連携が完了しました！');
} else {
    setFlash('error', 'Googleカレンダーの連携に失敗しました。設定を確認してください。');
}

header('Location: google_auth.php');
exit;
