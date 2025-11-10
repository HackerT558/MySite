<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Игра - Ловля пиццы</title>
    <link rel="stylesheet" href="../css/app-base.css">
    <link rel="stylesheet" href="../css/cabinet-header.css">
    <link rel="stylesheet" href="../css/game.css">
    <style>
        .leaderboard {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
            background: #fff;
        }
        
        .leaderboard thead {
            background: linear-gradient(135deg, #32b8c6 0%, #2da0ac 100%);
            color: white;
        }
        
        .leaderboard th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border: none;
        }
        
        .leaderboard td {
            padding: 10px 12px;
            border-bottom: 1px solid #e9ecef;
            font-size: 14px;
        }
        
        .leaderboard tbody tr:hover {
            background: #f8f9fa;
        }
        
        .leaderboard tbody tr:nth-child(1) {
            background: rgba(255, 215, 0, 0.1);
        }
        
        .leaderboard tbody tr:nth-child(2) {
            background: rgba(192, 192, 192, 0.1);
        }
        
        .leaderboard tbody tr:nth-child(3) {
            background: rgba(205, 127, 50, 0.1);
        }
        
        .leaderboard h3 {
            margin: 20px 0 10px 0;
            color: #333;
            font-size: 18px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <?php
    require __DIR__ . '/../config/config.php';
    
    if (empty($_SESSION['uid'])) {
        header('Location: ../auth/login.php');
        exit;
    }
    
    $active = 'game';
    require __DIR__ . '/../config/header-cabinet.inc.php';
    
    // Сохраняем имя пользователя в sessionStorage для отображения в таблице лидеров
    $username = $_SESSION['username'] ?? $_SESSION['login'] ?? 'Игрок';
    ?>
    
    <div class="container">
        <div class="game-wrap">
            <div class="game-header">
                <h1>🍕 Ловля пиццы</h1>
                <div class="game-info">
                    <div class="score-display">
                        Счет: <span id="score">0</span>
                    </div>
                    <div class="timer-display">
                        Время: <span id="timer">60с</span>
                    </div>
                    <div class="lives-display">
                        Жизни: <span id="lives">❤️❤️❤️</span>
                    </div>
                </div>
            </div>

            <div class="game-canvas-container">
                <canvas id="gameCanvas" width="800" height="600"></canvas>
                
                <!-- Стартовый экран -->
                <div id="startScreen" class="game-overlay">
                    <h2>Готовы поймать пиццу?</h2>
                    <p>Используйте стрелки ← → или мышь для управления коробкой</p>
                    <p>Ловите падающие пиццы и избегайте бомб!</p>
                    <button id="startButton" class="btn btn-orange btn-large">Начать игру</button>
                </div>

                <!-- Экран окончания игры -->
                <div id="gameOverScreen" class="game-overlay" style="display: none;">
                    <h2 id="gameOverTitle">Игра окончена!</h2>
                    <p id="finalScore">Ваш счет: 0</p>
                    <button id="restartButton" class="btn btn-orange btn-large">Играть снова</button>
                    <div id="leaderboardTable"></div>
                </div>

                <!-- Пауза -->
                <div id="pauseScreen" class="game-overlay" style="display: none;">
                    <h2>Пауза</h2>
                    <p>Нажмите ПРОБЕЛ для продолжения</p>
                    <button id="resumeButton" class="btn btn-orange">Продолжить</button>
                </div>
            </div>

            <div class="game-controls">
                <button id="pauseButton" class="btn btn-gray">⏸ Пауза</button>
                <button id="muteButton" class="btn btn-gray">🔊 Звук</button>
            </div>

            <div class="game-instructions card">
                <h3>Как играть:</h3>
                <ul>
                    <li>🎯 <strong>Цель:</strong> Поймайте как можно больше пицц за 60 секунд!</li>
                    <li>⌨️ <strong>Управление:</strong> Стрелки ← → на клавиатуре или двигайте мышью</li>
                    <li>🍕 <strong>Пицца:</strong> +10 очков</li>
                    <li>💣 <strong>Бомба:</strong> -1 жизнь</li>
                    <li>❤️ <strong>Жизни:</strong> У вас есть 3 жизни. Потеряв все - игра окончена!</li>
                    <li>⏸ <strong>Пауза:</strong> Нажмите ПРОБЕЛ или кнопку "Пауза"</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Передаем имя пользователя в JavaScript -->
    <script>
        sessionStorage.setItem('username', '<?php echo htmlspecialchars($username); ?>');
    </script>

    <!-- Скрипт подключается в конце -->
    <script src="../js/game.js"></script>
</body>
</html>