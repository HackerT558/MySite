<?php
require __DIR__ . '/../config/config.php';

require_role_min_db($mysqli, 'manager-top');
$active = 'manage';

$myRole  = $_SESSION['role'] ?? 'user';
$myLevel = role_level($myRole);
$notice = $error = '';

// ========================= helpers =========================
function handle_avatar_upload(string $fieldName, int $maxBytes): array {
    if (empty($_FILES[$fieldName]['name'])) return [null, null];
    if ($_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) return [null, 'Ошибка загрузки файла'];
    if ((int)$_FILES[$fieldName]['size'] > $maxBytes) return [null, 'Файл слишком большой (макс. 25 МБ)'];

    $ext = strtolower(pathinfo($_FILES[$fieldName]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','gif','webp'], true)) {
        return [null, 'Недопустимый формат аватара'];
    }

    $newName = 'ava_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destRel = 'avatar-uploads/' . $newName;
    $destAbs = __DIR__ . '/../avatar-uploads/' . $newName;

    if (!is_dir(dirname($destAbs))) {
        @mkdir(dirname($destAbs), 0777, true);
    }
    if (!move_uploaded_file($_FILES[$fieldName]['tmp_name'], $destAbs)) {
        return [null, 'Не удалось сохранить аватар'];
    }
    return [$destRel, null];
}

function sort_link($label, $col, $currentSort, $currentDir, $filterLast) {
    $nextDir = ($currentSort === $col && $currentDir === 'ASC') ? 'DESC' : 'ASC';
    $active  = $currentSort === $col ? 'active' : '';
    $arrow   = ($currentSort === $col) ? ($currentDir === 'ASC' ? '▲' : '▼') : '';
    $qs = http_build_query(['sort'=>$col,'dir'=>$nextDir,'last_name'=>$filterLast]);
    return '<a class="th-sort '.$active.'" href="?'.$qs.'"><span>'.$label.'</span><span class="arrow">'.$arrow.'</span></a>';
}

// ========================= actions =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = $_POST['role'] ?? 'user';
        $position = $_POST['position'] ?? null;

        if ($username === '' || $password === '' || role_level($role) <= 0) {
            $error = 'Заполните поля корректно';
        } elseif ($myLevel <= role_level($role) && $myRole !== 'admin') {
            $error = 'Недостаточно прав для создания пользователя с такой ролью';
        } else {
            $check = $mysqli->prepare('SELECT id FROM users WHERE username=? LIMIT 1');
            $check->bind_param('s', $username);
            $check->execute(); $check->store_result();
            if ($check->num_rows > 0) {
                $error = 'Пользователь с таким логином уже существует';
            }
            $check->close();

            if (!$error) {
                [$avatarPath, $upErr] = handle_avatar_upload('avatar', 25*1024*1024);
                if ($upErr) {
                    $error = $upErr;
                } else {
                    $ins = $mysqli->prepare(
                        'INSERT INTO users (username,password,role,position,avatar) VALUES (?,?,?,?,?)'
                    );
                    $ins->bind_param('sssss', $username, $password, $role, $position, $avatarPath);
                    $ins->execute(); $ins->close();
                    $notice = 'Пользователь добавлен';
                }
            }
        }
    }

    if ($action === 'update_profile') {
        $uid = (int)($_POST['id'] ?? 0);
        if ($uid <= 0) {
            $error = 'Некорректный идентификатор';
        } else {
            // Получаем старый аватар
            $stmtOld = $mysqli->prepare('SELECT avatar FROM users WHERE id = ?');
            $stmtOld->bind_param('i', $uid);
            $stmtOld->execute();
            $stmtOld->bind_result($oldAvatar);
            $stmtOld->fetch();
            $stmtOld->close();

            // Проверка прав
            $ru = $mysqli->prepare('SELECT role FROM users WHERE id=?');
            $ru->bind_param('i', $uid);
            $ru->execute(); $ru->bind_result($targetRole); $ru->fetch(); $ru->close();
            $targetLevel = role_level($targetRole ?? 'user');
            $editingSelf = ($uid === (int)$_SESSION['uid']);
            if (!$editingSelf && $myRole !== 'admin' && $myLevel <= $targetLevel) {
                $error = 'Недостаточно прав для редактирования этого пользователя';
            }

            $username    = trim($_POST['username'] ?? '');
            $last_name   = trim($_POST['last_name'] ?? '');
            $first_name  = trim($_POST['first_name'] ?? '');
            $middle_name = trim($_POST['middle_name'] ?? '');
            $phone       = trim($_POST['phone'] ?? '');
            $email       = trim($_POST['email'] ?? '');
            $position    = $_POST['position'] ?? null;
            $role2       = $_POST['role'] ?? null;
            $new_pass    = $_POST['password'] ?? '';

            if (!$error && $username === '') {
                $error = 'Логин не может быть пустым';
            }

            if (!$error) {
                $c = $mysqli->prepare('SELECT id FROM users WHERE username=? AND id<>? LIMIT 1');
                $c->bind_param('si', $username, $uid);
                $c->execute(); $c->store_result();
                if ($c->num_rows > 0) {
                    $error = 'Такой логин уже используется другим пользователем';
                }
                $c->close();
            }

            if (!$error && $email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Некорректный email';
            }

            $set = ['username=?','last_name=?','first_name=?','middle_name=?','phone=?','email=?','position=?'];
            $vals = [$username,$last_name,$first_name,$middle_name,$phone,$email,$position];
            $types= 'sssssss';

            if (!$error && $role2 !== null && role_level($role2) > 0) {
                if (!$editingSelf) {
                    $newLevel = role_level($role2);
                    if ($myRole === 'admin' || $myLevel > $newLevel) {
                        $set[] = 'role=?'; $vals[] = $role2; $types .= 's';
                    }
                }
            }

            // Аватар
            if (!$error && !empty($_FILES['avatar']['name'])) {
                [$newPath, $upErr] = handle_avatar_upload('avatar', 25*1024*1024);
                if ($upErr) {
                    $error = $upErr;
                } else {
                    if ($oldAvatar && $oldAvatar !== $newPath) {
                        $oldFile = __DIR__ . '/../' . $oldAvatar;
                        if (is_file($oldFile)) {
                            @unlink($oldFile);
                        }
                    }
                    $set[] = 'avatar=?'; $vals[] = $newPath; $types .= 's';
                }
            }

            // Пароль
            if (!$error && $new_pass !== '') {
                $set[] = 'password=?'; $vals[] = $new_pass; $types .= 's';
            }

            if (!$error) {
                $vals[] = $uid; $types .= 'i';
                $sql = 'UPDATE users SET '.implode(',', $set).' WHERE id=?';
                $stmt = $mysqli->prepare($sql);
                $stmt->bind_param($types, ...$vals);
                $stmt->execute(); $stmt->close();

                if ($editingSelf) {
                    $_SESSION['username'] = $username;
                    $check = $mysqli->prepare('SELECT role FROM users WHERE id=?');
                    $check->bind_param('i', $uid);
                    $check->execute(); $check->bind_result($nr); $check->fetch(); $check->close();
                    if (role_level($nr) < role_level('manager-top')) {
                        session_unset(); session_destroy();
                        header('Location: ../index.php'); exit;
                    } else {
                        $_SESSION['role'] = $nr;
                    }
                }

                $notice = 'Данные пользователя обновлены';
            }
        }
    }

    if ($action === 'delete') {
        $uid = (int)($_POST['id'] ?? 0);
        if ($uid <= 0) {
            $error = 'Некорректный идентификатор пользователя';
        } else {
            $ru = $mysqli->prepare('SELECT role FROM users WHERE id=?');
            $ru->bind_param('i', $uid);
            $ru->execute(); $ru->bind_result($targetRole); $ru->fetch(); $ru->close();
            $targetLevel = role_level($targetRole ?? 'user');

            if ($uid === (int)$_SESSION['uid']) {
                $error = 'Нельзя удалить текущую учётную запись';
            } elseif ($myRole !== 'admin' && $myLevel <= $targetLevel) {
                $error = 'Недостаточно прав для удаления этого пользователя';
            } else {
                // Удаляем аватар пользователя
                $stmtDel = $mysqli->prepare('SELECT avatar FROM users WHERE id=?');
                $stmtDel->bind_param('i', $uid); $stmtDel->execute();
                $stmtDel->bind_result($delAvatar); $stmtDel->fetch(); $stmtDel->close();
                if ($delAvatar) {
                    $f = __DIR__ . '/../' . $delAvatar;
                    if (is_file($f)) @unlink($f);
                }

                $stmt = $mysqli->prepare('DELETE FROM users WHERE id=?');
                $stmt->bind_param('i', $uid);
                $stmt->execute(); $stmt->close();
                $notice = 'Пользователь удалён';
            }
        }
    }
}

