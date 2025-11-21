<?php
require __DIR__ . '/../config/config.php';
if (empty($_SESSION['uid'])) { header('Location: ../index.php'); exit; }
$active = 'game';
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pizza Game - Аркадная игра</title>
    <link rel="stylesheet" href="../css/game.css">
</head>
<body>
    <?php require __DIR__ . '/../config/header-cabinet.inc.php'; ?>
    <div class="game-wrap">
        <!-- Заголовок -->
        <div class="game-header">
            <h1>🍕 Ловец пицц 🍕</h1>
        </div>

        <!-- Информационная панель -->
        <div class="game-info">
            <div class="score-display" id="scoreDisplay">Счет: <span>0</span></div>
            <div class="timer-display" id="timerDisplay">Время: <span>00:00</span></div>
            <div class="lives-display" id="livesDisplay">Жизни: <span>❤️❤️❤️</span></div>
        </div>

        <!-- Основной игровой контейнер -->
        <div class="game-canvas-container">
            <canvas id="gameCanvas" width="800" height="600">
            </canvas>
            
            <!-- Стартовый экран -->
            <div class="game-overlay" id="startScreen">
                <h2>🍕 Ловец пицц 🍕</h2>
                <p>Ловите падающие пиццы и избегайте бомб!</p>
                <p>Используйте стрелки ← → или мышь для управления коробкой</p>
                <button class="btn btn--primary btn-large" id="startButton">Начать игру</button>
            </div>

            <!-- Экран паузы -->
            <div class="game-overlay hidden" id="pauseScreen">
                <h2>⏸️ Пауза</h2>
                <p>Нажмите пробел или кнопку "Продолжить" для продолжения</p>
                <button class="btn btn--primary btn-large" id="resumeButton">Продолжить</button>
            </div>

            <!-- Экран конца игры -->
            <div class="game-overlay hidden" id="gameOverScreen">
                <h2 id="gameOverTitle">Игра окончена!</h2>
                <p id="finalScore">Ваш счет: 0</p>
                <div class="leaderboard-container">
                    <div class="leaderboard-title">Таблица лидеров (ТОП-10)</div>
                    <div id="leaderboardTable"></div>
                </div>
                <button class="btn btn--primary btn-large" id="restartButton">Играть снова</button>
            </div>
        </div>

        <!-- Управление -->
        <div class="game-controls">
            <button class="btn btn--secondary" id="pauseButton">⏸️ Пауза</button>
        </div>

        <!-- Инструкции -->
        <div class="game-instructions">
            <h3>📖 Инструкции</h3>
            <ul>
                <li><strong>Управление:</strong> Используйте стрелки ← → на клавиатуре или движите мышь</li>
                <li><strong>Пиццы (🍕):</strong> Ловите пиццы и получайте +10 очков</li>
                <li><strong>Бомбы (💣):</strong> Избегайте бомб, они отнимают жизнь</li>
                <li><strong>Сердечки (❤️):</strong> Поймайте сердечко для восстановления жизни (+1)</li>
                <li><strong>Жизни:</strong> У вас есть 3 жизни. Потеря всех означает конец игры</li>
                <li><strong>Время:</strong> Показывает, сколько секунд вы уже играете. Чем дольше, тем сложнее!</li>
                <li><strong>Пауза:</strong> Нажмите пробел (SPACE) или кнопку паузы чтобы остановить игру</li>
            </ul>
        </div>
    </div>

    <script src="../js/game.js"></script>
</body>
</html>