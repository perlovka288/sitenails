<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/onesignal.php';
require __DIR__ . '/includes/auth_check.php';

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfCheck()) {
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'confirm') {
        $pdo->prepare("UPDATE bookings SET status = 'confirmed', updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);

        // Пуш клиенту "Ваша запись принята" — без бота, обычное системное
        // уведомление (см. includes/onesignal.php). Если у заявки нет
        // привязанного аккаунта (user_id) или push не настроен — просто
        // ничего не отправляется, подтверждение статуса всё равно сохранится.
        $__b = $pdo->prepare('SELECT * FROM bookings WHERE id = ?');
        $__b->execute([$id]);
        $__booking = $__b->fetch();
        if ($__booking && !empty($__booking['user_id'])) {
            $__phone = getSetting('site_phone', '');
            $__address = getSetting('site_address', '');
            // Дата/время берутся из wanted_date — это именно то, что клиент
            // выбрал на сайте (см. select_slot.php), поэтому текст всегда
            // актуален и меняется автоматически для каждой записи.
            $__msg = 'Чекаємо на вас ' . $__booking['wanted_date'] . '! 💅';
            if ($__phone !== '') $__msg .= ' Майстер: ' . $__phone . '.';
            if ($__address !== '') $__msg .= ' Адреса: ' . $__address;
            sendOneSignalPush((int)$__booking['user_id'], 'Ваш запис підтверджено ✨', $__msg);
        }
    } elseif ($action === 'done') {
        $pdo->prepare("UPDATE bookings SET status = 'done', updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM bookings WHERE id = ?')->execute([$id]);
    } elseif ($action === 'toggle_slot') {
        $slotId = (int)($_POST['slot_id'] ?? 0);
        if ($slotId > 0) {
            $pdo->prepare('UPDATE available_slots SET is_booked = 1 - is_booked WHERE id = ?')->execute([$slotId]);
        }
    }
    redirect('bookings.php');
}

$bookings = $pdo->query('
    SELECT b.*, s.is_booked AS slot_is_booked, s.slot_date AS slot_date_fmt, s.slot_time AS slot_time_fmt
    FROM bookings b
    LEFT JOIN available_slots s ON s.id = b.slot_id
    ORDER BY b.created_at DESC
')->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Записи — Панель управления</title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>
<div class="admin-shell">
  <?php require __DIR__ . '/includes/nav.php'; ?>

  <div class="rec-list">
    <?php foreach ($bookings as $b): ?>
      <div class="rec-card">
        <div class="rec-card-head">
          <div class="rec-card-head-name">
            <span class="rec-card-id">#<?= (int)$b['id'] ?></span>
            <strong><?= e($b['client_name']) ?></strong>
          </div>
          <span class="badge <?= e($b['status']) ?>"><?= e(bookingStatusLabel($b['status'], 'ru')) ?></span>
        </div>
        <div class="rec-card-body">
          <?php if ($b['wanted_date']): ?>
          <div class="rec-card-row"><span class="rec-card-icon">📅</span><span><?= e($b['wanted_date']) ?></span></div>
          <?php endif; ?>
          <?php if ($b['service']): ?>
          <div class="rec-card-row"><span class="rec-card-icon">💅</span><span><?= e($b['service']) ?></span></div>
          <?php endif; ?>
          <?php if ($b['phone']): ?>
          <div class="rec-card-row"><span class="rec-card-icon">📞</span><a href="tel:<?= e(preg_replace('/\s+/', '', $b['phone'])) ?>" class="rec-card-tel"><?= e($b['phone']) ?></a></div>
          <?php endif; ?>
          <?php if ($b['comment']): ?>
          <div class="rec-card-row"><span class="rec-card-icon">💬</span><span><?= e($b['comment']) ?></span></div>
          <?php endif; ?>
          <?php if ($b['slot_id']): ?>
          <div class="rec-card-row"><span class="rec-card-icon">🗓️</span><span class="badge <?= $b['slot_is_booked'] ? 'done' : 'new' ?>"><?= $b['slot_is_booked'] ? 'слот занят' : 'слот свободен' ?></span></div>
          <?php endif; ?>
        </div>
        <div class="rec-card-actions">
          <?php if ($b['slot_id']): ?>
          <form method="post" title="Мама сама решает, занято это время или нет">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="slot_id" value="<?= (int)$b['slot_id'] ?>">
            <button name="action" value="toggle_slot" class="btn ghost rec-card-btn">
              <?= $b['slot_is_booked'] ? 'Освободить время' : 'Отметить занятым' ?>
            </button>
          </form>
          <?php endif; ?>
          <?php if ($b['status'] === 'new'): ?>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
            <button name="action" value="confirm" class="btn rec-card-btn">Подтвердить</button>
          </form>
          <?php endif; ?>
          <?php if ($b['status'] !== 'done'): ?>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
            <button name="action" value="done" class="btn ghost rec-card-btn">Готово</button>
          </form>
          <?php endif; ?>
          <form method="post" onsubmit="return confirm('Удалить запись?');">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
            <button name="action" value="delete" class="btn ghost rec-card-btn rec-card-btn-danger">Отменить</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$bookings): ?>
      <p class="rec-empty">Записей пока нет.</p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
