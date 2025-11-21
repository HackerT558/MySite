<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

try {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    
    // Запрос для получения топ-10 уникальных пользователей с лучшими результатами
    $stmt = $mysqli->prepare("
        SELECT 
            COALESCE(u.username, l.username) as display_username,
            l.score, 
            l.survival_time,
            l.created_at 
        FROM pizza_game_leaderboard l
        LEFT JOIN users u ON l.user_id = u.id
        WHERE l.id IN (
            SELECT MAX(id) 
            FROM pizza_game_leaderboard 
            GROUP BY COALESCE(user_id, username)
        )
        ORDER BY l.score DESC, l.survival_time DESC 
        LIMIT ?
    ");
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $leaderboard = [];
    while ($row = $result->fetch_assoc()) {
        // Форматируем время в минуты и секунды
        $minutes = floor($row['survival_time'] / 60);
        $seconds = $row['survival_time'] % 60;
        $formattedTime = sprintf("%02d:%02d", $minutes, $seconds);
        
        $leaderboard[] = [
            'username' => $row['display_username'],
            'score' => (int)$row['score'],
            'survival_time' => $formattedTime,
            'date' => date('d.m.Y H:i', strtotime($row['created_at']))
        ];
    }
    
    echo json_encode(['success' => true, 'data' => $leaderboard]);
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>