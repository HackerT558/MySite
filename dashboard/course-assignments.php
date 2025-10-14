<?php
// dashboard/course-assignments.php
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

// Проверяем права доступа
if ($currentLevel < role_level('manager-top')) {
    header('Location: courses.php');
    exit;
}

// Обработка назначений
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'assign_course':
            $courseId = (int)($_POST['course_id'] ?? 0);
            $userIds = $_POST['user_ids'] ?? [];
            $deadline = $_POST['deadline'] ?? null;
            
            if ($courseId && !empty($userIds)) {
                $successCount = 0;
                $errorCount = 0;
                
                foreach ($userIds as $targetUserId) {
                    if (assignCourseToUser($mysqli, (int)$targetUserId, $courseId, $userId, $deadline)) {
                        $successCount++;
                    } else {
                        $errorCount++;
                    }
                }
                
                if ($successCount > 0) {
                    $message = "Курс назначен пользователям: $successCount";
                    if ($errorCount > 0) {
                        $message .= " (ошибок: $errorCount)";
                    }
                    $messageType = 'success';
                } else {
                    $message = "Ошибка при назначении курса";
                    $messageType = 'error';
                }
            } else {
                $message = "Выберите курс и пользователей";
                $messageType = 'error';
            }
            break;
            
        case 'bulk_assign':
            $courseId = (int)($_POST['course_id'] ?? 0);
            $position = $_POST['position'] ?? '';
            $deadline = $_POST['deadline'] ?? null;
            
            if ($courseId && $position) {
                // Получаем всех пользователей с указанной позицией
                $stmt = $mysqli->prepare("
                    SELECT id FROM users 
                    WHERE position = ? AND id NOT IN (
                        SELECT user_id FROM user_course_assignments WHERE course_id = ?
                    )
                ");
                $stmt->bind_param('si', $position, $courseId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $successCount = 0;
                while ($row = $result->fetch_assoc()) {
                    if (assignCourseToUser($mysqli, $row['id'], $courseId, $userId, $deadline)) {
                        $successCount++;
                    }
                }
                $stmt->close();
                
                if ($successCount > 0) {
                    $message = "Курс назначен $successCount пользователям с позицией '$position'";
                    $messageType = 'success';
                } else {
                    $message = "Не найдено пользователей для назначения или все уже назначены";
                    $messageType = 'error';
                }
            }
            break;
            
        case 'remove_assignment':
            $assignmentId = (int)($_POST['assignment_id'] ?? 0);
            if ($assignmentId) {
                $stmt = $mysqli->prepare("DELETE FROM user_course_assignments WHERE id = ?");
                $stmt->bind_param('i', $assignmentId);
                
                if ($stmt->execute()) {
                    $message = "Назначение удалено";
                    $messageType = 'success';
                } else {
                    $message = "Ошибка при удалении назначения";
                    $messageType = 'error';
                }
                $stmt->close();
            }
            break;
    }
}

// Получаем данные
$allCourses = getAllCourses($mysqli);
$activeCourses = array_filter($allCourses, function($course) {
    return $course['is_active'] == 1;
});

// Получаем всех пользователей
$allUsers = getUsersForAssignment($mysqli);

// Группируем пользователей по позициям
$usersByPosition = [];
foreach ($allUsers as $user) {
    $pos = $user['position'] ?? 'Без позиции';
    if (!isset($usersByPosition[$pos])) {
        $usersByPosition[$pos] = [];
    }
    $usersByPosition[$pos][] = $user;
}

