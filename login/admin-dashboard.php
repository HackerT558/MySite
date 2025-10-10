<?php
require __DIR__ . '/config.php';
require_role_min_db($mysqli, 'manager-top'); // доступ с manager-top и выше
?>
<!doctype html>
<html lang="ру">
<head>
  <meta charset="UTF-8">
  <title>Админ-панель</title>
  <link rel="stylesheet" href="../css/app-base.css">
  <link rel="stylesheet" href="../css/app-admin.css">
</head>
<body>
  <div class="container admin-wrap">
    <section>
      <div class="login-box" style="width:100%;align-items:stretch;">
        <div class="box-head"><h2>Админ-панель</h2></div>
        <p style="margin:0 0 12px 0;">Здравствуйте, <strong><?= htmlspecialchars($_SESSION['username']) ?></strong></p>

        <a href="users.php" class="btn btn-orange" style="display:inline-block;">Управление пользователями</a>
        <a href="logout.php" class="btn btn-gray" style="display:inline-block;margin-left:8px;">Выйти</a>
      </div>
    </section>
  </div>
</body>
</html>
