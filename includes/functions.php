<?php
/**
 * 汎用ヘルパー関数
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';

// ============================================================
// セッション管理
// ============================================================

function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function getLoginUser(): ?array
{
    startSecureSession();
    $key = USER_SESSION_NAME;
    return $_SESSION[$key] ?? null;
}

function requireUserLogin(): array
{
    $user = getLoginUser();
    if (!$user) {
        header('Location: ' . APP_URL . '/public/index.php');
        exit;
    }
    return $user;
}

function getLoginAdmin(): ?array
{
    startSecureSession();
    $key = ADMIN_SESSION_NAME;
    return $_SESSION[$key] ?? null;
}

function requireAdminLogin(): array
{
    $admin = getLoginAdmin();
    if (!$admin) {
        header('Location: ' . APP_URL . '/admin/login.php');
        exit;
    }
    return $admin;
}

// ============================================================
// CSRF 対策
// ============================================================

function generateCsrfToken(): string
{
    startSecureSession();
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function verifyCsrfToken(string $token): bool
{
    startSecureSession();
    $stored = $_SESSION[CSRF_TOKEN_NAME] ?? '';
    return hash_equals($stored, $token);
}

function csrfField(): string
{
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . htmlspecialchars(generateCsrfToken()) . '">';
}

// ============================================================
// 入力サニタイズ
// ============================================================

function h(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function sanitizeInput(string $input): string
{
    return trim(strip_tags($input));
}

// ============================================================
// フラッシュメッセージ
// ============================================================

function setFlash(string $type, string $message): void
{
    startSecureSession();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    startSecureSession();
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function renderFlash(): string
{
    $flash = getFlash();
    if (!$flash) return '';
    $type = $flash['type'] === 'success' ? 'success' : ($flash['type'] === 'error' ? 'danger' : 'info');
    return '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">'
        . h($flash['message'])
        . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

// ============================================================
// ユーザー関連
// ============================================================

function getUserById(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function getUserByLineId(string $lineUserId): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE line_user_id = ?');
    $stmt->execute([$lineUserId]);
    return $stmt->fetch() ?: null;
}

// ============================================================
// ポイント関連
// ============================================================

function addPoints(int $userId, int $points, string $type, string $description, ?int $reservationId = null): void
{
    $pdo = db();
    // ポイント履歴追加
    $stmt = $pdo->prepare(
        'INSERT INTO points_history (user_id, reservation_id, points, type, description) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $reservationId, $points, $type, $description]);

    // ユーザーのポイント合計を更新
    $stmt = $pdo->prepare('UPDATE users SET points = points + ? WHERE id = ?');
    $stmt->execute([$points, $userId]);
}

function getUserPoints(int $userId): int
{
    $stmt = db()->prepare('SELECT points FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ? (int)$row['points'] : 0;
}

function getPointsHistory(int $userId, int $limit = 20): array
{
    $stmt = db()->prepare(
        'SELECT ph.*, r.reservation_date, r.menu_name
         FROM points_history ph
         LEFT JOIN reservations r ON ph.reservation_id = r.id
         WHERE ph.user_id = ?
         ORDER BY ph.created_at DESC
         LIMIT ?'
    );
    $stmt->execute([$userId, $limit]);
    return $stmt->fetchAll();
}

// ============================================================
// 予約関連
// ============================================================

function getReservationsByUser(int $userId): array
{
    $stmt = db()->prepare(
        'SELECT r.*, m.name AS menu_display_name
         FROM reservations r
         LEFT JOIN menus m ON r.menu_id = m.id
         WHERE r.user_id = ?
         ORDER BY r.reservation_date DESC, r.reservation_time DESC'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getUpcomingReservations(int $userId): array
{
    $stmt = db()->prepare(
        'SELECT r.*, m.name AS menu_display_name
         FROM reservations r
         LEFT JOIN menus m ON r.menu_id = m.id
         WHERE r.user_id = ? AND r.reservation_date >= CURDATE() AND r.status IN ("pending","confirmed")
         ORDER BY r.reservation_date ASC, r.reservation_time ASC'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getReservationById(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT r.*, u.name AS user_name, u.phone AS user_phone, m.name AS menu_display_name
         FROM reservations r
         LEFT JOIN users u ON r.user_id = u.id
         LEFT JOIN menus m ON r.menu_id = m.id
         WHERE r.id = ?'
    );
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function getActiveMenus(): array
{
    $stmt = db()->query(
        'SELECT * FROM menus WHERE is_active = 1 ORDER BY sort_order ASC, id ASC'
    );
    return $stmt->fetchAll();
}

function isTimeSlotAvailable(string $date, string $time, string $endTime, ?int $excludeId = null): bool
{
    $sql = 'SELECT COUNT(*) FROM reservations
            WHERE reservation_date = ?
              AND status IN ("pending","confirmed")
              AND reservation_time < ?
              AND end_time > ?';
    $params = [$date, $endTime, $time];
    if ($excludeId) {
        $sql .= ' AND id != ?';
        $params[] = $excludeId;
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn() === 0;
}

function getAvailableTimeSlots(string $date, int $durationMinutes = 60): array
{
    // 営業時間スロット生成
    $startHour   = (int)getSetting('reservation_start_hour', 9);
    $endHour     = (int)getSetting('reservation_end_hour', 19);
    $interval    = (int)getSetting('reservation_interval', 30);

    $slots      = [];
    $current    = new DateTime("{$date} {$startHour}:00");
    $limitEnd   = new DateTime("{$date} {$endHour}:00");

    while (true) {
        $end = clone $current;
        $end->modify("+{$durationMinutes} minutes");
        if ($end > $limitEnd) break;

        $time    = $current->format('H:i');
        $endTime = $end->format('H:i:s');

        if (isTimeSlotAvailable($date, $time . ':00', $endTime)) {
            $slots[] = $time;
        }
        $current->modify("+{$interval} minutes");
    }
    return $slots;
}

// ============================================================
// 設定関連
// ============================================================

function getSetting(string $key, string $default = ''): string
{
    $stmt = db()->prepare('SELECT value FROM settings WHERE key_name = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? (string)$row['value'] : $default;
}

function setSetting(string $key, string $value): void
{
    $stmt = db()->prepare(
        'INSERT INTO settings (key_name, value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE value = VALUES(value)'
    );
    $stmt->execute([$key, $value]);
}

// ============================================================
// 日付・時刻ヘルパー
// ============================================================

function formatDate(string $date): string
{
    return date('Y年m月d日', strtotime($date));
}

function formatDateTime(string $datetime): string
{
    return date('Y年m月d日 H:i', strtotime($datetime));
}

function formatTime(string $time): string
{
    return substr($time, 0, 5);
}

function getStatusLabel(string $status): string
{
    $labels = [
        'pending'   => '<span class="badge bg-warning text-dark">仮予約</span>',
        'confirmed' => '<span class="badge bg-success">確定</span>',
        'cancelled' => '<span class="badge bg-secondary">キャンセル</span>',
        'completed' => '<span class="badge bg-primary">完了</span>',
    ];
    return $labels[$status] ?? $status;
}

function getDayOfWeek(string $date): string
{
    $days = ['日', '月', '火', '水', '木', '金', '土'];
    return $days[(int)date('w', strtotime($date))];
}

// ============================================================
// 顧客統計
// ============================================================

function getCustomerStats(int $userId): array
{
    $pdo = db();

    // 最終来店日
    $stmt = $pdo->prepare(
        'SELECT MAX(reservation_date) FROM reservations WHERE user_id = ? AND status = "completed"'
    );
    $stmt->execute([$userId]);
    $lastVisit = $stmt->fetchColumn();

    // 来店回数
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM reservations WHERE user_id = ? AND status = "completed"'
    );
    $stmt->execute([$userId]);
    $visitCount = (int)$stmt->fetchColumn();

    // 累計売上
    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(price), 0) FROM reservations WHERE user_id = ? AND status = "completed"'
    );
    $stmt->execute([$userId]);
    $totalSales = (int)$stmt->fetchColumn();

    // 売上履歴
    $stmt = $pdo->prepare(
        'SELECT reservation_date, menu_name, price, points_earned, staff_notes
         FROM reservations WHERE user_id = ? AND status = "completed"
         ORDER BY reservation_date DESC LIMIT 50'
    );
    $stmt->execute([$userId]);
    $salesHistory = $stmt->fetchAll();

    return compact('lastVisit', 'visitCount', 'totalSales', 'salesHistory');
}
