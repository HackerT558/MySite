<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['score']) || !isset($data['survival_time'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

$score = (int)$data['score'];
$survival_time = (int)$data['survival_time'];
$user_id = $_SESSION['uid'] ?? null;
$username = $_SESSION['username'] ?? 'Игрок';

try {
    // Для авторизованных пользователей - обновляем существующую запись
    if ($user_id) {
        // Проверяем существующий рекорд пользователя
        $checkStmt = $mysqli->prepare("SELECT score FROM pizza_game_leaderboard WHERE user_id = ?");
        $checkStmt->bind_param('i', $user_id);
        $checkStmt->execute();
        $checkStmt->bind_result($existing_score);
        $checkStmt->fetch();
        $checkStmt->close();
        
        if ($existing_score === null) {
            // Первый результат пользователя - вставляем новую запись
            $stmt = $mysqli->prepare("INSERT INTO pizza_game_leaderboard (user_id, username, score, survival_time) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('isii', $user_id, $username, $score, $survival_time);
        } else if ($score > $existing_score) {
            // Новый результат лучше существующего - обновляем запись
            $stmt = $mysqli->prepare("UPDATE pizza_game_leaderboard SET score = ?, survival_time = ?, created_at = NOW() WHERE user_id = ?");
            $stmt->bind_param('iii', $score, $survival_time, $user_id);
        } else {
            // Новый результат не лучше существующего - ничего не делаем
            echo json_encode(['success' => true, 'message' => 'Score not better than existing record']);
            exit;
        }
    } else {
        // Для гостей - всегда вставляем новую запись
        $stmt = $mysqli->prepare("INSERT INTO pizza_game_leaderboard (user_id, username, score, survival_time) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('isii', $user_id, $username, $score, $survival_time);
    }
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>