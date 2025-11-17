<?php
// config/courses-functions.php
require_once __DIR__ . '/config.php';

function getCoursesByPosition(mysqli $db, string $position): array {
    $stmt = $db->prepare("
        SELECT id, title, description, difficulty_level, duration_minutes, passing_score 
        FROM courses 
        WHERE position = ? AND is_active = 1 
        ORDER BY difficulty_level, title
    ");
    $stmt->bind_param('s', $position);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $courses = [];
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
    $stmt->close();
    return $courses;
}

function getUserAssignedCourses(mysqli $db, int $userId): array {
    $stmt = $db->prepare("
        SELECT c.id, c.title, c.description, c.duration_minutes, c.passing_score, c.position,
               ua.status, ua.assigned_at, ua.deadline,
               COALESCE(lesson_stats.lessons_completed, 0) as lessons_completed,
               COALESCE(lesson_stats.total_lessons, 0) as total_lessons,
               COALESCE(test_stats.best_score, 0) as best_test_score,
               COALESCE(test_stats.passed, 0) as test_passed
        FROM user_course_assignments ua
        JOIN courses c ON ua.course_id = c.id
        LEFT JOIN (
            SELECT 
                cl.course_id,
                COUNT(cl.id) as total_lessons,
                COUNT(ulp.lesson_id) as lessons_completed
            FROM course_lessons cl
            LEFT JOIN user_lesson_progress ulp ON cl.id = ulp.lesson_id AND ulp.user_id = ?
            GROUP BY cl.course_id
        ) lesson_stats ON c.id = lesson_stats.course_id
        LEFT JOIN (
            SELECT course_id, MAX(percentage) as best_score, MAX(passed) as passed
            FROM course_test_results
            WHERE user_id = ?
            GROUP BY course_id
        ) test_stats ON c.id = test_stats.course_id
        WHERE ua.user_id = ? AND c.is_active = 1
        ORDER BY ua.assigned_at DESC
    ");
    $stmt->bind_param('iii', $userId, $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $courses = [];
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
    $stmt->close();
    return $courses;
}

function getCourseDetails(mysqli $db, int $courseId): ?array {
    $stmt = $db->prepare("
        SELECT id, title, description, position, difficulty_level, 
               duration_minutes, passing_score, is_active
        FROM courses 
        WHERE id = ?
    ");
    $stmt->bind_param('i', $courseId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        return $row;
    }
    $stmt->close();
    return null;
}

function getCourseLessons(mysqli $db, int $courseId, int $userId = null): array {
    $sql = "
        SELECT cl.id, cl.title, cl.lesson_order, cl.duration_minutes, cl.video_url,
               " . ($userId ? "ulp.completed_at" : "NULL as completed_at") . "
        FROM course_lessons cl
    ";
    
    if ($userId) {
        $sql .= " LEFT JOIN user_lesson_progress ulp ON cl.id = ulp.lesson_id AND ulp.user_id = ?";
    }
    
    $sql .= " WHERE cl.course_id = ? ORDER BY cl.lesson_order";
    
    $stmt = $db->prepare($sql);
    if ($userId) {
        $stmt->bind_param('ii', $userId, $courseId);
    } else {
        $stmt->bind_param('i', $courseId);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $lessons = [];
    while ($row = $result->fetch_assoc()) {
        $row['completed'] = !is_null($row['completed_at']);
        $lessons[] = $row;
    }
    $stmt->close();
    return $lessons;
}

function getLessonContent(mysqli $db, int $lessonId): ?array {
    $stmt = $db->prepare("
        SELECT cl.id, cl.title, cl.content, cl.lesson_order, cl.video_url, 
               cl.duration_minutes, c.title as course_title, c.id as course_id
        FROM course_lessons cl
        JOIN courses c ON cl.course_id = c.id
        WHERE cl.id = ?
    ");
    $stmt->bind_param('i', $lessonId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        return $row;
    }
    $stmt->close();
    return null;
}

function completeLesson(mysqli $db, int $userId, int $courseId, int $lessonId, int $timeSpent = 0): bool {
    $stmt = $db->prepare("
        INSERT INTO user_lesson_progress (user_id, course_id, lesson_id, time_spent_minutes)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            time_spent_minutes = time_spent_minutes + VALUES(time_spent_minutes),
            completed_at = CURRENT_TIMESTAMP
    ");
    $stmt->bind_param('iiii', $userId, $courseId, $lessonId, $timeSpent);
    $success = $stmt->execute();
    $stmt->close();
    
    // Обновить статус назначения курса на "в процессе"
    if ($success) {
        updateCourseAssignmentStatus($db, $userId, $courseId, 'in_progress');
    }
    
    return $success;
}

function getCourseTestQuestions(mysqli $db, int $courseId): array {
    $stmt = $db->prepare("
        SELECT id, question, option_a, option_b, option_c, option_d, points
        FROM course_questions
        WHERE course_id = ?
        ORDER BY RAND()
    ");
    $stmt->bind_param('i', $courseId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $questions = [];
    while ($row = $result->fetch_assoc()) {
        $questions[] = $row;
    }
    $stmt->close();
    return $questions;
}

function getCourseTestAnswers(mysqli $db, int $courseId): array {
    $stmt = $db->prepare("
        SELECT id, correct_answer, points
        FROM course_questions
        WHERE course_id = ?
    ");
    $stmt->bind_param('i', $courseId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $answers = [];
    while ($row = $result->fetch_assoc()) {
        $answers[$row['id']] = [
            'correct_answer' => $row['correct_answer'],
            'points' => $row['points']
        ];
    }
    $stmt->close();
    return $answers;
}

function saveTestResults(mysqli $db, int $userId, int $courseId, array $answers, int $score, int $maxScore): bool {
    $percentage = $maxScore > 0 ? ($score / $maxScore) * 100 : 0;
    
    // Получить проходной балл курса
    $stmt = $db->prepare("SELECT passing_score FROM courses WHERE id = ?");
    $stmt->bind_param('i', $courseId);
    $stmt->execute();
    $stmt->bind_result($passingScore);
    $stmt->fetch();
    $stmt->close();
    
    $passed = $percentage >= $passingScore;
    $answersJson = json_encode($answers);
    
    $stmt = $db->prepare("
        INSERT INTO course_test_results 
        (user_id, course_id, score, max_score, percentage, passed, answers)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('iiiddis', $userId, $courseId, $score, $maxScore, $percentage, $passed, $answersJson);
    $success = $stmt->execute();
    $stmt->close();
    
    // Обновить статус курса
    if ($success) {
        $newStatus = $passed ? 'completed' : 'failed';
        updateCourseAssignmentStatus($db, $userId, $courseId, $newStatus);
    }
    
    return $success;
}

function updateCourseAssignmentStatus(mysqli $db, int $userId, int $courseId, string $status): bool {
    $stmt = $db->prepare("
        UPDATE user_course_assignments 
        SET status = ? 
        WHERE user_id = ? AND course_id = ?
    ");
    $stmt->bind_param('sii', $status, $userId, $courseId);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

function assignCourseToUser(mysqli $db, int $userId, int $courseId, int $assignedBy, ?string $deadline = null): bool {
    $stmt = $db->prepare("
        INSERT INTO user_course_assignments (user_id, course_id, assigned_by, deadline)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE assigned_by = VALUES(assigned_by), deadline = VALUES(deadline)
    ");
    $stmt->bind_param('iiis', $userId, $courseId, $assignedBy, $deadline);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

function isTestAvailableForUser(mysqli $db, int $userId, int $courseId): bool {
    // Проверяем, что все уроки пройдены
    $stmt = $db->prepare("
        SELECT 
            COUNT(cl.id) as total_lessons,
            COUNT(ulp.lesson_id) as completed_lessons
        FROM course_lessons cl
        LEFT JOIN user_lesson_progress ulp ON cl.id = ulp.lesson_id AND ulp.user_id = ?
        WHERE cl.course_id = ?
    ");
    $stmt->bind_param('ii', $userId, $courseId);
    $stmt->execute();
    $stmt->bind_result($totalLessons, $completedLessons);
    $stmt->fetch();
    $stmt->close();
    
    return $totalLessons > 0 && $totalLessons == $completedLessons;
}

function getCoursesStatistics(mysqli $db): array {
    $stmt = $db->prepare("
        SELECT 
            c.id,
            c.title,
            c.position,
            COUNT(DISTINCT ua.user_id) as assigned_users,
            COUNT(CASE WHEN ua.status = 'completed' THEN 1 END) as completed_users,
            COUNT(CASE WHEN ua.status = 'in_progress' THEN 1 END) as in_progress_users,
            COUNT(CASE WHEN ua.status = 'failed' THEN 1 END) as failed_users,
            COALESCE(AVG(ctr.percentage), 0) as avg_test_score
        FROM courses c
        LEFT JOIN user_course_assignments ua ON c.id = ua.course_id
        LEFT JOIN course_test_results ctr ON c.id = ctr.course_id
        WHERE c.is_active = 1
        GROUP BY c.id, c.title, c.position
        ORDER BY c.title
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $statistics = [];
    while ($row = $result->fetch_assoc()) {
        $statistics[] = $row;
    }
    $stmt->close();
    return $statistics;
}

function getUsersForAssignment(mysqli $db): array {
    $stmt = $db->prepare("
        SELECT id, username, role, position,
               CONCAT_WS(' ', last_name, first_name, middle_name) as full_name
        FROM users
        ORDER BY role, username
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $stmt->close();
    return $users;
}

function isUserAssignedToCourse(mysqli $db, int $userId, int $courseId): bool {
    $stmt = $db->prepare("
        SELECT id FROM user_course_assignments 
        WHERE user_id = ? AND course_id = ?
    ");
    $stmt->bind_param('ii', $userId, $courseId);
    $stmt->execute();
    $result = $stmt->get_result();
    $assigned = $result->num_rows > 0;
    $stmt->close();
    return $assigned;
}

function getAllCourses(mysqli $db): array {
    $stmt = $db->prepare("
        SELECT id, title, description, position, difficulty_level, 
               passing_score, duration_minutes, is_active, created_at
        FROM courses
        ORDER BY title
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $courses = [];
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
    $stmt->close();
    return $courses;
}
?>