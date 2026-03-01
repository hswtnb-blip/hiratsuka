<?php
/**
 * メニュー管理ページ
 */
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
$admin    = requireAdminLogin();
$shopName = getSetting('shop_name', 'ご予約サイト');
$pdo      = db();
$errors   = [];

// POST 処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        setFlash('error', '不正なリクエストです。');
        header('Location: menus.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id          = (int)($_POST['id'] ?? 0);
        $name        = sanitizeInput($_POST['name']        ?? '');
        $price       = (int)($_POST['price']       ?? 0);
        $duration    = (int)($_POST['duration']    ?? 60);
        $description = sanitizeInput($_POST['description'] ?? '');
        $pointsRate  = (int)($_POST['points_rate'] ?? 1);
        $sortOrder   = (int)($_POST['sort_order']  ?? 0);
        $isActive    = isset($_POST['is_active']) ? 1 : 0;

        if (!$name) $errors[] = 'メニュー名を入力してください。';
        if ($price < 0) $errors[] = '料金は0以上を入力してください。';
        if ($duration < 15) $errors[] = '所要時間は15分以上を入力してください。';

        if (empty($errors)) {
            if ($id) {
                $stmt = $pdo->prepare(
                    'UPDATE menus SET name=?, price=?, duration=?, description=?, points_rate=?, sort_order=?, is_active=? WHERE id=?'
                );
                $stmt->execute([$name, $price, $duration, $description, $pointsRate, $sortOrder, $isActive, $id]);
                setFlash('success', 'メニューを更新しました。');
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO menus (name, price, duration, description, points_rate, sort_order, is_active) VALUES (?,?,?,?,?,?,?)'
                );
                $stmt->execute([$name, $price, $duration, $description, $pointsRate, $sortOrder, $isActive]);
                setFlash('success', 'メニューを追加しました。');
            }
            header('Location: menus.php');
            exit;
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $pdo->prepare('UPDATE menus SET is_active = 0 WHERE id = ?')->execute([$id]);
            setFlash('success', 'メニューを無効化しました。');
        }
        header('Location: menus.php');
        exit;
    }
}

// 編集対象
$editMenu = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM menus WHERE id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $editMenu = $stmt->fetch() ?: null;
}

// メニュー一覧
$menus = $pdo->query('SELECT * FROM menus ORDER BY sort_order ASC, id ASC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>メニュー管理 | <?= h($shopName) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<?php include __DIR__ . '/partials/navbar.php'; ?>

<div class="container-fluid py-4">
  <div class="row">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
      <h1 class="h3 fw-bold mb-4">メニュー管理</h1>

      <?= renderFlash() ?>

      <div class="row g-4">

        <!-- メニューフォーム -->
        <div class="col-lg-5">
          <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-bold">
              <?= $editMenu ? 'メニューを編集' : '新規メニューを追加' ?>
            </div>
            <div class="card-body">
              <?php if ($errors): ?>
              <div class="alert alert-danger"><ul class="mb-0 ps-3">
                <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
              </ul></div>
              <?php endif; ?>

              <form method="post" action="menus.php">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save">
                <?php if ($editMenu): ?>
                <input type="hidden" name="id" value="<?= $editMenu['id'] ?>">
                <?php endif; ?>

                <div class="mb-3">
                  <label class="form-label">メニュー名 <span class="text-danger">*</span></label>
                  <input type="text" name="name" class="form-control" required maxlength="100"
                         value="<?= h($editMenu['name'] ?? '') ?>">
                </div>
                <div class="row g-2 mb-3">
                  <div class="col-6">
                    <label class="form-label">料金（税込）<span class="text-danger">*</span></label>
                    <div class="input-group">
                      <span class="input-group-text">¥</span>
                      <input type="number" name="price" class="form-control" required min="0"
                             value="<?= h($editMenu['price'] ?? '') ?>">
                    </div>
                  </div>
                  <div class="col-6">
                    <label class="form-label">所要時間（分）<span class="text-danger">*</span></label>
                    <div class="input-group">
                      <input type="number" name="duration" class="form-control" required min="15" step="15"
                             value="<?= h($editMenu['duration'] ?? 60) ?>">
                      <span class="input-group-text">分</span>
                    </div>
                  </div>
                </div>
                <div class="mb-3">
                  <label class="form-label">説明</label>
                  <textarea name="description" class="form-control" rows="2" maxlength="500"><?= h($editMenu['description'] ?? '') ?></textarea>
                </div>
                <div class="row g-2 mb-3">
                  <div class="col-6">
                    <label class="form-label">ポイント付与率（%）</label>
                    <div class="input-group">
                      <input type="number" name="points_rate" class="form-control" min="0" max="100"
                             value="<?= h($editMenu['points_rate'] ?? 1) ?>">
                      <span class="input-group-text">%</span>
                    </div>
                  </div>
                  <div class="col-6">
                    <label class="form-label">表示順</label>
                    <input type="number" name="sort_order" class="form-control" min="0"
                           value="<?= h($editMenu['sort_order'] ?? 0) ?>">
                  </div>
                </div>
                <div class="mb-3 form-check">
                  <input type="checkbox" class="form-check-input" id="is_active" name="is_active"
                         <?= (!isset($editMenu) || $editMenu['is_active']) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="is_active">有効（予約フォームに表示）</label>
                </div>

                <div class="d-flex gap-2">
                  <button type="submit" class="btn btn-primary">
                    <?= $editMenu ? '更新する' : '追加する' ?>
                  </button>
                  <?php if ($editMenu): ?>
                  <a href="menus.php" class="btn btn-outline-secondary">キャンセル</a>
                  <?php endif; ?>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- メニュー一覧 -->
        <div class="col-lg-7">
          <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-bold">メニュー一覧</div>
            <?php if (empty($menus)): ?>
            <div class="card-body text-center text-muted py-4">メニューがありません</div>
            <?php else: ?>
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>メニュー名</th>
                    <th>料金</th>
                    <th>時間</th>
                    <th>Pt率</th>
                    <th>状態</th>
                    <th>操作</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($menus as $m): ?>
                  <tr class="<?= !$m['is_active'] ? 'table-secondary text-muted' : '' ?>">
                    <td class="fw-semibold"><?= h($m['name']) ?></td>
                    <td>¥<?= number_format($m['price']) ?></td>
                    <td><?= $m['duration'] ?>分</td>
                    <td><?= $m['points_rate'] ?>%</td>
                    <td>
                      <?= $m['is_active']
                        ? '<span class="badge bg-success">有効</span>'
                        : '<span class="badge bg-secondary">無効</span>' ?>
                    </td>
                    <td>
                      <a href="menus.php?edit=<?= $m['id'] ?>" class="btn btn-sm btn-outline-primary">編集</a>
                      <form method="post" action="menus.php" class="d-inline"
                            onsubmit="return confirm('このメニューを無効化しますか？')">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $m['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger">無効化</button>
                      </form>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php endif; ?>
          </div>
        </div>

      </div>
    </main>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
