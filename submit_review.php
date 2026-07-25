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

$pdo = getDB();
$stmt = $pdo->prepare("INSERT INTO reviews (author_name, rating, message, is_approved) VALUES (?, ?, ?, 0)");
$stmt->execute([$name, $rating, $message]);

redirect('index.php?tab=reviews&review_sent=1');
