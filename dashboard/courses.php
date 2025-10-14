<?php
// dashboard/courses.php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/courses-functions.php';

if (empty($_SESSION['uid'])) {
    header('Location: ../auth/login.php');
    exit;
}

$active = 'courses';
$userId = $_SESSION['uid'];
$userRole = $_SESSION['role'] ?? 'user';
$currentLevel = role_level($userRole);

// Получаем назначенные курсы
$assignedCourses = getUserAssignedCourses($mysqli, $userId);

// Для менеджеров - статистика
$statistics = [];
if ($currentLevel >= role_level('manager-top')) {
    $statistics = getCoursesStatistics($mysqli);
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Курсы обучения</title>
    <link rel="stylesheet" href="../css/app-base.css">
    <link rel="stylesheet" href="../css/cabinet-header.css">
    <link rel="stylesheet" href="../css/courses.css">
</head>
<body>
    <?php require __DIR__ . '/../config/header-cabinet.inc.php'; ?>
    
    <div class="container">
        <div class="courses-wrap">
            <div class="courses-header">
                <h1>Система обучения</h1>
                <?php if ($currentLevel >= role_level('manager-top')): ?>
                <div class="courses-actions">
                    <a href="course-management.php" class="btn btn-orange">Управление курсами</a>
                    <a href="course-assignments.php" class="btn btn-gray">Назначить курсы</a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Мои курсы -->
            <div class="section-card">
                <div class="section-header">
                    <h2>Мои назначенные курсы</h2>
                    <div class="section-info">
                        <span class="courses-count"><?= count($assignedCourses) ?> курсов</span>
                    </div>
                </div>
                
                <?php if (empty($assignedCourses)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📚</div>
                        <p>У вас пока нет назначенных курсов</p>
                        <p class="empty-subtitle">Обратитесь к менеджеру для получения курсов обучения</p>
                    </div>
                <?php else: ?>
                    <div class="courses-grid">
                        <?php foreach ($assignedCourses as $course): ?>
                            <div class="course-card <?= $course['status'] ?>">
                                <div class="course-header">
                                    <h3><?= htmlspecialchars($course['title']) ?></h3>
                                    <span class="course-status status-<?= $course['status'] ?>">
                                        <?php
                                        $statusText = [
                                            'assigned' => 'Назначен',
                                            'in_progress' => 'В процессе',
                                            'completed' => 'Завершен',
                                            'failed' => 'Не пройден'
                                        ];
                                        echo $statusText[$course['status']] ?? $course['status'];
                                        ?>
                                    </span>
                                </div>
                                
                                <div class="course-info">
                                    <p class="course-description"><?= htmlspecialchars($course['description'] ?? '') ?></p>
                                    
                                    <div class="course-meta">
                                        <span class="duration">
                                            <i class="icon-clock">⏱</i>
                                            <?= $course['duration_minutes'] ?> мин
                                        </span>
                                        <span class="position">
                                            <i class="icon-user">👤</i>
                                            <?= htmlspecialchars($course['position']) ?>
                                        </span>
                                        <?php if ($course['deadline']): ?>
                                        <span class="deadline">
                                            <i class="icon-calendar">📅</i>
                                            До <?= date('d.m.Y', strtotime($course['deadline'])) ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="course-progress">
                                    <div class="progress-info">
                                        <span>Прогресс: <?= $course['lessons_completed'] ?>/<?= $course['total_lessons'] ?> уроков</span>
                                        <?php if ($course['best_test_score'] > 0): ?>
                                            <span class="test-score <?= $course['test_passed'] ? 'passed' : 'failed' ?>">
                                                Тест: <?= number_format($course['best_test_score'], 1) ?>%
                                                <?= $course['test_passed'] ? '✓' : '✗' ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="progress-bar">
                                        <?php 
                                        $progressPercent = $course['total_lessons'] > 0 
                                            ? ($course['lessons_completed'] / $course['total_lessons']) * 100 
                                            : 0;
                                        ?>
                                        <div class="progress-fill" style="width: <?= $progressPercent ?>%"></div>
                                    </div>
                                </div>
                                
                                <div class="course-actions">
                                    <?php if ($course['status'] === 'completed'): ?>
                                        <a href="course-view.php?id=<?= $course['id'] ?>" class="btn btn-gray">Просмотреть</a>
                                        <?php if ($course['test_passed']): ?>
                                            <span class="certificate-icon" title="Курс успешно завершен">🏆</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <a href="course-view.php?id=<?= $course['id'] ?>" class="btn btn-orange">
                                            <?= $course['status'] === 'assigned' ? 'Начать курс' : 'Продолжить' ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($currentLevel >= role_level('manager-top') && !empty($statistics)): ?>
            <!-- Статистика для менеджеров -->
            <div class="section-card">
                <div class="section-header">
                    <h2>Статистика курсов</h2>
                    <div class="section-actions">
                        <a href="course-management.php" class="btn btn-sm btn-gray">Подробнее</a>
                    </div>
                </div>
                
                <div class="stats-grid">
                    <?php foreach ($statistics as $stat): ?>
                        <div class="stat-card">
                            <div class="stat-header">
                                <h4><?= htmlspecialchars($stat['title']) ?></h4>
                                <span class="stat-position"><?= htmlspecialchars($stat['position']) ?></span>
                            </div>
                            
                            <div class="stat-numbers">
                                <div class="stat-item">
                                    <span class="stat-value"><?= $stat['assigned_users'] ?></span>
                                    <span class="stat-label">Назначено</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-value stat-success"><?= $stat['completed_users'] ?></span>
                                    <span class="stat-label">Завершено</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-value stat-warning"><?= $stat['in_progress_users'] ?></span>
                                    <span class="stat-label">В процессе</span>
                                </div>
                                <?php if ($stat['avg_test_score'] > 0): ?>
                                <div class="stat-item">
                                    <span class="stat-value"><?= number_format($stat['avg_test_score'], 1) ?>%</span>
                                    <span class="stat-label">Средний балл</span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="../js/courses.js"></script>
</body>
</html>