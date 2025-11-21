<?php
// dashboard/course-test.php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/courses-functions.php';

if (empty($_SESSION['uid'])) {
    header('Location: ../index.php');
    exit;
}

$courseId = (int)($_GET['course_id'] ?? 0);
if ($courseId <= 0) {
    header('Location: courses.php');
    exit;
}

$active = 'courses';
$userId = $_SESSION['uid'];

// Проверяем, назначен ли курс пользователю
if (!isUserAssignedToCourse($mysqli, $userId, $courseId)) {
    header('Location: courses.php');
    exit;
}

// Проверяем доступность теста
if (!isTestAvailableForUser($mysqli, $userId, $courseId)) {
    header('Location: course-view.php?id=' . $courseId);
    exit;
}

// Получаем информацию о курсе
$course = getCourseDetails($mysqli, $courseId);
if (!$course) {
    header('Location: courses.php');
    exit;
}

$testCompleted = false;
$percentage = 0;
$passed = false;
$error = '';
$totalScore = 0;
$maxScore = 0;
$results = [];

// Обработка результатов теста (все вопросы отправлены)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_submit'])) {
    $userAnswers = $_POST['answers'] ?? [];
    $questionAnswers = getCourseTestAnswers($mysqli, $courseId);
    
    foreach ($questionAnswers as $questionId => $answerData) {
        $maxScore += $answerData['points'];
        $userAnswer = $userAnswers[$questionId] ?? '';
        $isCorrect = ($userAnswer === $answerData['correct_answer']);
        if ($isCorrect) {
            $totalScore += $answerData['points'];
        }
        $results[$questionId] = [
            'user_answer' => $userAnswer,
            'correct_answer' => $answerData['correct_answer'],
            'is_correct' => $isCorrect,
            'points' => $answerData['points']
        ];
    }
    
    // Сохраняем результаты
    if (saveTestResults($mysqli, $userId, $courseId, $results, $totalScore, $maxScore)) {
        $testCompleted = true;
        $percentage = $maxScore > 0 ? ($totalScore / $maxScore) * 100 : 0;
        $passed = $percentage >= $course['passing_score'];
    } else {
        $error = "Ошибка при сохранении результатов теста";
    }
}

