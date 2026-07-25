<?php
/**
 * select_slot.php
 * Клиент выбрал время в календаре и нажал "Записаться".
 * Помечаем слот занятым и сохраняем заявку в "Записи" панели администратора,
 * чтобы мама видела, какое время забронировано (клиент подтверждает детали
 * лично в Instagram/Viber/Telegram/по телефону).
 */
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrfCheck()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'bad_request']);
    exit;
}

$slotId = (int)($_POST['slot_id'] ?? 0);
if ($slotId <= 0) {
    echo json_encode(['success' => false, 'error' => 'no_slot']);
    exit;
}

$pdo = getDB();

$stmt = $pdo->prepare('SELECT * FROM available_slots WHERE id = ?');
$stmt->execute([$slotId]);
$slot = $stmt->fetch();

if (!$slot) {
    echo json_encode(['success' => false, 'error' => 'not_found']);
    exit;
}

if ((int)$slot['is_booked'] === 1) {
    echo json_encode(['success' => false, 'error' => 'already_booked']);
    exit;
}

// Атомарно помечаем занятым (защита от двойного клика/гонки)
$update = $pdo->prepare('UPDATE available_slots SET is_booked = 1 WHERE id = ? AND is_booked = 0');
$update->execute([$slotId]);

if ($update->rowCount() === 0) {
    echo json_encode(['success' => false, 'error' => 'already_booked']);
    exit;
}

$greetName = trim((string)($_POST['visitor_name'] ?? ''));
$clientName = $greetName !== '' ? $greetName : 'Клиент (через сайт)';

$pdo->prepare(
    "INSERT INTO bookings (client_name, phone, service, wanted_date, comment) VALUES (?, '', '', ?, ?)"
)->execute([$clientName, $slot['slot_date'] . ' ' . $slot['slot_time'], 'Слот выбран через календарь на сайте']);

echo json_encode([
    'success' => true,
    'date'    => $slot['slot_date'],
    'time'    => $slot['slot_time'],
]);
