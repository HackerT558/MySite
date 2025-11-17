<?php
require __DIR__ . '/../config/config.php';
if (empty($_SESSION['uid'])) { header('Location: ../index.php'); exit; }
$active = 'schedule';
// Личный кабинет доступен всем авторизованным; без редиректов по ролям
?>
<!doctype html>
<html lang="ру">
<head>
  <meta charset="UTF-8">
  <title>Личный кабинет</title>
  <link rel="stylesheet" href="../css/app-base.css">
</head>
<body>
  <?php require __DIR__ . '/../config/header-cabinet.inc.php'; ?>
  <div class="container">
    <section>
      <div class="login-box">
        <div class="box-head"><h2>Личный кабинет</h2></div>
        <p style="margin:0 0 12px 0;">Пользователь: <strong><?= htmlspecialchars($_SESSION['username']) ?></strong></p>
        <a href="../auth/logout.php" class="btn btn-gray" style="display:inline-block;">Выйти</a>
      </div>
    </section>
  </div>
</body>
</html>
