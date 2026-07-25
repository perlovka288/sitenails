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
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Свободное время — Панель управления</title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="admin-shell">
  <?php require __DIR__ . '/includes/nav.php'; ?>

  <div class="card">
    <h3>Добавить свободное время</h3>
    <form method="post" style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action" value="add">
      <div class="form-field" style="margin:0;">
        <label>Дата</label>
        <input type="date" name="slot_date" required value="<?= e($weekStart->format('Y-m-d')) ?>">
      </div>
      <div class="form-field" style="margin:0;">
        <label>Время</label>
        <input type="time" name="slot_time" required value="10:00">
      </div>
      <button type="submit" class="btn">Добавить</button>
    </form>
    <p style="color:var(--ink-soft); font-size:13px; margin-bottom:0;">
      Добавленное время сразу появится в календаре записи на сайте — клиент сможет
      выбрать его и написать вам в Instagram / Viber / Telegram / по телефону.
    </p>
  </div>

  <div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
      <a class="btn ghost" style="padding:8px 14px;font-size:13px;" href="?week=<?= e($prevWeek) ?>">← Прошлая неделя</a>
      <strong><?= e($weekStart->format('d.m')) ?> – <?= e($weekEnd->format('d.m.Y')) ?></strong>
      <a class="btn ghost" style="padding:8px 14px;font-size:13px;" href="?week=<?= e($nextWeek) ?>">Следующая неделя →</a>
    </div>

    <?php for ($i = 0; $i < 7; $i++): ?>
      <?php
        $d = (clone $weekStart)->modify("+{$i} day");
        $dateKey = $d->format('Y-m-d');
        $daySlots = $byDate[$dateKey] ?? [];
      ?>
      <div style="margin-bottom:16px;">
        <div style="font-weight:700; margin-bottom:6px;"><?= e($weekdays[$i]) ?>, <?= e($d->format('d.m')) ?></div>
        <?php if (!$daySlots): ?>
          <div style="color:var(--ink-soft); font-size:13px;">Нет времени на этот день</div>
        <?php else: ?>
          <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <?php foreach ($daySlots as $s): ?>
              <div style="display:flex; align-items:center; gap:6px; border:1px solid var(--line); border-radius:10px; padding:6px 10px;">
                <span class="badge <?= $s['is_booked'] ? 'done' : 'new' ?>"><?= e($s['slot_time']) ?> · <?= $s['is_booked'] ? 'занято' : 'свободно' ?></span>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                  <button class="btn ghost" style="padding:4px 8px;font-size:11px;" title="Переключить занято/свободно">⇄</button>
                </form>
                <form method="post" style="display:inline;" onsubmit="return confirm('Удалить это время?');">
                  <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                  <button class="btn ghost" style="padding:4px 8px;font-size:11px;" title="Удалить">✕</button>
                </form>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endfor; ?>
  </div>
</div>
</body>
</html>
