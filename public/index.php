<?php
/**
 * トップページ / LINEログイン
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/line_login.php';

startSecureSession();

// すでにログイン済みならマイページへ
if (getLoginUser()) {
    header('Location: mypage.php');
    exit;
}

$shopName = getSetting('shop_name', 'ご予約サイト');
$lineAuthUrl = LineLogin::getAuthUrl();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($shopName) ?> | ご予約</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">

<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">

      <!-- ヘッダーカード -->
      <div class="card shadow border-0 mb-4">
        <div class="card-body text-center py-5">
          <div class="shop-icon mb-3">
            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="currentColor"
                 class="text-primary" viewBox="0 0 16 16">
              <path d="M2.97 1.35A1 1 0 0 1 3.73 1h8.54a1 1 0 0 1 .76.35l2.609 3.044A1.5 1.5 0
                       0 1 16 5.37v.255a2.375 2.375 0 0 1-4.25 1.458A2.371 2.371 0 0 1 9.875 8
                       2.37 2.37 0 0 1 8 7.083 2.37 2.37 0 0 1 6.125 8a2.37 2.37 0 0 1-1.875-.917
                       A2.375 2.375 0 0 1 0 5.625V5.37a1.5 1.5 0 0 1 .361-.976zm1.78 4.275a1.375
                       1.375 0 0 0 2.75 0 .5.5 0 0 1 1 0 1.375 1.375 0 0 0 2.75 0 .5.5 0 0 1 1
                       0 1.375 1.375 0 1 0 2.75 0V5.37a.5.5 0 0 0-.12-.325L12.27 2H3.73L1.12
                       5.045A.5.5 0 0 0 1 5.37v.255a1.375 1.375 0 0 0 2.75 0 .5.5 0 0 1 1 0z"/>
              <path d="M1.5 8.5A.5.5 0 0 1 2 9v6h1v-5a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1v5h6V9a.5.5
                       0 0 1 1 0v6h.5a.5.5 0 0 1 0 1H.5a.5.5 0 0 1 0-1H1V9a.5.5 0 0 1 .5-.5zM4
                       15h3v-5H4zm6-5a1 1 0 0 1 1-1h1a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1a1 1 0 0
                       1-1-1z"/>
            </svg>
          </div>
          <h1 class="h3 fw-bold mb-1"><?= h($shopName) ?></h1>
          <p class="text-muted mb-4">オンライン予約</p>

          <?= renderFlash() ?>

          <p class="mb-4 text-secondary small">
            LINEアカウントでかんたんログイン。<br>
            初回のみお名前・電話番号の登録をお願いします。
          </p>

          <a href="<?= h($lineAuthUrl) ?>" class="btn btn-line btn-lg w-100 d-flex align-items-center justify-content-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="white">
              <path d="M19.365 9.89c.50 0 .907.41.907.91s-.407.91-.907.91H17.44v1.055h1.925c.5
                       0 .907.41.907.91s-.407.91-.907.91h-2.835a.91.91 0 0
                       1-.907-.91V9.89c0-.5.407-.91.907-.91h2.835zm-9.757.91c0-.5.407-.91.907-.91s.907.41.907.91v3.785a.91.91
                       0 0 1-.907.91.91.91 0 0 1-.907-.91zm-3.43-.91h-.001a.91.91 0 0 0-.907.91v3.785c0 .5.407.91.907.91h2.835c.5
                       0 .907-.41.907-.91s-.407-.91-.907-.91H6.178V9.89a.91.91 0 0 0-.907-.91zm17.356-2.52C24 5.876 18.636
                       2 12 2S0 5.876 0 10.624c0 4.306 3.818 7.91 8.976 8.593.35.075.826.23.947.527.108.27.071.694.035
                       .965l-.153.92c-.047.27-.215 1.058.927.577 1.142-.481 6.162-3.63 8.408-6.212C22.93
                       13.865 24 12.337 24 10.624z"/>
            </svg>
            LINEでログイン
          </a>
        </div>
      </div>

      <p class="text-center text-muted small">
        <a href="#" class="text-muted">利用規約</a> ・
        <a href="#" class="text-muted">プライバシーポリシー</a>
      </p>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
