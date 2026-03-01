<?php
/**
 * ポイント管理ページ（店側）
 */
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
$admin    = requireAdminLogin();
$shopName = getSetting('shop_name', 'ご予約サイト');
$pdo      = db();

// POST処理（ポイント付与・調整）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        setFlash('error', '不正なリクエストです。');
    } else {
        $action      = $_POST['action']      ?? '';
        $userId      = (int)($_POST['user_id']      ?? 0);
        $points      = (int)($_POST['points']       ?? 0);
        $description = sanitizeInput($_POST['description'] ?? '');
        $redirect    = $_POST['redirect'] ?? 'points.php';

        if ($action === 'adjust' && $userId && $points !== 0) {
            $user = getUserById($userId);
            if ($user) {
                // ポイントがマイナスになる場合は保有ポイントをチェック
                if ($points < 0 && ($user['points'] + $points) < 0) {
                    setFlash('error', '保有ポイントが不足しています。');
                } else {
                    $type = $points > 0 ? 'adjusted' : 'adjusted';
                    addPoints($userId, $points, $type, $description ?: 'ポイント調整');
                    setFlash('success', h($user['name']) . ' 様のポイントを ' . ($points > 0 ? '+' : '') . $points . 'pt 調整しました。');
                }
            }
        }
    }

    $redirect = filter_var($_POST['redirect'] ?? '', FILTER_SANITIZE_URL);
    if (!$redirect || !preg_match('/^[a-zA-Z0-9_.?\-=&]+$/', $redirect)) {
        $redirect = 'points.php';
    }
    header('Location: ' . $redirect);
    exit;
}

// 検索
$search  = sanitizeInput($_GET['search'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
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

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE {$whereClause}");
$countStmt->execute($params);
$totalCount = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalCount / $perPage));

$listStmt = $pdo->prepare(
    "SELECT u.*,
       (SELECT COUNT(*) FROM reservations r WHERE r.user_id = u.id AND r.status = 'completed') AS visit_count,
       (SELECT MAX(r.reservation_date) FROM reservations r WHERE r.user_id = u.id AND r.status = 'completed') AS last_visit
     FROM users u WHERE {$whereClause}
     ORDER BY u.points DESC, u.name ASC
     LIMIT {$perPage} OFFSET {$offset}"
);
$listStmt->execute($params);
$customers = $listStmt->fetchAll();

// 最近のポイント履歴
$recentPoints = $pdo->query(
    'SELECT ph.*, u.name AS user_name
     FROM points_history ph
     JOIN users u ON ph.user_id = u.id
     ORDER BY ph.created_at DESC LIMIT 20'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ポイント管理 | <?= h($shopName) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<?php include __DIR__ . '/partials/navbar.php'; ?>

<div class="container-fluid py-4">
  <div class="row">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
      <h1 class="h3 fw-bold mb-4">ポイント管理</h1>

      <?= renderFlash() ?>

      <div class="row g-4">

        <!-- 顧客一覧 -->
        <div class="col-lg-8">
          <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-bold">顧客ポイント一覧</div>
            <div class="card-body border-bottom">
              <form method="get" action="points.php" class="row g-2">
                <div class="col">
                  <input type="text" name="search" class="form-control" placeholder="名前・電話番号で検索"
                         value="<?= h($search) ?>">
                </div>
                <div class="col-auto">
                  <button type="submit" class="btn btn-primary">検索</button>
                </div>
              </form>
            </div>
            <?php if (empty($customers)): ?>
            <div class="card-body text-center text-muted py-4">顧客が見つかりません</div>
            <?php else: ?>
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>お名前</th>
                    <th>電話番号</th>
                    <th>保有ポイント</th>
                    <th>来店回数</th>
                    <th>最終来店</th>
                    <th>付与・調整</th>
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
                    <td class="small"><?= h($c['phone']) ?></td>
                    <td>
                      <span class="badge bg-warning text-dark fs-6"><?= number_format($c['points']) ?>pt</span>
                    </td>
                    <td class="text-center"><?= $c['visit_count'] ?>回</td>
                    <td class="small">
                      <?= $c['last_visit'] ? formatDate($c['last_visit']) : '-' ?>
                    </td>
                    <td>
                      <button class="btn btn-sm btn-outline-warning"
                              onclick="openPointModal(<?= $c['id'] ?>, '<?= h(addslashes($c['name'])) ?>', <?= $c['points'] ?>)">
                        調整
                      </button>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white">
              <nav>
                <ul class="pagination pagination-sm mb-0 justify-content-center">
                  <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                  <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                  </li>
                  <?php endfor; ?>
                </ul>
              </nav>
            </div>
            <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- 最近のポイント履歴 -->
        <div class="col-lg-4">
          <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-bold">最近のポイント動向</div>
            <div class="list-group list-group-flush">
              <?php if (empty($recentPoints)): ?>
              <div class="list-group-item text-center text-muted py-3">履歴がありません</div>
              <?php else: ?>
              <?php foreach ($recentPoints as $ph): ?>
              <div class="list-group-item py-2">
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <div class="small fw-semibold"><?= h($ph['user_name']) ?> 様</div>
                    <div class="text-muted" style="font-size:.75rem">
                      <?= h($ph['description'] ?? '') ?>・<?= date('m/d H:i', strtotime($ph['created_at'])) ?>
                    </div>
                  </div>
                  <div class="fw-bold <?= $ph['points'] >= 0 ? 'text-success' : 'text-danger' ?> small">
                    <?= $ph['points'] >= 0 ? '+' : '' ?><?= $ph['points'] ?>pt
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>

      </div><!-- /row -->
    </main>
  </div>
</div>

<!-- ポイント調整モーダル -->
<div class="modal fade" id="pointModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">ポイント調整</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" action="points.php">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="adjust">
        <input type="hidden" name="user_id" id="modal_user_id">
        <input type="hidden" name="redirect" value="points.php?search=<?= urlencode($search) ?>&page=<?= $page ?>">
        <div class="modal-body">
          <p>
            <strong id="modal_user_name"></strong> 様<br>
            現在のポイント: <strong id="modal_current_points" class="text-warning"></strong>pt
          </p>
          <div class="mb-3">
            <label class="form-label">調整ポイント数</label>
            <input type="number" name="points" class="form-control" required
                   placeholder="付与: 正の数（例: 100）/ 減算: 負の数（例: -100）">
            <div class="form-text">マイナス入力でポイントを減算します</div>
          </div>
          <div class="mb-3">
            <label class="form-label">調整理由</label>
            <input type="text" name="description" class="form-control"
                   placeholder="例: キャンペーン付与" maxlength="100">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
          <button type="submit" class="btn btn-warning">調整する</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openPointModal(userId, userName, currentPoints) {
    document.getElementById('modal_user_id').value = userId;
    document.getElementById('modal_user_name').textContent = userName;
    document.getElementById('modal_current_points').textContent = currentPoints.toLocaleString();
    new bootstrap.Modal(document.getElementById('pointModal')).show();
}
</script>
</body>
</html>
