<?php
require __DIR__ . '/../config/config.php';
if (empty($_SESSION['uid'])) { header('Location: ../index.php'); exit; }

// Получаем статистику пользователя
$userId = $_SESSION['uid'];
$userRole = $_SESSION['role'] ?? 'user';

// Статистика курсов
$coursesStats = [];
$q = $mysqli->prepare("
    SELECT 
        COUNT(*) as total_courses,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_courses,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_courses
    FROM user_course_assignments 
    WHERE user_id = ?
");
$q->bind_param('i', $userId);
$q->execute();
$q->bind_result($totalCourses, $completedCourses, $inProgressCourses);
$q->fetch();
$q->close();

// Ближайшие дедлайны (курсы, у которых дедлайн в течение 7 дней)
$upcomingDeadlines = [];
$q = $mysqli->prepare("
    SELECT c.title, uc.deadline, DATEDIFF(uc.deadline, CURDATE()) as days_left
    FROM user_course_assignments uc 
    JOIN courses c ON uc.course_id = c.id 
    WHERE uc.user_id = ? 
      AND uc.status = 'in_progress' 
      AND uc.deadline BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY uc.deadline ASC 
    LIMIT 5
");
$q->bind_param('i', $userId);
$q->execute();
$res = $q->get_result();
while ($row = $res->fetch_assoc()) {
    $upcomingDeadlines[] = $row;
}
$q->close();

// Срочные дедлайны (курсы, у которых дедлайн завтра)
$urgentDeadlines = [];
$q = $mysqli->prepare("
    SELECT c.title, uc.deadline
    FROM user_course_assignments uc 
    JOIN courses c ON uc.course_id = c.id 
    WHERE uc.user_id = ? 
      AND uc.status = 'in_progress' 
      AND uc.deadline = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
    ORDER BY uc.deadline ASC 
    LIMIT 3
");
$q->bind_param('i', $userId);
$q->execute();
$res = $q->get_result();
while ($row = $res->fetch_assoc()) {
    $urgentDeadlines[] = $row;
}
$q->close();

// Последняя активность (игра)
$lastGameScore = 0;
$q = $mysqli->prepare("SELECT score FROM pizza_game_leaderboard WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$q->bind_param('i', $userId);
$q->execute();
$q->bind_result($lastGameScore);
$q->fetch();
$q->close();

// Прогресс обучения (примерный расчет)
$progressPercent = 0;
if ($totalCourses > 0) {
    $progressPercent = round(($completedCourses / $totalCourses) * 100);
}

$active = 'schedule';
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Личный кабинет</title>
  <link rel="stylesheet" href="../css/app-base.css">
  <link rel="stylesheet" href="../css/dashboard.css">
</head>
<body>
  <?php require __DIR__ . '/../config/header-cabinet.inc.php'; ?>
  
  <div class="dashboard-container">
    <!-- Приветствие -->
    <div class="welcome-message">
      <h2>Добро пожаловать, <?= htmlspecialchars($_SESSION['username']) ?>! 👋</h2>
      <p>Рады видеть вас в системе обучения. Вот ваш прогресс на сегодня.</p>
    </div>

    <!-- Главная информация -->
    <div class="main-info-card">
      <div class="main-info-header">
        <span class="main-info-icon">🏠</span>
        <h3>Главная</h3>
      </div>
      <div class="main-info-content">
        <div class="user-details">
          <div class="user-detail">
            <strong>Пользователь:</strong> <?= htmlspecialchars($_SESSION['username']) ?>
          </div>
          <div class="user-detail">
            <strong>Роль:</strong> 
            <span class="user-role-badge"><?= htmlspecialchars($userRole) ?></span>
          </div>
          <div class="user-detail">
            <a href="../auth/logout.php" class="btn btn-gray" style="display:inline-block; margin-top: 8px;">Выйти</a>
          </div>
        </div>
        <?php if ($progressPercent > 0): ?>
          <div class="progress-section">
            <div class="progress-ring">
              <svg width="80" height="80" viewBox="0 0 80 80">
                <circle cx="40" cy="40" r="36" stroke="#f1f3f6" stroke-width="8" fill="none"/>
                <circle cx="40" cy="40" r="36" stroke="#f26822" stroke-width="8" fill="none" 
                        stroke-dasharray="226.2" stroke-dashoffset="<?= 226.2 - (226.2 * $progressPercent / 100) ?>"
                        transform="rotate(-90 40 40)" stroke-linecap="round"/>
                <text x="40" y="45" text-anchor="middle" font-size="14" font-weight="600" fill="#f26822">
                  <?= $progressPercent ?>%
                </text>
              </svg>
            </div>
            <div class="progress-text">Общий прогресс обучения</div>
          </div>
        <?php endif; ?>
      </div>
    </div>
    
    <!-- Статистика -->
    <div class="dashboard-grid">
      <div class="stat-card">
        <span class="stat-number"><?= $totalCourses ?? 0 ?></span>
        <div class="stat-label">Всего курсов</div>
      </div>
      
      <div class="stat-card">
        <span class="stat-number"><?= $completedCourses ?? 0 ?></span>
        <div class="stat-label">Завершено курсов</div>
      </div>
      
      <div class="stat-card">
        <span class="stat-number"><?= $inProgressCourses ?? 0 ?></span>
        <div class="stat-label">Курсов в процессе</div>
      </div>
      
      <div class="stat-card">
        <span class="stat-number"><?= $lastGameScore ?></span>
        <div class="stat-label">Лучший результат в игре</div>
      </div>
    </div>

    <div class="content-grid">
      <!-- Левая колонка -->
      <div>
        <!-- Ближайшие дедлайны -->
        <div class="dashboard-card">
          <div class="card-header">
            <span class="card-icon">📅</span>
            <h3>Ближайшие дедлайны</h3>
          </div>
          
          <?php if (!empty($urgentDeadlines)): ?>
            <div class="deadline-list">
              <?php foreach ($urgentDeadlines as $deadline): ?>
                <div class="deadline-item urgent">
                  <div class="deadline-info">
                    <div class="deadline-course"><?= htmlspecialchars($deadline['title']) ?></div>
                    <div class="deadline-meta">Срочно! Завтра дедлайн</div>
                  </div>
                  <div class="deadline-date">Завтра</div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          
          <?php if (!empty($upcomingDeadlines)): ?>
            <div class="deadline-list">
              <?php foreach ($upcomingDeadlines as $deadline): ?>
                <div class="deadline-item">
                  <div class="deadline-info">
                    <div class="deadline-course"><?= htmlspecialchars($deadline['title']) ?></div>
                    <div class="deadline-meta">Осталось <?= $deadline['days_left'] ?> дней</div>
                  </div>
                  <div class="deadline-date"><?= date('d.m.Y', strtotime($deadline['deadline'])) ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php elseif (empty($urgentDeadlines)): ?>
            <div class="empty-state">
              <div class="empty-icon">📚</div>
              <p>Нет активных дедлайнов</p>
              <p class="empty-subtitle">Все курсы завершены или дедлайны еще не назначены</p>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Правая колонка -->
      <div>
        <!-- Быстрые действия -->
        <div class="dashboard-card">
          <div class="card-header">
            <span class="card-icon">⚡</span>
            <h3>Быстрые действия</h3>
          </div>
          <div class="quick-actions-grid">
            <a href="courses.php" class="action-btn">
              <span class="action-icon">📚</span>
              <span class="action-text">Мои курсы</span>
            </a>
            <a href="game.php" class="action-btn">
              <span class="action-icon">🎮</span>
              <span class="action-text">Игра</span>
            </a>
            <a href="contacts.php" class="action-btn">
              <span class="action-icon">👥</span>
              <span class="action-text">Контакты</span>
            </a>
            <a href="profile.php" class="action-btn">
              <span class="action-icon">👤</span>
              <span class="action-text">Профиль</span>
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>