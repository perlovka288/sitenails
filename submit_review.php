<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/onesignal.php';

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

$pdo = getDB();
$__reviewAuthor = currentSiteUser();
$__reviewUserId = $__reviewAuthor ? (int)$__reviewAuthor['id'] : null;

$editId = (int)($_POST['review_id'] ?? 0);

if ($editId > 0) {
    // ==== Редактирование своего отзыва ====
    // Никогда не доверяем проверке на клиенте — заново проверяем и
    // владельца, и окно в 1-2 часа на сервере (см. reviewOwnedByCurrentUser).
    $stmt = $pdo->prepare('SELECT * FROM reviews WHERE id = ?');
    $stmt->execute([$editId]);
    $existing = $stmt->fetch();

    if (!$existing || !reviewOwnedByCurrentUser($existing, $__reviewAuthor)) {
        redirect('index.php?tab=reviews');
    }

    // Новые фото заменяют старые, только если клиент что-то реально
    // прикрепил в этот раз — иначе оставляем прежние фотографии как есть.
    if ($photoPaths) {
        foreach (reviewPhotoPaths($existing['photo_path']) as $__oldPhoto) {
            $__oldFile = __DIR__ . '/' . $__oldPhoto;
            if (is_file($__oldFile)) {
                @unlink($__oldFile);
            }
        }
        $finalPhotoPath = $photoPath;
    } else {
        $finalPhotoPath = $existing['photo_path'];
    }

    $pdo->prepare('UPDATE reviews SET author_name = ?, rating = ?, message = ?, photo_path = ? WHERE id = ?')
        ->execute([$name, $rating, $message, $finalPhotoPath, $editId]);

    redirect('index.php?tab=reviews&review_sent=1');
}

// ==== Новый отзыв ====
// Публикуется на сайте сразу же — мама, если что, всегда может скрыть
// его или удалить в панели управления (раздел «Отзывы») или прямо на
// сайте, если она вошла в браузере как администратор.
$stmt = $pdo->prepare("INSERT INTO reviews (author_name, rating, message, photo_path, is_approved, user_id) VALUES (?, ?, ?, ?, 1, ?)");
$stmt->execute([$name, $rating, $message, $photoPath, $__reviewUserId]);

// Пуш админам, что пришёл новый отзыв — не дожидаясь, пока кто-то сам
// откроет панель управления и заметит его в списке.
notifyAdminsNewReview($pdo, $name, $rating);

redirect('index.php?tab=reviews&review_sent=1');
