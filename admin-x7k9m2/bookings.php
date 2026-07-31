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

        $__b = $pdo->prepare('SELECT * FROM bookings WHERE id = ?');
        $__b->execute([$id]);
        $__booking = $__b->fetch();

        // Раньше слот нужно было отдельно отмечать занятым кнопкой
        // "Отметить занятым" — если забыть это сделать, время оставалось
        // видно свободным в календаре на сайте, и кто-то другой мог
        // записаться на то же время. Теперь при подтверждении заявки слот
        // (если он привязан, т.е. запись пришла через календарь) занимается
        // автоматически — вручную дополнительно жать ничего не нужно.
        if ($__booking && !empty($__booking['slot_id'])) {
            $pdo->prepare('UPDATE available_slots SET is_booked = 1 WHERE id = ?')->execute([(int)$__booking['slot_id']]);
        }

        // Пуш клиенту "Ваша запись принята" — без бота, обычное системное
        // уведомление (см. includes/onesignal.php). Если у заявки нет
        // привязанного аккаунта (user_id) или push не настроен — просто
        // ничего не отправляется, подтверждение статуса всё равно сохранится.
        if ($__booking && !empty($__booking['user_id'])) {
            $__phone = getSetting('site_phone', '');
            $__address = getSetting('site_address', '');
            $__msg = 'Чекаємо на вас ' . $__booking['wanted_date'] . '! 💅';
            if ($__phone !== '') $__msg .= ' Майстер: ' . $__phone . '.';
            if ($__address !== '') $__msg .= ' Адреса: ' . $__address;
            sendOneSignalPush((int)$__booking['user_id'], 'Ваш запис підтверджено ✨', $__msg);
        }
    } elseif ($action === 'cancel') {
        // "Отменить" теперь не удаляет заявку, а переводит её в статус
        // "отменено" вместе с причиной (её обязательно вводит админ в
        // модалке) — так клиент видит в истории записей и в пуш-уведомлении,
        // почему запись не приняли/отменили, а не просто теряет заявку.
        $reason = trim($_POST['reason'] ?? '');

        $__b = $pdo->prepare('SELECT * FROM bookings WHERE id = ?');
        $__b->execute([$id]);
        $__booking = $__b->fetch();

        if ($__booking) {
            $pdo->prepare("UPDATE bookings SET status = 'cancelled', cancel_reason = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute([$reason !== '' ? $reason : null, $id]);

            // Если слот успел стать занятым (запись уже была подтверждена
            // ранее, а потом её всё же отменили) — освобождаем время обратно.
            if (!empty($__booking['slot_id'])) {
                $pdo->prepare('UPDATE available_slots SET is_booked = 0 WHERE id = ?')->execute([(int)$__booking['slot_id']]);
            }

            if (!empty($__booking['user_id'])) {
                $__msg = 'Ваш запис на ' . $__booking['wanted_date'] . ' не прийнято.';
                if ($reason !== '') {
                    $__msg .= ' Причина: ' . $reason;
                }
                sendOneSignalPush((int)$__booking['user_id'], 'Запис скасовано', $__msg);
            }
        }
    }
    redirect('bookings.php');
}

$bookings = $pdo->query('
    SELECT b.*, s.is_booked AS slot_is_booked, s.slot_date AS slot_date_fmt, s.slot_time AS slot_time_fmt,
           u.avatar_path AS client_avatar_path
    FROM bookings b
    LEFT JOIN available_slots s ON s.id = b.slot_id
    LEFT JOIN site_users u ON u.id = b.user_id
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
          <div class="rec-card-head-left">
            <span class="review-avatar rec-card-avatar" aria-hidden="true">
              <?php if (!empty($b['client_avatar_path'])): ?>
                <img src="<?= e(widgetAdminSrc($b['client_avatar_path'])) ?>" alt="" class="review-avatar-img">
              <?php else: ?>
                <span class="review-avatar-fallback"><?= e(mb_strtoupper(mb_substr($b['client_name'], 0, 1))) ?></span>
              <?php endif; ?>
            </span>
            <div class="rec-card-head-name">
              <span class="rec-card-id">#<?= (int)$b['id'] ?></span>
              <strong><?= e($b['client_name']) ?></strong>
            </div>
          </div>
          <span class="badge <?= e($b['status']) ?>"><?= e(bookingStatusLabel($b['status'], 'ru')) ?></span>
        </div>
        <div class="rec-card-body">
          <?php if ($b['wanted_date']): ?>
          <div class="rec-card-row"><span class="rec-card-icon">📅</span><span><?= e(formatBookingDateTime($b['wanted_date'])) ?></span></div>
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
          <?php if ($b['status'] === 'cancelled' && $b['cancel_reason']): ?>
          <div class="rec-card-row"><span class="rec-card-icon">✖️</span><span>Причина отмены: <?= e($b['cancel_reason']) ?></span></div>
          <?php endif; ?>
          <?php if ($b['slot_id']): ?>
          <div class="rec-card-row"><span class="rec-card-icon">🗓️</span><span class="badge <?= $b['slot_is_booked'] ? 'done' : 'new' ?>"><?= $b['slot_is_booked'] ? 'слот занят' : 'слот свободен' ?></span></div>
          <?php endif; ?>
        </div>
        <div class="rec-card-actions">
          <?php if ($b['status'] === 'new'): ?>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
            <button name="action" value="confirm" class="btn rec-card-btn">Подтвердить</button>
          </form>
          <?php endif; ?>
          <?php if (in_array($b['status'], ['new', 'confirmed'], true)): ?>
          <button type="button" class="btn ghost rec-card-btn rec-card-btn-danger" data-cancel-open data-id="<?= (int)$b['id'] ?>">Отменить</button>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$bookings): ?>
      <p class="rec-empty">Записей пока нет.</p>
    <?php endif; ?>
  </div>

  <!-- Модалка причины отмены — общая для всех карточек, id записи
       подставляется в скрытое поле при открытии (см. скрипт ниже). -->
  <div class="modal-overlay" id="cancelModal">
    <div class="modal-box">
      <h3>Причина отмены</h3>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="action" value="cancel">
        <input type="hidden" name="id" id="cancelBookingId" value="">
        <div class="form-field">
          <label>Что написать клиенту (придёт пуш-уведомлением)</label>
          <textarea name="reason" id="cancelReason" rows="3" placeholder="Например: на это время мастер уже занят, выберите другое время"></textarea>
        </div>
        <button type="submit" class="btn full">Отменить запись</button>
        <button type="button" class="btn ghost full" style="margin-top:8px;" data-modal-close>Назад</button>
      </form>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var cancelModal = document.getElementById('cancelModal');
  var cancelBookingId = document.getElementById('cancelBookingId');
  var cancelReason = document.getElementById('cancelReason');

  document.querySelectorAll('[data-cancel-open]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      cancelBookingId.value = btn.dataset.id;
      cancelReason.value = '';
      cancelModal.classList.add('open');
    });
  });

  document.querySelectorAll('[data-modal-close]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var overlay = btn.closest('.modal-overlay');
      if (overlay) overlay.classList.remove('open');
    });
  });

  cancelModal.addEventListener('click', function (e) {
    if (e.target === cancelModal) cancelModal.classList.remove('open');
  });
});
</script>
</body>
</html>
