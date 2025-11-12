<?php
require __DIR__ . '/../config/config.php';

// Уже авторизован — направим по уровню
if (!empty($_SESSION['uid'])) {
  $lvl = role_level($_SESSION['role'] ?? 'user');
  if ($lvl >= role_level('manager-top')) {
    header('Location: ../dashboard/admin-dashboard.php');
  } else {
    header('Location: ../dashboard/user-dashboard.php');
  }
  exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['login'] ?? '');
  $password = $_POST['pass'] ?? '';

  if ($username === '' || $password === '') {
    $error = 'Заполните логин и пароль';
  } else {
    $stmt = $mysqli->prepare('SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
      $stmt->bind_result($uid, $uname, $pwd, $urole);
      $stmt->fetch();
      if ($password === $pwd) {
        session_regenerate_id(true);
        $_SESSION['uid'] = $uid;
        $_SESSION['username'] = $uname;
        $_SESSION['role'] = $urole;

        $lvl = role_level($urole);
        if ($lvl >= role_level('manager-top')) {
          header('Location: ../dashboard/admin-dashboard.php');
        } else {
          header('Location: ../dashboard/user-dashboard.php');
        }
        exit;
      } else {
        $error = 'Неверный логин или пароль';
      }
    } else {
      $error = 'Неверный логин или пароль';
    }
    $stmt->close();
  }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../css/style-login.css">
  <title>Login</title>
</head>
<body class="page-center">
  <div class="container">
    <section>
      <div class="login-box">
        <div class="box-head"><h2>DADA PIZZA</h2></div>

        <?php if ($error): ?>
          <div style="width:100%;margin-bottom:14px;color:#b00020;background:#fde7e9;border:1px solid #f5c2c7;border-radius:8px;padding:10px 12px;font-size:14px;">
            <?= htmlspecialchars($error, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') ?>
          </div>
        <?php endif; ?>

        <form action="" method="post" autocomplete="off" novalidate>
          <div class="form-main">
            <div class="input-window">
              <input placeholder="Логин" type="text" name="login" required>
            </div>
            <div class="input-window">
              <input placeholder="Пароль" type="password" name="pass" required>
            </div>
          </div>
          <button type="submit">Войти</button>
        </form>
      </div>
    </section>
    <footer>
      <a target="_blank" href="https://cdn.dodostatic.net/files/docs/saas_dodopizzaru_ru.pdf" class="agree">Пользовательское соглашение</a>
    </footer>
  </div>
</body>
</html>
