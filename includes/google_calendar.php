<?php
/**
 * Google Calendar API v3 ヘルパー
 * OAuth 2.0 認証フロー
 * https://developers.google.com/calendar/api/v3/reference
 */

require_once __DIR__ . '/../config/config.php';

class GoogleCalendar
{
    private const AUTH_URL    = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL   = 'https://oauth2.googleapis.com/token';
    private const API_BASE    = 'https://www.googleapis.com/calendar/v3';
    private const SCOPE       = 'https://www.googleapis.com/auth/calendar';

    private ?array $token = null;

    public function __construct()
    {
        $this->loadToken();
    }

    // ============================================================
    // OAuth 認証
    // ============================================================

    /**
     * Google 認証URLを生成する（管理者が初回設定時に使用）
     */
    public static function getAuthUrl(): string
    {
        $params = http_build_query([
            'client_id'             => GOOGLE_CLIENT_ID,
            'redirect_uri'          => GOOGLE_REDIRECT_URI,
            'response_type'         => 'code',
            'scope'                 => self::SCOPE,
            'access_type'           => 'offline',
            'prompt'                => 'consent',
            'state'                 => bin2hex(random_bytes(16)),
        ]);
        return self::AUTH_URL . '?' . $params;
    }

    /**
     * 認証コードからトークンを取得・保存する
     */
    public static function exchangeCodeForToken(string $code): bool
    {
        $params = [
            'code'          => $code,
            'client_id'     => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri'  => GOOGLE_REDIRECT_URI,
            'grant_type'    => 'authorization_code',
        ];

        $response = self::httpPost(self::TOKEN_URL, $params);
        if (!$response) return false;

        $data = json_decode($response, true);
        if (!isset($data['access_token'])) return false;

        $data['created_at'] = time();
        file_put_contents(GOOGLE_TOKEN_FILE, json_encode($data));
        return true;
    }

    /**
     * トークンファイルが存在し有効かどうか確認
     */
    public static function isAuthorized(): bool
    {
        return file_exists(GOOGLE_TOKEN_FILE);
    }

    // ============================================================
    // カレンダー操作
    // ============================================================

    /**
     * 予約をGoogleカレンダーに追加する
     */
    public function createEvent(array $reservation): ?string
    {
        $token = $this->getValidToken();
        if (!$token) return null;

        $calendarId = GOOGLE_CALENDAR_ID ?: 'primary';

        $startDt = $reservation['reservation_date'] . 'T' . $reservation['reservation_time'];
        $endDt   = $reservation['reservation_date'] . 'T' . $reservation['end_time'];

        $event = [
            'summary'     => ($reservation['user_name'] ?? '顧客') . '様 - ' . ($reservation['menu_name'] ?? '予約'),
            'description' => sprintf(
                "電話番号: %s\nメニュー: %s\n料金: ¥%s\n備考: %s",
                $reservation['user_phone'] ?? '',
                $reservation['menu_name'] ?? '',
                number_format($reservation['price'] ?? 0),
                $reservation['customer_notes'] ?? ''
            ),
            'start' => [
                'dateTime' => $startDt,
                'timeZone' => 'Asia/Tokyo',
            ],
            'end' => [
                'dateTime' => $endDt,
                'timeZone' => 'Asia/Tokyo',
            ],
            'colorId' => '2', // セージ（緑）
        ];

        $url      = self::API_BASE . '/calendars/' . urlencode($calendarId) . '/events';
        $response = $this->apiRequest('POST', $url, $token, $event);

        if (!$response) return null;
        $data = json_decode($response, true);
        return $data['id'] ?? null;
    }

