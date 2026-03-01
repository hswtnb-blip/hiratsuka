<?php
/**
 * システム設定ファイル
 * 本番環境では各値を実際の値に変更してください
 */

// ============================================================
// データベース設定
// ============================================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'reservation_db');         // データベース名
define('DB_USER', 'db_user');                // DBユーザー名
define('DB_PASS', 'db_password');            // DBパスワード
define('DB_CHARSET', 'utf8mb4');

// ============================================================
// LINE Login 設定
// LINE Developers Console で取得
// https://developers.line.biz/console/
// ============================================================
define('LINE_CHANNEL_ID', 'YOUR_LINE_CHANNEL_ID');         // チャネルID
define('LINE_CHANNEL_SECRET', 'YOUR_LINE_CHANNEL_SECRET'); // チャネルシークレット
define('LINE_REDIRECT_URI', 'https://yourdomain.com/public/callback.php'); // コールバックURL

// ============================================================
// Google Calendar API 設定
// Google Cloud Console で OAuth 2.0 認証情報を取得
// https://console.cloud.google.com/
// ============================================================
define('GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_ID');
define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET');
define('GOOGLE_REDIRECT_URI', 'https://yourdomain.com/admin/google_callback.php');
define('GOOGLE_CALENDAR_ID', 'primary'); // カレンダーID（primary または specific@group.calendar.google.com）
define('GOOGLE_TOKEN_FILE', __DIR__ . '/google_token.json'); // トークン保存ファイル

// ============================================================
// アプリケーション設定
// ============================================================
define('APP_URL', 'https://yourdomain.com');       // サイトURL（末尾スラッシュなし）
define('APP_NAME', 'ご予約サイト');                 // サービス名
define('ADMIN_SESSION_NAME', 'admin_session');
define('USER_SESSION_NAME', 'user_session');

// ============================================================
// セキュリティ設定
// ============================================================
define('SESSION_LIFETIME', 3600);   // セッション有効時間（秒）
define('CSRF_TOKEN_NAME', '_csrf_token');

// ============================================================
// タイムゾーン設定
// ============================================================
date_default_timezone_set('Asia/Tokyo');

// ============================================================
// デバッグ設定（本番環境では false に設定）
// ============================================================
define('DEBUG_MODE', false);

if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}
