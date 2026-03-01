<?php
/**
 * 管理者ダッシュボード
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/google_calendar.php';

startSecureSession();
$admin    = requireAdminLogin();
$shopName = getSetting('shop_name', 'ご予約サイト');

$pdo = db();

// 今日の予約
$stmt = $pdo->prepare(
    'SELECT r.*, u.name AS user_name, u.phone AS user_phone
     FROM reservations r
     JOIN users u ON r.user_id = u.id
     WHERE r.reservation_date = CURDATE() AND r.status IN ("pending","confirmed")
     ORDER BY r.reservation_time ASC'
);
$stmt->execute();
$todayReservations = $stmt->fetchAll();

// 今週の予約数
$stmt = $pdo->query(
    'SELECT COUNT(*) FROM reservations
     WHERE reservation_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
       AND status IN ("pending","confirmed")'
);
$weekCount = (int)$stmt->fetchColumn();

// 今月の売上（完了済み）
$stmt = $pdo->query(
    'SELECT COALESCE(SUM(price), 0) FROM reservations
     WHERE MONTH(reservation_date) = MONTH(CURDATE())
       AND YEAR(reservation_date)  = YEAR(CURDATE())
       AND status = "completed"'
);
$monthSales = (int)$stmt->fetchColumn();

// 顧客総数
$stmt      = $pdo->query('SELECT COUNT(*) FROM users');
$userCount = (int)$stmt->fetchColumn();

// 未確定予約数
$stmt         = $pdo->query('SELECT COUNT(*) FROM reservations WHERE status = "pending"');
$pendingCount = (int)$stmt->fetchColumn();

$gcal           = new GoogleCalendar();
$isGcalAuthorized = GoogleCalendar::isAuthorized();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ダッシュボード | <?= h($shopName) ?> 管理</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<?php include __DIR__ . '/partials/navbar.php'; ?>

<div class="container-fluid py-4">
  <div class="row">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 fw-bold">ダッシュボード</h1>
        <div class="text-muted small"><?= date('Y年m月d日（') . getDayOfWeek(date('Y-m-d')) . '）' ?></div>
      </div>

      <?= renderFlash() ?>

      <!-- Google Calendar 連携アラート -->
      <?php if (!$isGcalAuthorized): ?>
      <div class="alert alert-warning d-flex align-items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
          <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98
                   1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35
                   3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1
                   0 0 1 0-2z"/>
        </svg>
        <div>
          Googleカレンダーが未連携です。
          <a href="google_auth.php" class="alert-link">こちらから連携する</a>
        </div>
      </div>
      <?php endif; ?>

      <!-- 統計カード -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
          <div class="card stat-card border-0 shadow-sm">
            <div class="card-body">
              <div class="text-muted small mb-1">今日の予約</div>
              <div class="fs-2 fw-bold text-primary"><?= count($todayReservations) ?></div>
              <div class="text-muted small">件</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="card stat-card border-0 shadow-sm">
            <div class="card-body">
              <div class="text-muted small mb-1">今週の予約</div>
              <div class="fs-2 fw-bold text-success"><?= $weekCount ?></div>
              <div class="text-muted small">件</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="card stat-card border-0 shadow-sm">
            <div class="card-body">
              <div class="text-muted small mb-1">今月の売上</div>
              <div class="fs-2 fw-bold text-warning">¥<?= number_format($monthSales) ?></div>
              <div class="text-muted small">完了済み</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="card stat-card border-0 shadow-sm">
            <div class="card-body">
              <div class="text-muted small mb-1">顧客数</div>
              <div class="fs-2 fw-bold text-info"><?= $userCount ?></div>
              <div class="text-muted small">名</div>
            </div>
          </div>
        </div>
      </div>

      <!-- 未確定予約アラート -->
      <?php if ($pendingCount > 0): ?>
      <div class="alert alert-info d-flex justify-content-between align-items-center">
        <span>
          <strong><?= $pendingCount ?> 件</strong>の未確定予約があります
        </span>
        <a href="reservations.php?status=pending" class="btn btn-sm btn-info text-white">確認する</a>
      </div>
      <?php endif; ?>

      <!-- 今日の予約一覧 -->
      <div class="card shadow-sm border-0">
        <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
          <span>本日の予約</span>
          <a href="reservations.php" class="btn btn-sm btn-outline-secondary">全予約を見る</a>
        </div>
        <?php if (empty($todayReservations)): ?>
        <div class="card-body text-center py-5 text-muted">
          本日の予約はありません
        </div>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>時間</th>
                <th>お名前</th>
                <th>メニュー</th>
                <th>料金</th>
                <th>ステータス</th>
                <th>操作</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($todayReservations as $r): ?>
              <tr>
                <td class="fw-semibold">
                  <?= formatTime($r['reservation_time']) ?>〜<?= formatTime($r['end_time']) ?>
                </td>
                <td>
                  <a href="customer_detail.php?id=<?= $r['user_id'] ?>" class="text-decoration-none fw-semibold">
                    <?= h($r['user_name']) ?>
                  </a>
                  <div class="text-muted small"><?= h($r['user_phone']) ?></div>
                </td>
                <td><?= h($r['menu_name']) ?></td>
                <td>¥<?= number_format($r['price']) ?></td>
                <td><?= getStatusLabel($r['status']) ?></td>
                <td>
                  <a href="reservations.php?action=edit&id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary">編集</a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>

    </main>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
