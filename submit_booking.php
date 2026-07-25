<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrfCheck()) {
    redirect('index.php');
}

$name   = trim($_POST['client_name'] ?? '');
$phone  = trim($_POST['phone'] ?? '');
$service = trim($_POST['service'] ?? '');
$date   = trim($_POST['wanted_date'] ?? '');
$comment = trim($_POST['comment'] ?? '');

if ($name === '' || $phone === '') {
    redirect('index.php?tab=booking');
}

$pdo = getDB();
$stmt = $pdo->prepare(
    "INSERT INTO bookings (client_name, phone, service, wanted_date, comment) VALUES (?, ?, ?, ?, ?)"
);
$stmt->execute([$name, $phone, $service, $date, $comment]);

redirect('index.php?tab=booking&booking_sent=1');
