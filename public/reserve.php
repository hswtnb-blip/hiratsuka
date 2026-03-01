<?php
/**
 * 予約フォームページ
 */
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
$user     = requireUserLogin();
$shopName = getSetting('shop_name', 'ご予約サイト');
$menus    = getActiveMenus();
$errors   = [];
$success  = false;

// デフォルト選択日は翌日
$defaultDate = date('Y-m-d', strtotime('+1 day'));
$minDate     = date('Y-m-d', strtotime('+1 day'));
$maxDate     = date('Y-m-d', strtotime('+2 months'));

// 選択中メニュー
$selectedMenuId   = (int)($_POST['menu_id']   ?? $_GET['menu_id']   ?? 0);
$selectedDate     = $_POST['date'] ?? $_GET['date'] ?? $defaultDate;
$selectedTime     = $_POST['time'] ?? '';

// 日付が変更されたとき（Ajax的な再ロード）
$selectedMenu     = null;
$availableSlots   = [];

if ($selectedMenuId) {
    foreach ($menus as $m) {
        if ((int)$m['id'] === $selectedMenuId) {
            $selectedMenu = $m;
            break;
        }
    }
}

if ($selectedMenu && $selectedDate) {
    $availableSlots = getAvailableTimeSlots($selectedDate, (int)$selectedMenu['duration']);
}

