<?php
/**
 * get_slots.php
 * Возвращает JSON со свободным/занятым временем на конкретную неделю
 * (понедельник — воскресенье) для календаря записи.
 *
 * Параметр ?offset=N (0, 1, 2...) — на сколько недель вперёд от текущей
 * листать календарь стрелочками «‹ ›» на сайте. Общее окно ограничено
 * ближайшими ~30 днями (5 недель, offset 0..4) — дальше стрелка «вперёд»
 * не пускает.
 *
 * Отдаём Content-Type: application/json сразу и подавляем вывод
 * предупреждений PHP в тело ответа, чтобы случайный notice/warning
 * на хостинге не ломал JSON на клиенте.
 */
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

try {
    require __DIR__ . '/config.php';
    require __DIR__ . '/includes/functions.php';
    require __DIR__ . '/includes/lang.php';

    $pdo = getDB();

    // Окно навигации: сегодняшняя неделя (offset 0) + ещё немного вперёд,
    // всего покрывая ближайшие ~30 дней.
    $maxOffset = (int)floor(30 / 7); // 4 — то есть недели 0..4 (5 штук)

    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    if ($offset < 0) $offset = 0;
    if ($offset > $maxOffset) $offset = $maxOffset;

    $weekStart = (new DateTime('monday this week'))->modify("+{$offset} week");
    $weekEnd = (clone $weekStart)->modify('+6 days');

    $stmt = $pdo->prepare(
        "SELECT * FROM available_slots WHERE slot_date BETWEEN ? AND ? ORDER BY slot_date, slot_time"
    );
    $stmt->execute([$weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d')]);
    $slots = $stmt->fetchAll();

    $byDate = [];
    foreach ($slots as $s) {
        $byDate[$s['slot_date']][] = [
            'id'     => (int)$s['id'],
            'time'   => $s['slot_time'],
            'booked' => (bool)$s['is_booked'],
        ];
    }

    $weekdays = t('weekdays');
    $months = t('months');
    $today = new DateTime('today');

    $days = [];
    for ($i = 0; $i < 7; $i++) {
        $d = (clone $weekStart)->modify("+{$i} day");
        $dateKey = $d->format('Y-m-d');
        $days[] = [
            'date'    => $dateKey,
            'weekday' => $weekdays[$i],
            'day'     => (int)$d->format('j'),
            'month'   => $months[(int)$d->format('n') - 1],
            'is_past' => $d < $today,
            'slots'   => $byDate[$dateKey] ?? [],
        ];
    }

    echo json_encode([
        'success'    => true,
        'week_start' => $weekStart->format('Y-m-d'),
        'week_end'   => $weekEnd->format('Y-m-d'),
        'offset'     => $offset,
        'can_prev'   => $offset > 0,
        'can_next'   => $offset < $maxOffset,
        'days'       => $days,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'server_error']);
}
