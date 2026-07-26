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

// Название "запоминающей" куки — переживает даже потерю сессии на хостинге
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
