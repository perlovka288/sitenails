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
        $pdo->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ?")->execute([$id]);

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
        $pdo->prepare("UPDATE bookings SET status = 'done' WHERE id = ?")->execute([$id]);
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

  <table class="admin-table">
    <thead>
      <tr><th>ID</th><th>Статус</th><th>Клиент</th><th>Телефон</th><th>Услуга</th><th>Дата</th><th>Время в календаре</th><th>Комментарий</th><th>Действия</th></tr>
    </thead>
    <tbody>
      <?php foreach ($bookings as $b): ?>
        <tr>
          <td style="color:var(--ink-faint); font-size:12px;">#<?= (int)$b['id'] ?></td>
          <td><span class="badge <?= e($b['status']) ?>"><?= e(bookingStatusLabel($b['status'], 'ru')) ?></span></td>
          <td><?= e($b['client_name']) ?></td>
          <td><?= e($b['phone'] ?: '—') ?></td>
          <td><?= e($b['service'] ?: '—') ?></td>
          <td><?= e($b['wanted_date'] ?: '—') ?></td>
          <td>
            <?php if ($b['slot_id']): ?>
              <span class="badge <?= $b['slot_is_booked'] ? 'done' : 'new' ?>">
                <?= $b['slot_is_booked'] ? 'занято' : 'свободно' ?>
              </span>
            <?php else: ?>
              —
            <?php endif; ?>
          </td>
          <td><?= e($b['comment'] ?: '—') ?></td>
          <td style="white-space:nowrap;">
            <?php if ($b['slot_id']): ?>
            <form method="post" style="display:inline;" title="Мама сама решает, занято это время или нет">
              <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
              <input type="hidden" name="slot_id" value="<?= (int)$b['slot_id'] ?>">
              <button name="action" value="toggle_slot" class="btn ghost" style="padding:6px 12px;font-size:12px;">
                <?= $b['slot_is_booked'] ? 'Освободить время' : 'Отметить занятым' ?>
              </button>
            </form>
            <?php endif; ?>
            <?php if ($b['status'] === 'new'): ?>
            <form method="post" style="display:inline;">
              <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
              <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
              <button name="action" value="confirm" class="btn ghost" style="padding:6px 12px;font-size:12px;">Подтвердить</button>
            </form>
            <?php endif; ?>
            <?php if ($b['status'] !== 'done'): ?>
            <form method="post" style="display:inline;">
              <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
              <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
              <button name="action" value="done" class="btn" style="padding:6px 12px;font-size:12px;">Готово</button>
            </form>
            <?php endif; ?>
            <form method="post" style="display:inline;" onsubmit="return confirm('Удалить запись?');">
              <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
              <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
              <button name="action" value="delete" class="btn ghost" style="padding:6px 12px;font-size:12px;">Удалить</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$bookings): ?>
        <tr><td colspan="9">Записей пока нет.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
</body>
</html>
