<?php
/**
 * マイページ（顧客）
 * 予約一覧・ポイント確認
 */
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
$user     = requireUserLogin();
$shopName = getSetting('shop_name', 'ご予約サイト');

// DBから最新のユーザー情報を取得
$user = getUserById($user['id']);
$_SESSION[USER_SESSION_NAME] = $user;

$upcomingReservations = getUpcomingReservations($user['id']);
$pastReservations     = array_filter(getReservationsByUser($user['id']), function($r) {
    return $r['status'] === 'completed' || ($r['reservation_date'] < date('Y-m-d') && $r['status'] !== 'cancelled');
});
$pointsHistory = getPointsHistory($user['id'], 10);
$points        = getUserPoints($user['id']);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>マイページ | <?= h($shopName) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">

<!-- ナビバー -->
<nav class="navbar navbar-dark bg-primary">
  <div class="container">
    <a class="navbar-brand fw-bold" href="mypage.php"><?= h($shopName) ?></a>
    <a href="logout.php" class="btn btn-outline-light btn-sm">ログアウト</a>
  </div>
</nav>

<div class="container py-4">

  <?= renderFlash() ?>

  <!-- ウェルカムカード -->
  <div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
      <div class="d-flex align-items-center gap-3">
        <div class="user-avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center"
             style="width:56px;height:56px;font-size:1.5rem;flex-shrink:0;">
          <?= mb_substr($user['name'], 0, 1) ?>
        </div>
        <div>
          <div class="fw-bold fs-5"><?= h($user['name']) ?> 様</div>
          <div class="text-muted small"><?= h($user['phone']) ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- ポイントカード -->
  <div class="card shadow-sm border-0 mb-4 bg-primary text-white">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <div class="small opacity-75 mb-1">保有ポイント</div>
          <div class="display-6 fw-bold"><?= number_format($points) ?> <small class="fs-5">pt</small></div>
        </div>
        <div class="opacity-50">
          <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="currentColor" viewBox="0 0 16 16">
            <path d="M12 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h8zM4 0a2 2 0 0 0-2
                     2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H4z"/>
            <path d="M4 2.5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-.5.5h-7a.5.5 0 0 1-.5-.5v-2zm0
                     4a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1zm0
                     3a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1zm0
                     3a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1zm3-6a.5.5
                     0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1zm0 3a.5.5 0 0 1
                     .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1zm0 3a.5.5 0 0 1 .5-.5h1a.5.5
                     0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1zm3-6a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1
                     .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1zm0 3a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v4a.5.5
                     0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-4z"/>
          </svg>
        </div>
      </div>
    </div>
  </div>

  <!-- 予約ボタン -->
  <div class="d-grid mb-4">
    <a href="reserve.php" class="btn btn-success btn-lg py-3 fw-bold fs-5">
      ＋ 予約する
    </a>
  </div>

  <!-- 今後の予約 -->
  <h2 class="h5 fw-bold mb-3">今後のご予約</h2>
  <?php if (empty($upcomingReservations)): ?>
    <div class="card shadow-sm border-0 mb-4">
      <div class="card-body text-center py-4 text-muted">
        予約はありません
      </div>
    </div>
  <?php else: ?>
    <?php foreach ($upcomingReservations as $r): ?>
    <div class="card shadow-sm border-0 mb-3">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="fw-bold mb-1"><?= h($r['menu_name']) ?></div>
            <div class="text-muted small">
              <?= formatDate($r['reservation_date']) ?>(<?= getDayOfWeek($r['reservation_date']) ?>)
              <?= formatTime($r['reservation_time']) ?>〜<?= formatTime($r['end_time']) ?>
            </div>
            <div class="text-muted small">¥<?= number_format($r['price']) ?></div>
          </div>
          <div><?= getStatusLabel($r['status']) ?></div>
        </div>
        <?php if ($r['customer_notes']): ?>
          <div class="mt-2 small text-muted border-top pt-2">備考: <?= h($r['customer_notes']) ?></div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <!-- ポイント履歴 -->
  <h2 class="h5 fw-bold mt-4 mb-3">ポイント履歴</h2>
  <?php if (empty($pointsHistory)): ?>
    <div class="card shadow-sm border-0 mb-4">
      <div class="card-body text-center py-4 text-muted">
        ポイント履歴はありません
      </div>
    </div>
  <?php else: ?>
    <div class="card shadow-sm border-0 mb-4">
      <div class="list-group list-group-flush">
        <?php foreach ($pointsHistory as $ph): ?>
        <div class="list-group-item">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="small fw-semibold"><?= h($ph['description'] ?? '') ?></div>
              <div class="text-muted" style="font-size:.75rem">
                <?= date('Y年m月d日', strtotime($ph['created_at'])) ?>
              </div>
            </div>
            <div class="fw-bold <?= $ph['points'] >= 0 ? 'text-success' : 'text-danger' ?>">
              <?= $ph['points'] >= 0 ? '+' : '' ?><?= number_format($ph['points']) ?>pt
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