// POST送信（予約確定）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = '不正なリクエストです。';
    } else {
        $menuId   = (int)($_POST['menu_id'] ?? 0);
        $date     = sanitizeInput($_POST['date']  ?? '');
        $time     = sanitizeInput($_POST['time']  ?? '');
        $notes    = sanitizeInput($_POST['notes'] ?? '');

        // バリデーション
        if (!$menuId) $errors[] = 'メニューを選択してください。';
        if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $errors[] = '日付を選択してください。';
        if (!$time || !preg_match('/^\d{2}:\d{2}$/', $time)) $errors[] = '時間を選択してください。';
        if ($date && $date < $minDate) $errors[] = '過去の日付は選択できません。';

        if (empty($errors)) {
            // メニュー取得
            $menuStmt = db()->prepare('SELECT * FROM menus WHERE id = ? AND is_active = 1');
            $menuStmt->execute([$menuId]);
            $menu = $menuStmt->fetch();

            if (!$menu) {
                $errors[] = 'メニューが見つかりません。';
            } else {
                // 終了時刻計算
                $endTime = date('H:i:s', strtotime($time . ' +' . $menu['duration'] . ' minutes'));

                // 空き確認
                if (!isTimeSlotAvailable($date, $time . ':00', $endTime)) {
                    $errors[] = '選択した時間帯はすでに予約が入っています。別の時間をお選びください。';
                }
            }
        }

        if (empty($errors)) {
            try {
                $pdo = db();

                // ポイント計算（料金の1%分）
                $pointRate   = (int)($menu['points_rate'] ?? 1);
                $pointsEarned = (int)floor($menu['price'] * $pointRate / 100);

                $stmt = $pdo->prepare(
                    'INSERT INTO reservations
                     (user_id, menu_id, menu_name, reservation_date, reservation_time, end_time, price, customer_notes, points_earned)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $user['id'],
                    $menu['id'],
                    $menu['name'],
                    $date,
                    $time . ':00',
                    $endTime,
                    $menu['price'],
                    $notes,
                    $pointsEarned,
                ]);

                setFlash('success', 'ご予約を受け付けました！確定次第ご連絡いたします。');
                header('Location: mypage.php');
                exit;
            } catch (PDOException $e) {
                error_log('Reserve error: ' . $e->getMessage());
                $errors[] = '予約の登録に失敗しました。再度お試しください。';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>予約する | <?= h($shopName) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">

<!-- ナビバー -->
<nav class="navbar navbar-dark bg-primary">
  <div class="container">
    <a class="navbar-brand fw-bold" href="mypage.php"><?= h($shopName) ?></a>
    <div class="d-flex gap-2">
      <a href="mypage.php" class="btn btn-outline-light btn-sm">マイページ</a>
      <a href="logout.php" class="btn btn-outline-light btn-sm">ログアウト</a>
    </div>
  </div>
</nav>

<div class="container py-4">
  <h1 class="h4 fw-bold mb-4">予約する</h1>

  <?= renderFlash() ?>

  <?php if ($errors): ?>
  <div class="alert alert-danger">
    <ul class="mb-0 ps-3">
      <?php foreach ($errors as $e): ?>
        <li><?= h($e) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <form method="post" action="reserve.php" id="reserveForm">
    <?= csrfField() ?>

    <!-- STEP 1: メニュー選択 -->
    <div class="card shadow-sm border-0 mb-4">
      <div class="card-header bg-white fw-semibold">
        <span class="badge bg-primary me-2">1</span>メニューを選ぶ
      </div>
      <div class="card-body">
        <div class="row g-3">
          <?php foreach ($menus as $menu): ?>
          <div class="col-12">
            <label class="menu-card <?= ($selectedMenuId === (int)$menu['id']) ? 'selected' : '' ?>">
              <input type="radio" name="menu_id" value="<?= $menu['id'] ?>"
                     <?= ($selectedMenuId === (int)$menu['id']) ? 'checked' : '' ?>
                     class="menu-radio" onchange="this.form.submit()">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <div class="fw-semibold"><?= h($menu['name']) ?></div>
                  <?php if ($menu['description']): ?>
                    <div class="text-muted small"><?= h($menu['description']) ?></div>
                  <?php endif; ?>
                </div>
                <div class="text-end">
                  <div class="fw-bold text-primary">¥<?= number_format($menu['price']) ?></div>
                  <div class="text-muted small"><?= $menu['duration'] ?>分</div>
                </div>
              </div>
            </label>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- STEP 2: 日付選択 -->
    <?php if ($selectedMenu): ?>
    <div class="card shadow-sm border-0 mb-4">
      <div class="card-header bg-white fw-semibold">
        <span class="badge bg-primary me-2">2</span>日付を選ぶ
      </div>
      <div class="card-body">
        <input type="date" name="date" class="form-control form-control-lg"
               value="<?= h($selectedDate) ?>"
               min="<?= $minDate ?>" max="<?= $maxDate ?>"
               onchange="this.form.submit()">
      </div>
    </div>

    <!-- STEP 3: 時間選択 -->
    <div class="card shadow-sm border-0 mb-4">
      <div class="card-header bg-white fw-semibold">
        <span class="badge bg-primary me-2">3</span>時間を選ぶ
      </div>
      <div class="card-body">
        <?php if (empty($availableSlots)): ?>
          <div class="alert alert-warning mb-0">
            選択された日に空き時間がありません。別の日を選んでください。
          </div>
        <?php else: ?>
          <div class="row g-2">
            <?php foreach ($availableSlots as $slot): ?>
            <div class="col-4 col-sm-3">
              <label class="time-slot <?= ($selectedTime === $slot) ? 'selected' : '' ?>">
                <input type="radio" name="time" value="<?= $slot ?>"
                       <?= ($selectedTime === $slot) ? 'checked' : '' ?>>
                <?= $slot ?>
              </label>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- STEP 4: 備考 -->
    <?php if ($selectedTime): ?>
    <div class="card shadow-sm border-0 mb-4">
      <div class="card-header bg-white fw-semibold">
        <span class="badge bg-primary me-2">4</span>ご要望・備考（任意）
      </div>
      <div class="card-body">
        <textarea name="notes" class="form-control" rows="3"
                  placeholder="アレルギー、ご要望など"
                  maxlength="500"><?= h($_POST['notes'] ?? '') ?></textarea>
      </div>
    </div>

    <!-- 確認・送信 -->
    <div class="card shadow-sm border-0 mb-4">
      <div class="card-body">
        <h5 class="fw-bold mb-3">予約内容の確認</h5>
        <table class="table table-sm">
          <tr>
            <th class="text-muted" style="width:40%">メニュー</th>
            <td class="fw-semibold"><?= h($selectedMenu['name']) ?></td>
          </tr>
          <tr>
            <th class="text-muted">日時</th>
            <td class="fw-semibold">
              <?= formatDate($selectedDate) ?>(<?= getDayOfWeek($selectedDate) ?>)
              <?= h($selectedTime) ?>〜
            </td>
          </tr>
          <tr>
            <th class="text-muted">料金</th>
            <td class="fw-semibold">¥<?= number_format($selectedMenu['price']) ?></td>
          </tr>
          <tr>
            <th class="text-muted">獲得ポイント</th>
            <td class="fw-semibold text-success">
              +<?= (int)floor($selectedMenu['price'] * ($selectedMenu['points_rate'] ?? 1) / 100) ?>pt（施術完了後付与）
            </td>
          </tr>
        </table>
        <div class="d-grid mt-3">
          <button type="submit" name="confirm" value="1" class="btn btn-primary btn-lg">
            この内容で予約する
          </button>
        </div>
      </div>
    </div>
    <?php endif; // selectedTime ?>
    <?php endif; // selectedMenu ?>

  </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/main.js"></script>
</body>
</html>
