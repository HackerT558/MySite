<?php
// dashboard/course-details.php - Вспомогательный файл для модального окна
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/courses-functions.php';

if (empty($_SESSION['uid'])) {
    http_response_code(403);
    exit('Access denied');
}

$userRole = $_SESSION['role'] ?? 'user';
$currentLevel = role_level($userRole);

// Проверяем права доступа
if ($currentLevel < role_level('manager-top')) {
    http_response_code(403);
    exit('Access denied');
}

$courseId = (int)($_GET['id'] ?? 0);
if ($courseId <= 0) {
    exit('Invalid course ID');
}

// Получаем информацию о курсе
$course = getCourseDetails($mysqli, $courseId);
if (!$course) {
    exit('Course not found');
}

// Получаем уроки курса
$lessons = getCourseLessons($mysqli, $courseId);

// Получаем вопросы для теста
$questions = getCourseTestQuestions($mysqli, $courseId);

// Получаем статистику назначений
$stmt = $mysqli->prepare("
    SELECT 
        COUNT(*) as total_assignments,
        COUNT(CASE WHEN status = 'assigned' THEN 1 END) as assigned_count,
        COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress_count,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count,
        COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_count
    FROM user_course_assignments 
    WHERE course_id = ?
");
$stmt->bind_param('i', $courseId);
$stmt->execute();
$assignmentStats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Получаем средний балл тестов
$stmt = $mysqli->prepare("
    SELECT AVG(percentage) as avg_score, COUNT(*) as test_attempts
    FROM course_test_results 
    WHERE course_id = ?
");
$stmt->bind_param('i', $courseId);
$stmt->execute();
$testStats = $stmt->get_result()->fetch_assoc();
$stmt->close();

?>

<div class="course-details">
    <!-- Основная информация -->
    <div class="detail-section">
        <h4><?= htmlspecialchars($course['title']) ?></h4>
        <div class="course-meta">
            <div class="meta-row">
                <span><strong>ID курса:</strong> <?= $course['id'] ?></span>
                <span><strong>Позиция:</strong> <?= htmlspecialchars($course['position']) ?></span>
            </div>
            <div class="meta-row">
                <span><strong>Уровень:</strong> 
                    <?php
                    echo [
                        'beginner' => 'Начальный',
                        'intermediate' => 'Средний',
                        'advanced' => 'Продвинутый'
                    ][$course['difficulty_level']] ?? $course['difficulty_level'];
                    ?>
                </span>
                <span><strong>Проходной балл:</strong> <?= $course['passing_score'] ?>%</span>
            </div>
            <div class="meta-row">
                <span><strong>Длительность:</strong> <?= $course['duration_minutes'] ?> мин</span>
                <span><strong>Статус:</strong> 
                    <span class="badge <?= $course['is_active'] ? 'badge-success' : 'badge-secondary' ?>">
                        <?= $course['is_active'] ? 'Активный' : 'Неактивный' ?>
                    </span>
                </span>
            </div>
        </div>
        
        <?php if ($course['description']): ?>
        <div class="course-description">
            <strong>Описание:</strong>
            <p><?= nl2br(htmlspecialchars($course['description'])) ?></p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Статистика назначений -->
    <div class="detail-section">
        <h4>Статистика назначений</h4>
        <div class="assignment-stats">
            <div class="stat-item">
                <span class="stat-number"><?= $assignmentStats['total_assignments'] ?></span>
                <span class="stat-label">Всего назначений</span>
            </div>
            <div class="stat-item">
                <span class="stat-number stat-info"><?= $assignmentStats['assigned_count'] ?></span>
                <span class="stat-label">Назначено</span>
            </div>
            <div class="stat-item">
                <span class="stat-number stat-warning"><?= $assignmentStats['in_progress_count'] ?></span>
                <span class="stat-label">В процессе</span>
            </div>
            <div class="stat-item">
                <span class="stat-number stat-success"><?= $assignmentStats['completed_count'] ?></span>
                <span class="stat-label">Завершено</span>
            </div>
            <div class="stat-item">
                <span class="stat-number stat-error"><?= $assignmentStats['failed_count'] ?></span>
                <span class="stat-label">Не пройдено</span>
            </div>
        </div>

        <?php if ($testStats['test_attempts'] > 0): ?>
        <div class="test-stats">
            <p><strong>Статистика тестирования:</strong></p>
            <p>Попыток сдачи: <?= $testStats['test_attempts'] ?></p>
            <p>Средний балл: <?= number_format($testStats['avg_score'], 1) ?>%</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Уроки -->
    <div class="detail-section">
        <h4>Программа обучения (<?= count($lessons) ?> уроков)</h4>
        <?php if (empty($lessons)): ?>
            <p class="text-muted">Уроки не добавлены</p>
        <?php else: ?>
            <div class="lessons-list-mini">
                <?php foreach ($lessons as $lesson): ?>
                    <div class="lesson-mini">
                        <span class="lesson-number"><?= $lesson['lesson_order'] ?></span>
                        <div class="lesson-info">
                            <strong><?= htmlspecialchars($lesson['title']) ?></strong>
                            <small><?= $lesson['duration_minutes'] ?> мин 
                                <?= $lesson['video_url'] ? '• Видео' : '' ?>
                            </small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Вопросы теста -->
    <div class="detail-section">
        <h4>Тест (<?= count($questions) ?> вопросов)</h4>
        <?php if (empty($questions)): ?>
            <p class="text-muted">Вопросы для теста не добавлены</p>
        <?php else: ?>
            <div class="questions-preview">
                <p>Общее количество баллов: <?= array_sum(array_column($questions, 'points')) ?></p>
                <p>Проходной балл: <?= $course['passing_score'] ?>%</p>
                <div class="questions-list-mini">
                    <?php foreach (array_slice($questions, 0, 3) as $index => $question): ?>
                        <div class="question-mini">
                            <strong><?= $index + 1 ?>.</strong> 
                            <?= htmlspecialchars(substr($question['question'], 0, 80)) ?>...
                            <small>(<?= $question['points'] ?> балл<?= $question['points'] > 1 ? 'а' : '' ?>)</small>
                        </div>
                    <?php endforeach; ?>
                    <?php if (count($questions) > 3): ?>
                        <p class="text-muted">... и еще <?= count($questions) - 3 ?> вопросов</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Действия -->
    <div class="detail-actions">
        <a href="course-management.php?edit=<?= $courseId ?>" class="btn btn-orange">Редактировать</a>
        <a href="course-view.php?id=<?= $courseId ?>" class="btn btn-gray" target="_blank">Предпросмотр</a>
    </div>
</div>

<style>
.course-details {
    max-height: 70vh;
    overflow-y: auto;
}

.detail-section {
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid #eee;
}

.detail-section:last-child {
    border-bottom: none;
}

.detail-section h4 {
    margin: 0 0 12px 0;
    color: #333;
    font-size: 16px;
    font-weight: 600;
}

.course-meta {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.meta-row {
    display: flex;
    gap: 24px;
    flex-wrap: wrap;
}

.meta-row span {
    font-size: 14px;
    color: #555;
}

.course-description {
    margin-top: 12px;
}

.course-description p {
    margin: 4px 0 0 0;
    color: #666;
    line-height: 1.4;
}

.assignment-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    gap: 16px;
    margin-bottom: 16px;
}

.stat-item {
    text-align: center;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 6px;
}

.stat-number {
    display: block;
    font-size: 20px;
    font-weight: 700;
    color: #333;
}

.stat-number.stat-info { color: #17a2b8; }
.stat-number.stat-warning { color: #ffc107; }
.stat-number.stat-success { color: #28a745; }
.stat-number.stat-error { color: #dc3545; }

.stat-label {
    font-size: 12px;
    color: #666;
    text-transform: uppercase;
}

.test-stats {
    padding: 12px;
    background: #f8f9fa;
    border-radius: 6px;
    font-size: 14px;
}

.test-stats p {
    margin: 4px 0;
}

.lessons-list-mini {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.lesson-mini {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px;
    background: #f8f9fa;
    border-radius: 4px;
}

.lesson-number {
    width: 24px;
    height: 24px;
    background: #e9ecef;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 600;
    color: #666;
    flex-shrink: 0;
}

.lesson-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.lesson-info strong {
    font-size: 14px;
    color: #333;
}

.lesson-info small {
    font-size: 12px;
    color: #666;
}

.questions-preview {
    font-size: 14px;
}

.questions-preview p {
    margin: 4px 0;
    color: #555;
}

.questions-list-mini {
    margin-top: 12px;
}

.question-mini {
    padding: 8px 12px;
    margin-bottom: 6px;
    background: #f8f9fa;
    border-radius: 4px;
    font-size: 14px;
    line-height: 1.4;
}

.question-mini small {
    color: #666;
    font-size: 12px;
}

.detail-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    padding-top: 16px;
    border-top: 1px solid #eee;
}

.text-muted {
    color: #666;
    font-style: italic;
}

.badge-success {
    background: #d4edda;
    color: #155724;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 12px;
}

.badge-secondary {
    background: #e2e3e5;
    color: #6c757d;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 12px;
}
</style>