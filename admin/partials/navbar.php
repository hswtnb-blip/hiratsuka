<nav class="navbar navbar-expand-md navbar-dark bg-dark sticky-top">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="dashboard.php"><?= h($shopName) ?> 管理</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="adminNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item">
          <span class="nav-link text-white-50 small"><?= h($admin['name']) ?> 様</span>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="logout.php">ログアウト</a>
        </li>
      </ul>
    </div>
  </div>
</nav>
