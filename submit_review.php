<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrfCheck()) {
    redirect('index.php');
}

$name    = trim($_POST['author_name'] ?? '');
$rating  = max(1, min(5, (int)($_POST['rating'] ?? 5)));
$message = trim($_POST['message'] ?? '');

if ($name === '' || $message === '') {
    redirect('index.php?tab=reviews');
}

// ==== Фото к отзыву (необязательное) ====
// Сохраняем в assets/img/reviews/, чтобы отзывы вместе с фото
// оставались на сайте после обновления страницы (обычная запись в БД
// + файл на диске, никакого localStorage — переживает перезагрузку
// и работает у всех посетителей одинаково).
$photoPath = null;
if (!empty($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['photo'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
        ];

        $maxBytes = 6 * 1024 * 1024; // 6 МБ
        $mime = function_exists('mime_content_type') ? mime_content_type($file['tmp_name']) : null;

        if ($file['size'] <= $maxBytes && $mime !== null && isset($allowed[$mime])) {
            $ext = $allowed[$mime];
            $dir = __DIR__ . '/assets/img/reviews';
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $filename = 'review_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $destination = $dir . '/' . $filename;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $photoPath = 'assets/img/reviews/' . $filename;
            }
        }
        // Если формат/размер не подошли — просто сохраняем отзыв без фото,
        // не обрываем отправку из-за файла.
    }
}

// Отзыв публикуется на сайте сразу же — мама, если что, всегда может
// скрыть его или удалить в панели управления (раздел «Отзывы») или
// прямо на сайте, если она вошла в браузере как администратор.
$pdo = getDB();
$stmt = $pdo->prepare("INSERT INTO reviews (author_name, rating, message, photo_path, is_approved) VALUES (?, ?, ?, ?, 1)");
$stmt->execute([$name, $rating, $message, $photoPath]);

redirect('index.php?tab=reviews&review_sent=1');
