<?php
/**
 * select_slot.php
 * Клиент выбрал время в календаре, заполнил анкету записи (имя, телефон,
 * услуги, способ связи) и нажал "Подтвердить запись" — см. модалку
 * #bookingFormOverlay в index.php. Слот пока НЕ помечается занятым в этот
 * момент — заявка сохраняется в "Записи" панели администратора как "новая".
 * Слот автоматически становится занятым, когда мама нажимает "Подтвердить"
 * в панели (см. admin-x7k9m2/bookings.php) — то есть после того, как она
 * реально согласовала время с клиентом, а не сразу по любой заявке
 * (иначе случайные/спамные заявки блокировали бы реальное время).
 */
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

try {
    require __DIR__ . '/config.php';
    require __DIR__ . '/includes/functions.php';
    require __DIR__ . '/includes/lang.php';
    require __DIR__ . '/includes/onesignal.php';

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

    // ==== Данные анкеты записи ====
    $clientName = trim((string)($_POST['client_name'] ?? ''));
    $phone      = trim((string)($_POST['phone'] ?? ''));
    $contactMethod = $_POST['contact_method'] ?? '';
    $contactMethod = in_array($contactMethod, ['instagram', 'viber', 'phone'], true) ? $contactMethod : '';

    if ($clientName === '') {
        $greetName = trim((string)($_POST['visitor_name'] ?? ''));
        $clientName = $greetName !== '' ? $greetName : 'Клиент (через сайт)';
    }
    if (mb_strlen($clientName) > 100) {
        $clientName = mb_substr($clientName, 0, 100);
    }
    $phone = mb_substr($phone, 0, 40);

    // Выбранные услуги (id из price_items) собираем в одну читаемую строку,
    // которую видно в "Записях" панели управления. Ни один из трёх списков
    // не обязателен, но хотя бы один пункт должен быть выбран.
    $serviceIds = array_filter([
        (int)($_POST['service_manicure'] ?? 0),
        (int)($_POST['service_pedicure'] ?? 0),
        (int)($_POST['service_extra'] ?? 0),
    ]);

    if (!$serviceIds) {
        echo json_encode(['success' => false, 'error' => 'no_service']);
        exit;
    }

    $servicePlaceholders = implode(',', array_fill(0, count($serviceIds), '?'));
    $serviceStmt = $pdo->prepare("SELECT title, price FROM price_items WHERE id IN ($servicePlaceholders)");
    $serviceStmt->execute(array_values($serviceIds));
    $serviceLabels = [];
    foreach ($serviceStmt->fetchAll() as $__row) {
        $serviceLabels[] = $__row['title'] . ' (' . $__row['price'] . ')';
    }
    $serviceText = implode('; ', $serviceLabels);

    // Если заявку оставляет зарегистрированный посетитель — сразу привязываем
    // её к его аккаунту (для истории записей / мини-профиля в шапке сайта).
    $__siteUser = currentSiteUser();
    $userId = $__siteUser ? (int)$__siteUser['id'] : null;

    // Слот остаётся видимым как свободный в календаре — заявка попадает
    // в "Записи" панели со ссылкой на этот слот (slot_id), чтобы мама
    // одним кликом отметила время занятым, когда договорится с клиентом.
    $pdo->prepare(
        "INSERT INTO bookings (client_name, phone, service, wanted_date, comment, slot_id, user_id, contact_method)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    )->execute([
        $clientName,
        $phone,
        $serviceText,
        $slot['slot_date'] . ' ' . $slot['slot_time'],
        'Запись через анкету на сайте',
        $slotId,
        $userId,
        $contactMethod,
    ]);

    // Пуш всем администраторам сайта — см. includes/onesignal.php.
    notifyAdminsNewBooking($pdo, $clientName, $slot['slot_date'] . ' ' . $slot['slot_time'], $serviceText);

    echo json_encode([
        'success' => true,
        'date'    => $slot['slot_date'],
        'time'    => $slot['slot_time'],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'server_error']);
}
