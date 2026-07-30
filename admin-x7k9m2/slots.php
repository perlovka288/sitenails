<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/includes/auth_check.php';

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfCheck()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $date = trim($_POST['slot_date'] ?? '');
        $time = trim($_POST['slot_time'] ?? '');
        if ($date !== '' && $time !== '') {
            $exists = $pdo->prepare('SELECT COUNT(*) FROM available_slots WHERE slot_date = ? AND slot_time = ?');
            $exists->execute([$date, $time]);
            if ((int)$exists->fetchColumn() === 0) {
                $pdo->prepare('INSERT INTO available_slots (slot_date, slot_time) VALUES (?, ?)')
                    ->execute([$date, $time]);
            }
        }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE available_slots SET is_booked = 1 - is_booked WHERE id = ?')->execute([$id]);
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM available_slots WHERE id = ?')->execute([$id]);
    }

    redirect('slots.php' . (isset($_GET['week']) ? '?week=' . urlencode($_GET['week']) : ''));
}

try {
    $weekStart = isset($_GET['week']) ? new DateTime($_GET['week']) : new DateTime('monday this week');
} catch (Exception $e) {
    $weekStart = new DateTime('monday this week');
}
$weekStart->modify('monday this week');
$weekEnd = (clone $weekStart)->modify('+6 days');
$prevWeek = (clone $weekStart)->modify('-7 days')->format('Y-m-d');
$nextWeek = (clone $weekStart)->modify('+7 days')->format('Y-m-d');

$stmt = $pdo->prepare('SELECT * FROM available_slots WHERE slot_date BETWEEN ? AND ? ORDER BY slot_date, slot_time');
$stmt->execute([$weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d')]);
$slots = $stmt->fetchAll();

$byDate = [];
foreach ($slots as $s) {
    $byDate[$s['slot_date']][] = $s;
}

$weekdays = ['Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота', 'Воскресенье'];
$weekdaysShort = ['ПН', 'ВТ', 'СР', 'ЧТ', 'ПТ', 'СБ', 'ВС'];
$todayKey = (new DateTime())->format('Y-m-d');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Свободное время — Панель управления</title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>
<div class="admin-shell">
  <?php require __DIR__ . '/includes/nav.php'; ?>

  <div class="card">
    <h3 style="margin-bottom:10px;">Добавить свободное время</h3>
    <form method="post" class="slot-quick-add">
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action" value="add">
      <input type="date" name="slot_date" required value="<?= e($weekStart->format('Y-m-d')) ?>">
      <input type="time" name="slot_time" required value="10:00">
      <button type="submit" class="slot-quick-add-btn" title="Добавить время" aria-label="Добавить время">+</button>
    </form>
    <p class="field-hint">
      Добавленное время сразу появится в календаре записи на сайте — клиент сможет
      выбрать его и написать вам в Instagram / Viber / Telegram / по телефону.
    </p>
  </div>

  <div class="card">
    <div class="slot-week-nav">
      <a class="icon-btn" href="?week=<?= e($prevWeek) ?>" aria-label="Прошлая неделя" title="Прошлая неделя">‹</a>
      <strong><?= e($weekStart->format('d.m')) ?> – <?= e($weekEnd->format('d.m.Y')) ?></strong>
      <a class="icon-btn" href="?week=<?= e($nextWeek) ?>" aria-label="Следующая неделя" title="Следующая неделя">›</a>
    </div>

    <div class="slot-day-list">
      <?php for ($i = 0; $i < 7; $i++): ?>
        <?php
          $d = (clone $weekStart)->modify("+{$i} day");
          $dateKey = $d->format('Y-m-d');
          $daySlots = $byDate[$dateKey] ?? [];
        ?>
        <div class="slot-day-card<?= $dateKey === $todayKey ? ' is-today' : '' ?>">
          <div class="slot-day-card-head">
            <div class="slot-day-card-date">
              <span class="wd"><?= e($weekdaysShort[$i]) ?></span>
              <span class="num"><?= e($d->format('d')) ?></span>
            </div>
            <div class="slot-day-card-title"><?= e($weekdays[$i]) ?>, <?= e($d->format('d.m')) ?></div>
            <button type="button" class="icon-btn slot-day-add-btn" data-day-add="<?= e($dateKey) ?>" title="Добавить время на этот день" aria-label="Добавить время">+</button>
          </div>
          <div class="slot-day-card-body">
            <?php if (!$daySlots): ?>
              <span class="slot-day-empty">Нет времени на этот день</span>
            <?php else: ?>
              <?php foreach ($daySlots as $s): ?>
                <div class="slot-chip">
                  <span class="badge <?= $s['is_booked'] ? 'done' : 'new' ?>"><?= e($s['slot_time']) ?> · <?= $s['is_booked'] ? 'занято' : 'свободно' ?></span>
                  <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                    <button class="icon-btn icon-btn--sm" title="Переключить занято/свободно">⇄</button>
                  </form>
                  <form method="post" onsubmit="return confirm('Удалить это время?');">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                    <button class="icon-btn icon-btn--sm icon-btn--danger" title="Удалить">✕</button>
                  </form>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
            <form method="post" class="slot-inline-add" id="inlineAdd-<?= e($dateKey) ?>" hidden>
              <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
              <input type="hidden" name="action" value="add">
              <input type="hidden" name="slot_date" value="<?= e($dateKey) ?>">
              <input type="time" name="slot_time" required autofocus>
              <button type="submit" class="icon-btn icon-btn--sm" title="Подтвердить">✓</button>
              <button type="button" class="icon-btn icon-btn--sm" data-day-add-cancel title="Отмена">✕</button>
            </form>
          </div>
        </div>
      <?php endfor; ?>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-day-add]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var row = document.getElementById('inlineAdd-' + btn.dataset.dayAdd);
      if (!row) return;
      row.hidden = false;
      btn.hidden = true;
      var timeInput = row.querySelector('input[type="time"]');
      if (timeInput) timeInput.focus();
    });
  });
  document.querySelectorAll('[data-day-add-cancel]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var row = btn.closest('.slot-inline-add');
      if (!row) return;
      row.hidden = true;
      var addBtn = document.querySelector('[data-day-add="' + row.querySelector('[name="slot_date"]').value + '"]');
      if (addBtn) addBtn.hidden = false;
    });
  });
});
</script>
</body>
</html>