// Получаем текущие назначения
$stmt = $mysqli->prepare("
    SELECT ua.id, ua.status, ua.assigned_at, ua.deadline,
           u.username, u.position, CONCAT_WS(' ', u.last_name, u.first_name, u.middle_name) as full_name,
           c.title as course_title, c.id as course_id,
           (SELECT COUNT(*) FROM course_lessons WHERE course_id = c.id) as total_lessons,
           (SELECT COUNT(*) FROM user_lesson_progress ulp 
            JOIN course_lessons cl ON ulp.lesson_id = cl.id 
            WHERE ulp.user_id = ua.user_id AND cl.course_id = c.id) as completed_lessons,
           ctr.percentage as test_score, ctr.passed as test_passed
    FROM user_course_assignments ua
    JOIN users u ON ua.user_id = u.id
    JOIN courses c ON ua.course_id = c.id
    LEFT JOIN (
        SELECT user_id, course_id, MAX(percentage) as percentage, MAX(passed) as passed
        FROM course_test_results
        GROUP BY user_id, course_id
    ) ctr ON ua.user_id = ctr.user_id AND ua.course_id = ctr.course_id
    ORDER BY ua.assigned_at DESC
    LIMIT 50
");
$stmt->execute();
$currentAssignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Назначение курсов</title>
    <link rel="stylesheet" href="../css/app-base.css">
    <link rel="stylesheet" href="../css/cabinet-header.css">
    <link rel="stylesheet" href="../css/courses.css">
    <link rel="stylesheet" href="../css/app-admin.css">
</head>
<body>
    <?php require __DIR__ . '/../config/header-cabinet.inc.php'; ?>
    
    <div class="container">
        <div class="admin-wrap">
            <div class="assignments-header">
                <h1>Назначение курсов</h1>
                <div class="header-actions">
                    <a href="courses.php" class="btn btn-gray">← К курсам</a>
                    <a href="course-management.php" class="btn btn-orange">Управление курсами</a>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="<?= $messageType === 'success' ? 'notice' : 'error' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- Назначение курса -->
            <div class="card">
                <h2>Назначить курс пользователям</h2>
                
                <form method="POST" id="assignmentForm">
                    <input type="hidden" name="action" value="assign_course">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="course_id">Выберите курс *</label>
                            <select id="course_id" name="course_id" required onchange="updateCourseInfo()">
                                <option value="">-- Выберите курс --</option>
                                <?php foreach ($activeCourses as $course): ?>
                                    <option value="<?= $course['id'] ?>" 
                                            data-position="<?= htmlspecialchars($course['position']) ?>"
                                            data-description="<?= htmlspecialchars($course['description']) ?>">
                                        <?= htmlspecialchars($course['title']) ?> (<?= htmlspecialchars($course['position']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="deadline">Срок выполнения</label>
                            <input type="date" id="deadline" name="deadline" 
                                   min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                        </div>
                    </div>
                    
                    <div id="courseInfo" class="course-info" style="display: none;">
                        <h4>Информация о курсе</h4>
                        <div id="courseDescription"></div>
                        <div id="recommendedUsers" class="recommended-users"></div>
                    </div>
                    
                    <div class="form-group">
                        <label>Выберите пользователей *</label>
                        <div class="users-selection">
                            <?php foreach ($usersByPosition as $position => $users): ?>
                                <div class="position-group">
                                    <div class="position-header">
                                        <label class="position-title">
                                            <input type="checkbox" class="position-checkbox" 
                                                   data-position="<?= htmlspecialchars($position) ?>">
                                            <?= htmlspecialchars($position) ?> (<?= count($users) ?> чел.)
                                        </label>
                                    </div>
                                    
                                    <div class="users-list">
                                        <?php foreach ($users as $user): ?>
                                            <label class="user-checkbox">
                                                <input type="checkbox" name="user_ids[]" value="<?= $user['id'] ?>"
                                                       data-position="<?= htmlspecialchars($user['position']) ?>">
                                                <span class="user-info">
                                                    <strong><?= htmlspecialchars($user['full_name'] ?: $user['username']) ?></strong>
                                                    <small><?= htmlspecialchars($user['username']) ?> • <?= htmlspecialchars($user['role']) ?></small>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-orange">Назначить курс</button>
                        <button type="button" class="btn btn-gray" onclick="clearSelection()">Очистить выбор</button>
                    </div>
                </form>
            </div>

            <!-- Массовое назначение -->
            <div class="card">
                <h2>Массовое назначение по должности</h2>
                
                <form method="POST">
                    <input type="hidden" name="action" value="bulk_assign">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="bulk_course_id">Курс</label>
                            <select id="bulk_course_id" name="course_id" required>
                                <option value="">-- Выберите курс --</option>
                                <?php foreach ($activeCourses as $course): ?>
                                    <option value="<?= $course['id'] ?>">
                                        <?= htmlspecialchars($course['title']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="bulk_position">Должность</label>
                            <select id="bulk_position" name="position" required>
                                <option value="">-- Выберите должность --</option>
                                <?php foreach ($usersByPosition as $position => $users): ?>
                                    <option value="<?= htmlspecialchars($position) ?>">
                                        <?= htmlspecialchars($position) ?> (<?= count($users) ?> чел.)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="bulk_deadline">Срок выполнения</label>
                            <input type="date" id="bulk_deadline" name="deadline" 
                                   min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-orange" onclick="return confirm('Назначить курс всем сотрудникам выбранной должности?')">
                                Назначить всем
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Текущие назначения -->
            <div class="card">
                <h2>Текущие назначения</h2>
                
                <div class="assignments-filters">
                    <input type="text" id="assignmentSearch" placeholder="Поиск по имени или курсу..." 
                           onkeyup="filterAssignments()">
                    <select id="statusFilter" onchange="filterAssignments()">
                        <option value="">Все статусы</option>
                        <option value="assigned">Назначен</option>
                        <option value="in_progress">В процессе</option>
                        <option value="completed">Завершен</option>
                        <option value="failed">Не пройден</option>
                    </select>
                </div>
                
                <div class="table-container">
                    <table id="assignmentsTable">
                        <thead>
                            <tr>
                                <th>Пользователь</th>
                                <th>Курс</th>
                                <th>Статус</th>
                                <th>Прогресс</th>
                                <th>Назначен</th>
                                <th>Срок</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($currentAssignments as $assignment): ?>
                                <tr data-status="<?= $assignment['status'] ?>">
                                    <td>
                                        <div class="user-cell">
                                            <strong><?= htmlspecialchars($assignment['full_name'] ?: $assignment['username']) ?></strong>
                                            <small><?= htmlspecialchars($assignment['username']) ?> • <?= htmlspecialchars($assignment['position']) ?></small>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($assignment['course_title']) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= $assignment['status'] ?>">
                                            <?php
                                            $statusText = [
                                                'assigned' => 'Назначен',
                                                'in_progress' => 'В процессе',
                                                'completed' => 'Завершен',
                                                'failed' => 'Не пройден'
                                            ];
                                            echo $statusText[$assignment['status']] ?? $assignment['status'];
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="progress-cell">
                                            <div class="lesson-progress">
                                                Уроки: <?= $assignment['completed_lessons'] ?>/<?= $assignment['total_lessons'] ?>
                                            </div>
                                            <?php if ($assignment['test_score'] > 0): ?>
                                                <div class="test-progress <?= $assignment['test_passed'] ? 'passed' : 'failed' ?>">
                                                    Тест: <?= number_format($assignment['test_score'], 1) ?>%
                                                    <?= $assignment['test_passed'] ? '✓' : '✗' ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?= date('d.m.Y', strtotime($assignment['assigned_at'])) ?></td>
                                    <td>
                                        <?php if ($assignment['deadline']): ?>
                                            <?php
                                            $deadline = strtotime($assignment['deadline']);
                                            $now = time();
                                            $isOverdue = $deadline < $now;
                                            ?>
                                            <span class="deadline <?= $isOverdue ? 'overdue' : '' ?>">
                                                <?= date('d.m.Y', $deadline) ?>
                                                <?= $isOverdue ? ' (просрочен)' : '' ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="no-deadline">Без срока</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline;" 
                                              onsubmit="return confirm('Удалить назначение?')">
                                            <input type="hidden" name="action" value="remove_assignment">
                                            <input type="hidden" name="assignment_id" value="<?= $assignment['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-red">Удалить</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updateCourseInfo() {
            const select = document.getElementById('course_id');
            const info = document.getElementById('courseInfo');
            const description = document.getElementById('courseDescription');
            const recommended = document.getElementById('recommendedUsers');
            
            if (select.value) {
                const option = select.selectedOptions[0];
                const position = option.dataset.position;
                const desc = option.dataset.description;
                
                description.innerHTML = `<p><strong>Описание:</strong> ${desc}</p>`;
                recommended.innerHTML = `<p><strong>Рекомендуется для:</strong> ${position}</p>`;
                info.style.display = 'block';
                
                // Выделяем соответствующую позицию
                highlightRecommendedPosition(position);
            } else {
                info.style.display = 'none';
                clearHighlights();
            }
        }
        
        function highlightRecommendedPosition(position) {
            clearHighlights();
            const positionGroups = document.querySelectorAll('.position-group');
            positionGroups.forEach(group => {
                const checkbox = group.querySelector('.position-checkbox');
                if (checkbox.dataset.position === position) {
                    group.classList.add('recommended');
                }
            });
        }
        
        function clearHighlights() {
            document.querySelectorAll('.position-group').forEach(group => {
                group.classList.remove('recommended');
            });
        }
        
        // Обработка чекбоксов по позициям
        document.querySelectorAll('.position-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const position = this.dataset.position;
                const userCheckboxes = document.querySelectorAll(`input[name="user_ids[]"][data-position="${position}"]`);
                
                userCheckboxes.forEach(userCheckbox => {
                    userCheckbox.checked = this.checked;
                });
            });
        });
        
        // Обновление состояния чекбоксов позиций при изменении пользователей
        document.querySelectorAll('input[name="user_ids[]"]').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const position = this.dataset.position;
                const positionCheckbox = document.querySelector(`.position-checkbox[data-position="${position}"]`);
                const userCheckboxes = document.querySelectorAll(`input[name="user_ids[]"][data-position="${position}"]`);
                const checkedUsers = document.querySelectorAll(`input[name="user_ids[]"][data-position="${position}"]:checked`);
                
                if (checkedUsers.length === 0) {
                    positionCheckbox.indeterminate = false;
                    positionCheckbox.checked = false;
                } else if (checkedUsers.length === userCheckboxes.length) {
                    positionCheckbox.indeterminate = false;
                    positionCheckbox.checked = true;
                } else {
                    positionCheckbox.indeterminate = true;
                }
            });
        });
        
        function clearSelection() {
            document.querySelectorAll('input[name="user_ids[]"]').forEach(checkbox => {
                checkbox.checked = false;
            });
            document.querySelectorAll('.position-checkbox').forEach(checkbox => {
                checkbox.checked = false;
                checkbox.indeterminate = false;
            });
        }
        
        function filterAssignments() {
            const searchTerm = document.getElementById('assignmentSearch').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const rows = document.querySelectorAll('#assignmentsTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const status = row.dataset.status;
                
                const matchesSearch = text.includes(searchTerm);
                const matchesStatus = !statusFilter || status === statusFilter;
                
                row.style.display = matchesSearch && matchesStatus ? '' : 'none';
            });
        }
        
        // Автоматическое скрытие сообщений
        setTimeout(function() {
            const notices = document.querySelectorAll('.notice, .error');
            notices.forEach(notice => {
                notice.style.opacity = '0';
                setTimeout(() => notice.remove(), 300);
            });
        }, 5000);
    </script>

    <style>
        .assignments-header {
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
        .form-group select {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .course-info {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 16px;
            margin: 16px 0;
        }
        
        .users-selection {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 12px;
        }
        
        .position-group {
            margin-bottom: 16px;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            overflow: hidden;
        }
        
        .position-group.recommended {
            border-color: #f26822;
            background: #fff8f6;
        }
        
        .position-header {
            background: #f8f9fa;
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .position-title {
            font-weight: 600;
            color: #333;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .users-list {
            padding: 12px;
        }
        
        .user-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px;
            margin-bottom: 4px;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .user-checkbox:hover {
            background: #f8f9fa;
        }
        
        .user-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        
        .user-info small {
            color: #666;
            font-size: 12px;
        }
        
        .assignments-filters {
            display: flex;
            gap: 12px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        
        .assignments-filters input,
        .assignments-filters select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        .user-cell {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        
        .user-cell small {
            color: #666;
            font-size: 12px;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-assigned {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .status-in_progress {
            background: #fff3e0;
            color: #f57c00;
        }
        
        .status-completed {
            background: #e8f5e8;
            color: #2e7d32;
        }
        
        .status-failed {
            background: #ffebee;
            color: #c62828;
        }
        
        .progress-cell {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .lesson-progress {
            font-size: 14px;
            color: #666;
        }
        
        .test-progress {
            font-size: 12px;
            font-weight: 600;
        }
        
        .test-progress.passed {
            color: #2e7d32;
        }
        
        .test-progress.failed {
            color: #c62828;
        }
        
        .deadline.overdue {
            color: #c62828;
            font-weight: 600;
        }
        
        .no-deadline {
            color: #666;
            font-style: italic;
        }
        
        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            padding-top: 16px;
            border-top: 1px solid #eee;
        }
        
        @media (max-width: 768px) {
            .assignments-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .assignments-filters {
                flex-direction: column;
            }
            
            .form-actions {
                justify-content: stretch;
            }
            
            .form-actions .btn {
                flex: 1;
            }
        }
    </style>
</body>
</html>