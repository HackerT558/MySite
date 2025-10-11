<?php
// dashboard/game.php

require __DIR__ . '/../config/config.php';
if (empty($_SESSION['uid'])) {
    header('Location: ../auth/login.php');
    exit;
}
$active = 'game';
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Игра</title>
    <link rel="stylesheet" href="../css/app-base.css">
    <link rel="stylesheet" href="../css/cabinet-header.css">
    <link rel="stylesheet" href="../css/game.css">
</head>
<body>
<?php require __DIR__ . '/../config/header-cabinet.inc.php'; ?>

<div class="game-container">
    <canvas id="gameCanvas" width="800" height="600"></canvas>
    <div class="score-board">Счёт: <span id="score">0</span></div>
    <div class="lives-board">Жизни: <span id="lives">3</span></div>
</div>
<div class="controls">
    <button id="btnLeft"  class="game-btn">◀</button>
    <button id="btnRight" class="game-btn">▶</button>
</div>

<script src="../js/game.js"></script>
</body>
</html>
