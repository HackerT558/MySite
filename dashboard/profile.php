<?php
require __DIR__ . '/../config/config.php';
if (empty($_SESSION['uid'])) {
    header('Location: ../index.php');
    exit;
}

$userId = (int)$_SESSION['uid'];

$q = $mysqli->prepare(
    'SELECT username, first_name, last_name, middle_name,
            phone, email, position, role, avatar
     FROM users WHERE id = ? LIMIT 1'
);
$q->bind_param('i', $userId);
$q->execute();
$q->bind_result(
    $username, $firstName, $lastName, $middleName,
    $phone, $email, $position, $role, $avatar
);
$q->fetch();
$q->close();

$nameParts = array_filter([$lastName, $firstName, $middleName]);
$fullName = !empty($nameParts) ? implode(' ', $nameParts) : ($firstName ?: 'Пользователь');

function avatar_url(?string $path): string {
    if ($path && is_file(__DIR__ . '/../' . $path)) {
        return '/MySite/' . $path;
    }
    return 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120"><circle cx="60" cy="60" r="60" fill="%233a414a"/></svg>';
}
$avatarUrl = avatar_url($avatar);

$active = 'profile';
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Профиль пользователя</title>
    <link rel="stylesheet" href="../css/app-base.css">
    <link rel="stylesheet" href="../css/cabinet-header.css">
    <link rel="stylesheet" href="../css/profile.css">
</head>
<body>
<?php require __DIR__ . '/../config/header-cabinet.inc.php'; ?>

<div class="profile-card">
    <div class="profile-header">
        <img class="profile-avatar" src="<?= $avatarUrl ?>" alt="avatar">
        <div class="profile-info">
            <h2><?= htmlspecialchars($fullName, ENT_QUOTES) ?></h2>
            <p><strong>Должность:</strong> <?= htmlspecialchars($position ?: '—', ENT_QUOTES) ?></p>
        </div>
    </div>

    <div class="profile-section">
        <h3>Контактная информация</h3>
        <dl>
            <dt>Телефон:</dt>
            <dd><?= htmlspecialchars($phone ?: '—', ENT_QUOTES) ?></dd>
            <dt>Email:</dt>
            <dd><?= htmlspecialchars($email ?: '—', ENT_QUOTES) ?></dd>
        </dl>
    </div>
</div>

</body>
</html>