// ========================= listing (sort + search) =========================
$allowedSort = ['id','username','last_name','first_name','role','position'];
$sort = $_GET['sort'] ?? 'id';
$dir  = strtoupper($_GET['dir'] ?? 'ASC');
if (!in_array($sort, $allowedSort, true)) $sort = 'id';
$dir = $dir === 'DESC' ? 'DESC' : 'ASC';

$filterLast = trim($_GET['last_name'] ?? '');
$whereSql = ''; $params=[]; $types='';
if ($filterLast !== '') {
    $whereSql = 'WHERE last_name LIKE ?';
    $params[] = '%'.$filterLast.'%';
    $types .= 's';
}

$sql = "SELECT id, username, role, last_name, first_name, middle_name, phone, email, position, avatar
        FROM users
        $whereSql
        ORDER BY $sort $dir";

$stmtList = $mysqli->prepare($sql);
if ($types !== '') {
    $stmtList->bind_param($types, ...$params);
}
$stmtList->execute();
$res = $stmtList->get_result();
$users = $res->fetch_all(MYSQLI_ASSOC);
$stmtList->close();

$positions = ['Стажер','Пиццамейкер','Кассир','Универсал','Менеджер','Заместитель управляющего','Управляющий'];
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление пользователями</title>
    <link rel="stylesheet" href="../css/app-base.css">
    <link rel="stylesheet" href="../css/app-admin.css">
