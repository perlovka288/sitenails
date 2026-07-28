<?php
/**
 * includes/functions.php
 * Небольшие вспомогательные функции.
 */

// Безопасный вывод текста в HTML (защита от XSS)
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// Отзывы могут хранить фото в photo_path в двух форматах:
//  - старый: обычная строка "assets/img/reviews/xxx.jpg" (одно фото)
//  - новый:  JSON-массив ["assets/img/reviews/a.jpg", "..."] (до 3 фото)
// Эта функция всегда возвращает обычный PHP-массив путей (может быть пустым).
function reviewPhotoPaths(?string $photoPath): array
{
    if ($photoPath === null || $photoPath === '') {
        return [];
    }
    $decoded = json_decode($photoPath, true);
    if (is_array($decoded)) {
        return array_values(array_filter($decoded, 'is_string'));
    }
    return [$photoPath];
}

// Форматирует дату отзыва ("2026-07-26 14:32:10" из базы) в читаемый вид,
// используя названия месяцев текущего языка сайта (из includes/lang.php).
function formatReviewDate(?string $createdAt): string
{
    if (!$createdAt) {
        return '';
    }

    $ts = strtotime($createdAt);
    if ($ts === false) {
        return '';
    }

    $months = t('months');
    $day    = (int)date('j', $ts);
    $month  = $months[(int)date('n', $ts) - 1] ?? '';
    $year   = date('Y', $ts);
    $time   = date('H:i', $ts);

    return trim("{$day} {$month} {$year}, {$time}");
}


const REMEMBER_COOKIE_NAME = 'nails_remember';
const REMEMBER_LIFETIME    = 60 * 60 * 24 * 90; // 90 дней

// Проверка: авторизован ли администратор.
// Если PHP-сессия почему-то "слетела" (частая история на бесплатном
// хостинге), пробуем восстановить вход по долгоживущей куке.
function isAdmin(): bool
{
    if (!empty($_SESSION['admin_id'])) {
        return true;
    }

    if (!empty($_COOKIE[REMEMBER_COOKIE_NAME])) {
        $raw = (string)$_COOKIE[REMEMBER_COOKIE_NAME];
        [$userId, $token] = array_pad(explode(':', $raw, 2), 2, '');
        $userId = (int)$userId;

        if ($userId > 0 && $token !== '') {
            $pdo = getDB();
            $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE id = ?');
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if ($user
                && !empty($user['remember_token'])
                && !empty($user['remember_expires'])
                && strtotime($user['remember_expires']) > time()
                && hash_equals($user['remember_token'], hash('sha256', $token))
            ) {
                $_SESSION['admin_id'] = $user['id'];
                // Продлеваем токен ещё на 90 дней при каждом успешном заходе.
                issueRememberCookie($user['id']);
                return true;
            }

            // Кука есть, но недействительна — подчищаем её.
            clearRememberCookie();
        }
    }

    return false;
}

