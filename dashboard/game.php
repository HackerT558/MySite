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

<div class="game-container" id="gameContainer">
  <canvas id="gameCanvas" width="800" height="600"></canvas>
  <div class="score-board">Счёт: <span id="score">0</span></div>
  <div class="lives-board">Жизни: <span id="lives">3</span></div>
  <button class="fullscreen-btn" id="fullscreenBtn">Полный экран</button>
  <button class="pause-btn" id="pauseBtn">Пауза</button>
  <div class="paused-overlay" id="pausedOverlay">
    <h2>Игра на паузе</h2>
  </div>
  <div class="game-over" id="gameOver">
    <h2>Игра окончена</h2>
    <button id="restartBtn">Начать заново</button>
  </div>
</div>

<div class="controls" id="controls">
  <button id="btnLeft"  class="game-btn">◀</button>
  <button id="btnRight" class="game-btn">▶</button>
</div>

<script src="../js/game.js"></script>
</body>
</html>
