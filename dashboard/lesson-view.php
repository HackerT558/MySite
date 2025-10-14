<?php
// dashboard/lesson-view.php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/courses-functions.php';

if (empty($_SESSION['uid'])) {
    header('Location: ../auth/login.php');
    exit;
}

$lessonId = (int)($_GET['id'] ?? 0);
if ($lessonId <= 0) {
    header('Location: courses.php');
    exit;
}

$active = 'courses';
$userId = $_SESSION['uid'];

// Получаем информацию об уроке
$lesson = getLessonContent($mysqli, $lessonId);
if (!$lesson) {
    header('Location: courses.php');
    exit;
}

// Проверяем, назначен ли курс пользователю
if (!isUserAssignedToCourse($mysqli, $userId, $lesson['course_id'])) {
    header('Location: courses.php');
    exit;
}

// Проверяем, пройден ли урок
$stmt = $mysqli->prepare("
    SELECT completed_at, time_spent_minutes 
    FROM user_lesson_progress 
    WHERE user_id = ? AND lesson_id = ?
");
$stmt->bind_param('ii', $userId, $lessonId);
$stmt->execute();
$result = $stmt->get_result();
$progress = $result->fetch_assoc();
$stmt->close();

$isCompleted = !empty($progress);

// Получаем соседние уроки для навигации
$stmt = $mysqli->prepare("
    SELECT id, title, lesson_order
    FROM course_lessons
    WHERE course_id = ?
    ORDER BY lesson_order
");
$stmt->bind_param('i', $lesson['course_id']);
$stmt->execute();
$result = $stmt->get_result();
$allLessons = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Находим текущий урок в списке и определяем предыдущий/следующий
$currentIndex = array_search($lessonId, array_column($allLessons, 'id'));
$prevLesson = $currentIndex > 0 ? $allLessons[$currentIndex - 1] : null;
$nextLesson = $currentIndex < count($allLessons) - 1 ? $allLessons[$currentIndex + 1] : null;

// Обработка отметки о завершении урока
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_lesson'])) {
    $timeSpent = (int)($_POST['time_spent'] ?? 0);
    
    if (completeLesson($mysqli, $userId, $lesson['course_id'], $lessonId, $timeSpent)) {
        // Перенаправляем на ту же страницу, чтобы обновить статус
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    } else {
        $error = "Ошибка при отметке урока. Попробуйте еще раз.";
    }
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($lesson['title']) ?> - Урок</title>
    <link rel="stylesheet" href="../css/app-base.css">
    <link rel="stylesheet" href="../css/cabinet-header.css">
    <link rel="stylesheet" href="../css/courses.css">
</head>
<body>
    <?php require __DIR__ . '/../config/header-cabinet.inc.php'; ?>
    
    <div class="container">
        <div class="lesson-view-wrap">
            <div class="breadcrumb">
                <a href="courses.php">Курсы</a> → 
                <a href="course-view.php?id=<?= $lesson['course_id'] ?>"><?= htmlspecialchars($lesson['course_title']) ?></a> → 
                <?= htmlspecialchars($lesson['title']) ?>
            </div>

            <div class="lesson-header-card">
                <div class="lesson-header-content">
                    <h1><?= htmlspecialchars($lesson['title']) ?></h1>
                    <div class="lesson-meta">
                        <span class="lesson-number">Урок <?= $lesson['lesson_order'] ?></span>
                        <span class="lesson-duration">
                            ⏱ <?= $lesson['duration_minutes'] ?> минут
                        </span>
                        <?php if ($isCompleted): ?>
                            <span class="lesson-status completed">
                                ✓ Завершен
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="lesson-navigation">
                    <?php if ($prevLesson): ?>
                        <a href="lesson-view.php?id=<?= $prevLesson['id'] ?>" class="nav-btn prev-btn" title="Предыдущий урок">
                            <span class="nav-arrow">←</span>
                            <span class="nav-text">
                                <small>Предыдущий урок</small>
                                <strong><?= htmlspecialchars($prevLesson['title']) ?></strong>
                            </span>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($nextLesson): ?>
                        <a href="lesson-view.php?id=<?= $nextLesson['id'] ?>" class="nav-btn next-btn" title="Следующий урок">
                            <span class="nav-text">
                                <small>Следующий урок</small>
                                <strong><?= htmlspecialchars($nextLesson['title']) ?></strong>
                            </span>
                            <span class="nav-arrow">→</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Видео (если есть) -->
            <?php if (!empty($lesson['video_url'])): ?>
            <div class="lesson-video-card">
                <div class="video-container">
                    <iframe 
                        src="<?= htmlspecialchars($lesson['video_url']) ?>" 
                        frameborder="0" 
                        allowfullscreen
                        title="<?= htmlspecialchars($lesson['title']) ?>"
                    ></iframe>
                </div>
            </div>
            <?php endif; ?>

            <!-- Содержимое урока -->
            <div class="lesson-content-card">
                <div class="lesson-content">
                    <?= nl2br(htmlspecialchars($lesson['content'])) ?>
                </div>
            </div>

            <!-- Прогресс и действия -->
            <div class="lesson-actions-card">
                <?php if (isset($error)): ?>
                    <div class="error-message">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if ($isCompleted): ?>
                    <div class="completion-info">
                        <div class="completion-status">
                            <span class="completion-icon">✓</span>
                            <div class="completion-details">
                                <strong>Урок завершен</strong>
                                <p>Завершено: <?= date('d.m.Y в H:i', strtotime($progress['completed_at'])) ?></p>
                                <?php if ($progress['time_spent_minutes'] > 0): ?>
                                    <p>Затрачено времени: <?= $progress['time_spent_minutes'] ?> мин</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="action-buttons">
                        <a href="course-view.php?id=<?= $lesson['course_id'] ?>" class="btn btn-gray">
                            Вернуться к курсу
                        </a>
                        <?php if ($nextLesson): ?>
                            <a href="lesson-view.php?id=<?= $nextLesson['id'] ?>" class="btn btn-orange">
                                Следующий урок
                            </a>
                        <?php else: ?>
                            <a href="course-view.php?id=<?= $lesson['course_id'] ?>" class="btn btn-orange">
                                К тестированию
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="completion-form">
                        <div class="completion-prompt">
                            <h3>Изучили материал урока?</h3>
                            <p>Отметьте урок как пройденный, чтобы продолжить обучение.</p>
                        </div>
                        
                        <form method="POST" id="complete-lesson-form">
                            <input type="hidden" name="complete_lesson" value="1">
                            <input type="hidden" name="time_spent" id="time-spent" value="0">
                            
                            <div class="action-buttons">
                                <a href="course-view.php?id=<?= $lesson['course_id'] ?>" class="btn btn-gray">
                                    Вернуться к курсу
                                </a>
                                <button type="submit" class="btn btn-orange" id="complete-btn">
                                    ✓ Урок пройден
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Общий прогресс по курсу -->
                <div class="course-progress-info">
                    <?php
                    $completedCount = 0;
                    foreach ($allLessons as $l) {
                        $stmt = $mysqli->prepare("SELECT id FROM user_lesson_progress WHERE user_id = ? AND lesson_id = ?");
                        $stmt->bind_param('ii', $userId, $l['id']);
                        $stmt->execute();
                        if ($stmt->get_result()->fetch_assoc()) {
                            $completedCount++;
                        }
                        $stmt->close();
                    }
                    $progressPercent = count($allLessons) > 0 ? ($completedCount / count($allLessons)) * 100 : 0;
                    ?>
                    
                    <div class="progress-header">
                        <span>Общий прогресс по курсу</span>
                        <span><?= $completedCount ?>/<?= count($allLessons) ?> уроков</span>
                    </div>
                    
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= $progressPercent ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Отслеживание времени на странице
        let startTime = Date.now();
        
        // Обновление времени при отправке формы
        const form = document.getElementById('complete-lesson-form');
        if (form) {
            form.addEventListener('submit', function() {
                const timeSpent = Math.round((Date.now() - startTime) / 60000); // в минутах
                document.getElementById('time-spent').value = Math.max(1, timeSpent); // минимум 1 минута
            });
        }
        
        // Предотвращение случайного закрытия страницы, если урок не завершен
        <?php if (!$isCompleted): ?>
        window.addEventListener('beforeunload', function(e) {
            const timeSpent = Math.round((Date.now() - startTime) / 60000);
            if (timeSpent > 2) { // если пользователь провел на странице больше 2 минут
                e.preventDefault();
                e.returnValue = 'Вы уверены, что хотите покинуть страницу? Урок не отмечен как пройденный.';
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>