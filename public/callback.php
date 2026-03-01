<?php
/**
 * LINE Login コールバック
 * LINEから認証コードを受け取り、ユーザー情報を取得する
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/line_login.php';

startSecureSession();

// エラーチェック
if (isset($_GET['error'])) {
    setFlash('error', 'LINEログインがキャンセルされました。');
    header('Location: index.php');
    exit;
}

$code  = $_GET['code']  ?? '';
$state = $_GET['state'] ?? '';

// stateパラメータ検証（CSRF対策）
if (!LineLogin::verifyState($state)) {
    setFlash('error', 'セキュリティエラーが発生しました。再度お試しください。');
    header('Location: index.php');
    exit;
}

if (!$code) {
    setFlash('error', '認証コードが取得できませんでした。');
    header('Location: index.php');
    exit;
}

// アクセストークン取得
$tokenData = LineLogin::getAccessToken($code);
if (!$tokenData) {
    setFlash('error', 'LINEとの連携に失敗しました。再度お試しください。');
    header('Location: index.php');
    exit;
}

// プロフィール取得
$profile = LineLogin::getProfile($tokenData['access_token']);
if (!$profile) {
    setFlash('error', 'プロフィールの取得に失敗しました。');
    header('Location: index.php');
    exit;
}

$lineUserId = $profile['userId'];

// DBでユーザー検索
$user = getUserByLineId($lineUserId);

if ($user) {
    // 既存ユーザー → セッションに保存してマイページへ
    $_SESSION[USER_SESSION_NAME] = $user;
    header('Location: mypage.php');
    exit;
} else {
    // 新規ユーザー → 情報登録ページへ
    $_SESSION['line_user_id']      = $lineUserId;
    $_SESSION['line_display_name'] = $profile['displayName'] ?? '';
    header('Location: register.php');
    exit;
}
