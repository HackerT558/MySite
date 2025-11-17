<?php
// dashboard/course-management.php - Улучшенная версия с вкладками
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/courses-functions.php';

if (empty($_SESSION['uid'])) {
    header('Location: ../index.php');
    exit;
}

$active = 'courses';
$userId = $_SESSION['uid'];
$userRole = $_SESSION['role'] ?? 'user';
$currentLevel = role_level($userRole);

// Проверяем права доступа
if ($currentLevel < role_level('manager-top')) {
    header('Location: courses.php');
    exit;
}

// Обработка действий
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_course':
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $position = $_POST['position'] ?? '';
            $difficulty = $_POST['difficulty_level'] ?? 'beginner';
            $passingScore = (int)($_POST['passing_score'] ?? 80);
            $duration = (int)($_POST['duration_minutes'] ?? 60);
            
            if ($title && $position) {
                $stmt = $mysqli->prepare("
                    INSERT INTO courses (title, description, position, difficulty_level, passing_score, duration_minutes)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param('ssssii', $title, $description, $position, $difficulty, $passingScore, $duration);
                
                if ($stmt->execute()) {
                    $newCourseId = $mysqli->insert_id;
                    $message = "Курс успешно создан (ID: $newCourseId)";
                    $messageType = 'success';
                    header("Location: course-management.php?edit=$newCourseId&tab=lessons");
                    exit;
                } else {
                    $message = "Ошибка при создании курса: " . $mysqli->error;
                    $messageType = 'error';
                }
                $stmt->close();
            } else {
                $message = "Заполните обязательные поля";
                $messageType = 'error';
            }
            break;
            
        case 'update_course':
            $courseId = (int)($_POST['course_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $position = $_POST['position'] ?? '';
            $difficulty = $_POST['difficulty_level'] ?? 'beginner';
            $passingScore = (int)($_POST['passing_score'] ?? 80);
            $duration = (int)($_POST['duration_minutes'] ?? 60);
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            if ($courseId && $title && $position) {
                $stmt = $mysqli->prepare("
                    UPDATE courses 
                    SET title = ?, description = ?, position = ?, difficulty_level = ?, 
                        passing_score = ?, duration_minutes = ?, is_active = ?
                    WHERE id = ?
                ");
                $stmt->bind_param('ssssiiii', $title, $description, $position, $difficulty, $passingScore, $duration, $isActive, $courseId);
                
                if ($stmt->execute()) {
                    $message = "Курс успешно обновлен";
                    $messageType = 'success';
                } else {
                    $message = "Ошибка при обновлении курса: " . $mysqli->error;
                    $messageType = 'error';
                }
                $stmt->close();
            } else {
                $message = "Заполните обязательные поля";
                $messageType = 'error';
            }
            break;
            
        case 'delete_course':
            $courseId = (int)($_POST['course_id'] ?? 0);
            if ($courseId) {
                // Проверяем, есть ли активные назначения
                $stmt = $mysqli->prepare("
                    SELECT COUNT(*) FROM user_course_assignments 
                    WHERE course_id = ? AND status IN ('assigned', 'in_progress')
                ");
                $stmt->bind_param('i', $courseId);
                $stmt->execute();
                $stmt->bind_result($activeAssignments);
                $stmt->fetch();
                $stmt->close();
                
                if ($activeAssignments > 0) {
                    $message = "Невозможно удалить курс: есть активные назначения ($activeAssignments)";
                    $messageType = 'error';
                } else {
                    $stmt = $mysqli->prepare("DELETE FROM courses WHERE id = ?");
                    $stmt->bind_param('i', $courseId);
                    
                    if ($stmt->execute()) {
                        $message = "Курс успешно удален";
                        $messageType = 'success';
                        header("Location: course-management.php");
                        exit;
                    } else {
                        $message = "Ошибка при удалении курса: " . $mysqli->error;
                        $messageType = 'error';
                    }
                    $stmt->close();
                }
            }
            break;
            
        case 'add_lesson':
            $courseId = (int)($_POST['course_id'] ?? 0);
            $title = trim($_POST['lesson_title'] ?? '');
            $content = trim($_POST['lesson_content'] ?? '');
            $order = (int)($_POST['lesson_order'] ?? 1);
            $duration = (int)($_POST['lesson_duration'] ?? 10);
            $videoUrl = trim($_POST['video_url'] ?? '');
            
            if ($courseId && $title && $content) {
                $stmt = $mysqli->prepare("
                    INSERT INTO course_lessons (course_id, title, content, lesson_order, duration_minutes, video_url)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param('isssis', $courseId, $title, $content, $order, $duration, $videoUrl);
                
                if ($stmt->execute()) {
                    $message = "Урок успешно добавлен";
                    $messageType = 'success';
                } else {
                    $message = "Ошибка при добавлении урока: " . $mysqli->error;
                    $messageType = 'error';
                }
                $stmt->close();
            } else {
                $message = "Заполните обязательные поля урока";
                $messageType = 'error';
            }
            break;

        case 'update_lesson':
            $lessonId = (int)($_POST['lesson_id'] ?? 0);
            $title = trim($_POST['lesson_title'] ?? '');
            $content = trim($_POST['lesson_content'] ?? '');
            $order = (int)($_POST['lesson_order'] ?? 1);
            $duration = (int)($_POST['lesson_duration'] ?? 10);
            $videoUrl = trim($_POST['video_url'] ?? '');

            if ($lessonId && $title && $content) {
                $stmt = $mysqli->prepare("
                    UPDATE course_lessons 
                    SET title = ?, content = ?, lesson_order = ?, duration_minutes = ?, video_url = ?
                    WHERE id = ?
                ");
                $stmt->bind_param('ssiisi', $title, $content, $order, $duration, $videoUrl, $lessonId);
                
                if ($stmt->execute()) {
                    $message = "Урок успешно обновлен";
                    $messageType = 'success';
                } else {
                    $message = "Ошибка при обновлении урока: " . $mysqli->error;
                    $messageType = 'error';
                }
                $stmt->close();
            }
            break;

        case 'delete_lesson':
            $lessonId = (int)($_POST['lesson_id'] ?? 0);
            if ($lessonId) {
                $stmt = $mysqli->prepare("DELETE FROM course_lessons WHERE id = ?");
                $stmt->bind_param('i', $lessonId);
                
                if ($stmt->execute()) {
                    $message = "Урок успешно удален";
                    $messageType = 'success';
                } else {
                    $message = "Ошибка при удалении урока: " . $mysqli->error;
                    $messageType = 'error';
                }
                $stmt->close();
            }
            break;
            
        case 'add_question':
            $courseId = (int)($_POST['course_id'] ?? 0);
            $question = trim($_POST['question_text'] ?? '');
            $optionA = trim($_POST['option_a'] ?? '');
            $optionB = trim($_POST['option_b'] ?? '');
            $optionC = trim($_POST['option_c'] ?? '');
            $optionD = trim($_POST['option_d'] ?? '');
            $correctAnswer = $_POST['correct_answer'] ?? '';
            $points = (int)($_POST['points'] ?? 1);
            
            if ($courseId && $question && $optionA && $optionB && $optionC && $optionD && $correctAnswer) {
                $stmt = $mysqli->prepare("
                    INSERT INTO course_questions (course_id, question, option_a, option_b, option_c, option_d, correct_answer, points)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param('issssssi', $courseId, $question, $optionA, $optionB, $optionC, $optionD, $correctAnswer, $points);
                
                if ($stmt->execute()) {
                    $message = "Вопрос успешно добавлен";
                    $messageType = 'success';
                } else {
                    $message = "Ошибка при добавлении вопроса: " . $mysqli->error;
                    $messageType = 'error';
                }
                $stmt->close();
            } else {
                $message = "Заполните все поля вопроса";
                $messageType = 'error';
            }
            break;

        case 'update_question':
            $questionId = (int)($_POST['question_id'] ?? 0);
            $question = trim($_POST['question_text'] ?? '');
            $optionA = trim($_POST['option_a'] ?? '');
            $optionB = trim($_POST['option_b'] ?? '');
            $optionC = trim($_POST['option_c'] ?? '');
            $optionD = trim($_POST['option_d'] ?? '');
            $correctAnswer = $_POST['correct_answer'] ?? '';
            $points = (int)($_POST['points'] ?? 1);

            if ($questionId && $question && $optionA && $optionB && $optionC && $optionD && $correctAnswer) {
                $stmt = $mysqli->prepare("
                    UPDATE course_questions 
                    SET question = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_answer = ?, points = ?
                    WHERE id = ?
                ");
                $stmt->bind_param('ssssssii', $question, $optionA, $optionB, $optionC, $optionD, $correctAnswer, $points, $questionId);
                
                if ($stmt->execute()) {
                    $message = "Вопрос успешно обновлен";
                    $messageType = 'success';
                } else {
                    $message = "Ошибка при обновлении вопроса: " . $mysqli->error;
                    $messageType = 'error';
                }
                $stmt->close();
            }
            break;

        case 'delete_question':
            $questionId = (int)($_POST['question_id'] ?? 0);
            if ($questionId) {
                $stmt = $mysqli->prepare("DELETE FROM course_questions WHERE id = ?");
                $stmt->bind_param('i', $questionId);
                
                if ($stmt->execute()) {
                    $message = "Вопрос успешно удален";
                    $messageType = 'success';
                } else {
                    $message = "Ошибка при удалении вопроса: " . $mysqli->error;
                    $messageType = 'error';
                }
                $stmt->close();
            }
            break;
    }
}

// Получаем все курсы
$allCourses = getAllCourses($mysqli);

// Получаем статистику
$statistics = getCoursesStatistics($mysqli);

// Получаем выбранный курс для редактирования
$editCourse = null;
$editCourseId = (int)($_GET['edit'] ?? 0);
$activeTab = $_GET['tab'] ?? 'info';

if ($editCourseId > 0) {
    $editCourse = getCourseDetails($mysqli, $editCourseId);
    
    if ($editCourse) {
        // Получаем уроки курса
        $courseLessons = getCourseLessons($mysqli, $editCourseId);
        
        // Получаем вопросы курса
        $courseQuestions = getCourseTestQuestions($mysqli, $editCourseId);
        
        // Добавляем правильные ответы к вопросам
        $stmt = $mysqli->prepare("
            SELECT id, question, option_a, option_b, option_c, option_d, correct_answer, points
            FROM course_questions
            WHERE course_id = ?
            ORDER BY id
        ");
        $stmt->bind_param('i', $editCourseId);
        $stmt->execute();
        $courseQuestionsDetailed = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

// Позиции из таблицы users
$positions = ['Стажер', 'Пиццамейкер', 'Кассир', 'Универсал', 'Менеджер', 'Зам.управляющего', 'Управляющий'];

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление курсами</title>
    <link rel="stylesheet" href="../css/app-base.css">
    <link rel="stylesheet" href="../css/cabinet-header.css">
    <link rel="stylesheet" href="../css/courses.css">
    <link rel="stylesheet" href="../css/app-admin.css">
    <link rel="stylesheet" href="../css/course-management-extended.css">
</head>
<body>
    <?php require __DIR__ . '/../config/header-cabinet.inc.php'; ?>
    
    <div class="container">
        <div class="admin-wrap">
            <div class="management-header">
                <h1>Управление курсами</h1>
                <div class="header-actions">
                    <a href="courses.php" class="btn btn-gray">← Назад к курсам</a>
                    <a href="course-assignments.php" class="btn btn-orange">Назначить курсы</a>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="<?= $messageType === 'success' ? 'notice' : 'error' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if (!$editCourse): ?>
            <!-- Статистика -->
            <div class="card">
                <h2>Общая статистика</h2>
                <div class="stats-grid">
                    <?php
                    $totalCourses = count($allCourses);
                    $activeCourses = count(array_filter($allCourses, function($c) { return $c['is_active']; }));
                    $totalAssignments = array_sum(array_column($statistics, 'assigned_users'));
                    $completedAssignments = array_sum(array_column($statistics, 'completed_users'));
                    ?>
                    <div class="stat-card">
                        <div class="stat-value"><?= $totalCourses ?></div>
                        <div class="stat-label">Всего курсов</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value stat-success"><?= $activeCourses ?></div>
                        <div class="stat-label">Активных</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $totalAssignments ?></div>
                        <div class="stat-label">Назначений</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value stat-success"><?= $completedAssignments ?></div>
                        <div class="stat-label">Завершено</div>
                    </div>
                </div>
            </div>

            <!-- Создание курса -->
            <div class="card">
                <h2>Создать новый курс</h2>
                
                <form method="POST" class="course-form">
                    <input type="hidden" name="action" value="create_course">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="title">Название курса *</label>
                            <input type="text" id="title" name="title" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="position">Для должности *</label>
                            <select id="position" name="position" required>
                                <option value="">Выберите должность</option>
                                <?php foreach ($positions as $pos): ?>
                                    <option value="<?= htmlspecialchars($pos) ?>"><?= htmlspecialchars($pos) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Описание</label>
                        <textarea id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="difficulty_level">Уровень сложности</label>
                            <select id="difficulty_level" name="difficulty_level">
                                <option value="beginner">Начальный</option>
                                <option value="intermediate">Средний</option>
                                <option value="advanced">Продвинутый</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="passing_score">Проходной балл (%)</label>
                            <input type="number" id="passing_score" name="passing_score" min="1" max="100" value="80">
                        </div>
                        
                        <div class="form-group">
                            <label for="duration_minutes">Длительность (мин)</label>
                            <input type="number" id="duration_minutes" name="duration_minutes" min="1" value="60">
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-orange">Создать курс</button>
                    </div>
                </form>
            </div>

            <!-- Список курсов -->
            <div class="card">
                <h2>Все курсы</h2>
                
                <div class="table-container">
                    <table class="table-compact">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Название</th>
                                <th>Должность</th>
                                <th>Уровень</th>
                                <th>Проходной балл</th>
                                <th>Статус</th>
                                <th>Назначено</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allCourses as $course): ?>
                                <?php
                                $stat = array_filter($statistics, function($s) use ($course) {
                                    return $s['id'] == $course['id'];
                                });
                                $stat = reset($stat) ?: ['assigned_users' => 0, 'completed_users' => 0];
                                ?>
                                <tr>
                                    <td><?= $course['id'] ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($course['title']) ?></strong>
                                        <?php if ($course['description']): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars(substr($course['description'], 0, 60)) ?>...</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($course['position']) ?></td>
                                    <td>
                                        <span class="badge badge-<?= $course['difficulty_level'] ?>">
                                            <?php
                                            echo [
                                                'beginner' => 'Начальный',
                                                'intermediate' => 'Средний',
                                                'advanced' => 'Продвинутый'
                                            ][$course['difficulty_level']] ?? $course['difficulty_level'];
                                            ?>
                                        </span>
                                    </td>
                                    <td><?= $course['passing_score'] ?>%</td>
                                    <td>
                                        <span class="badge <?= $course['is_active'] ? 'badge-success' : 'badge-secondary' ?>">
                                            <?= $course['is_active'] ? 'Активный' : 'Неактивный' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= $stat['assigned_users'] ?> чел.
                                        <?php if ($stat['completed_users'] > 0): ?>
                                            <br><small class="text-success">Завершили: <?= $stat['completed_users'] ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="row-actions">
                                        <a href="?edit=<?= $course['id'] ?>" class="btn btn-sm btn-orange">Редактировать</a>
                                        <a href="course-view.php?id=<?= $course['id'] ?>" class="btn btn-sm btn-gray" target="_blank">Предпросмотр</a>
                                        
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Удалить курс?')">
                                            <input type="hidden" name="action" value="delete_course">
                                            <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-red">Удалить</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <?php else: ?>
            <!-- Редактирование курса с вкладками -->
            <div class="card">
                <div class="course-edit-header">
                    <h2>Редактирование: <?= htmlspecialchars($editCourse['title']) ?></h2>
                    <a href="course-management.php" class="btn btn-gray">← К списку курсов</a>
                </div>

                <!-- Вкладки -->
                <div class="tabs-container">
                    <div class="tabs-nav">
                        <button class="tab-btn <?= $activeTab === 'info' ? 'active' : '' ?>" onclick="switchTab('info')">
                            Информация о курсе
                        </button>
                        <button class="tab-btn <?= $activeTab === 'lessons' ? 'active' : '' ?>" onclick="switchTab('lessons')">
                            Уроки <span class="count">(<?= count($courseLessons) ?>)</span>
                        </button>
                        <button class="tab-btn <?= $activeTab === 'questions' ? 'active' : '' ?>" onclick="switchTab('questions')">
                            Тест <span class="count">(<?= count($courseQuestionsDetailed) ?>)</span>
                        </button>
                    </div>

                    <!-- Вкладка: Информация о курсе -->
                    <div id="tab-info" class="tab-content <?= $activeTab === 'info' ? 'active' : '' ?>">
                        <form method="POST" class="course-form">
                            <input type="hidden" name="action" value="update_course">
                            <input type="hidden" name="course_id" value="<?= $editCourse['id'] ?>">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="edit_title">Название курса *</label>
                                    <input type="text" id="edit_title" name="title" required 
                                           value="<?= htmlspecialchars($editCourse['title']) ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="edit_position">Для должности *</label>
                                    <select id="edit_position" name="position" required>
                                        <option value="">Выберите должность</option>
                                        <?php foreach ($positions as $pos): ?>
                                            <option value="<?= htmlspecialchars($pos) ?>" 
                                                    <?= $editCourse['position'] === $pos ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($pos) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="edit_description">Описание</label>
                                <textarea id="edit_description" name="description" rows="3"><?= htmlspecialchars($editCourse['description'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="edit_difficulty_level">Уровень сложности</label>
                                    <select id="edit_difficulty_level" name="difficulty_level">
                                        <option value="beginner" <?= $editCourse['difficulty_level'] === 'beginner' ? 'selected' : '' ?>>Начальный</option>
                                        <option value="intermediate" <?= $editCourse['difficulty_level'] === 'intermediate' ? 'selected' : '' ?>>Средний</option>
                                        <option value="advanced" <?= $editCourse['difficulty_level'] === 'advanced' ? 'selected' : '' ?>>Продвинутый</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="edit_passing_score">Проходной балл (%)</label>
                                    <input type="number" id="edit_passing_score" name="passing_score" min="1" max="100" 
                                           value="<?= $editCourse['passing_score'] ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="edit_duration_minutes">Длительность (мин)</label>
                                    <input type="number" id="edit_duration_minutes" name="duration_minutes" min="1" 
                                           value="<?= $editCourse['duration_minutes'] ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" name="is_active" <?= $editCourse['is_active'] ? 'checked' : '' ?>>
                                    Активный курс
                                </label>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-orange">Сохранить изменения</button>
                            </div>
                        </form>
                    </div>

                    <!-- Вкладка: Уроки -->
                    <div id="tab-lessons" class="tab-content <?= $activeTab === 'lessons' ? 'active' : '' ?>">
                        <!-- Форма добавления урока -->
                        <div class="sub-section">
                            <h3>Добавить новый урок</h3>
                            <form method="POST" class="lesson-form">
                                <input type="hidden" name="action" value="add_lesson">
                                <input type="hidden" name="course_id" value="<?= $editCourse['id'] ?>">
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="lesson_title">Название урока *</label>
                                        <input type="text" id="lesson_title" name="lesson_title" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="lesson_order">Порядок *</label>
                                        <input type="number" id="lesson_order" name="lesson_order" min="1" value="<?= count($courseLessons) + 1 ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="lesson_duration">Длительность (мин)</label>
                                        <input type="number" id="lesson_duration" name="lesson_duration" min="1" value="10">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="video_url">URL видео (необязательно)</label>
                                    <input type="url" id="video_url" name="video_url" placeholder="https://youtube.com/watch?v=...">
                                </div>
                                
                                <div class="form-group">
                                    <label for="lesson_content">Содержание урока *</label>
                                    <textarea id="lesson_content" name="lesson_content" rows="6" required 
                                              placeholder="Введите содержание урока..."></textarea>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-orange">Добавить урок</button>
                                </div>
                            </form>
                        </div>

                        <!-- Список уроков -->
                        <div class="sub-section">
                            <h3>Уроки курса</h3>
                            <?php if (empty($courseLessons)): ?>
                                <p class="text-muted">Уроки не добавлены</p>
                            <?php else: ?>
                                <div class="lessons-management">
                                    <?php foreach ($courseLessons as $lesson): ?>
                                        <div class="lesson-management-item" id="lesson-<?= $lesson['id'] ?>">
                                            <div class="lesson-display">
                                                <div class="lesson-header">
                                                    <span class="lesson-order">Урок <?= $lesson['lesson_order'] ?></span>
                                                    <h4><?= htmlspecialchars($lesson['title']) ?></h4>
                                                    <div class="lesson-meta">
                                                        <?= $lesson['duration_minutes'] ?> мин
                                                        <?= $lesson['video_url'] ? '• Видео' : '' ?>
                                                    </div>
                                                </div>
                                                <div class="lesson-content-preview">
                                                    <?= nl2br(htmlspecialchars(substr($lesson['content'] ?? '', 0, 200))) ?>
                                                    <?= strlen($lesson['content'] ?? '') > 200 ? '...' : '' ?>
                                                </div>
                                                <div class="lesson-actions">
                                                    <button onclick="editLesson(<?= $lesson['id'] ?>)" class="btn btn-sm btn-gray">Редактировать</button>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Удалить урок?')">
                                                        <input type="hidden" name="action" value="delete_lesson">
                                                        <input type="hidden" name="lesson_id" value="<?= $lesson['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-red">Удалить</button>
                                                    </form>
                                                </div>
                                            </div>

                                            <div class="lesson-edit" style="display: none;">
                                                <form method="POST">
                                                    <input type="hidden" name="action" value="update_lesson">
                                                    <input type="hidden" name="lesson_id" value="<?= $lesson['id'] ?>">
                                                    
                                                    <div class="form-row">
                                                        <div class="form-group">
                                                            <label>Название урока *</label>
                                                            <input type="text" name="lesson_title" required 
                                                                   value="<?= htmlspecialchars($lesson['title']) ?>">
                                                        </div>
                                                        
                                                        <div class="form-group">
                                                            <label>Порядок *</label>
                                                            <input type="number" name="lesson_order" min="1" 
                                                                   value="<?= $lesson['lesson_order'] ?>">
                                                        </div>
                                                        
                                                        <div class="form-group">
                                                            <label>Длительность (мин)</label>
                                                            <input type="number" name="lesson_duration" min="1" 
                                                                   value="<?= $lesson['duration_minutes'] ?>">
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <label>URL видео</label>
                                                        <input type="url" name="video_url" 
                                                               value="<?= htmlspecialchars($lesson['video_url'] ?? '') ?>">
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <label>Содержание урока *</label>
                                                        <textarea name="lesson_content" rows="6" required><?= htmlspecialchars($lesson['content'] ?? '') ?></textarea>
                                                    </div>
                                                    
                                                    <div class="form-actions">
                                                        <button type="submit" class="btn btn-orange">Сохранить</button>
                                                        <button type="button" onclick="cancelEditLesson(<?= $lesson['id'] ?>)" class="btn btn-gray">Отмена</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Вкладка: Тест -->
                    <div id="tab-questions" class="tab-content <?= $activeTab === 'questions' ? 'active' : '' ?>">
                        <!-- Форма добавления вопроса -->
                        <div class="sub-section">
                            <h3>Добавить новый вопрос</h3>
                            <form method="POST" class="question-form">
                                <input type="hidden" name="action" value="add_question">
                                <input type="hidden" name="course_id" value="<?= $editCourse['id'] ?>">
                                
                                <div class="form-group">
                                    <label for="question_text">Текст вопроса *</label>
                                    <textarea id="question_text" name="question_text" rows="3" required 
                                              placeholder="Введите текст вопроса..."></textarea>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="option_a">Вариант A *</label>
                                        <input type="text" id="option_a" name="option_a" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="option_b">Вариант B *</label>
                                        <input type="text" id="option_b" name="option_b" required>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="option_c">Вариант C *</label>
                                        <input type="text" id="option_c" name="option_c" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="option_d">Вариант D *</label>
                                        <input type="text" id="option_d" name="option_d" required>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="correct_answer">Правильный ответ *</label>
                                        <select id="correct_answer" name="correct_answer" required>
                                            <option value="">Выберите правильный ответ</option>
                                            <option value="A">A</option>
                                            <option value="B">B</option>
                                            <option value="C">C</option>
                                            <option value="D">D</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="points">Баллы за вопрос</label>
                                        <input type="number" id="points" name="points" min="1" value="1">
                                    </div>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-orange">Добавить вопрос</button>
                                </div>
                            </form>
                        </div>

                        <!-- Список вопросов -->
                        <div class="sub-section">
                            <h3>Вопросы теста</h3>
                            
                            <?php if (empty($courseQuestionsDetailed)): ?>
                                <p class="text-muted">Вопросы не добавлены</p>
                            <?php else: ?>
                                <div class="test-info">
                                    <p><strong>Всего вопросов:</strong> <?= count($courseQuestionsDetailed) ?></p>
                                    <p><strong>Максимум баллов:</strong> <?= array_sum(array_column($courseQuestionsDetailed, 'points')) ?></p>
                                    <p><strong>Проходной балл:</strong> <?= $editCourse['passing_score'] ?>%</p>
                                </div>

                                <div class="questions-management">
                                    <?php foreach ($courseQuestionsDetailed as $index => $question): ?>
                                        <div class="question-management-item" id="question-<?= $question['id'] ?>">
                                            <div class="question-display">
                                                <div class="question-header">
                                                    <span class="question-number">Вопрос <?= $index + 1 ?></span>
                                                    <span class="question-points"><?= $question['points'] ?> балл(а)</span>
                                                </div>
                                                
                                                <div class="question-text">
                                                    <strong><?= nl2br(htmlspecialchars($question['question'])) ?></strong>
                                                </div>
                                                
                                                <div class="question-options">
                                                    <div class="option <?= $question['correct_answer'] === 'A' ? 'correct' : '' ?>">
                                                        <strong>A.</strong> <?= htmlspecialchars($question['option_a']) ?>
                                                    </div>
                                                    <div class="option <?= $question['correct_answer'] === 'B' ? 'correct' : '' ?>">
                                                        <strong>B.</strong> <?= htmlspecialchars($question['option_b']) ?>
                                                    </div>
                                                    <div class="option <?= $question['correct_answer'] === 'C' ? 'correct' : '' ?>">
                                                        <strong>C.</strong> <?= htmlspecialchars($question['option_c']) ?>
                                                    </div>
                                                    <div class="option <?= $question['correct_answer'] === 'D' ? 'correct' : '' ?>">
                                                        <strong>D.</strong> <?= htmlspecialchars($question['option_d']) ?>
                                                    </div>
                                                </div>
                                                
                                                <div class="question-actions">
                                                    <button onclick="editQuestion(<?= $question['id'] ?>)" class="btn btn-sm btn-gray">Редактировать</button>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Удалить вопрос?')">
                                                        <input type="hidden" name="action" value="delete_question">
                                                        <input type="hidden" name="question_id" value="<?= $question['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-red">Удалить</button>
                                                    </form>
                                                </div>
                                            </div>

                                            <div class="question-edit" style="display: none;">
                                                <form method="POST">
                                                    <input type="hidden" name="action" value="update_question">
                                                    <input type="hidden" name="question_id" value="<?= $question['id'] ?>">
                                                    
                                                    <div class="form-group">
                                                        <label>Текст вопроса *</label>
                                                        <textarea name="question_text" rows="3" required><?= htmlspecialchars($question['question']) ?></textarea>
                                                    </div>
                                                    
                                                    <div class="form-row">
                                                        <div class="form-group">
                                                            <label>Вариант A *</label>
                                                            <input type="text" name="option_a" required 
                                                                   value="<?= htmlspecialchars($question['option_a']) ?>">
                                                        </div>
                                                        
                                                        <div class="form-group">
                                                            <label>Вариант B *</label>
                                                            <input type="text" name="option_b" required 
                                                                   value="<?= htmlspecialchars($question['option_b']) ?>">
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="form-row">
                                                        <div class="form-group">
                                                            <label>Вариант C *</label>
                                                            <input type="text" name="option_c" required 
                                                                   value="<?= htmlspecialchars($question['option_c']) ?>">
                                                        </div>
                                                        
                                                        <div class="form-group">
                                                            <label>Вариант D *</label>
                                                            <input type="text" name="option_d" required 
                                                                   value="<?= htmlspecialchars($question['option_d']) ?>">
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="form-row">
                                                        <div class="form-group">
                                                            <label>Правильный ответ *</label>
                                                            <select name="correct_answer" required>
                                                                <option value="A" <?= $question['correct_answer'] === 'A' ? 'selected' : '' ?>>A</option>
                                                                <option value="B" <?= $question['correct_answer'] === 'B' ? 'selected' : '' ?>>B</option>
                                                                <option value="C" <?= $question['correct_answer'] === 'C' ? 'selected' : '' ?>>C</option>
                                                                <option value="D" <?= $question['correct_answer'] === 'D' ? 'selected' : '' ?>>D</option>
                                                            </select>
                                                        </div>
                                                        
                                                        <div class="form-group">
                                                            <label>Баллы за вопрос</label>
                                                            <input type="number" name="points" min="1" 
                                                                   value="<?= $question['points'] ?>">
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="form-actions">
                                                        <button type="submit" class="btn btn-orange">Сохранить</button>
                                                        <button type="button" onclick="cancelEditQuestion(<?= $question['id'] ?>)" class="btn btn-gray">Отмена</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="../js/course-management.js"></script>

    <style>
        .management-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }
        
        .header-actions {
            display: flex;
            gap: 10px;
        }
        
        .course-edit-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 16px;
        }
        
        /* Вкладки */
        .tabs-container {
            width: 100%;
        }
        
        .tabs-nav {
            display: flex;
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .tab-btn {
            background: none;
            border: none;
            padding: 12px 20px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .tab-btn:hover {
            color: #f26822;
            background: #fff8f6;
        }
        
        .tab-btn.active {
            color: #f26822;
            border-bottom-color: #f26822;
            background: #fff8f6;
        }
        
        .tab-btn .count {
            background: #e9ecef;
            color: #666;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .tab-btn.active .count {
            background: #f26822;
            color: #fff;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Подсекции */
        .sub-section {
            margin-bottom: 32px;
            padding-bottom: 24px;
            border-bottom: 1px solid #eee;
        }
        
        .sub-section:last-child {
            border-bottom: none;
        }
        
        .sub-section h3 {
            margin: 0 0 16px 0;
            color: #333;
            font-size: 18px;
            font-weight: 600;
        }
        
        /* Управление уроками */
        .lessons-management {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        .lesson-management-item {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .lesson-display {
            padding: 16px;
            background: #f8f9fa;
        }
        
        .lesson-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
            flex-wrap: wrap;
        }
        
        .lesson-order {
            background: #e9ecef;
            color: #666;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .lesson-header h4 {
            margin: 0;
            color: #333;
            font-size: 16px;
            font-weight: 600;
            flex: 1;
        }
        
        .lesson-meta {
            color: #666;
            font-size: 14px;
        }
        
        .lesson-content-preview {
            color: #555;
            line-height: 1.5;
            margin: 12px 0;
        }
        
        .lesson-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }
        
        .lesson-edit {
            padding: 20px;
            background: #fff;
            border-top: 1px solid #e9ecef;
        }
        
        /* Управление вопросами */
        .test-info {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
        }
        
        .test-info p {
            margin: 4px 0;
            color: #555;
        }
        
        .questions-management {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        .question-management-item {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .question-display {
            padding: 16px;
            background: #f8f9fa;
        }
        
        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .question-number {
            background: #e9ecef;
            color: #666;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .question-points {
            color: #f26822;
            font-weight: 600;
            font-size: 14px;
        }
        
        .question-text {
            margin-bottom: 12px;
            color: #333;
            line-height: 1.4;
        }
        
        .question-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 8px;
            margin-bottom: 12px;
        }
        
        .option {
            padding: 8px;
            background: #fff;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            font-size: 14px;
            line-height: 1.3;
        }
        
        .option.correct {
            background: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        
        .question-actions {
            display: flex;
            gap: 8px;
        }
        
        .question-edit {
            padding: 20px;
            background: #fff;
            border-top: 1px solid #e9ecef;
        }
        
        /* Формы */
        .course-form, .lesson-form, .question-form {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            margin-bottom: 4px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
        }
        
        .form-group textarea {
            resize: vertical;
            font-family: inherit;
        }
        
        .form-actions {
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
        
        .text-success {
            color: #28a745;
        }
        
        /* Адаптивность */
        @media (max-width: 768px) {
            .management-header,
            .course-edit-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                justify-content: stretch;
            }
            
            .form-actions .btn {
                flex: 1;
            }
            
            .question-options {
                grid-template-columns: 1fr;
            }
            
            .lesson-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</body>
</html>