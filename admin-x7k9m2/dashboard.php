<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/includes/auth_check.php';

$pdo = getDB();
$newBookings   = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'new'")->fetchColumn();
$pendingReviews = $pdo->query("SELECT COUNT(*) FROM reviews WHERE is_approved = 0")->fetchColumn();
$priceCount    = $pdo->query("SELECT COUNT(*) FROM price_items")->fetchColumn();
$freeSlots     = $pdo->query("SELECT COUNT(*) FROM available_slots WHERE is_booked = 0 AND slot_date >= date('now')")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Панель управления</title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="admin-shell">
  <?php require __DIR__ . '/includes/nav.php'; ?>

  <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:14px;">
    <div class="card">
      <div style="font-size:13px;color:var(--ink-soft);">Новые записи</div>
      <div style="font-size:32px;font-family:'Manrope',sans-serif;font-weight:800;"><?= (int)$newBookings ?></div>
      <a href="bookings.php">Посмотреть →</a>
    </div>
    <div class="card">
      <div style="font-size:13px;color:var(--ink-soft);">Отзывы на проверке</div>
      <div style="font-size:32px;font-family:'Manrope',sans-serif;font-weight:800;"><?= (int)$pendingReviews ?></div>
      <a href="reviews.php">Посмотреть →</a>
    </div>
    <div class="card">
      <div style="font-size:13px;color:var(--ink-soft);">Позиций в прайсе</div>
      <div style="font-size:32px;font-family:'Manrope',sans-serif;font-weight:800;"><?= (int)$priceCount ?></div>
      <a href="prices.php">Редактировать →</a>
    </div>
    <div class="card">
      <div style="font-size:13px;color:var(--ink-soft);">Свободных слотов (будущих)</div>
      <div style="font-size:32px;font-family:'Manrope',sans-serif;font-weight:800;"><?= (int)$freeSlots ?></div>
      <a href="slots.php">Настроить →</a>
    </div>
  </div>
</div>
</body>
</html>
