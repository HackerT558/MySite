<?php
require __DIR__ . '/../config/config.php';
require_role_min_db($mysqli, 'manager'); // доступ с manager и выше
$active = 'shift';
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Менеджер смены</title>
  <link rel="stylesheet" href="../css/app-base.css">
</head>
<body>
<?php require __DIR__ . '/../config/header-cabinet.inc.php'; ?>
<!-- Контент менеджера смены -->
</body>
</html>