    /**
     * Googleカレンダーのイベントを更新する
     */
    public function updateEvent(string $eventId, array $reservation): bool
    {
        $token = $this->getValidToken();
        if (!$token) return false;

        $calendarId = GOOGLE_CALENDAR_ID ?: 'primary';

        $startDt = $reservation['reservation_date'] . 'T' . $reservation['reservation_time'];
        $endDt   = $reservation['reservation_date'] . 'T' . $reservation['end_time'];

        $event = [
            'summary'     => ($reservation['user_name'] ?? '顧客') . '様 - ' . ($reservation['menu_name'] ?? '予約'),
            'description' => sprintf(
                "電話番号: %s\nメニュー: %s\n料金: ¥%s\n備考: %s",
                $reservation['user_phone'] ?? '',
                $reservation['menu_name'] ?? '',
                number_format($reservation['price'] ?? 0),
                $reservation['customer_notes'] ?? ''
            ),
            'start' => [
                'dateTime' => $startDt,
                'timeZone' => 'Asia/Tokyo',
            ],
            'end' => [
                'dateTime' => $endDt,
                'timeZone' => 'Asia/Tokyo',
            ],
        ];

        $url      = self::API_BASE . '/calendars/' . urlencode($calendarId) . '/events/' . urlencode($eventId);
        $response = $this->apiRequest('PUT', $url, $token, $event);
        return $response !== null;
    }

    /**
     * Googleカレンダーのイベントを削除する
     */
    public function deleteEvent(string $eventId): bool
    {
        $token = $this->getValidToken();
        if (!$token) return false;

        $calendarId = GOOGLE_CALENDAR_ID ?: 'primary';
        $url        = self::API_BASE . '/calendars/' . urlencode($calendarId) . '/events/' . urlencode($eventId);
        $response   = $this->apiRequest('DELETE', $url, $token);
        return $response !== null;
    }

    /**
     * 指定日のイベント一覧を取得する
     */
    public function getEventsByDate(string $date): array
    {
        $token = $this->getValidToken();
        if (!$token) return [];

        $calendarId = GOOGLE_CALENDAR_ID ?: 'primary';
        $timeMin    = $date . 'T00:00:00+09:00';
        $timeMax    = $date . 'T23:59:59+09:00';

        $params = http_build_query([
            'timeMin'      => $timeMin,
            'timeMax'      => $timeMax,
            'singleEvents' => 'true',
            'orderBy'      => 'startTime',
        ]);
        $url      = self::API_BASE . '/calendars/' . urlencode($calendarId) . '/events?' . $params;
        $response = $this->apiRequest('GET', $url, $token);

        if (!$response) return [];
        $data = json_decode($response, true);
        return $data['items'] ?? [];
    }

    // ============================================================
    // トークン管理
    // ============================================================

    private function loadToken(): void
    {
        if (!file_exists(GOOGLE_TOKEN_FILE)) return;
        $content = file_get_contents(GOOGLE_TOKEN_FILE);
        $this->token = json_decode($content, true);
    }

    private function getValidToken(): ?array
    {
        if (!$this->token) return null;

        // アクセストークンが期限切れならリフレッシュ
        $expiresAt = ($this->token['created_at'] ?? 0) + ($this->token['expires_in'] ?? 3600) - 60;
        if (time() > $expiresAt) {
            return $this->refreshToken();
        }

        return $this->token;
    }

    private function refreshToken(): ?array
    {
        if (empty($this->token['refresh_token'])) return null;

        $params = [
            'client_id'     => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'refresh_token' => $this->token['refresh_token'],
            'grant_type'    => 'refresh_token',
        ];

        $response = self::httpPost(self::TOKEN_URL, $params);
        if (!$response) return null;

        $data = json_decode($response, true);
        if (!isset($data['access_token'])) return null;

        // リフレッシュトークンは返ってこない場合があるので保持する
        $data['refresh_token'] = $data['refresh_token'] ?? $this->token['refresh_token'];
        $data['created_at']    = time();

        $this->token = $data;
        file_put_contents(GOOGLE_TOKEN_FILE, json_encode($data));
        return $this->token;
    }

    // ============================================================
    // HTTP 通信
    // ============================================================

    private function apiRequest(string $method, string $url, array $token, ?array $body = null): ?string
    {
        $headers = [
            'Authorization: Bearer ' . $token['access_token'],
            'Accept: application/json',
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
        ]);

        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error     = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log('Google Calendar API cURL error: ' . $error);
            return null;
        }
        if ($httpCode >= 400 && $httpCode !== 204) {
            error_log('Google Calendar API error ' . $httpCode . ': ' . $response);
            return null;
        }
        return $response ?: '';
    }

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
        return $error ? null : ($response ?: null);
    }
}
