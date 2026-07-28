<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrfCheck()) {
    redirect('index.php');
}

if (!isAdmin() && !isSiteUser()) {
    redirect('login.php');
}

$name   = trim($_POST['client_name'] ?? '');
$phone  = trim($_POST['phone'] ?? '');
$service = trim($_POST['service'] ?? '');
$date   = trim($_POST['wanted_date'] ?? '');
$comment = trim($_POST['comment'] ?? '');

if ($name === '' || $phone === '') {
    redirect('index.php?tab=booking');
}

// Если заявку оставляет зарегистрированный посетитель — сразу привязываем
// её к его аккаунту (на будущее, для истории записей клиента).
$__siteUser = currentSiteUser();
$userId = $__siteUser ? (int)$__siteUser['id'] : null;

$pdo = getDB();
$stmt = $pdo->prepare(
    "INSERT INTO bookings (client_name, phone, service, wanted_date, comment, user_id) VALUES (?, ?, ?, ?, ?, ?)"
);
$stmt->execute([$name, $phone, $service, $date, $comment, $userId]);

redirect('index.php?tab=booking&booking_sent=1');
