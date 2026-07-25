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

// Проверка: авторизован ли администратор
function isAdmin(): bool
{
    return !empty($_SESSION['admin_id']);
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
