<?php
// dashboard/course-test.php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/courses-functions.php';

if (empty($_SESSION['uid'])) {
    header('Location: ../auth/login.php');
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

// Обработка отправки теста
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_test'])) {
    $userAnswers = $_POST['answers'] ?? [];
    $correctAnswers = getCourseTestAnswers($mysqli, $courseId);
    
    $totalScore = 0;
    $maxScore = 0;
    $results = [];
    
    foreach ($correctAnswers as $questionId => $answerData) {
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
if (!isset($testCompleted)) {
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
    <link rel="stylesheet" href="../css/app-base.css">
    <link rel="stylesheet" href="../css/cabinet-header.css">
    <link rel="stylesheet" href="../css/courses.css">
</head>
<body>
    <?php require __DIR__ . '/../config/header-cabinet.inc.php'; ?>
    
    <div class="container">
        <div class="test-view-wrap">
            <div class="breadcrumb">
                <a href="courses.php">Курсы</a> → 
                <a href="course-view.php?id=<?= $courseId ?>"><?= htmlspecialchars($course['title']) ?></a> → 
                Тестирование
            </div>

            <?php if (isset($testCompleted)): ?>
                <!-- Результаты теста -->
                <div class="test-results-card">
                    <div class="results-header <?= $passed ? 'passed' : 'failed' ?>">
                        <div class="result-icon">
                            <?= $passed ? '🎉' : '😞' ?>
                        </div>
                        <div class="result-info">
                            <h1><?= $passed ? 'Поздравляем!' : 'Тест не пройден' ?></h1>
                            <p><?= $passed ? 'Вы успешно прошли тестирование' : 'К сожалению, результат ниже проходного балла' ?></p>
                        </div>
                    </div>

                    <div class="results-stats">
                        <div class="stat-item">
                            <div class="stat-value <?= $passed ? 'success' : 'error' ?>">
                                <?= number_format($percentage, 1) ?>%
                            </div>
                            <div class="stat-label">Ваш результат</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">
                                <?= $course['passing_score'] ?>%
                            </div>
                            <div class="stat-label">Проходной балл</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">
                                <?= $totalScore ?>/<?= $maxScore ?>
                            </div>
                            <div class="stat-label">Баллы</div>
                        </div>
                    </div>

                    <div class="results-actions">
                        <a href="course-view.php?id=<?= $courseId ?>" class="btn btn-gray">
                            Вернуться к курсу
                        </a>
                        <?php if (!$passed): ?>
                            <a href="course-test.php?course_id=<?= $courseId ?>" class="btn btn-orange">
                                Пройти еще раз
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Детальные результаты -->
                    <div class="results-details">
                        <h3>Детальные результаты</h3>
                        <div class="questions-review">
                            <?php 
                            $questionIndex = 1;
                            $questionsData = getCourseTestQuestions($mysqli, $courseId);
                            foreach ($questionsData as $question):
                                $result = $results[$question['id']] ?? null;
                                if (!$result) continue;
                            ?>
                                <div class="question-review <?= $result['is_correct'] ? 'correct' : 'incorrect' ?>">
                                    <div class="question-header">
                                        <span class="question-number">Вопрос <?= $questionIndex++ ?></span>
                                        <span class="question-result">
                                            <?= $result['is_correct'] ? '✓ Правильно' : '✗ Неправильно' ?>
                                        </span>
                                    </div>
                                    <div class="question-text">
                                        <?= htmlspecialchars($question['question']) ?>
                                    </div>
                                    <div class="question-answers">
                                        <div class="answer-item your-answer <?= $result['is_correct'] ? 'correct' : 'incorrect' ?>">
                                            <strong>Ваш ответ:</strong> 
                                            <?= $result['user_answer'] ?>. <?= htmlspecialchars($question['option_' . strtolower($result['user_answer'])]) ?>
                                        </div>
                                        <?php if (!$result['is_correct']): ?>
                                            <div class="answer-item correct-answer">
                                                <strong>Правильный ответ:</strong> 
                                                <?= $result['correct_answer'] ?>. <?= htmlspecialchars($question['option_' . strtolower($result['correct_answer'])]) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

            <?php elseif (isset($error)): ?>
                <!-- Ошибка -->
                <div class="error-card">
                    <h1>Ошибка</h1>
                    <p><?= htmlspecialchars($error) ?></p>
                    <a href="course-view.php?id=<?= $courseId ?>" class="btn btn-gray">
                        Вернуться к курсу
                    </a>
                </div>

            <?php else: ?>
                <!-- Форма теста -->
                <div class="test-form-card">
                    <div class="test-header">
                        <h1>Тест: <?= htmlspecialchars($course['title']) ?></h1>
                        <div class="test-info">
                            <p><strong>Проходной балл:</strong> <?= $course['passing_score'] ?>%</p>
                            <p><strong>Вопросов:</strong> <?= count($questions) ?></p>
                            <p><strong>Внимание:</strong> После отправки теста изменить ответы будет невозможно</p>
                        </div>
                    </div>

                    <form method="POST" id="test-form">
                        <input type="hidden" name="submit_test" value="1">
                        
                        <div class="questions-list">
                            <?php foreach ($questions as $index => $question): ?>
                                <div class="question-item">
                                    <div class="question-header">
                                        <span class="question-number">Вопрос <?= $index + 1 ?></span>
                                        <span class="question-points"><?= $question['points'] ?> балл<?= $question['points'] > 1 ? 'а' : '' ?></span>
                                    </div>
                                    
                                    <div class="question-text">
                                        <?= htmlspecialchars($question['question']) ?>
                                    </div>
                                    
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
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="test-actions">
                            <a href="course-view.php?id=<?= $courseId ?>" class="btn btn-gray">
                                Отмена
                            </a>
                            <button type="submit" class="btn btn-orange" id="submit-test-btn">
                                Завершить тест
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Подтверждение отправки теста
        document.getElementById('test-form')?.addEventListener('submit', function(e) {
            const formData = new FormData(this);
            const answeredQuestions = new Set();
            
            for (let [key, value] of formData.entries()) {
                if (key.startsWith('answers[')) {
                    const questionId = key.match(/answers\[(\d+)\]/)[1];
                    answeredQuestions.add(questionId);
                }
            }
            
            const totalQuestions = <?= count($questions ?? []) ?>;
            if (answeredQuestions.size < totalQuestions) {
                e.preventDefault();
                alert('Пожалуйста, ответьте на все вопросы');
                return;
            }
            
            if (!confirm('Вы уверены, что хотите завершить тест? Изменить ответы после отправки будет невозможно.')) {
                e.preventDefault();
            }
        });

        // Предупреждение о закрытии страницы
        <?php if (!isset($testCompleted) && !isset($error)): ?>
        window.addEventListener('beforeunload', function(e) {
            e.preventDefault();
            e.returnValue = 'Вы уверены, что хотите покинуть страницу? Прогресс теста будет потерян.';
        });
        <?php endif; ?>
    </script>
</body>
</html>