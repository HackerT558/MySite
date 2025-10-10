<?php
session_start();

$mysqli = new mysqli('localhost', 'root', '', 'site1');
if ($mysqli->connect_errno) {
  die('DB connection error');
}

if (!function_exists('role_level')) {
  function role_level(string $r): int {
    static $map = [
      'user' => 1,
      'manager' => 2,
      'manager-top' => 3,
      'manager-general' => 4,
      'admin' => 5,
    ];
    return $map[$r] ?? 0;
  }
}

/**
 * Обновляет роль и имя пользователя в сессии на основе текущих данных БД.
 * Возвращает актуальную роль (или 'user', если пользователь не найден).
 */
if (!function_exists('refresh_session_role')) {
  function refresh_session_role(mysqli $db): string {
    if (empty($_SESSION['uid'])) return 'user';
    $uid = (int)$_SESSION['uid'];
    $stmt = $db->prepare('SELECT role, username FROM users WHERE id=? LIMIT 1');
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $stmt->bind_result($dbRole, $dbUsername);
    if ($stmt->fetch()) {
      $_SESSION['role'] = $dbRole;
      if ($dbUsername) { $_SESSION['username'] = $dbUsername; }
      $stmt->close();
      return $dbRole;
    }
    $stmt->close();
    // Пользователь удалён — инвалидируем сессию
    session_unset();
    session_destroy();
    return 'user';
  }
}

/**
 * Требует минимальную роль для доступа, перепроверяя её из БД на каждом запросе.
 * При недостатке прав — редирект на login.php.
 */
if (!function_exists('require_role_min_db')) {
    function require_role_min_db(mysqli $db, string $minRole): void {
        $role = refresh_session_role($db);
        if (empty($_SESSION['uid']) || role_level($role) < role_level($minRole)) {
            header('Location: ../auth/login.php');
            exit;
        }
    }
}