// Выдать (или продлить) "запоминающую" куку после успешного входа.
function issueRememberCookie(int $userId): void
{
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + REMEMBER_LIFETIME);

    $pdo = getDB();
    $pdo->prepare('UPDATE admin_users SET remember_token = ?, remember_expires = ? WHERE id = ?')
        ->execute([hash('sha256', $token), $expires, $userId]);

    setcookie(REMEMBER_COOKIE_NAME, $userId . ':' . $token, [
        'expires'  => time() + REMEMBER_LIFETIME,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// Удалить "запоминающую" куку и токен из базы (используется при выходе).
function clearRememberCookie(): void
{
    if (!empty($_SESSION['admin_id'])) {
        $pdo = getDB();
        $pdo->prepare('UPDATE admin_users SET remember_token = NULL, remember_expires = NULL WHERE id = ?')
            ->execute([$_SESSION['admin_id']]);
    }
    setcookie(REMEMBER_COOKIE_NAME, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// Принудительно требовать авторизацию (используется на всех страницах админки)
function requireAdmin(string $loginUrl): void
{
    if (!isAdmin()) {
        header('Location: ' . $loginUrl);
        exit;
    }
}

// Простой редирект с последующей остановкой скрипта
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

// Проверка CSRF-токена (базовая защита форм)
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfCheck(): bool
{
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// ==== РЕГИСТРАЦИЯ И ВХОД ОБЫЧНЫХ ПОСЕТИТЕЛЕЙ САЙТА ====
// Это ОТДЕЛЬНАЯ система от isAdmin() (панель управления) — обычный
// посетитель регистрируется через register.php (имя, логин, телефон,
// пароль) или входит через login.php, и должен это сделать, прежде чем
// увидеть сам сайт (см. гейт в начале index.php). Данные сохраняются в
// таблице site_users и в будущем будут использоваться для записи.
const SITE_REMEMBER_COOKIE_NAME = 'nails_client_remember';
const SITE_REMEMBER_LIFETIME    = 60 * 60 * 24 * 90; // 90 дней

// Данные вошедшего посетителя (строка из site_users) или null.
// Как и isAdmin() — подстраховывается "запоминающей" кукой, если
// PHP-сессия слетела (частая история на бесплатных хостингах).
function currentSiteUser(): ?array
{
    static $cached = false;
    static $checked = false;
    if ($checked) {
        return $cached;
    }
    $checked = true;

    $pdo = getDB();

    if (!empty($_SESSION['site_user_id'])) {
        $stmt = $pdo->prepare('SELECT * FROM site_users WHERE id = ?');
        $stmt->execute([$_SESSION['site_user_id']]);
        $user = $stmt->fetch();
        if ($user) {
            $cached = $user;
            return $cached;
        }
        unset($_SESSION['site_user_id']);
    }

    if (!empty($_COOKIE[SITE_REMEMBER_COOKIE_NAME])) {
        $raw = (string)$_COOKIE[SITE_REMEMBER_COOKIE_NAME];
        [$userId, $token] = array_pad(explode(':', $raw, 2), 2, '');
        $userId = (int)$userId;

        if ($userId > 0 && $token !== '') {
            $stmt = $pdo->prepare('SELECT * FROM site_users WHERE id = ?');
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if ($user
                && !empty($user['remember_token'])
                && !empty($user['remember_expires'])
                && strtotime($user['remember_expires']) > time()
                && hash_equals($user['remember_token'], hash('sha256', $token))
            ) {
                $_SESSION['site_user_id'] = $user['id'];
                issueSiteRememberCookie((int)$user['id']);
                $cached = $user;
                return $cached;
            }

            clearSiteRememberCookie();
        }
    }

    return null;
}

function isSiteUser(): bool
{
    return currentSiteUser() !== null;
}

// Выдать (или продлить) "запоминающую" куку клиенту после входа/регистрации.
function issueSiteRememberCookie(int $userId): void
{
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + SITE_REMEMBER_LIFETIME);

    $pdo = getDB();
    $pdo->prepare('UPDATE site_users SET remember_token = ?, remember_expires = ? WHERE id = ?')
        ->execute([hash('sha256', $token), $expires, $userId]);

    setcookie(SITE_REMEMBER_COOKIE_NAME, $userId . ':' . $token, [
        'expires'  => time() + SITE_REMEMBER_LIFETIME,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function clearSiteRememberCookie(): void
{
    if (!empty($_SESSION['site_user_id'])) {
        $pdo = getDB();
        $pdo->prepare('UPDATE site_users SET remember_token = NULL, remember_expires = NULL WHERE id = ?')
            ->execute([$_SESSION['site_user_id']]);
    }
    setcookie(SITE_REMEMBER_COOKIE_NAME, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// Логин — как в Инстаграме: только латиница/цифры/точка/подчёркивание,
// от 3 до 20 символов, без пробелов. Храним/сравниваем в нижнем регистре
// (login_lower), но показываем как человек его ввёл (login).
function isValidLogin(string $login): bool
{
    return (bool)preg_match('/^[a-zA-Z0-9_.]{3,20}$/', $login);
}

// Оставляет от номера телефона только "+" в начале и цифры, чтобы один
// и тот же номер, введённый по-разному ("+380 96 055 44 85" / "0960554485"),
// не создавал путаницы при будущем поиске клиента.
function normalizePhone(string $phone): string
{
    $trimmed = trim($phone);
    $hasPlus = str_starts_with($trimmed, '+');
    $digits = preg_replace('/\D/', '', $trimmed);
    return ($hasPlus ? '+' : '') . $digits;
}

// Принудительно требовать вход посетителя (используется в index.php).
// Мама, вошедшая в панель управления (isAdmin()), проходит сквозь гейт
// без отдельной клиентской регистрации.
function requireSiteAccess(string $loginUrl): void
{
    if (!isAdmin() && !isSiteUser()) {
        $next = $_SERVER['REQUEST_URI'] ?? '';
        $suffix = $next !== '' && $next !== '/' ? ('?next=' . urlencode($next)) : '';
        header('Location: ' . $loginUrl . $suffix);
        exit;
    }
}


// $allowedMime — карта "mime-тип => расширение", $destDir — относительный
// путь от корня сайта (например "assets/uploads/widgets/3").
// Возвращает относительный путь к сохранённому файлу или null, если файла
// не было / он не прошёл проверку.
function saveUploadedFile(string $fieldName, string $destDir, array $allowedMime, int $maxBytes, string $prefix): ?string
{
    if (empty($_FILES[$fieldName]) || ($_FILES[$fieldName]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $tmpName = $_FILES[$fieldName]['tmp_name'];
    $size = (int)$_FILES[$fieldName]['size'];
    if ($size <= 0 || $size > $maxBytes) {
        return null;
    }

    $mime = function_exists('mime_content_type') ? mime_content_type($tmpName) : null;
    if ($mime === null || !isset($allowedMime[$mime])) {
        return null;
    }

    $ext = $allowedMime[$mime];
    $fullDir = __DIR__ . '/../' . ltrim($destDir, '/');
    if (!is_dir($fullDir)) {
        mkdir($fullDir, 0755, true);
    }

    $filename = $prefix . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destination = $fullDir . '/' . $filename;

    if (!move_uploaded_file($tmpName, $destination)) {
        return null;
    }

    return rtrim($destDir, '/') . '/' . $filename;
}

// Удаляет файл с диска по относительному пути от корня сайта (тихо
// игнорирует отсутствующий файл — например, если его уже удалили руками).
function deleteUploadedFile(?string $relativePath): void
{
    if (!$relativePath) {
        return;
    }
    $full = __DIR__ . '/../' . ltrim($relativePath, '/');
    if (is_file($full)) {
        @unlink($full);
    }
}

// Переводит значение php.ini вида "8M", "512K", "2G" в байты.
// Нужно, чтобы показывать пользователю реальный текущий лимит хостинга,
// а не только лимит, заданный в коде сайта (они могут отличаться).
function iniSizeToBytes(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }
    $unit = strtolower(substr($value, -1));
    $number = (float)$value;
    return (int)match ($unit) {
        'g' => $number * 1024 * 1024 * 1024,
        'm' => $number * 1024 * 1024,
        'k' => $number * 1024,
        default => $number,
    };
}

// Реальный текущий лимит загрузки на этом хостинге прямо сейчас (минимум
// из upload_max_filesize и post_max_size из php.ini/.user.ini/.htaccess) —
// то, что сайт может принять, независимо от лимита конкретного раздела.
function currentServerUploadLimitBytes(): int
{
    $upload = iniSizeToBytes((string)ini_get('upload_max_filesize'));
    $post   = iniSizeToBytes((string)ini_get('post_max_size'));
    if ($upload <= 0) return $post;
    if ($post <= 0) return $upload;
    return min($upload, $post);
}

// ==== CLOUDINARY: файлы, загруженные прямо из браузера (см. video-compress.js) ====

// Проверяет, что путь — это внешняя ссылка (Cloudinary), а не локальный
// файл на диске хостинга. Старые записи (photo/pdf, старые видео) хранят
// обычный относительный путь и сюда не попадают.
function isCloudUrl(?string $path): bool
{
    return $path !== null && (str_starts_with($path, 'http://') || str_starts_with($path, 'https://'));
}

// В админке пути к локальным файлам выводятся с "../" (страница лежит в
// подпапке admin-x7k9m2/), а ссылки на Cloudinary — уже полные и "../" им
// только всё портит. Эта функция выбирает правильный вариант.
function widgetAdminSrc(string $path): string
{
    return isCloudUrl($path) ? $path : '../' . $path;
}

// Удаляет файл из Cloudinary (используется при удалении записи в панели
// управления для видео, загруженных напрямую из браузера). Тихо
// возвращает false при любой ошибке — отсутствие файла в Cloudinary не
// должно мешать удалить саму запись из базы.
function cloudinaryDestroy(string $publicId, string $resourceType = 'video'): bool
{
    if ($publicId === '' || !defined('CLOUDINARY_API_SECRET') || !defined('CLOUDINARY_API_KEY') || !defined('CLOUDINARY_CLOUD_NAME')) {
        return false;
    }

    $timestamp = time();
    $signature = sha1('public_id=' . $publicId . '&timestamp=' . $timestamp . CLOUDINARY_API_SECRET);

    $postFields = http_build_query([
        'public_id' => $publicId,
        'timestamp' => $timestamp,
        'api_key'   => CLOUDINARY_API_KEY,
        'signature' => $signature,
    ]);

    $url = 'https://api.cloudinary.com/v1_1/' . CLOUDINARY_CLOUD_NAME . '/' . $resourceType . '/destroy';

    $context = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content'       => $postFields,
            'timeout'       => 15,
            'ignore_errors' => true,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return false;
    }

    $data = json_decode($response, true);
    return is_array($data) && ($data['result'] ?? '') === 'ok';
}

// Единая точка удаления файла записи виджета: сам решает, удалять с диска
// хостинга (старые загрузки) или из Cloudinary (новые видео, загруженные
// напрямую из браузера) — вызывающему коду разница не важна.
function deleteWidgetItemFile(array $item): void
{
    $path = $item['file_path'] ?? null;
    if (isCloudUrl($path)) {
        $publicId = trim((string)($item['cloud_public_id'] ?? ''));
        if ($publicId !== '') {
            cloudinaryDestroy($publicId, 'video');
        }
        return;
    }
    deleteUploadedFile($path);
}

// ==== Кнопки блока «О мне»: превращает выбранный в панели управления
// тип кнопки ('instagram' / 'reviews' / 'viber' / 'custom') в реальную
// ссылку. Для 'custom' используется ссылка, вписанная мамой вручную. ====
function aboutButtonHref(string $type, ?string $customUrl): string
{
    switch ($type) {
        case 'instagram':
            return getSetting('social_instagram_url', '');
        case 'reviews':
            return '?tab=reviews';
        case 'viber':
            $phone = preg_replace('/\D/', '', getSetting('social_viber_phone', ''));
            return 'viber://chat?number=%2B' . $phone;
        default:
            return trim((string)($customUrl ?? ''));
    }
}

// Внешние ссылки открываем в новой вкладке, внутренние (например переход
// на вкладку "Отзывы" этого же сайта) — в этой же, без target="_blank".
function aboutButtonIsExternal(string $href): bool
{
    return str_starts_with($href, 'http://') || str_starts_with($href, 'https://');
}

// Допустимые MIME-типы для загрузок виджетов, по типу категории.
function widgetAllowedMime(string $type): array
{
    return match ($type) {
        'photo' => [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
        ],
        'video' => [
            'video/mp4'       => 'mp4',
            'video/webm'      => 'webm',
            'video/quicktime' => 'mov',
        ],
        'pdf' => [
            'application/pdf' => 'pdf',
        ],
        default => [],
    };
}
