
<?php
require __DIR__ . '/config.php';

// Собираем все актуальные имена файлов аватаров из БД
$pathsInDb = [];
$res = $mysqli->query('SELECT avatar FROM users WHERE avatar IS NOT NULL');
while ($row = $res->fetch_assoc()) {
    $pathsInDb[] = basename($row['avatar']);
}

// Папка с аватарами
$dir = __DIR__ . '/../avatar-uploads';
$files = array_diff(scandir($dir), ['.','..']);

foreach ($files as $file) {
    if (!in_array($file, $pathsInDb, true)) {
        @unlink($dir . '/' . $file);
    }
}
echo "Cleanup complete. Removed unused files.\n";
