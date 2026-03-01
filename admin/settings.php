<?php
/**
 * システム設定ページ
 */
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
$admin    = requireAdminLogin();
$shopName = getSetting('shop_name', 'ご予約サイト');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        setFlash('error', '不正なリクエストです。');
    } else {
        $settings = [
            'shop_name'              => sanitizeInput($_POST['shop_name']              ?? ''),
            'shop_phone'             => sanitizeInput($_POST['shop_phone']             ?? ''),
            'shop_address'           => sanitizeInput($_POST['shop_address']           ?? ''),
            'reservation_start_hour' => (string)(int)($_POST['reservation_start_hour'] ?? 9),
            'reservation_end_hour'   => (string)(int)($_POST['reservation_end_hour']   ?? 19),
            'reservation_interval'   => (string)(int)($_POST['reservation_interval']   ?? 30),
            'google_calendar_id'     => sanitizeInput($_POST['google_calendar_id']     ?? ''),
        ];
        foreach ($settings as $key => $value) {
            setSetting($key, $value);
        }

        // 管理者パスワード変更
        if (!empty($_POST['new_password'])) {
            $newPass     = $_POST['new_password'];
            $confirmPass = $_POST['confirm_password'] ?? '';
            if ($newPass !== $confirmPass) {
                setFlash('error', '設定を保存しましたが、パスワードが一致しません。');
            } elseif (strlen($newPass) < 8) {
                setFlash('error', '設定を保存しましたが、パスワードは8文字以上にしてください。');
            } else {
                $hash = password_hash($newPass, PASSWORD_DEFAULT);
                db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')
                    ->execute([$hash, $admin['id']]);
                setFlash('success', '設定とパスワードを保存しました。');
            }
        } else {
            setFlash('success', '設定を保存しました。');
        }
        header('Location: settings.php');
        exit;
    }
}

// 現在の設定を取得
$currentSettings = [
    'shop_name'              => getSetting('shop_name'),
    'shop_phone'             => getSetting('shop_phone'),
    'shop_address'           => getSetting('shop_address'),
    'reservation_start_hour' => getSetting('reservation_start_hour', '9'),
    'reservation_end_hour'   => getSetting('reservation_end_hour',   '19'),
    'reservation_interval'   => getSetting('reservation_interval',   '30'),
    'google_calendar_id'     => getSetting('google_calendar_id'),
];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>設定 | <?= h($shopName) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<?php include __DIR__ . '/partials/navbar.php'; ?>

<div class="container-fluid py-4">
  <div class="row">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
      <h1 class="h3 fw-bold mb-4">設定</h1>

      <?= renderFlash() ?>

      <div class="row justify-content-center">
        <div class="col-lg-7">
          <form method="post" action="settings.php">
            <?= csrfField() ?>

            <!-- 基本情報 -->
            <div class="card shadow-sm border-0 mb-4">
              <div class="card-header bg-white fw-bold">店舗基本情報</div>
              <div class="card-body">
                <div class="mb-3">
                  <label class="form-label">店舗名</label>
                  <input type="text" name="shop_name" class="form-control" maxlength="100"
                         value="<?= h($currentSettings['shop_name']) ?>">
                </div>
                <div class="mb-3">
                  <label class="form-label">電話番号</label>
                  <input type="text" name="shop_phone" class="form-control" maxlength="20"
                         value="<?= h($currentSettings['shop_phone']) ?>">
                </div>
                <div class="mb-3">
                  <label class="form-label">住所</label>
                  <input type="text" name="shop_address" class="form-control" maxlength="255"
                         value="<?= h($currentSettings['shop_address']) ?>">
                </div>
              </div>
            </div>

            <!-- 予約設定 -->
            <div class="card shadow-sm border-0 mb-4">
              <div class="card-header bg-white fw-bold">予約設定</div>
              <div class="card-body">
                <div class="row g-3">
                  <div class="col-sm-4">
                    <label class="form-label">受付開始時間</label>
                    <div class="input-group">
                      <input type="number" name="reservation_start_hour" class="form-control"
                             min="0" max="23"
                             value="<?= h($currentSettings['reservation_start_hour']) ?>">
                      <span class="input-group-text">時</span>
                    </div>
                  </div>
                  <div class="col-sm-4">
                    <label class="form-label">受付終了時間</label>
                    <div class="input-group">
                      <input type="number" name="reservation_end_hour" class="form-control"
                             min="0" max="24"
                             value="<?= h($currentSettings['reservation_end_hour']) ?>">
                      <span class="input-group-text">時</span>
                    </div>
                  </div>
                  <div class="col-sm-4">
                    <label class="form-label">予約間隔</label>
                    <div class="input-group">
                      <select name="reservation_interval" class="form-select">
                        <?php foreach ([15, 30, 60] as $min): ?>
                        <option value="<?= $min ?>"
                                <?= $currentSettings['reservation_interval'] == $min ? 'selected' : '' ?>>
                          <?= $min ?>
                        </option>
                        <?php endforeach; ?>
                      </select>
                      <span class="input-group-text">分</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Google Calendar -->
            <div class="card shadow-sm border-0 mb-4">
              <div class="card-header bg-white fw-bold">Google Calendar</div>
              <div class="card-body">
                <div class="mb-3">
                  <label class="form-label">カレンダーID</label>
                  <input type="text" name="google_calendar_id" class="form-control" maxlength="200"
                         placeholder="primary または xxx@group.calendar.google.com"
                         value="<?= h($currentSettings['google_calendar_id']) ?>">
                  <div class="form-text">
                    「primary」でメインカレンダーに追加。専用カレンダーを使う場合はカレンダーIDを入力してください。
                  </div>
                </div>
              </div>
            </div>

            <!-- パスワード変更 -->
            <div class="card shadow-sm border-0 mb-4">
              <div class="card-header bg-white fw-bold">管理者パスワード変更</div>
              <div class="card-body">
                <div class="mb-3">
                  <label class="form-label">新しいパスワード</label>
                  <input type="password" name="new_password" class="form-control"
                         placeholder="変更する場合のみ入力" minlength="8" autocomplete="new-password">
                </div>
                <div class="mb-3">
                  <label class="form-label">新しいパスワード（確認）</label>
                  <input type="password" name="confirm_password" class="form-control"
                         placeholder="確認用に再入力" autocomplete="new-password">
                </div>
              </div>
            </div>

            <div class="d-grid">
              <button type="submit" class="btn btn-primary btn-lg">設定を保存する</button>
            </div>
          </form>
        </div>
      </div>

    </main>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
