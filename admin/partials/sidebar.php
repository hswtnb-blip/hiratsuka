<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$navItems = [
    ['href' => 'dashboard.php',      'icon' => '🏠', 'label' => 'ダッシュボード',   'key' => 'dashboard'],
    ['href' => 'reservations.php',   'icon' => '📅', 'label' => '予約管理',         'key' => 'reservations'],
    ['href' => 'customers.php',      'icon' => '👥', 'label' => '顧客一覧',         'key' => 'customers'],
    ['href' => 'points.php',         'icon' => '⭐', 'label' => 'ポイント管理',     'key' => 'points'],
    ['href' => 'menus.php',          'icon' => '📋', 'label' => 'メニュー管理',     'key' => 'menus'],
    ['href' => 'google_auth.php',    'icon' => '📆', 'label' => 'Googleカレンダー', 'key' => 'google_auth'],
    ['href' => 'settings.php',       'icon' => '⚙️', 'label' => '設定',             'key' => 'settings'],
];
?>
<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar border-end">
  <div class="position-sticky pt-3">
    <ul class="nav flex-column">
      <?php foreach ($navItems as $item): ?>
      <li class="nav-item">
        <a class="nav-link <?= ($currentPage === $item['key']) ? 'active fw-bold' : 'text-dark' ?>"
           href="<?= $item['href'] ?>">
          <?= $item['icon'] ?> <?= $item['label'] ?>
        </a>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
</nav>
