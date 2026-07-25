<?php
/**
 * get_slots.php
 * Возвращает JSON со свободным/занятым временем на текущую неделю
 * (понедельник — воскресенье) для календаря записи.
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

    // Всегда берём текущую календарную неделю (понедельник этой недели) —
    // переключения недель на сайте больше нет.
    $weekStart = new DateTime('monday this week');
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
        'days'       => $days,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'server_error']);
}
