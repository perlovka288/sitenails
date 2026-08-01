<?php
// admin-x7k9m2/slots.php
// Объединённая вкладка "Запись" — заменяет прежние отдельные вкладки
// "Записи" и "Свободное время". Состоит из двух блоков:
//   1) Активные заявки (status = 'new') — ждут подтверждения/отклонения.
//   2) Календарь-аккордеон по дням недели — в каждом дне вперемешку по
//      времени показаны подтверждённые записи и свободные слоты.
// Все действия (подтвердить/отклонить/добавить-переключить-удалить время/
// сохранить заметку) работают и как обычный POST+redirect (если JS
// выключен), и как AJAX (см. assets/admin.js) — тогда в ответе сразу
// приходит свежий HTML обоих блоков, страница не перезагружается.
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/onesignal.php';
require __DIR__ . '/includes/auth_check.php';

$pdo = getDB();

function slotsPageIsAjax(): bool
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch'
        || ($_POST['ajax'] ?? '') === '1';
}

// Считает всё, что нужно двум блокам страницы для конкретной недели,
// и возвращает готовый HTML каждого блока. Используется и при обычной
// загрузке страницы, и при AJAX-обновлении после любого действия —
// один источник правды, без дублирования разметки/запросов.
function slotsPageRenderBlocks(PDO $pdo, string $weekParam): array
{
    // ---- Блок 1: активные заявки, ждут подтверждения ----
    $activeBookings = $pdo->query("
        SELECT b.*, u.avatar_path AS client_avatar_path
        FROM bookings b
        LEFT JOIN site_users u ON u.id = b.user_id
        WHERE b.status = 'new'
        ORDER BY b.created_at DESC
    ")->fetchAll();

    // ---- Блок 2: неделя для календаря-аккордеона ----
    try {
        $weekStart = $weekParam !== '' ? new DateTime($weekParam) : new DateTime('monday this week');
    } catch (Exception $e) {
        $weekStart = new DateTime('monday this week');
    }
    $weekStart->modify('monday this week');
    $weekEnd = (clone $weekStart)->modify('+6 days');
    $prevWeek = (clone $weekStart)->modify('-7 days')->format('Y-m-d');
    $nextWeek = (clone $weekStart)->modify('+7 days')->format('Y-m-d');
    $weekStartStr = $weekStart->format('Y-m-d');
    $weekEndStr = $weekEnd->format('Y-m-d');

    // Свободные (и вручную занятые без привязанной подтверждённой записи)
    // слоты. Слоты с привязанной подтверждённой записью не показываем
    // отдельным чипом — они уже показаны как карточка записи ниже, чтобы
    // одно и то же время не дублировалось в дне.
    $slotStmt = $pdo->prepare("
        SELECT s.*, bk.id AS linked_booking_id
        FROM available_slots s
        LEFT JOIN bookings bk ON bk.slot_id = s.id AND bk.status = 'confirmed'
        WHERE s.slot_date BETWEEN ? AND ?
        ORDER BY s.slot_date, s.slot_time
    ");
    $slotStmt->execute([$weekStartStr, $weekEndStr]);

    $byDate = [];
    foreach ($slotStmt->fetchAll() as $s) {
        if (!empty($s['linked_booking_id'])) {
            continue;
        }
        $byDate[$s['slot_date']][] = ['type' => 'slot', 'time' => $s['slot_time'], 'data' => $s];
    }

    // Подтверждённые записи недели (формат wanted_date — "Y-m-d H:i",
    // так его сохраняет select_slot.php при записи через календарь).
    $bookingStmt = $pdo->prepare("
        SELECT * FROM bookings
        WHERE status = 'confirmed' AND wanted_date >= ? AND wanted_date <= ?
        ORDER BY wanted_date
    ");
    $bookingStmt->execute([$weekStartStr . ' 00:00', $weekEndStr . ' 23:59']);

    foreach ($bookingStmt->fetchAll() as $b) {
        $wd = (string)$b['wanted_date'];
        $dateKey = substr($wd, 0, 10);
        $timeKey = (strlen($wd) >= 16) ? substr($wd, 11, 5) : '00:00';
        if ($dateKey < $weekStartStr || $dateKey > $weekEndStr) {
            continue; // на всякий случай, если формат даты нестандартный
        }
        $byDate[$dateKey][] = ['type' => 'booking', 'time' => $timeKey, 'data' => $b];
    }

    foreach ($byDate as $d => &$items) {
        usort($items, fn($a, $b) => strcmp($a['time'], $b['time']));
    }
    unset($items);

    $weekdays = ['Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота', 'Воскресенье'];
    $weekdaysShort = ['ПН', 'ВТ', 'СР', 'ЧТ', 'ПТ', 'СБ', 'ВС'];
    $todayKey = (new DateTime())->format('Y-m-d');

    ob_start();
    include __DIR__ . '/includes/slots_active_block.php';
    $activeHtml = ob_get_clean();

    ob_start();
    include __DIR__ . '/includes/slots_calendar_block.php';
    $calendarHtml = ob_get_clean();

    return ['active' => $activeHtml, 'calendar' => $calendarHtml, 'weekStartStr' => $weekStartStr];
}

$weekParam = trim((string)($_GET['week'] ?? $_POST['week'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ajax = slotsPageIsAjax();

    if (!csrfCheck()) {
        if ($ajax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'csrf']);
            exit;
        }
        redirect('slots.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'confirm') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE bookings SET status = 'confirmed', updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);

        $__b = $pdo->prepare('SELECT * FROM bookings WHERE id = ?');
        $__b->execute([$id]);
        $__booking = $__b->fetch();

        // Слот (если запись пришла через календарь) занимается автоматически
        // вместе с подтверждением — вручную дополнительно жать ничего не нужно.
        if ($__booking && !empty($__booking['slot_id'])) {
            $pdo->prepare('UPDATE available_slots SET is_booked = 1 WHERE id = ?')->execute([(int)$__booking['slot_id']]);
        }

        // Пуш клиенту "Ваша запись принята".
        if ($__booking && !empty($__booking['user_id'])) {
            $__phone = getSetting('site_phone', '');
            $__address = getSetting('site_address', '');
            $__msg = 'Чекаємо на вас ' . $__booking['wanted_date'] . '! 💅';
            if ($__phone !== '') $__msg .= ' Майстер: ' . $__phone . '.';
            if ($__address !== '') $__msg .= ' Адреса: ' . $__address;
            sendOneSignalPush((int)$__booking['user_id'], 'Ваш запис підтверджено ✨', $__msg, 'profile.php#booking-' . (int)$__booking['id']);
        }
    } elseif ($action === 'cancel') {
        $id = (int)($_POST['id'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');

        $__b = $pdo->prepare('SELECT * FROM bookings WHERE id = ?');
        $__b->execute([$id]);
        $__booking = $__b->fetch();

        if ($__booking) {
            $pdo->prepare("UPDATE bookings SET status = 'cancelled', cancel_reason = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute([$reason !== '' ? $reason : null, $id]);

            if (!empty($__booking['slot_id'])) {
                $pdo->prepare('UPDATE available_slots SET is_booked = 0 WHERE id = ?')->execute([(int)$__booking['slot_id']]);
            }

            if (!empty($__booking['user_id'])) {
                $__msg = 'Ваш запис на ' . $__booking['wanted_date'] . ' не прийнято.';
                if ($reason !== '') {
                    $__msg .= ' Причина: ' . $reason;
                }
                sendOneSignalPush((int)$__booking['user_id'], 'Запис скасовано', $__msg, 'profile.php#booking-' . (int)$__booking['id']);
            }
        }
    } elseif ($action === 'add_slot') {
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
    } elseif ($action === 'toggle_slot') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE available_slots SET is_booked = 1 - is_booked WHERE id = ?')->execute([$id]);
    } elseif ($action === 'delete_slot') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM available_slots WHERE id = ?')->execute([$id]);
    } elseif ($action === 'save_note') {
        $id = (int)($_POST['id'] ?? 0);
        $note = trim((string)($_POST['note'] ?? ''));
        if (mb_strlen($note) > 200) {
            $note = mb_substr($note, 0, 200);
        }
        $pdo->prepare('UPDATE bookings SET admin_note = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
            ->execute([$note !== '' ? $note : null, $id]);
    }

    if ($ajax) {
        $blocks = slotsPageRenderBlocks($pdo, $weekParam);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'active' => $blocks['active'], 'calendar' => $blocks['calendar']]);
        exit;
    }
    redirect('slots.php' . ($weekParam !== '' ? '?week=' . urlencode($weekParam) : ''));
}

// ==================== GET: обычная загрузка страницы ====================

// Режим "только блоки" — используется JS при переключении недели в
// календаре, чтобы обновить только блок 2 (и заодно блок 1) без полной
// перезагрузки страницы.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['ajax_blocks'] ?? '') === '1') {
    $blocks = slotsPageRenderBlocks($pdo, $weekParam);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'active' => $blocks['active'], 'calendar' => $blocks['calendar']]);
    exit;
}

$blocks = slotsPageRenderBlocks($pdo, $weekParam);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Запись — Панель управления</title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>
<div class="admin-shell">
  <?php require __DIR__ . '/includes/nav.php'; ?>

  <!-- ==================== Блок 1: активные заявки ==================== -->
  <div id="activeBookingsBlock"><?= $blocks['active'] ?></div>

  <!-- ==================== Блок 2: календарь-аккордеон ==================== -->
  <div id="calendarBlock"><?= $blocks['calendar'] ?></div>

  <!-- Модалка причины отмены — общая для всех карточек заявок -->
  <div class="modal-overlay" id="cancelModal">
    <div class="modal-box">
      <h3>Причина отмены</h3>
      <form method="post" data-ajax-form>
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
window.ADMIN_CSRF_TOKEN = <?= json_encode(csrfToken()) ?>;
window.SLOTS_CURRENT_WEEK = <?= json_encode($blocks['weekStartStr']) ?>;
</script>
<script src="assets/admin.js?v=<?= filemtime(__DIR__ . '/assets/admin.js') ?>" defer></script>
</body>
</html>
