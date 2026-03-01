<?php
/**
 * 管理者ログインページ
 */
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();

// 既にログイン済み
if (getLoginAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$shopName = getSetting('shop_name', 'ご予約サイト');
$errors   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = '不正なリクエストです。';
    } else {
        $username = sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username && $password) {
            $stmt = db()->prepare('SELECT * FROM admins WHERE username = ?');
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password_hash'])) {
                $_SESSION[ADMIN_SESSION_NAME] = $admin;
                session_regenerate_id(true);
                header('Location: dashboard.php');
                exit;
            } else {
                $errors[] = 'ユーザー名またはパスワードが正しくありません。';
            }
        } else {
            $errors[] = 'ユーザー名とパスワードを入力してください。';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>管理者ログイン | <?= h($shopName) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">

<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">
      <div class="card shadow border-0">
        <div class="card-header bg-dark text-white text-center py-3">
          <h1 class="h5 mb-0">管理者ログイン</h1>
          <div class="small opacity-75"><?= h($shopName) ?></div>
        </div>
        <div class="card-body p-4">
          <?php if ($errors): ?>
          <div class="alert alert-danger">
            <?php foreach ($errors as $e): ?><?= h($e) ?><?php endforeach; ?>
          </div>
          <?php endif; ?>

          <form method="post" action="login.php">
            <?= csrfField() ?>
            <div class="mb-3">
              <label for="username" class="form-label">ユーザー名</label>
              <input type="text" id="username" name="username" class="form-control"
                     autocomplete="username" required>
            </div>
            <div class="mb-4">
              <label for="password" class="form-label">パスワード</label>
              <input type="password" id="password" name="password" class="form-control"
                     autocomplete="current-password" required>
            </div>
            <div class="d-grid">
              <button type="submit" class="btn btn-dark">ログイン</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
