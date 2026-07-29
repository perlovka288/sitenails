<?php
/**
 * update_avatar.php
 *
 * Клиент меняет фото в мини-профиле (шапка сайта). Отдельная простая форма
 * (см. .profile-avatar в includes/header.php) — обычный POST + редирект,
 * как submit_review.php/submit_booking.php, без AJAX.
 */
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrfCheck()) {
    redirect('index.php');
}

$__siteUser = currentSiteUser();
if (!$__siteUser) {
    redirect('login.php');
}

$backTab = $_POST['back_tab'] ?? '';
$backTab = in_array($backTab, ['about', 'reviews', 'price', 'booking', 'profile'], true) ? $backTab : 'about';

$newPath = saveUploadedFile(
    'avatar',
    'assets/img/avatars',
    [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ],
    4 * 1024 * 1024, // 4 МБ
    'avatar'
);

if ($newPath !== null) {
    // Удаляем старое фото с диска (если оно было локальным, не заглушкой)
    deleteUploadedFile($__siteUser['avatar_path'] ?? null);

    $pdo = getDB();
    $pdo->prepare('UPDATE site_users SET avatar_path = ? WHERE id = ?')
        ->execute([$newPath, $__siteUser['id']]);
}

redirect($backTab === 'profile' ? 'profile.php' : 'index.php?tab=' . $backTab);
