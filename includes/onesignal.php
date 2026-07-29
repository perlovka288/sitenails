<?php
/**
 * includes/onesignal.php
 *
 * Отправка push-уведомлений клиентам через OneSignal (бесплатный сервис,
 * без своего бота/сервера уведомлений — то, что и просили: не через бот,
 * а системное уведомление на телефон, как от мессенджера).
 *
 * Работает одним HTTPS-запросом к api.onesignal.com — никакого шифрования
 * Web Push вручную писать не нужно, этим занимается сам OneSignal.
 * На бесплатном хостинге (InfinityFree и т.п.) единственный риск — если
 * там ограничены исходящие запросы к внешним доменам; функция просто
 * тихо логирует неудачу и не ломает подтверждение записи.
 */

// Отправляет push конкретному клиенту (по id из site_users — используется
// как OneSignal External ID, см. OneSignal.login() в assets/js/script.js).
function sendOneSignalPush(int $userId, string $title, string $message): bool
{
    $appId = getSetting('onesignal_app_id', '');
    $apiKey = getSetting('onesignal_api_key', '');

    if ($appId === '' || $apiKey === '') {
        // Уведомления ещё не настроены в панели управления (Настройки →
        // «Уведомления о записи») — просто ничего не отправляем.
        return false;
    }

    if (!function_exists('curl_init')) {
        error_log('sendOneSignalPush: расширение cURL недоступно на хостинге.');
        return false;
    }

    $payload = [
        'app_id'          => $appId,
        'headings'        => ['ru' => $title, 'en' => $title],
        'contents'        => ['ru' => $message, 'en' => $message],
        'include_aliases' => ['external_id' => [(string)$userId]],
        'target_channel'  => 'push',
    ];

    $ch = curl_init('https://api.onesignal.com/notifications');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Key ' . $apiKey,
        ],
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        error_log('sendOneSignalPush: cURL error — ' . $curlError . ' (возможно, хостинг блокирует исходящие запросы)');
        return false;
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        error_log('sendOneSignalPush: OneSignal вернул HTTP ' . $httpCode . ' — ' . $response);
        return false;
    }

    return true;
}
