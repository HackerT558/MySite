<?php
require __DIR__ . '/../config/config.php';
if (empty($_SESSION['uid'])) { header('Location: ../auth/login.php'); exit; }

function avatar_url(?string $path): string {
    if ($path && is_file(__DIR__ . '/../' . $path)) {
        return '/MySite/' . $path;
    }
    return 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80"><circle cx="40" cy="40" r="40" fill="%23e8edf3"/></svg>';
}

$term = trim($_GET['q'] ?? '');

$sql = "SELECT first_name, last_name, middle_name, phone, email, position, avatar
        FROM users";
if ($term !== '') {
    $sql .= " WHERE (first_name LIKE ? OR last_name LIKE ? OR middle_name LIKE ? OR phone LIKE ? OR email LIKE ? OR position LIKE ?)";
}
$sql .= " ORDER BY last_name ASC, first_name ASC";

$stmt = $mysqli->prepare($sql);
if ($term !== '') {
    $like = '%'.$term.'%';
    $stmt->bind_param('ssssss', $like, $like, $like, $like, $like, $like);
}
$stmt->execute();
$res = $stmt->get_result();
$people = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$active = 'contacts';
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Контакты</title>
  <link rel="stylesheet" href="../css/app-base.css">
  <link rel="stylesheet" href="../css/cabinet-header.css">
  <link rel="stylesheet" href="../css/contacts.css">
</head>
<body>
<?php require __DIR__ . '/../config/header-cabinet.inc.php'; ?>

<div class="contacts-wrap">
  <div class="contacts-card">
    <div class="contacts-head">
      <h2>Контакты</h2>
    </div>

    <form class="search-bar" method="get" autocomplete="off">
      <input type="text" name="q" placeholder="Поиск по имени, телефону, email или должности" value="<?= htmlspecialchars($term, ENT_QUOTES) ?>">
      <button type="submit">Искать</button>
      <a class="btn-gray" href="contacts.php" style="display:inline-block;text-decoration:none;padding:10px 14px;border-radius:8px;font-weight:600;">Сбросить</a>
    </form>

    <?php if (empty($people)): ?>
      <div class="empty">Ничего не найдено</div>
    <?php else: ?>
      <table class="contacts-list">
        <thead>
          <tr>
            <th>Сотрудник</th>
            <th>Должность</th>
            <th>Телефон</th>
            <th>Email</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($people as $p):
          $nameParts = array_filter([$p['last_name'], $p['first_name'], $p['middle_name']]);
          $fullName  = !empty($nameParts) ? implode(' ', $nameParts) : 'Без имени';
          $avatarUrl = avatar_url($p['avatar'] ?? null);
          $phone     = $p['phone'] ?: '—';
          $email     = $p['email'] ?: '—';
          $position  = $p['position'] ?: '—';
        ?>
          <tr>
            <td>
              <div class="contact-row">
                <img class="contact-avatar" src="<?= $avatarUrl ?>" alt="">
                <div>
                  <div class="contact-name"><?= htmlspecialchars($fullName, ENT_QUOTES) ?></div>
                </div>
              </div>
            </td>
            <td class="contact-meta"><?= htmlspecialchars($position, ENT_QUOTES) ?></td>
            <td class="contact-meta"><?= htmlspecialchars($phone, ENT_QUOTES) ?></td>
            <td class="contact-meta">
              <?php if ($email !== '—'): ?>
                <a href="mailto:<?= htmlspecialchars($email, ENT_QUOTES) ?>"><?= htmlspecialchars($email, ENT_QUOTES) ?></a>
              <?php else: ?>
                —
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

</body>
</html>