</head>
<body>
    <?php require __DIR__ . '/../config/header-cabinet.inc.php'; ?>
<div class="container admin-wrap">
    <section>
        <div class="login-box" style="align-items:stretch;">
            <div class="box-head"><h2>Управление пользователями</h2></div>

            <?php if ($notice): ?><div class="notice"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

            <!-- Добавление -->
            <div class="card">
                <h3>Добавить пользователя</h3>
                <form method="post" class="inline" autocomplete="off" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add">
                    <input type="text" name="username" placeholder="Логин" required>
                    <input type="password" name="password" placeholder="Пароль" required>
                    <select name="role" title="Можно создать не выше своей роли">
                        <option value="user">user</option>
                        <option value="manager" <?= $myLevel < role_level('manager') ? 'disabled':''; ?>>manager</option>
                        <option value="manager-top" <?= $myLevel < role_level('manager-top') ? 'disabled':''; ?>>manager-top</option>
                        <option value="manager-general" <?= $myLevel < role_level('manager-general') ? 'disabled':''; ?>>manager-general</option>
                        <option value="admin" <?= $myRole !== 'admin' ? 'disabled':''; ?>>admin</option>
                    </select>
                    <select name="position">
                        <option value="">Должность</option>
                        <?php foreach ($positions as $p): ?>
                            <option><?= htmlspecialchars($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="file" name="avatar" accept="image/*" title="Максимум 25 МБ">
                    <small>Максимальный размер файла: 25 МБ</small>
                    <button class="btn btn-orange" type="submit">Добавить</button>
                </form>
            </div>

            <!-- Поиск -->
            <div class="card">
                <form method="get" class="inline" autocomplete="off">
                    <input type="text" name="last_name" placeholder="Поиск по фамилии" value="<?= htmlspecialchars($filterLast) ?>">
                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
                    <input type="hidden" name="dir"  value="<?= htmlspecialchars($dir) ?>">
                    <button class="btn btn-orange" type="submit">Искать</button>
                    <a class="btn btn-gray" href="users.php">Сбросить</a>
                </form>
            </div>

            <!-- Список -->
            <div class="card" style="overflow:auto;">
                <table class="table-compact">
                    <thead>
                        <tr>
                            <th><?= sort_link('ID','id',$sort,$dir,$filterLast) ?></th>
                            <th>Аватар</th>
                            <th><?= sort_link('Логин','username',$sort,$dir,$filterLast) ?></th>
                            <th><?= sort_link('Фамилия','last_name',$sort,$dir,$filterLast) ?> / <?= sort_link('Имя','first_name',$sort,$dir,$filterLast) ?></th>
                            <th><?= sort_link('Роль','role',$sort,$dir,$filterLast) ?></th>
                            <th><?= sort_link('Должность','position',$sort,$dir,$filterLast) ?></th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): 
                            $isSelf = ((int)$u['id'] === (int)$_SESSION['uid']);
                            $tLevel = role_level($u['role']);
                            $canManage = ($myRole === 'admin') || ($myLevel > $tLevel) || $isSelf;
                        ?>
                        <tr data-user='<?= json_encode($u, JSON_UNESCAPED_UNICODE) ?>'
                            data-self="<?= $isSelf ? '1':'0' ?>" data-manage="<?= $canManage ? '1':'0' ?>">
                            <td><?= (int)$u['id'] ?></td>
                            <td>
                                <?php if (!empty($u['avatar'])): ?>
                                    <img src="/MySite/<?= htmlspecialchars($u['avatar']) ?>" class="avatar-sm" alt="avatar">
                                <?php else: ?>
                                    <div class="avatar-sm" style="background:#e8edf3;display:flex;align-items:center;justify-content:center;color:#666;font-size:12px;">—</div>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                            <td><strong><?= htmlspecialchars($u['last_name']?:'—') ?></strong> <?= ' ' . htmlspecialchars($u['first_name']?:'') ?></td>
                            <td><?= htmlspecialchars($u['role']) ?></td>
                            <td><?= htmlspecialchars($u['position']?:'—') ?></td>
                            <td class="row-actions">
                                <button class="link-btn js-edit" <?= $canManage ? '' : 'disabled title="Недостаточно прав"' ?>>Редактировать</button>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Удалить пользователя <?= htmlspecialchars($u['username']) ?>?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                    <button class="btn btn-red" type="submit" <?= ($isSelf||(!$canManage)||($myLevel<=$tLevel&&$myRole!=='admin')) ? 'disabled title="Недостаточно прав"' : '' ?>>Удалить</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </section>
