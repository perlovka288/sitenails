<?php
/**
 * admin-x7k9m2/translate.php
 *
 * Маленький AJAX-помощник: переводит текст с русского на украинский,
 * чтобы не нужно было вручную вводить два варианта текста в каждой форме.
 * Кнопка "Перевести → укр." в разделах "О мне" и "Прайс" дергает этот файл.
 *
 * Используется бесплатный сервис MyMemory (без ключа, без регистрации).
 * У него есть разумные ограничения:
 *  - длина одного запроса — примерно до 500 символов;
 *  - анонимные запросы ограничены по количеству в день.
 * Поэтому перевод — это "черновик", который мама всегда может поправить
 * руками в поле "укр." после того, как он подставится.
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

if ($text === '') {
    echo json_encode(['ok' => true, 'translated' => '']);
    exit;
}

$textLength = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
if ($textLength > 480) {
    echo json_encode(['ok' => false, 'error' => 'Текст слишком длинный для автоперевода (макс. ~480 символов). Впишите украинский вариант вручную.']);
    exit;
}

$translated = translateRuToUa($text);

if ($translated === null) {
    echo json_encode(['ok' => false, 'error' => 'Не получилось перевести автоматически. Проверьте интернет-соединение сервера или впишите украинский вариант вручную.']);
    exit;
}

echo json_encode(['ok' => true, 'translated' => $translated]);

/**
 * Переводит текст ru -> uk через бесплатный публичный API MyMemory.
 * Возвращает null, если перевод не удался (нет сети, сервис недоступен и т.п.) —
 * в этом случае мама просто впишет украинский текст сама, ничего не сломается.
 */
function translateRuToUa(string $text): ?string
{
    $url = 'https://api.mymemory.translated.net/get?'
        . http_build_query(['q' => $text, 'langpair' => 'ru|uk']);

    $result = null;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_USERAGENT      => 'KostlimNailsSite/1.0',
        ]);
        $raw = curl_exec($ch);
        $ok  = $raw !== false && curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
        curl_close($ch);
        if ($ok) {
            $result = $raw;
        }
    } elseif (ini_get('allow_url_fopen')) {
        $context = stream_context_create(['http' => ['timeout' => 8]]);
        $raw = @file_get_contents($url, false, $context);
        if ($raw !== false) {
            $result = $raw;
        }
    }

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
