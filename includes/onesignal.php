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
// $urlPath — необязательный путь ОТ КОРНЯ САЙТА (например 'profile.php#booking-12'
// или 'admin-x7k9m2/slots.php'), куда попадёт человек, если нажмёт на само
// уведомление на телефоне/компьютере — а не просто откроет сайт с главной.
function sendOneSignalPush(int $userId, string $title, string $message, string $urlPath = ''): bool
{
    return sendOneSignalPushLocalized(
        $userId,
        ['ru' => $title, 'uk' => $title, 'en' => $title],
        ['ru' => $message, 'uk' => $message, 'en' => $message],
        $urlPath
    );
}

// То же самое, но заголовок/текст можно задать ОТДЕЛЬНО для каждого языка —
// OneSignal сам подставит нужный вариант по языку устройства получателя
// (используется там, где хочется живого текста на укр., а не просто того
// же текста на русском под видом украинского, как раньше в sendOneSignalPush).
// $headings/$contents — массивы вида ['ru' => '...', 'uk' => '...'], ключ
// 'en' можно не указывать — если его нет, используется 'ru' как запасной.
function sendOneSignalPushLocalized(int $userId, array $headings, array $contents, string $urlPath = ''): bool
{
    if (!isset($headings['en'])) $headings['en'] = $headings['ru'] ?? reset($headings);
    if (!isset($contents['en'])) $contents['en'] = $contents['ru'] ?? reset($contents);

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

    // Абсолютная ссылка на логотип сайта — нужна OneSignal, чтобы показывать
    // иконку в уведомлении на Android (без неё браузер сам подставляет
    // generic-кружок с буквой вместо иконки сайта). Строим от текущего
    // домена, чтобы работало и на InfinityFree-поддомене, и на своём домене.
    $__scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $__host = $_SERVER['HTTP_HOST'] ?? '';
    $__iconUrl = $__host !== '' ? $__scheme . '://' . $__host . '/assets/img/social/nails.png' : '';

    $payload = [
        'app_id'          => $appId,
        'headings'        => $headings,
        'contents'        => $contents,
        'include_aliases' => ['external_id' => [(string)$userId]],
        'target_channel'  => 'push',
    ];
    if ($__iconUrl !== '') {
        // chrome_web_icon — большая иконка в самом уведомлении (десктоп/Android).
        // chrome_web_badge — маленький значок в шторке уведомлений Android.
        $payload['chrome_web_icon'] = $__iconUrl;
        $payload['chrome_web_badge'] = $__iconUrl;
    }
    if ($urlPath !== '' && $__host !== '') {
        // 'url' — стандартное поле OneSignal Web Push: куда открыть/переключить
        // вкладку браузера по клику на само уведомление (а не на кнопку внутри
        // него). Работает и для установленного на главный экран iPhone сайта.
        $payload['url'] = $__scheme . '://' . $__host . '/' . ltrim($urlPath, '/');
    }

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

    pushLog("отправлено успешно (userId=$userId, title=\"{$headings['ru']}\") — HTTP $httpCode — $response");
    return true;
}

// Пуш всем администраторам (site_users.is_admin = 1, назначаются в Настройках
// → «Администраторы») о новой заявке на запись — приходит на телефон сразу,
// как только клиент отправил анкету, не дожидаясь, пока кто-то откроет
// панель управления и увидит "Записей пока нет" в старом состоянии.
function notifyAdminsNewBooking(PDO $pdo, string $clientName, string $wantedDate, string $service): void
{
    $admins = $pdo->query('SELECT id FROM site_users WHERE is_admin = 1')->fetchAll(PDO::FETCH_COLUMN);
    if (!$admins) {
        return;
    }

    $title = 'Новая запись 💅';
    $message = $clientName;
    if ($wantedDate !== '') {
        $message .= ' — ' . $wantedDate;
    }
    if ($service !== '') {
        $message .= ' (' . $service . ')';
    }

    foreach ($admins as $adminUserId) {
        sendOneSignalPush((int)$adminUserId, $title, $message, 'admin-x7k9m2/slots.php');
    }
}

// Пуш всем администраторам о новом отзыве — как и с новой записью, приходит
// сразу на телефон, не дожидаясь захода в панель управления. Клик по
// уведомлению открывает список отзывов в панели.
function notifyAdminsNewReview(PDO $pdo, string $authorName, int $rating): void
{
    $admins = $pdo->query('SELECT id FROM site_users WHERE is_admin = 1')->fetchAll(PDO::FETCH_COLUMN);
    if (!$admins) {
        return;
    }

    $stars = str_repeat('★', max(0, min(5, $rating)));
    $title = 'Новый отзыв ✨';
    $message = $authorName . ($stars !== '' ? ' — ' . $stars : '');

    foreach ($admins as $adminUserId) {
        sendOneSignalPush((int)$adminUserId, $title, $message, 'admin-x7k9m2/reviews.php');
    }
}

// Пуш клиенту сразу после того, как мама отметила его запись выполненной
// ("✓ Готово" в календаре, см. admin-x7k9m2/slots.php) — благодарность и
// приглашение оставить отзыв. Текст сразу на двух языках (не просто
// продублированный на "укр." русский, а отдельный украинский вариант) —
// OneSignal сам покажет получателю нужный по языку его устройства/браузера.
// Клик по уведомлению ведёт прямо на вкладку "Отзывы" на сайте.
function notifyClientBookingDone(int $userId): void
{
    $headings = [
        'ru' => 'Спасибо, что были у нас! 💅',
        'uk' => 'Дякуємо, що завітали! 💅',
    ];
    $contents = [
        'ru' => 'Если вам не сложно — оставьте, пожалуйста, отзыв, нажав на это сообщение.',
        'uk' => 'Якщо вам не важко — залиште, будь ласка, відгук, натиснувши на це повідомлення.',
    ];
    sendOneSignalPushLocalized($userId, $headings, $contents, 'index.php?tab=reviews');
}
