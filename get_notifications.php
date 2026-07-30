<?php
/**
 * get_notifications.php
 * Отдаёт JSON-список уведомлений для Центра уведомлений (колокольчик в
 * шапке сайта, includes/header.php). Источник уведомлений — собственные
 * записи текущего клиента (bookings.user_id), у которых статус сменился
 * на "подтверждена" или "выполнена" (см. admin-x7k9m2/bookings.php).
 * Отдельной таблицы уведомлений нет и не нужна — читаем прямо из bookings.
 */
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

try {
    require __DIR__ . '/config.php';
    require __DIR__ . '/includes/functions.php';
    require __DIR__ . '/includes/lang.php';

    $siteUser = currentSiteUser();
    if (!$siteUser) {
        echo json_encode(['success' => true, 'items' => []]);
        exit;
    }

    $lang = currentLang();
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT id, status, wanted_date, service, updated_at, created_at
        FROM bookings
        WHERE user_id = ? AND status IN ('confirmed', 'done')
        ORDER BY COALESCE(updated_at, created_at) DESC, id DESC
        LIMIT 20
    ");
    $stmt->execute([(int)$siteUser['id']]);
    $rows = $stmt->fetchAll();

    $items = [];
    foreach ($rows as $b) {
        $when = $b['updated_at'] ?: $b['created_at'];
        if ($b['status'] === 'confirmed') {
            $message = $lang === 'ua'
                ? 'Ваш запис на ' . $b['wanted_date'] . ' успішно підтверджено! 💅'
                : 'Ваша запись на ' . $b['wanted_date'] . ' успешно подтверждена! 💅';
        } else {
            $message = $lang === 'ua'
                ? 'Запис на ' . $b['wanted_date'] . ' відмічено як виконаний. Чекаємо на вас знову! ✨'
                : 'Запись на ' . $b['wanted_date'] . ' отмечена как выполненная. Ждём вас снова! ✨';
        }
        $items[] = [
            'id'         => (int)$b['id'],
            'status'     => $b['status'],
            'message'    => $message,
            'updated_at' => $when,
            'time_label' => $when ? date('d.m.Y H:i', strtotime($when)) : '',
        ];
    }

    echo json_encode(['success' => true, 'items' => $items]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'items' => []]);
}
