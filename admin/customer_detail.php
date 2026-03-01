<?php
/**
 * 顧客詳細ページ
 * 最終来店日・来店回数・売上履歴表示
 */
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
$admin    = requireAdminLogin();
$shopName = getSetting('shop_name', 'ご予約サイト');
$pdo      = db();

$userId   = (int)($_GET['id'] ?? 0);
$customer = getUserById($userId);

if (!$customer) {
    setFlash('error', '顧客が見つかりません。');
    header('Location: customers.php');
    exit;
}

$stats         = getCustomerStats($userId);
$allReservations = getReservationsByUser($userId);
$pointsHistory = getPointsHistory($userId, 30);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($customer['name']) ?> 様 | <?= h($shopName) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<?php include __DIR__ . '/partials/navbar.php'; ?>

<div class="container-fluid py-4">
  <div class="row">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
      <div class="d-flex align-items-center gap-3 mb-4">
        <a href="customers.php" class="btn btn-outline-secondary btn-sm">← 一覧に戻る</a>
        <h1 class="h3 fw-bold mb-0"><?= h($customer['name']) ?> 様</h1>
      </div>

      <?= renderFlash() ?>

      <div class="row g-4">

        <!-- 左カラム: 顧客情報・統計 -->
        <div class="col-lg-4">

          <!-- 顧客情報 -->
          <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white fw-bold">顧客情報</div>
            <div class="card-body">
              <dl class="row mb-0">
                <dt class="col-5 text-muted small">お名前</dt>
                <dd class="col-7 fw-semibold"><?= h($customer['name']) ?></dd>
                <dt class="col-5 text-muted small">電話番号</dt>
                <dd class="col-7"><?= h($customer['phone']) ?></dd>
                <dt class="col-5 text-muted small">保有ポイント</dt>
                <dd class="col-7">
                  <span class="badge bg-warning text-dark fs-6"><?= number_format($customer['points']) ?>pt</span>
                </dd>
                <dt class="col-5 text-muted small">登録日</dt>
                <dd class="col-7 small"><?= date('Y年m月d日', strtotime($customer['created_at'])) ?></dd>
              </dl>
            </div>
          </div>

          <!-- 来店統計 -->
          <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white fw-bold">来店統計</div>
            <div class="card-body">
              <div class="stat-row mb-3 pb-3 border-bottom">
                <div class="text-muted small">最終来店日</div>
                <div class="fw-bold fs-5">
                  <?= $stats['lastVisit'] ? formatDate($stats['lastVisit']) : '-' ?>
                </div>
              </div>
              <div class="stat-row mb-3 pb-3 border-bottom">
                <div class="text-muted small">来店回数</div>
                <div class="fw-bold fs-4 text-primary"><?= $stats['visitCount'] ?> 回</div>
              </div>
              <div class="stat-row">
                <div class="text-muted small">累計売上</div>
                <div class="fw-bold fs-4 text-success">¥<?= number_format($stats['totalSales']) ?></div>
              </div>
            </div>
          </div>

          <!-- ポイント調整 -->
          <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white fw-bold">ポイント調整</div>
            <div class="card-body">
              <form method="post" action="points.php">
                <?= csrfField() ?>
                <input type="hidden" name="user_id" value="<?= $customer['id'] ?>">
                <input type="hidden" name="redirect" value="customer_detail.php?id=<?= $customer['id'] ?>">
                <div class="mb-2">
                  <label class="form-label small">ポイント数（マイナス入力で減算）</label>
                  <input type="number" name="points" class="form-control" required
                         placeholder="例: 100 または -100">
                </div>
                <div class="mb-3">
                  <label class="form-label small">理由</label>
                  <input type="text" name="description" class="form-control"
                         placeholder="例: キャンペーン付与" maxlength="100">
                </div>
                <button type="submit" name="action" value="adjust" class="btn btn-warning w-100">
                  ポイントを調整する
                </button>
              </form>
            </div>
          </div>

        </div><!-- /left -->

        <!-- 右カラム: 予約・売上履歴 -->
        <div class="col-lg-8">

          <!-- 予約・売上履歴 -->
          <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white fw-bold d-flex justify-content-between">
              <span>予約・売上履歴</span>
              <span class="text-muted small">来店: <?= $stats['visitCount'] ?>回 / ¥<?= number_format($stats['totalSales']) ?></span>
            </div>
            <?php if (empty($allReservations)): ?>
            <div class="card-body text-center py-4 text-muted">予約履歴がありません</div>
            <?php else: ?>
            <div class="table-responsive">
              <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>日時</th>
                    <th>メニュー</th>
                    <th>料金</th>
                    <th>獲得Pt</th>
                    <th>ステータス</th>
                    <th>備考</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($allReservations as $r): ?>
                  <tr>
                    <td class="small">
                      <div><?= formatDate($r['reservation_date']) ?></div>
                      <div class="text-muted"><?= formatTime($r['reservation_time']) ?>〜</div>
                    </td>
                    <td class="small"><?= h($r['menu_name']) ?></td>
                    <td class="small">¥<?= number_format($r['price']) ?></td>
                    <td class="small">
                      <?php if ($r['status'] === 'completed' && $r['points_earned'] > 0): ?>
                        <span class="text-success">+<?= $r['points_earned'] ?>pt</span>
                      <?php else: ?>
                        <span class="text-muted">-</span>
                      <?php endif; ?>
                    </td>
                    <td><?= getStatusLabel($r['status']) ?></td>
                    <td class="small text-muted"><?= $r['staff_notes'] ? h(mb_strimwidth($r['staff_notes'], 0, 20, '…')) : '' ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php endif; ?>
          </div>

          <!-- ポイント履歴 -->
          <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-bold">ポイント履歴</div>
            <?php if (empty($pointsHistory)): ?>
            <div class="card-body text-center py-4 text-muted">ポイント履歴がありません</div>
            <?php else: ?>
            <div class="table-responsive">
              <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>日時</th>
                    <th>種別</th>
                    <th>内容</th>
                    <th>ポイント</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($pointsHistory as $ph): ?>
                  <tr>
                    <td class="small"><?= date('Y/m/d', strtotime($ph['created_at'])) ?></td>
                    <td>
                      <?php $typeBadges = [
                        'earned'   => '<span class="badge bg-success">付与</span>',
                        'used'     => '<span class="badge bg-danger">使用</span>',
                        'adjusted' => '<span class="badge bg-warning text-dark">調整</span>',
                        'expired'  => '<span class="badge bg-secondary">期限切</span>',
                      ]; echo $typeBadges[$ph['type']] ?? ''; ?>
                    </td>
                    <td class="small"><?= h($ph['description'] ?? '') ?></td>
                    <td class="fw-semibold <?= $ph['points'] >= 0 ? 'text-success' : 'text-danger' ?>">
                      <?= $ph['points'] >= 0 ? '+' : '' ?><?= number_format($ph['points']) ?>pt
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php endif; ?>
          </div>

        </div><!-- /right -->

      </div><!-- /row -->
    </main>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
