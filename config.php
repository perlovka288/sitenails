<?php
/**
 * config.php
 * Подключение к базе данных (SQLite — не требует настройки хостинга)
 * и общие настройки сайта.
 */

session_start();

// ==== ОБЩИЕ НАСТРОЙКИ САЙТА ====
define('SITE_NAME', 'Nails by Mama');   // TODO: поменяйте на реальное имя
define('SITE_PHONE', '+380 96 055 44 85');
define('DB_PATH', __DIR__ . '/data/database.sqlite');

// ==== СОЦИАЛЬНЫЕ СЕТИ И КОНТАКТЫ МАСТЕРА ====
// Используются в разделе "Запись" вместо анкеты — клиент выбирает удобное
// время в календаре и пишет мастеру напрямую.
define('SOCIAL_INSTAGRAM_URL', 'https://www.instagram.com/nails_master_kurakoluba?utm_source=qr');
define('SOCIAL_VIBER_PHONE', '+380960554485');
define('SOCIAL_TELEGRAM_PHONE', '+380960554485');
define('SOCIAL_PHONE', '+380960554485');
define('SITE_LOCATION_METRO', 'ХТЗ'); // станция метро (см. прайс)

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

    // category / title хранятся на русском, *_ua — украинский перевод
    // (используется при переключении языка сайта на УКР).
    $pdo->exec("
        CREATE TABLE price_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            category TEXT NOT NULL,
            category_ua TEXT,
            title TEXT NOT NULL,
            title_ua TEXT,
            price TEXT NOT NULL,
            sort_order INTEGER NOT NULL DEFAULT 0
        )
    ");

    $pdo->exec("
        CREATE TABLE bookings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            client_name TEXT NOT NULL,
            phone TEXT,
            service TEXT,
            wanted_date TEXT,
            comment TEXT,
            status TEXT NOT NULL DEFAULT 'new',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Свободные слоты, которые мама выставляет в календаре записи.
    // Клиент кликает по свободному времени на сайте, чтобы выбрать его.
    $pdo->exec("
        CREATE TABLE available_slots (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            slot_date TEXT NOT NULL,
            slot_time TEXT NOT NULL,
            is_booked INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Стартовый администратор — логин mama / пароль changeme123
    // !! ОБЯЗАТЕЛЬНО смените пароль после первого входа (раздел "Настройки" в панели) !!
    $stmt = $pdo->prepare('INSERT INTO admin_users (username, password_hash) VALUES (?, ?)');
    $stmt->execute(['mama', password_hash('changeme123', PASSWORD_DEFAULT)]);

    // Полный прайс, перенесённый с фото (РУС / УКР)
    $pdo->exec("INSERT INTO price_items (category, category_ua, title, title_ua, price, sort_order) VALUES
        ('Маникюр', 'Манікюр', 'Маникюр б/покрытия', 'Манікюр б/покриття', '450 грн', 1),
        ('Маникюр', 'Манікюр', 'Маникюр + покрытие гель-лаком', 'Манікюр + покриття гель-лаком', '650 грн', 2),
        ('Маникюр', 'Манікюр', 'Укрепление гелем', 'Укріплення гелем', '+ 50 грн', 3),

        ('Педикюр', 'Педикюр', '6/покрытие (пальчики)', '6/покриття (пальчики)', '500 грн', 1),
        ('Педикюр', 'Педикюр', '6/покрытие (пальчики + стопа)', '6/покриття (пальчики + стопа)', '600 грн', 2),
        ('Педикюр', 'Педикюр', 'С покрытием лаком (пальчики)', 'З покриттям лаку (пальчики)', '600 грн', 3),
        ('Педикюр', 'Педикюр', 'С покрытием (пальчики + стопа)', 'З покриттям (пальчики + стопа)', '700 грн', 4),

        ('Наращивание / Коррекция', 'Нарощування / Корекція', '1–2 ногтя', '1–2', '800 / 850 грн', 1),
        ('Наращивание / Коррекция', 'Нарощування / Корекція', '3–4 ногтя', '3–4', '900 / 950 грн', 2),
        ('Наращивание / Коррекция', 'Нарощування / Корекція', '5–6 ногтей', '5–6', '1000 / 1050 грн', 3),
        ('Наращивание / Коррекция', 'Нарощування / Корекція', '7–8 ногтей', '7–8', '1100 / 1150 грн', 4),
        ('Наращивание / Коррекция', 'Нарощування / Корекція', '9–10 ногтей', '9–10', '1200 / 1250 грн', 5),
        ('Наращивание / Коррекция', 'Нарощування / Корекція', 'Наращивание 1 ногтя', 'Нарощування 1 нігтя', '+ 50 грн', 6),

        ('Дополнительно', 'Додатково', 'Камни, декор (1 шт)', 'Камінці, декор (1 шт)', '1–30 грн', 1),
        ('Дополнительно', 'Додатково', 'Снятие материала другого мастера', 'Зняття матеріалу іншого майстра', '+ 50 грн', 2),
        ('Дополнительно', 'Додатково', 'Снятие материала без последующего покрытия', 'Зняття матеріалу без подальшого покриття', '+ 100 грн', 3)
    ");

    $pdo->exec("INSERT INTO reviews (author_name, rating, message, is_approved) VALUES
        ('Оксана', 5, 'Очень довольна результатом, мастер — золотые руки!', 1),
        ('Ирина', 5, 'Хожу уже год, всегда всё аккуратно и вовремя.', 1),
        ('фіянс', 5, 'класс', 1),
        ('Андрій', 5, 'Класс!!!', 1)
    ");

    // Немного примеров свободного времени, чтобы календарь не был пустым
    $today = new DateTime('today');
    $stmtSlot = $pdo->prepare('INSERT INTO available_slots (slot_date, slot_time) VALUES (?, ?)');
    foreach ([1, 2, 3, 4, 5] as $dayOffset) {
        $d = (clone $today)->modify('+' . $dayOffset . ' day')->format('Y-m-d');
        foreach (['10:00', '13:00', '16:00'] as $time) {
            $stmtSlot->execute([$d, $time]);
        }
    }
}
