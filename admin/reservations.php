<?php
/**
 * 予約管理ページ（店側）
 * GoogleカレンダーAPIと連動
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/google_calendar.php';

startSecureSession();
$admin    = requireAdminLogin();
$shopName = getSetting('shop_name', 'ご予約サイト');
$pdo      = db();
$gcal     = new GoogleCalendar();

// ============================================================
// POST アクション処理
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        setFlash('error', '不正なリクエストです。');
        header('Location: reservations.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    $resId  = (int)($_POST['reservation_id'] ?? 0);

    if ($resId && in_array($action, ['confirm', 'complete', 'cancel'], true)) {
        $reservation = getReservationById($resId);

        if ($reservation) {
            $statusMap = [
                'confirm'  => 'confirmed',
                'complete' => 'completed',
                'cancel'   => 'cancelled',
            ];
            $newStatus = $statusMap[$action];

            // DB更新
            $staffNotes = sanitizeInput($_POST['staff_notes'] ?? '');
            $stmt = $pdo->prepare(
                'UPDATE reservations SET status = ?, staff_notes = ?, updated_at = NOW() WHERE id = ?'
            );
            $stmt->execute([$newStatus, $staffNotes ?: $reservation['staff_notes'], $resId]);

            // ポイント付与（完了時）
            if ($action === 'complete' && $reservation['points_earned'] > 0) {
                addPoints(
                    $reservation['user_id'],
                    $reservation['points_earned'],
                    'earned',
                    $reservation['menu_name'] . ' 来店ポイント',
                    $resId
                );
            }

            // Google Calendar 連携
            if ($action === 'confirm') {
                // カレンダーにイベント追加
                $eventId = $gcal->createEvent([
                    'user_name'          => $reservation['user_name'],
                    'user_phone'         => $reservation['user_phone'],
                    'menu_name'          => $reservation['menu_name'],
                    'reservation_date'   => $reservation['reservation_date'],
                    'reservation_time'   => $reservation['reservation_time'],
                    'end_time'           => $reservation['end_time'],
                    'price'              => $reservation['price'],
                    'customer_notes'     => $reservation['customer_notes'],
                ]);
                if ($eventId) {
                    $pdo->prepare('UPDATE reservations SET google_event_id = ? WHERE id = ?')
                        ->execute([$eventId, $resId]);
                }
            } elseif ($action === 'cancel' && $reservation['google_event_id']) {
                $gcal->deleteEvent($reservation['google_event_id']);
                $pdo->prepare('UPDATE reservations SET google_event_id = NULL WHERE id = ?')
                    ->execute([$resId]);
            }

            $labels = ['confirm' => '確定', 'complete' => '完了', 'cancel' => 'キャンセル'];
            setFlash('success', "予約を{$labels[$action]}しました。");
        }
    }

    header('Location: reservations.php' . ($_POST['redirect'] ? '?' . $_POST['redirect'] : ''));
    exit;
}

// ============================================================
// 編集画面
// ============================================================
$editReservation = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $editReservation = getReservationById((int)$_GET['id']);
}

// ============================================================
// 一覧取得
// ============================================================
$filterStatus = $_GET['status'] ?? '';
$filterDate   = $_GET['date']   ?? '';
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 20;
$offset       = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];

if ($filterStatus) {
    $where[]  = 'r.status = ?';
    $params[] = $filterStatus;
}
if ($filterDate) {
    $where[]  = 'r.reservation_date = ?';
    $params[] = $filterDate;
}

$whereClause = implode(' AND ', $where);

$countStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM reservations r JOIN users u ON r.user_id = u.id WHERE {$whereClause}"
);
$countStmt->execute($params);
$totalCount = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalCount / $perPage));

$listStmt = $pdo->prepare(
    "SELECT r.*, u.name AS user_name, u.phone AS user_phone
     FROM reservations r
     JOIN users u ON r.user_id = u.id
     WHERE {$whereClause}
     ORDER BY r.reservation_date DESC, r.reservation_time DESC
     LIMIT {$perPage} OFFSET {$offset}"
);
$listStmt->execute($params);
$reservations = $listStmt->fetchAll();

$menus = getActiveMenus();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>予約管理 | <?= h($shopName) ?></title>
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
        <h1 class="h3 fw-bold">予約管理</h1>
      </div>

      <?= renderFlash() ?>

      <!-- 編集パネル -->
      <?php if ($editReservation): ?>
      <div class="card shadow-sm border-0 mb-4 border-primary">
        <div class="card-header bg-primary text-white fw-bold">
          予約 #<?= $editReservation['id'] ?> の編集
        </div>
        <div class="card-body">
          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <div class="text-muted small">お名前</div>
              <div class="fw-bold">
                <a href="customer_detail.php?id=<?= $editReservation['user_id'] ?>">
                  <?= h($editReservation['user_name']) ?>
                </a>
              </div>
            </div>
            <div class="col-md-4">
              <div class="text-muted small">電話番号</div>
              <div class="fw-bold"><?= h($editReservation['user_phone']) ?></div>
            </div>
            <div class="col-md-4">
              <div class="text-muted small">ステータス</div>
              <div><?= getStatusLabel($editReservation['status']) ?></div>
            </div>
            <div class="col-md-4">
              <div class="text-muted small">日時</div>
              <div class="fw-bold">
                <?= formatDate($editReservation['reservation_date']) ?>
                (<?= getDayOfWeek($editReservation['reservation_date']) ?>)
                <?= formatTime($editReservation['reservation_time']) ?>〜<?= formatTime($editReservation['end_time']) ?>
              </div>
            </div>
            <div class="col-md-4">
              <div class="text-muted small">メニュー</div>
              <div><?= h($editReservation['menu_name']) ?></div>
            </div>
            <div class="col-md-4">
              <div class="text-muted small">料金</div>
              <div class="fw-bold">¥<?= number_format($editReservation['price']) ?></div>
            </div>
            <?php if ($editReservation['customer_notes']): ?>
            <div class="col-12">
              <div class="text-muted small">顧客備考</div>
              <div class="border rounded p-2 small"><?= h($editReservation['customer_notes']) ?></div>
            </div>
            <?php endif; ?>
          </div>

          <form method="post" action="reservations.php">
            <?= csrfField() ?>
            <input type="hidden" name="reservation_id" value="<?= $editReservation['id'] ?>">
            <input type="hidden" name="redirect" value="<?= h($_SERVER['QUERY_STRING']) ?>">

            <div class="mb-3">
              <label class="form-label">スタッフメモ</label>
              <textarea name="staff_notes" class="form-control" rows="2"><?= h($editReservation['staff_notes']) ?></textarea>
            </div>

            <div class="d-flex gap-2 flex-wrap">
              <?php if ($editReservation['status'] === 'pending'): ?>
              <button type="submit" name="action" value="confirm" class="btn btn-success">
                ✓ 予約を確定する（Googleカレンダーに追加）
              </button>
              <?php endif; ?>
              <?php if (in_array($editReservation['status'], ['pending', 'confirmed'], true)): ?>
              <button type="submit" name="action" value="complete" class="btn btn-primary">
                施術完了・ポイント付与
              </button>
              <button type="submit" name="action" value="cancel" class="btn btn-outline-danger"
                      onclick="return confirm('キャンセルしますか？')">
                キャンセル
              </button>
              <?php endif; ?>
              <a href="reservations.php" class="btn btn-outline-secondary">戻る</a>
            </div>
          </form>
        </div>
      </div>
      <?php endif; ?>

      <!-- フィルター -->
      <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
          <form method="get" action="reservations.php" class="row g-2 align-items-end">
            <div class="col-sm-auto">
              <label class="form-label small">日付</label>
              <input type="date" name="date" class="form-control" value="<?= h($filterDate) ?>">
            </div>
            <div class="col-sm-auto">
              <label class="form-label small">ステータス</label>
              <select name="status" class="form-select">
                <option value="">すべて</option>
                <option value="pending"   <?= $filterStatus === 'pending'   ? 'selected' : '' ?>>仮予約</option>
                <option value="confirmed" <?= $filterStatus === 'confirmed' ? 'selected' : '' ?>>確定</option>
                <option value="completed" <?= $filterStatus === 'completed' ? 'selected' : '' ?>>完了</option>
                <option value="cancelled" <?= $filterStatus === 'cancelled' ? 'selected' : '' ?>>キャンセル</option>
              </select>
            </div>
            <div class="col-sm-auto">
              <button type="submit" class="btn btn-primary">絞り込む</button>
              <a href="reservations.php" class="btn btn-outline-secondary">リセット</a>
            </div>
          </form>
        </div>
      </div>

      <!-- 予約一覧 -->
      <div class="card shadow-sm border-0">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
          <span class="fw-bold">予約一覧</span>
          <span class="text-muted small">全 <?= $totalCount ?> 件</span>
        </div>
        <?php if (empty($reservations)): ?>
        <div class="card-body text-center py-5 text-muted">予約がありません</div>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>日時</th>
                <th>お名前</th>
                <th>メニュー</th>
                <th>料金</th>
                <th>ステータス</th>
                <th>Gカレ</th>
                <th>操作</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($reservations as $r): ?>
              <tr>
                <td class="small">
                  <div class="fw-semibold"><?= formatDate($r['reservation_date']) ?></div>
                  <div class="text-muted"><?= formatTime($r['reservation_time']) ?>〜<?= formatTime($r['end_time']) ?></div>
                </td>
                <td>
                  <a href="customer_detail.php?id=<?= $r['user_id'] ?>" class="text-decoration-none fw-semibold">
                    <?= h($r['user_name']) ?>
                  </a>
                  <div class="text-muted small"><?= h($r['user_phone']) ?></div>
                </td>
                <td class="small"><?= h($r['menu_name']) ?></td>
                <td class="small">¥<?= number_format($r['price']) ?></td>
                <td><?= getStatusLabel($r['status']) ?></td>
                <td>
                  <?php if ($r['google_event_id']): ?>
                    <span class="badge bg-success" title="Googleカレンダー連携済み">✓</span>
                  <?php else: ?>
                    <span class="badge bg-light text-muted">-</span>
                  <?php endif; ?>
                </td>
                <td>
                  <a href="reservations.php?action=edit&id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary">
                    編集
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
                <a class="page-link" href="?page=<?= $i ?>&status=<?= h($filterStatus) ?>&date=<?= h($filterDate) ?>">
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