</div>

<div class="modal" id="userModal" aria-hidden="true">
    <div class="modal__dialog">
        <div class="modal__head">
            <h3 class="modal__title">Редактирование пользователя</h3>
            <button class="modal__close" type="button" aria-label="Close">&times;</button>
        </div>
        <div class="modal__body">
            <div class="modal__row" id="avatarPreviewRow" style="align-items:center;">
                <img id="f_avatar_img" class="avatar-lg" src="" alt="" style="display:none;">
                <span id="f_avatar_placeholder" style="color:#888;">Аватар не установлен</span>
            </div>
            <form id="editForm" method="post" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" name="action" value="update_profile">
                <input type="hidden" name="id" id="f_id">
                <div class="modal__row">
                    <input type="text" name="username" id="f_username" placeholder="Логин">
                    <select name="role" id="f_role">
                        <option value="user">user</option>
                        <option value="manager">manager</option>
                        <option value="manager-top">manager-top</option>
                        <option value="manager-general">manager-general</option>
                        <option value="admin">admin</option>
                    </select>
                    <select name="position" id="f_position">
                        <option value="">Должность</option>
                        <?php foreach ($positions as $p): ?>
                            <option><?= htmlspecialchars($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="modal__row">
                    <input type="text" name="last_name" id="f_last_name" placeholder="Фамилия">
                    <input type="text" name="first_name" id="f_first_name" placeholder="Имя">
                    <input type="text" name="middle_name" id="f_middle_name" placeholder="Отчество">
                </div>
                <div class="modal__row">
                    <input type="text" name="phone" id="f_phone" placeholder="Телефон">
                    <input type="email" name="email" id="f_email" placeholder="Почта">
                    <input type="file" name="avatar" id="f_avatar" accept="image/*" title="Максимум 25 МБ">
                </div>
                <div class="modal__row">
                    <input type="password" name="password" id="f_password" placeholder="Новый пароль (необязательно)">
                </div>
                <div class="modal__actions">
                    <button type="button" class="btn btn-gray" id="btnClose">Отмена</button>
                    <button type="submit" class="btn btn-orange" id="btnSaveProfile">Сохранить</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="../js/users.js"></script>
