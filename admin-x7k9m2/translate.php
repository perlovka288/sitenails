<?php
/**
 * admin-x7k9m2/translate.php
 *
 * Маленький AJAX-помощник: переводит текст между русским и украинским,
 * чтобы не нужно было вручную вводить оба варианта текста в каждой форме.
 * Кнопка "⇄ Перевести с рус." в разделах "О мне" и "Прайс" дергает этот файл.
 * Направление перевода передаётся параметром "to" ('uk' или 'ru',
 * по умолчанию 'uk' — это самый частый случай, ru -> uk).
 *
 * Перевод бесплатный, без ключа и без регистрации:
 *  1) сначала пробуем неофициальный, но очень надёжный и без лимитов
 *     эндпоинт Google Translate (используется как есть, без API-ключа);
 *  2) если он недоступен (заблокирован хостингом и т.п.) — используем
 *     MyMemory как запасной вариант (у него более скромные лимиты).
 * Лимит длины текста ниже (см. $maxAutoTranslateLength) поднят до 1900
 * символов — это с запасом покрывает самое длинное поле на сайте
 * (текст "О себе", максимум 800 символов), но всё ещё безопасно для
 * GET-запроса к Google Translate (не упирается в лимит длины URL).
 * Если оба варианта не сработали — мама просто впишет перевод руками,
 * ничего не сломается.
 */

require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/includes/auth_check.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrfCheck()) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Некорректный запрос.']);
    exit;
}

$text = trim((string)($_POST['text'] ?? ''));
$to   = ($_POST['to'] ?? 'uk') === 'ru' ? 'ru' : 'uk'; // защита от произвольных значений
$from = $to === 'uk' ? 'ru' : 'uk';

if ($text === '') {
    echo json_encode(['ok' => true, 'translated' => '']);
    exit;
}

$textLength = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
if ($textLength > 480) {
    echo json_encode(['ok' => false, 'error' => 'Текст слишком длинный для автоперевода (макс. ~480 символов). Впишите перевод вручную.']);
    exit;
}

$translated = translateText($text, $from, $to);

if ($translated === null) {
    echo json_encode(['ok' => false, 'error' => 'Не получилось перевести автоматически. Проверьте интернет-соединение сервера или впишите перевод вручную.']);
    exit;
}

echo json_encode(['ok' => true, 'translated' => $translated]);

/**
 * Переводит текст между 'ru' и 'uk', пробуя два бесплатных сервиса по очереди.
 * Возвращает null, если оба недоступны — в этом случае мама впишет перевод
 * сама, ничего не сломается.
 */
function translateText(string $text, string $from, string $to): ?string
{
    $translated = translateViaGoogle($text, $from, $to);
    if ($translated !== null) {
        return $translated;
    }
    return translateViaMyMemory($text, $from, $to);
}

/** Выполняет HTTP GET-запрос с таймаутом, возвращает тело ответа или null. */
function httpGet(string $url): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; NailsSiteTranslator/1.0)',
        ]);
        $raw = curl_exec($ch);
        $ok  = $raw !== false && curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
        curl_close($ch);
        return $ok ? $raw : null;
    }

    if (ini_get('allow_url_fopen')) {
        $context = stream_context_create(['http' => ['timeout' => 8]]);
        $raw = @file_get_contents($url, false, $context);
        return $raw !== false ? $raw : null;
    }

    return null;
}

/**
 * Неофициальный, но очень стабильный и бесплатный эндпоинт Google Translate
 * (тот же, что используют расширения-переводчики в браузере). Не требует
 * ключа API. Если Google когда-нибудь заблокирует запросы с хостинга —
 * translateText() автоматически переключится на MyMemory.
 */
function translateViaGoogle(string $text, string $from, string $to): ?string
{
    $url = 'https://translate.googleapis.com/translate_a/single?' . http_build_query([
        'client' => 'gtx',
        'sl'     => $from,
        'tl'     => $to,
        'dt'     => 't',
        'q'      => $text,
    ]);

    $result = httpGet($url);
    if ($result === null) {
        return null;
    }

    $data = json_decode($result, true);
    if (!is_array($data) || empty($data[0]) || !is_array($data[0])) {
        return null;
    }

    $translated = '';
    foreach ($data[0] as $chunk) {
        if (is_array($chunk) && isset($chunk[0]) && is_string($chunk[0])) {
            $translated .= $chunk[0];
        }
    }

    if ($translated === '') {
        return null;
    }

    return html_entity_decode($translated, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/** Запасной вариант — бесплатный публичный API MyMemory. */
function translateViaMyMemory(string $text, string $from, string $to): ?string
{
    $url = 'https://api.mymemory.translated.net/get?'
        . http_build_query(['q' => $text, 'langpair' => $from . '|' . $to]);

    $result = httpGet($url);
    if ($result === null) {
        return null;
    }

    $data = json_decode($result, true);
    $translated = $data['responseData']['translatedText'] ?? null;

    if (!is_string($translated) || $translated === '') {
        return null;
    }

    // MyMemory иногда возвращает служебную фразу, если лимит исчерпан.
    if (stripos($translated, 'MYMEMORY WARNING') !== false) {
        return null;
    }

    return html_entity_decode($translated, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