// Если тест не отправлен, получаем вопросы
if (!isset($testCompleted) || !$testCompleted) {
    $questions = getCourseTestQuestions($mysqli, $courseId);
    if (empty($questions)) {
        $error = "Для данного курса не настроены вопросы теста";
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Тест: <?= htmlspecialchars($course['title']) ?></title>
    <link rel="stylesheet" href="../css/cabinet-header.css">
    <link rel="stylesheet" href="../css/app-base.css">
    <link rel="stylesheet" href="../css/courses.css">
    <link rel="stylesheet" href="../css/test.css">
    <script src="../js/test.js" defer></script>
</head>
<body>
    <?php require __DIR__ . '/../config/header-cabinet.inc.php'; ?>

    <div class="test-view-wrap">
        <!-- Хлебные крошки -->
        <div class="breadcrumb">
            <a href="courses.php">Курсы</a> / 
            <a href="course-view.php?id=<?= $courseId ?>">Тестирование</a>
        </div>

        <?php if ($testCompleted): ?>
            <!-- Результаты теста -->
            <div class="test-results-card">
                <div class="results-header">
                    <h1>Результаты тестирования</h1>
                    <div class="results-status <?= $passed ? 'passed' : 'failed' ?>">
                        <span class="status-icon"><?= $passed ? '✓' : '✗' ?></span>
                        <span class="status-text">
                            <?= $passed ? 'Вы успешно прошли тестирование!' : 'К сожалению, результат ниже проходного балла' ?>
                        </span>
                    </div>
                </div>

                <div class="results-summary">
                    <div class="summary-item">
                        <span class="summary-label">Ваш балл</span>
                        <span class="summary-value"><?= $totalScore ?>/<?= $maxScore ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Процент</span>
                        <span class="summary-value"><?= round($percentage, 1) ?>%</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Проходной балл</span>
                        <span class="summary-value"><?= $course['passing_score'] ?>%</span>
                    </div>
                </div>

                <div class="results-details">
                    <h2>Детали ответов</h2>
                    <div class="results-list">
                        <?php foreach ($results as $questionId => $result): ?>
                            <div class="result-item <?= $result['is_correct'] ? 'correct' : 'incorrect' ?>">
                                <div class="result-header">
                                    <span class="result-icon"><?= $result['is_correct'] ? '✓' : '✗' ?></span>
                                    <span class="result-question">Вопрос <?= htmlspecialchars($questionId) ?></span>
                                    <span class="result-points"><?= $result['points'] ?> баллов</span>
                                </div>
                                <?php if (!$result['is_correct']): ?>
                                    <div class="result-details">
                                        <p><strong>Ваш ответ:</strong> <?= htmlspecialchars($result['user_answer']) ?></p>
                                        <p><strong>Правильный ответ:</strong> <?= htmlspecialchars($result['correct_answer']) ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="results-actions">
                    <a href="course-view.php?id=<?= $courseId ?>" class="btn btn--primary">Вернуться к курсу</a>
                </div>
            </div>

        <?php elseif (!empty($error)): ?>
            <!-- Ошибка -->
            <div class="test-error-card">
                <div class="error-icon">⚠</div>
                <h2><?= htmlspecialchars($error) ?></h2>
                <a href="course-view.php?id=<?= $courseId ?>" class="btn btn--primary">Вернуться к курсу</a>
            </div>

        <?php else: ?>
            <!-- Форма тестирования с динамической загрузкой вопросов -->
            <form id="testForm" method="POST" action="" class="test-form">
                <input type="hidden" name="test_submit" value="1">

                <!-- Информационная панель -->
                <div class="test-info-panel">
                    <div class="info-item">
                        <span class="info-label">Всего вопросов:</span>
                        <span class="info-value"><?= count($questions) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Проходной балл:</span>
                        <span class="info-value"><?= $course['passing_score'] ?>%</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Текущий вопрос:</span>
                        <span class="info-value"><span id="currentQuestion">1</span>/<span id="totalQuestions"><?= count($questions) ?></span></span>
                    </div>
                </div>

                <!-- Контейнер для вопросов -->
                <div class="questions-container">
                    <?php 
                    $questionIndex = 1;
                    foreach ($questions as $question): 
                    ?>
                        <div class="question-block" data-question-index="<?= $questionIndex ?>">
                            <div class="question-header">
                                <span class="question-number">Вопрос <?= $questionIndex ?></span>
                                <span class="question-progress"><?= $questionIndex ?>/<?= count($questions) ?></span>
                            </div>

                            <h3 class="question-text"><?= htmlspecialchars($question['question']) ?></h3>

                            <div class="question-options">
                                <label class="option-label">
                                            <input type="radio" name="answers[<?= $question['id'] ?>]" value="A" required>
                                            <span class="option-text">A. <?= htmlspecialchars($question['option_a']) ?></span>
                                        </label>
                                        
                                        <label class="option-label">
                                            <input type="radio" name="answers[<?= $question['id'] ?>]" value="B" required>
                                            <span class="option-text">B. <?= htmlspecialchars($question['option_b']) ?></span>
                                        </label>
                                        
                                        <label class="option-label">
                                            <input type="radio" name="answers[<?= $question['id'] ?>]" value="C" required>
                                            <span class="option-text">C. <?= htmlspecialchars($question['option_c']) ?></span>
                                        </label>
                                        
                                        <label class="option-label">
                                            <input type="radio" name="answers[<?= $question['id'] ?>]" value="D" required>
                                            <span class="option-text">D. <?= htmlspecialchars($question['option_d']) ?></span>
                                        </label>
                            </div>

                            <div class="question-navigation">
                                <button 
                                    type="button" 
                                    class="btn btn--secondary nav-btn-prev" 
                                    onclick="showPreviousQuestion()"
                                    <?= $questionIndex === 1 ? 'disabled' : '' ?>
                                >
                                    ← Предыдущий
                                </button>

                                <?php if ($questionIndex === count($questions)): ?>
                                    <button type="submit" class="btn btn--primary nav-btn-submit">
                                        Завершить тест
                                    </button>
                                <?php else: ?>
                                    <button 
                                        type="button" 
                                        class="btn btn--primary nav-btn-next" 
                                        onclick="showNextQuestion()"
                                    >
                                        Следующий →
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php 
                    $questionIndex++;
                    endforeach; 
                    ?>
                </div>

                <!-- Информация перед завершением -->
                <div class="test-warning">
                    <strong>⚠ Внимание!</strong> После отправки теста изменить ответы будет невозможно.
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
