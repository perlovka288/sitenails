<?php
/**
 * config.php
 * Подключение к базе данных (SQLite — не требует настройки хостинга)
 * и общие настройки сайта.
 */

session_start();

// ==== ОБЩИЕ НАСТРОЙКИ САЙТА ====
define('SITE_NAME', 'Название студии');   // TODO: поменяйте на реальное имя
define('SITE_PHONE', '+380 00 000 00 00'); // TODO
define('DB_PATH', __DIR__ . '/data/database.sqlite');

// ==== ПОДКЛЮЧЕНИЕ К БАЗЕ ====
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $isNew = !file_exists(DB_PATH);

        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA foreign_keys = ON');

        if ($isNew) {
            initDB($pdo);
        }
    }

    return $pdo;
}

// ==== СОЗДАНИЕ ТАБЛИЦ ПРИ ПЕРВОМ ЗАПУСКЕ ====
function initDB(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE admin_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL
        )
    ");

    $pdo->exec("
        CREATE TABLE reviews (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            author_name TEXT NOT NULL,
            rating INTEGER NOT NULL DEFAULT 5,
            message TEXT NOT NULL,
            is_approved INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE price_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            category TEXT NOT NULL,
            title TEXT NOT NULL,
            price TEXT NOT NULL,
            sort_order INTEGER NOT NULL DEFAULT 0
        )
    ");

    $pdo->exec("
        CREATE TABLE bookings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            client_name TEXT NOT NULL,
            phone TEXT NOT NULL,
            service TEXT,
            wanted_date TEXT,
            comment TEXT,
            status TEXT NOT NULL DEFAULT 'new',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Стартовый администратор — логин mama / пароль changeme123
    // !! ОБЯЗАТЕЛЬНО смените пароль после первого входа (раздел "Настройки" в панели) !!
    $stmt = $pdo->prepare('INSERT INTO admin_users (username, password_hash) VALUES (?, ?)');
    $stmt->execute(['mama', password_hash('changeme123', PASSWORD_DEFAULT)]);

    // Немного примеров, чтобы сайт не был пустым при первом открытии
    $pdo->exec("INSERT INTO price_items (category, title, price, sort_order) VALUES
        ('Маникюр', 'Классический маникюр', '350 грн', 1),
        ('Маникюр', 'Маникюр + покрытие гель-лак', '550 грн', 2),
        ('Педикюр', 'Классический педикюр', '450 грн', 1)
    ");

    $pdo->exec("INSERT INTO reviews (author_name, rating, message, is_approved) VALUES
        ('Оксана', 5, 'Очень довольна результатом, мастер — золотые руки!', 1),
        ('Ирина', 5, 'Хожу уже год, всегда всё аккуратно и вовремя.', 1)
    ");
}
