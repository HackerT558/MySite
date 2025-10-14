<?php
// config/header-cabinet.inc.php
require_once __DIR__ . '/config.php';

if (!function_exists('avatar_url')) {
  function avatar_url(?string $path): string {
    if ($path && is_file(__DIR__ . '/../' . $path)) {
      return '/MySite/' . $path;
    }
    return 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80"><circle cx="40" cy="40" r="40" fill="%233a414a"/></svg>';
  }
}

$userId = $_SESSION['uid'] ?? 0;
$userFullName = 'Пользователь';
$userAvatar = null;

if ($userId > 0) {
  $q = $mysqli->prepare('SELECT first_name, last_name, middle_name, avatar FROM users WHERE id=?');
  $q->bind_param('i', $userId);
  $q->execute();
  $q->bind_result($firstName, $lastName, $middleName, $avatar);
  $q->fetch();
  $q->close();

  $nameParts = array_filter([$lastName, $firstName, $middleName]);
  if (!empty($nameParts)) $userFullName = implode(' ', $nameParts);
  if ($avatar) { $userAvatar = $avatar; $_SESSION['avatar'] = $avatar; }
}

$userFullName = htmlspecialchars($userFullName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$avatarUrl = avatar_url($userAvatar);

$currentRole  = $_SESSION['role'] ?? 'user';
$roleLevelMap = [
  'user'             => 1,
  'manager'          => 2,
  'manager-top'      => 3,
  'manager-general'  => 4,
  'admin'            => 5,
];
$currentLevel = $roleLevelMap[$currentRole] ?? 0;

$active = $active ?? '';
?>
<link rel="stylesheet" href="../css/cabinet-header.css">
<header class="cab-header">
  <div class="wrap">
    <div class="cab-brand">
      <span>Dodo IS</span>
      <span class="crumbs">→ Кабинет сотрудника</span>
    </div>
    <div class="cab-user" onclick="toggleUserDropdown()">
      <img class="avatar" src="<?= $avatarUrl ?>" alt="avatar">
      <span class="name"><?= $userFullName ?></span>
      <div class="user-dropdown" id="userDropdown">
        <a href="profile.php">Профиль</a>
        <a href="../auth/logout.php">Выйти</a>
      </div>
    </div>
  </div>
</header>
<nav class="cab-nav">
  <div class="tabs">
    <a class="tab <?= $active==='schedule'?'active':'' ?>" href="user-dashboard.php">График</a>
    <a class="tab <?= $active==='contacts'?'active':'' ?>" href="contacts.php">Контакты</a>
    <a href="courses.php" class="tab <?= $active === 'courses' ? 'active' : '' ?>">Курсы</a>
    <a class="tab <?= $active==='game'?'active':'' ?>" href="game.php">Игра</a>

    <?php if ($currentLevel >= $roleLevelMap['manager']) : ?>
      <a class="tab <?= $active==='shift'?'active':'' ?>" href="shift-manager.php">Менеджер смены</a>
    <?php endif; ?>

    <?php if ($currentLevel >= $roleLevelMap['manager-top']) : ?>
      <a class="tab <?= $active==='manage'?'active':'' ?>" href="users.php">Управление</a>
    <?php endif; ?>

    <span class="spacer"></span>
  </div>
</nav>

<script>
function toggleUserDropdown() {
  const dropdown = document.getElementById('userDropdown');
  dropdown.classList.toggle('show');
}
document.addEventListener('click', function(event) {
  const userBlock = event.target.closest('.cab-user');
  const dropdown = document.getElementById('userDropdown');
  if (!userBlock && dropdown) dropdown.classList.remove('show');
});
</script>
