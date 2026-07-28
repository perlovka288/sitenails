<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrfCheck()) {
    redirect('index.php');
}

if (!isAdmin() && !isSiteUser()) {
    redirect('login.php');
}

$name    = trim($_POST['author_name'] ?? '');
$rating  = max(1, min(5, (int)($_POST['rating'] ?? 5)));
$message = trim($_POST['message'] ?? '');

if ($name === '' || $message === '') {
    redirect('index.php?tab=reviews');
}

// ==== Фото к отзыву (до 3 штук, необязательно) ====
// Сохраняем в assets/img/reviews/, чтобы отзывы вместе с фото
// оставались на сайте после обновления страницы (обычная запись в БД
// + файлы на диске, никакого localStorage — переживает перезагрузку
// и работает у всех посетителей одинаково).
// Пути к фото храним в photo_path одной строкой в формате JSON-массива
// (["assets/img/reviews/a.jpg", "..."]) — так один и тот же столбец
// БД поддерживает от 0 до 3 фото без изменения структуры таблицы.
$photoPaths = [];
$allowed = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
];
$maxBytes = 6 * 1024 * 1024; // 6 МБ на файл
$maxPhotos = 3;

if (!empty($_FILES['photos']) && is_array($_FILES['photos']['name'] ?? null)) {
    $count = count($_FILES['photos']['name']);
    for ($i = 0; $i < $count && count($photoPaths) < $maxPhotos; $i++) {
        if (($_FILES['photos']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            continue;
        }
        $tmpName = $_FILES['photos']['tmp_name'][$i];
        $size = (int)$_FILES['photos']['size'][$i];
        $mime = function_exists('mime_content_type') ? mime_content_type($tmpName) : null;

        if ($size > 0 && $size <= $maxBytes && $mime !== null && isset($allowed[$mime])) {
            $ext = $allowed[$mime];
            $dir = __DIR__ . '/assets/img/reviews';
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $filename = 'review_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $destination = $dir . '/' . $filename;

            if (move_uploaded_file($tmpName, $destination)) {
                $photoPaths[] = 'assets/img/reviews/' . $filename;
            }
        }
        // Если формат/размер не подошли — просто пропускаем этот файл,
        // не обрываем отправку отзыва из-за него.
    }
}
$photoPath = $photoPaths ? json_encode($photoPaths) : null;

// Отзыв публикуется на сайте сразу же — мама, если что, всегда может
// скрыть его или удалить в панели управления (раздел «Отзывы») или
// прямо на сайте, если она вошла в браузере как администратор.
$pdo = getDB();
$stmt = $pdo->prepare("INSERT INTO reviews (author_name, rating, message, photo_path, is_approved) VALUES (?, ?, ?, ?, 1)");
$stmt->execute([$name, $rating, $message, $photoPath]);

redirect('index.php?tab=reviews&review_sent=1');
