<?php
/**
 * Google Calendar OAuth 認証ページ
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/google_calendar.php';

startSecureSession();
$admin    = requireAdminLogin();
$shopName = getSetting('shop_name', 'ご予約サイト');

$isAuthorized = GoogleCalendar::isAuthorized();
$authUrl      = GoogleCalendar::getAuthUrl();

// 解除処理
if (isset($_POST['revoke']) && verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
    if (file_exists(GOOGLE_TOKEN_FILE)) {
        unlink(GOOGLE_TOKEN_FILE);
    }
    setFlash('success', 'Googleカレンダー連携を解除しました。');
    header('Location: google_auth.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Googleカレンダー連携 | <?= h($shopName) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<?php include __DIR__ . '/partials/navbar.php'; ?>

<div class="container-fluid py-4">
  <div class="row">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
      <h1 class="h3 fw-bold mb-4">Googleカレンダー連携</h1>

      <?= renderFlash() ?>

      <div class="row justify-content-center">
        <div class="col-lg-6">
          <div class="card shadow-sm border-0">
            <div class="card-body p-4 text-center">
              <div style="font-size:4rem;">📆</div>
              <h2 class="h5 mb-3">Googleカレンダーと連携する</h2>

              <?php if ($isAuthorized): ?>
              <div class="alert alert-success">
                <strong>✓ 連携済みです</strong><br>
                予約確定時にGoogleカレンダーへ自動登録されます。
              </div>
              <p class="text-muted small">
                カレンダーID: <code><?= h(GOOGLE_CALENDAR_ID) ?></code>
              </p>
              <form method="post" action="google_auth.php"
                    onsubmit="return confirm('連携を解除しますか？')">
                <?= csrfField() ?>
                <button type="submit" name="revoke" value="1" class="btn btn-outline-danger">
                  連携を解除する
                </button>
              </form>
              <?php else: ?>
              <p class="text-muted">
                Googleアカウントと連携すると、予約が確定した際に自動でカレンダーに追加されます。
              </p>
              <div class="alert alert-info small text-start">
                <strong>設定前の確認事項</strong>
                <ul class="mb-0 mt-1">
                  <li>config/config.php の Google API 設定を完了してください</li>
                  <li>Google Cloud Console でリダイレクトURIを登録してください</li>
                  <li>Google Calendar API を有効化してください</li>
                </ul>
              </div>
              <a href="<?= h($authUrl) ?>" class="btn btn-primary btn-lg">
                Googleアカウントで認証する
              </a>
              <?php endif; ?>
            </div>
          </div>

          <!-- 設定手順 -->
          <div class="card shadow-sm border-0 mt-4">
            <div class="card-header bg-white fw-bold">Google API 設定手順</div>
            <div class="card-body">
              <ol class="small">
                <li class="mb-2">
                  <a href="https://console.cloud.google.com/" target="_blank" rel="noopener">Google Cloud Console</a>
                  にアクセスしてプロジェクトを作成
                </li>
                <li class="mb-2">「APIとサービス」→「ライブラリ」→「Google Calendar API」を有効化</li>
                <li class="mb-2">「APIとサービス」→「認証情報」→「OAuthクライアントIDを作成」</li>
                <li class="mb-2">
                  リダイレクトURIに以下を追加:<br>
                  <code><?= h(GOOGLE_REDIRECT_URI) ?></code>
                </li>
                <li class="mb-2">クライアントIDとシークレットを <code>config/config.php</code> に設定</li>
                <li>上の「Googleアカウントで認証する」ボタンをクリック</li>
              </ol>
            </div>
          </div>
        </div>
      </div>

    </main>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
