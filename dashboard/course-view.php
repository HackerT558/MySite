<?php
// dashboard/course-view.php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/courses-functions.php';

if (empty($_SESSION['uid'])) {
    header('Location: ../index.php');
    exit;
}

$courseId = (int)($_GET['id'] ?? 0);
if ($courseId <= 0) {
    header('Location: courses.php');
    exit;
}

$active = 'courses';
$userId = $_SESSION['uid'];

// Получаем информацию о курсе
$course = getCourseDetails($mysqli, $courseId);
if (!$course) {
    header('Location: courses.php');
    exit;
}

// Проверяем, назначен ли курс пользователю
if (!isUserAssignedToCourse($mysqli, $userId, $courseId)) {
    header('Location: courses.php');
    exit;
}

// Получаем уроки курса с прогрессом пользователя
$lessons = getCourseLessons($mysqli, $courseId, $userId);

// Проверяем доступность теста
$testAvailable = isTestAvailableForUser($mysqli, $userId, $courseId);

// Получаем историю тестов
$stmt = $mysqli->prepare("
    SELECT percentage, passed, completed_at
    FROM course_test_results
    WHERE user_id = ? AND course_id = ?
    ORDER BY completed_at DESC
    LIMIT 5
");
$stmt->bind_param('ii', $userId, $courseId);
$stmt->execute();
$testHistory = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$completedLessons = array_filter($lessons, function($lesson) {
    return $lesson['completed'];
});

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($course['title']) ?> - Курс</title>
    <link rel="stylesheet" href="../css/test-history.css">
    <link rel="stylesheet" href="../css/app-base.css">
    <link rel="stylesheet" href="../css/cabinet-header.css">
    <link rel="stylesheet" href="../css/courses.css">
</head>
<body>
    <?php require __DIR__ . '/../config/header-cabinet.inc.php'; ?>
    
    <div class="container">
        <div class="course-view-wrap">
            <div class="breadcrumb">
                <a href="courses.php">Курсы</a> → <?= htmlspecialchars($course['title']) ?>
            </div>

            <div class="course-detail-card">
                <div class="course-detail-header">
                    <h1><?= htmlspecialchars($course['title']) ?></h1>
                    <div class="course-badges">
                        <span class="badge badge-<?= $course['difficulty_level'] ?>">
                            <?php
                            $difficultyText = [
                                'beginner' => 'Начальный',
                                'intermediate' => 'Средний', 
                                'advanced' => 'Продвинутый'
                            ];
                            echo $difficultyText[$course['difficulty_level']] ?? $course['difficulty_level'];
                            ?>
                        </span>
                        <span class="badge badge-position">
                            <?= htmlspecialchars($course['position']) ?>
                        </span>
                    </div>
                </div>

                <div class="course-description">
                    <p><?= nl2br(htmlspecialchars($course['description'] ?? '')) ?></p>
                </div>

                <div class="course-meta">
                    <div class="meta-item">
                        <strong>Длительность:</strong> <?= $course['duration_minutes'] ?> минут
                    </div>
                    <div class="meta-item">
                        <strong>Проходной балл:</strong> <?= $course['passing_score'] ?>%
                    </div>
                    <div class="meta-item">
                        <strong>Уроков:</strong> <?= count($lessons) ?>
                    </div>
                    <div class="meta-item">
                        <strong>Пройдено:</strong> <?= count($completedLessons) ?>/<?= count($lessons) ?>
                    </div>
                </div>
            </div>

            <!-- Уроки курса -->
            <div class="lessons-card">
                <div class="lessons-header">
                    <h2>Программа обучения</h2>
                    <div class="lessons-progress">
                        <?= count($completedLessons) ?> из <?= count($lessons) ?> уроков завершено
                    </div>
                </div>
                
                <div class="lessons-list">
                    <?php foreach ($lessons as $index => $lesson): ?>
                        <div class="lesson-item <?= $lesson['completed'] ? 'completed' : '' ?>">
                            <div class="lesson-number">
                                <?php if ($lesson['completed']): ?>
                                    <span class="check-icon">✓</span>
                                <?php else: ?>
                                    <?= $index + 1 ?>
                                <?php endif; ?>
                            </div>
                            
                            <div class="lesson-content">
                                <h3><?= htmlspecialchars($lesson['title']) ?></h3>
                                <div class="lesson-meta">
                                    <span class="duration">
                                        ⏱ <?= $lesson['duration_minutes'] ?> мин
                                    </span>
                                    <?php if ($lesson['video_url']): ?>
                                        <span class="has-video">
                                            🎥 Видео
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($lesson['completed']): ?>
                                    <div class="completion-info">
                                        ✓ Завершено: <?= date('d.m.Y H:i', strtotime($lesson['completed_at'])) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="lesson-actions">
                                <a href="lesson-view.php?id=<?= $lesson['id'] ?>" class="btn btn-sm <?= $lesson['completed'] ? 'btn-gray' : 'btn-orange' ?>">
                                    <?= $lesson['completed'] ? 'Повторить' : 'Изучить' ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Тестирование -->
            <div class="test-card">
                <div class="test-header">
                    <h2>Итоговая аттестация</h2>
                </div>
                
                <?php if (!$testAvailable): ?>
                    <div class="test-unavailable">
                        <div class="test-status">
                            <span class="test-icon">🔒</span>
                            <div class="test-info">
                                <h3>Тест недоступен</h3>
                                <p>Для прохождения теста необходимо завершить все уроки курса</p>
                            </div>
                        </div>
                        <div class="progress-info">
                            <div class="progress-text">
                                Пройдено уроков: <?= count($completedLessons) ?>/<?= count($lessons) ?>
                            </div>
                            <div class="progress-bar">
                                <?php $progressPercent = count($lessons) > 0 ? (count($completedLessons) / count($lessons)) * 100 : 0; ?>
                                <div class="progress-fill" style="width: <?= $progressPercent ?>%"></div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="test-available">
                        <div class="test-status">
                            <span class="test-icon">📝</span>
                            <div class="test-info">
                                <h3>Тест доступен</h3>
                                <p>Все уроки пройдены. Теперь вы можете пройти итоговый тест.</p>
                                <p><strong>Проходной балл:</strong> <?= $course['passing_score'] ?>%</p>
                            </div>
                        </div>
                        
                        <div class="test-actions">
                            <a href="course-test.php?course_id=<?= $courseId ?>" class="btn btn-orange btn-large">
                                Пройти тест
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- История тестов -->
                <?php if (!empty($testHistory)): ?>
                <div class="test-history">
                    
                    <!-- Заголовок -->
                    <div class="test-history-header">
                        <span class="test-history-icon">📊</span>
                        <h3>История попыток теста</h3>
                    </div>

                    <!-- Статистика -->
                    <div class="test-history-stats">
                        <div class="stat-box">
                            <div class="stat-value"><?= count($testHistory) ?></div>
                            <div class="stat-label">Всего попыток</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-value">
                                <?= count(array_filter($testHistory, fn($t) => $t['passed'])) ?>
                            </div>
                            <div class="stat-label">Успешных</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-value">
                                <?= count(array_filter($testHistory, fn($t) => !$t['passed'])) ?>
                            </div>
                            <div class="stat-label">Не пройдено</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-value">
                                <?= max(array_map(fn($t) => $t['percentage'], $testHistory)) ?? 0 ?>%
                            </div>
                            <div class="stat-label">Лучший результат</div>
                        </div>
                    </div>

                    <!-- Легенда -->
                    <div class="test-history-legend">
                        <div class="legend-item">
                            <span class="legend-dot passed"></span>
                            <span>Пройдено</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-dot failed"></span>
                            <span>Не пройдено</span>
                        </div>
                    </div>

                    <!-- Список попыток -->
                    <div class="test-history-attempts">
                        <?php foreach ($testHistory as $index => $attempt): 
                            $isPassed = $attempt['passed'];
                            $percentage = (int)$attempt['percentage'];
                            $isBest = ($index === 0 && $isPassed) || 
                                    ($percentage === max(array_map(fn($t) => $t['percentage'], $testHistory)));
                            $isLatest = $index === 0;
                            $attemptDate = new DateTime($attempt['completed_at']);
                            $now = new DateTime();
                            $interval = $now->diff($attemptDate);
                            $timeAgo = $interval->format('%d дн. %h ч. назад');
                        ?>
                        <div class="test-attempt <?= $isLatest ? 'latest' : '' ?> <?= $isBest ? 'best' : '' ?>">
                            
                            <!-- Индикатор -->
                            <div class="attempt-indicator <?= $isPassed ? 'passed' : 'failed' ?>">
                                <?= $isPassed ? '✓' : '✗' ?>
                            </div>

                            <!-- Информация -->
                            <div class="attempt-info">
                                <div class="attempt-score">
                                    <span class="attempt-percentage <?= $isPassed ? 'passed' : 'failed' ?>">
                                        <?= $percentage ?>%
                                    </span>
                                    <span class="attempt-status-badge <?= $isPassed ? 'passed' : 'failed' ?>">
                                        <?= $isPassed ? '✓ Пройдено' : '✗ Не пройдено' ?>
                                    </span>
                                </div>
                                <div class="attempt-progress-bar">
                                    <div class="attempt-progress-fill <?= $isPassed ? 'passed' : 'failed' ?>" 
                                        style="width: <?= $percentage ?>%"></div>
                                </div>
                                <div class="attempt-date">
                                    📅 <?= $attemptDate->format('d.m.Y в H:i') ?> 
                                    <span style="color: #A0A9B0;">(<?= $timeAgo ?>)</span>
                                </div>
                            </div>

                            <!-- Детали -->
                            <div class="attempt-details">
                                <?php if ($isBest): ?>
                                <div class="attempt-rank">🏆 Лучший результат</div>
                                <?php else: ?>
                                <div class="attempt-rank">Попытка #<?= count($testHistory) - $index ?></div>
                                <?php endif; ?>
                                <?php if ($isLatest): ?>
                                <div class="attempt-rank" style="background: rgba(242, 104, 34, 0.12); color: #f26822;">
                                    Последняя
                                </div>
                                <?php endif; ?>
                            </div>

                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Примечание -->
                    <div style="margin-top: 20px; padding: 12px 16px; background: rgba(242, 104, 34, 0.12); border-left: 4px solid #f26822; border-radius: 4px;">
                        <p style="margin: 0; color: #626C71; font-size: 13px;">
                            <strong>ℹ️ Примечание:</strong> Для успешного завершения курса необходимо набрать минимум <strong><?= $course['passing_score'] ?>%</strong> на тесте.
                        </p>
                    </div>

                </div>
                <?php else: ?>

                <div class="test-history">
                <div class="test-history-header">
                    <span class="test-history-icon">📊</span>
                    <h3>История попыток теста</h3>
                </div>
                <div class="test-history-empty">
                    <div class="test-history-empty-icon">📝</div>
                    <p class="test-history-empty-text">Попыток пока нет</p>
                    <p class="test-history-empty-hint">Здесь будут отображаться все ваши попытки прохождения теста</p>
                </div>
            </div>
            <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>