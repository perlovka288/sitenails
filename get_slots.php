<?php
/**
 * get_slots.php
 * Возвращает JSON со свободным/занятым временем на неделю для календаря записи.
 * GET-параметр week_start = понедельник недели (YYYY-MM-DD).
 */
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/lang.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = getDB();
$lang = currentLang();

$weekStartParam = $_GET['week_start'] ?? '';
try {
    if ($weekStartParam !== '') {
        $weekStart = new DateTime($weekStartParam);
    } else {
        $weekStart = new DateTime('monday this week');
    }
} catch (Exception $e) {
    $weekStart = new DateTime('monday this week');
}

// Всегда приводим к понедельнику этой недели, чтобы сетка была стабильной
$weekStart->modify('monday this week');

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

$days = [];
for ($i = 0; $i < 7; $i++) {
    $d = (clone $weekStart)->modify("+{$i} day");
    $dateKey = $d->format('Y-m-d');
    $days[] = [
        'date'    => $dateKey,
        'weekday' => $weekdays[$i],
        'day'     => (int)$d->format('j'),
        'month'   => $months[(int)$d->format('n') - 1],
        'is_past' => $d < new DateTime('today'),
        'slots'   => $byDate[$dateKey] ?? [],
    ];
}

echo json_encode([
    'week_start' => $weekStart->format('Y-m-d'),
    'week_prev'  => (clone $weekStart)->modify('-7 days')->format('Y-m-d'),
    'week_next'  => (clone $weekStart)->modify('+7 days')->format('Y-m-d'),
    'days'       => $days,
], JSON_UNESCAPED_UNICODE);
