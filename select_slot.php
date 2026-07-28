<?php
/**
 * select_slot.php
 * Клиент выбрал время в календаре и нажал "Записаться".
 * Слот НЕ помечается занятым автоматически — заявка просто сохраняется
 * в "Записи" панели администратора. Мама сама решает, отметить ли это
 * время занятым (в разделе «Записи» или «Свободное время» панели),
 * после того как договорится с клиентом лично в Instagram/Viber/Telegram/
 * по телефону.
 */
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

try {
    require __DIR__ . '/config.php';
    require __DIR__ . '/includes/functions.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrfCheck()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'bad_request']);
        exit;
    }

    if (!isAdmin() && !isSiteUser()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'auth_required']);
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

    $greetName = trim((string)($_POST['visitor_name'] ?? ''));
    $clientName = $greetName !== '' ? $greetName : 'Клиент (через сайт)';

    // Слот остаётся видимым как свободный в календаре — заявка попадает
    // в "Записи" панели со ссылкой на этот слот (slot_id), чтобы мама
    // одним кликом отметила время занятым, когда договорится с клиентом.
    $pdo->prepare(
        "INSERT INTO bookings (client_name, phone, service, wanted_date, comment, slot_id) VALUES (?, '', '', ?, ?, ?)"
    )->execute([$clientName, $slot['slot_date'] . ' ' . $slot['slot_time'], 'Слот выбран через календарь на сайте', $slotId]);

    echo json_encode([
        'success' => true,
        'date'    => $slot['slot_date'],
        'time'    => $slot['slot_time'],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'server_error']);
}
