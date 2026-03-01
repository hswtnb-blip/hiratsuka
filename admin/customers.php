<?php
/**
 * 顧客一覧ページ（店側）
 */
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
$admin    = requireAdminLogin();
$shopName = getSetting('shop_name', 'ご予約サイト');
$pdo      = db();

// 検索
$search  = sanitizeInput($_GET['search'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$offset  = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];

if ($search) {
    $where[]  = '(u.name LIKE ? OR u.phone LIKE ?)';
    $like     = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
}

$whereClause = implode(' AND ', $where);

// 顧客一覧（来店回数・売上付き）
$countStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM users u WHERE {$whereClause}"
);
$countStmt->execute($params);
$totalCount = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalCount / $perPage));

$listStmt = $pdo->prepare(
    "SELECT u.*,
       (SELECT COUNT(*) FROM reservations r WHERE r.user_id = u.id AND r.status = 'completed') AS visit_count,
       (SELECT COALESCE(SUM(r.price), 0) FROM reservations r WHERE r.user_id = u.id AND r.status = 'completed') AS total_sales,
       (SELECT MAX(r.reservation_date) FROM reservations r WHERE r.user_id = u.id AND r.status = 'completed') AS last_visit
     FROM users u
     WHERE {$whereClause}
     ORDER BY u.created_at DESC
     LIMIT {$perPage} OFFSET {$offset}"
);
$listStmt->execute($params);
$customers = $listStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>顧客一覧 | <?= h($shopName) ?></title>
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
        <h1 class="h3 fw-bold">顧客一覧</h1>
        <span class="badge bg-secondary fs-6"><?= $totalCount ?> 名</span>
      </div>

      <?= renderFlash() ?>

      <!-- 検索 -->
      <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
          <form method="get" action="customers.php" class="row g-2 align-items-end">
            <div class="col-sm">
              <input type="text" name="search" class="form-control"
                     placeholder="名前・電話番号で検索"
                     value="<?= h($search) ?>">
            </div>
            <div class="col-sm-auto">
              <button type="submit" class="btn btn-primary">検索</button>
              <a href="customers.php" class="btn btn-outline-secondary">リセット</a>
            </div>
          </form>
        </div>
      </div>

      <!-- 顧客一覧 -->
      <div class="card shadow-sm border-0">
        <?php if (empty($customers)): ?>
        <div class="card-body text-center py-5 text-muted">顧客が見つかりません</div>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>お名前</th>
                <th>電話番号</th>
                <th>来店回数</th>
                <th>最終来店日</th>
                <th>累計売上</th>
                <th>保有Pt</th>
                <th>登録日</th>
                <th>詳細</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($customers as $c): ?>
              <tr>
                <td class="fw-semibold">
                  <a href="customer_detail.php?id=<?= $c['id'] ?>" class="text-decoration-none">
                    <?= h($c['name']) ?>
                  </a>
                </td>
                <td><?= h($c['phone']) ?></td>
                <td class="text-center"><?= number_format($c['visit_count']) ?> 回</td>
                <td>
                  <?php if ($c['last_visit']): ?>
                    <?= formatDate($c['last_visit']) ?>
                  <?php else: ?>
                    <span class="text-muted">-</span>
                  <?php endif; ?>
                </td>
                <td>¥<?= number_format($c['total_sales']) ?></td>
                <td>
                  <span class="badge bg-warning text-dark"><?= number_format($c['points']) ?>pt</span>
                </td>
                <td class="small text-muted"><?= date('Y/m/d', strtotime($c['created_at'])) ?></td>
                <td>
                  <a href="customer_detail.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary">
                    詳細
                  </a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- ページネーション -->
        <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white">
          <nav>
            <ul class="pagination pagination-sm mb-0 justify-content-center">
              <?php for ($i = 1; $i <= $totalPages; $i++): ?>
              <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>">
                  <?= $i ?>
                </a>
              </li>
              <?php endfor; ?>
            </ul>
          </nav>
        </div>
        <?php endif; ?>
        <?php endif; ?>
      </div>

    </main>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
