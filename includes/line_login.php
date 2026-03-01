<?php
/**
 * LINE Login ヘルパー
 * LINE Login v2.1 OAuth 2.0 フロー
 * https://developers.line.biz/ja/docs/line-login/
 */

require_once __DIR__ . '/../config/config.php';

class LineLogin
{
    private const AUTH_URL    = 'https://access.line.me/oauth2/v2.1/authorize';
    private const TOKEN_URL   = 'https://api.line.me/oauth2/v2.1/token';
    private const PROFILE_URL = 'https://api.line.me/v2/profile';
    private const VERIFY_URL  = 'https://api.line.me/oauth2/v2.1/verify';

    /**
     * LINE認証URLを生成する
     */
    public static function getAuthUrl(): string
    {
        $state = bin2hex(random_bytes(16));
        session_start();
        $_SESSION['line_state'] = $state;

        $params = http_build_query([
            'response_type' => 'code',
            'client_id'     => LINE_CHANNEL_ID,
            'redirect_uri'  => LINE_REDIRECT_URI,
            'state'         => $state,
            'scope'         => 'profile openid',
            'bot_prompt'    => 'normal', // LINEお友達追加を促す（任意）
        ]);

        return self::AUTH_URL . '?' . $params;
    }

    /**
     * 認証コードからアクセストークンを取得する
     */
    public static function getAccessToken(string $code): ?array
    {
        $params = [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => LINE_REDIRECT_URI,
            'client_id'     => LINE_CHANNEL_ID,
            'client_secret' => LINE_CHANNEL_SECRET,
        ];

        $response = self::httpPost(self::TOKEN_URL, $params);
        if (!$response) return null;

        $data = json_decode($response, true);
        return isset($data['access_token']) ? $data : null;
    }

    /**
     * アクセストークンからプロフィールを取得する
     */
    public static function getProfile(string $accessToken): ?array
    {
        $response = self::httpGet(self::PROFILE_URL, $accessToken);
        if (!$response) return null;

        $data = json_decode($response, true);
        return isset($data['userId']) ? $data : null;
    }

    /**
     * stateパラメータを検証する
     */
    public static function verifyState(string $state): bool
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $stored = $_SESSION['line_state'] ?? '';
        unset($_SESSION['line_state']);
        return hash_equals($stored, $state);
    }

    /**
     * HTTP POST リクエスト
     */
    private static function httpPost(string $url, array $params): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log('LINE API cURL error: ' . $error);
            return null;
        }
        return $response ?: null;
    }

    /**
     * HTTP GET リクエスト（Bearer認証）
     */
    private static function httpGet(string $url, string $accessToken): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log('LINE API cURL error: ' . $error);
            return null;
        }
        return $response ?: null;
    }
}
