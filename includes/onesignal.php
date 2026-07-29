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

// Пишет строку и в error_log (если доступен), и в свой файл push_log.txt —
// на бесплатных хостингах вроде InfinityFree владелец сайта обычно НЕ имеет
// доступа к системному логу PHP, поэтому свой файл — единственный способ
// реально увидеть, что пошло не так (401 от OneSignal, недоступен cURL и т.п.).
function pushLog(string $line): void
{
    error_log('sendOneSignalPush: ' . $line);
    $logFile = __DIR__ . '/../data/push_log.txt';
    $entry = '[' . date('Y-m-d H:i:s') . '] ' . $line . "\n";
    // Ограничиваем размер файла — держим только последние ~500 строк, чтобы
    // не разрастался бесконечно на бесплатном хостинге с лимитом места.
    @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    $lines = @file($logFile);
    if ($lines && count($lines) > 500) {
        @file_put_contents($logFile, implode('', array_slice($lines, -500)), LOCK_EX);
    }
}

// Отправляет push конкретному клиенту (по id из site_users — используется
// как OneSignal External ID, см. OneSignal.login() в assets/js/script.js).
function sendOneSignalPush(int $userId, string $title, string $message): bool
{
    $appId = getSetting('onesignal_app_id', '');
    $apiKey = getSetting('onesignal_api_key', '');

    if ($appId === '' || $apiKey === '') {
        // Уведомления ещё не настроены в панели управления (Настройки →
        // «Уведомления о записи») — просто ничего не отправляем.
        pushLog("пропущено (userId=$userId): App ID или API Key не заполнены в Настройках.");
        return false;
    }

    if (!function_exists('curl_init')) {
        pushLog('расширение cURL недоступно на хостинге.');
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
        pushLog("cURL error (userId=$userId) — $curlError (возможно, хостинг блокирует исходящие запросы)");
        return false;
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        pushLog("OneSignal вернул HTTP $httpCode (userId=$userId) — $response");
        return false;
    }

    pushLog("отправлено успешно (userId=$userId, title=\"$title\") — HTTP $httpCode — $response");
    return true;
}
