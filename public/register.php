<?php
/**
 * 初回ユーザー登録ページ
 * LINEログイン後、名前・電話番号を登録する
 */
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();

// LINE認証済みでなければトップへ
if (empty($_SESSION['line_user_id'])) {
    header('Location: index.php');
    exit;
}

$lineUserId    = $_SESSION['line_user_id'];
$lineDisplayName = $_SESSION['line_display_name'] ?? '';
$shopName = getSetting('shop_name', 'ご予約サイト');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF検証
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = '不正なリクエストです。';
    } else {
        $name  = sanitizeInput($_POST['name']  ?? '');
        $phone = sanitizeInput($_POST['phone'] ?? '');

        // バリデーション
        if (mb_strlen($name) < 1) {
            $errors[] = 'お名前を入力してください。';
        } elseif (mb_strlen($name) > 100) {
            $errors[] = 'お名前は100文字以内で入力してください。';
        }

        if (!preg_match('/^[0-9\-\+\(\) ]{7,20}$/', $phone)) {
            $errors[] = '電話番号を正しい形式で入力してください（例: 090-1234-5678）。';
        }

        if (empty($errors)) {
            try {
                $pdo = db();
                $stmt = $pdo->prepare(
                    'INSERT INTO users (line_user_id, name, phone) VALUES (?, ?, ?)'
                );
                $stmt->execute([$lineUserId, $name, $phone]);
                $userId = (int)$pdo->lastInsertId();

                $user = getUserById($userId);
                $_SESSION[USER_SESSION_NAME] = $user;
                unset($_SESSION['line_user_id'], $_SESSION['line_display_name']);

                setFlash('success', 'ご登録ありがとうございます！');
                header('Location: mypage.php');
                exit;
            } catch (PDOException $e) {
                error_log('Register error: ' . $e->getMessage());
                $errors[] = '登録に失敗しました。再度お試しください。';
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
<title>会員情報登録 | <?= h($shopName) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">

<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">

      <div class="card shadow border-0">
        <div class="card-header bg-primary text-white text-center py-3">
          <h2 class="h5 mb-0">会員情報のご登録</h2>
        </div>
        <div class="card-body p-4">
          <p class="text-muted small mb-4">
            初回のみ、以下の情報をご入力ください。<br>
            ご予約の確認やポイントの管理に使用します。
          </p>

          <?php if ($errors): ?>
          <div class="alert alert-danger">
            <ul class="mb-0 ps-3">
              <?php foreach ($errors as $e): ?>
                <li><?= h($e) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
          <?php endif; ?>

          <form method="post" action="register.php" novalidate>
            <?= csrfField() ?>

            <div class="mb-3">
              <label for="name" class="form-label fw-semibold">お名前 <span class="text-danger">*</span></label>
              <input type="text" id="name" name="name" class="form-control form-control-lg"
                     placeholder="例: 山田 太郎"
                     value="<?= h($lineDisplayName) ?>"
                     required maxlength="100">
            </div>

            <div class="mb-4">
              <label for="phone" class="form-label fw-semibold">電話番号 <span class="text-danger">*</span></label>
              <input type="tel" id="phone" name="phone" class="form-control form-control-lg"
                     placeholder="例: 090-1234-5678"
                     required maxlength="20">
              <div class="form-text">ハイフンあり・なしどちらでも入力可能です</div>
            </div>

            <div class="d-grid">
              <button type="submit" class="btn btn-primary btn-lg">登録して予約へ進む</button>
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
