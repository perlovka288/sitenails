<?php
/**
 * config.php
 * Подключение к базе данных (SQLite — не требует настройки хостинга)
 * и общие настройки сайта.
 */

// ==== СЕССИЯ ====
// На некоторых бесплатных хостингах (например, InfinityFree) файлы сессий
// живут недолго, из-за чего вход в панель управления "слетал". Чтобы мама
// оставалась в панели авторизованной подолгу, куки сессии выставляем
// на 30 дней вперёд, а также отдельно используем "запоминающую" куку
// (см. issueRememberCookie() в includes/functions.php) — она переживает
// даже полную очистку файлов сессий на хостинге.
if (session_status() !== PHP_SESSION_ACTIVE) {
    $__sessionLifetime = 60 * 60 * 24 * 30; // 30 дней
    session_set_cookie_params([
        'lifetime' => $__sessionLifetime,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ==== СЕКРЕТНЫЙ КОД ДЛЯ ПЕРВИЧНОЙ РЕГИСТРАЦИИ МАМЫ В ПАНЕЛИ ====
// Мама один раз открывает admin-x7k9m2/register.php?code=ЭТОТ_КОД,
// придумывает свой логин/пароль — и дальше входит уже под ним.
// После использования регистрация сама себя отключает (см. register.php).
// Хотите — смените код на свой, любую строку без пробелов.
define('ADMIN_REGISTER_CODE', 'mama-nails-2026-secret');

// ==== ОБЩИЕ НАСТРОЙКИ САЙТА ====
define('DB_PATH', __DIR__ . '/data/database.sqlite');

// ==== CLOUDINARY (прямая загрузка видео из браузера в облако) ====
// Раньше видео из формы "Достижения" шло через сам хостинг — а бесплатные
// хостинги режут PHP upload_max_filesize/post_max_size намного строже, чем
// указано в настройках сайта (даже 20-23 МБ файл мог не пройти). Теперь
// видео летит СРАЗУ из браузера в Cloudinary (assets/js/video-compress.js),
// а на сервер приходит только маленькая ссылка на готовый файл — лимиты
// хостинга на PHP тут вообще ни при чём.
// CLOUD_NAME и UPLOAD_PRESET не секретны (и так видны в JS-коде страницы —
// иначе прямая загрузка была бы невозможна). API_KEY/API_SECRET нужны
// только на сервере — для удаления файла из Cloudinary, когда мама удаляет
// запись в панели управления; сама загрузка идёт по "unsigned" preset и
// их не использует.
define('CLOUDINARY_CLOUD_NAME', 'ds6buwmpj');
define('CLOUDINARY_UPLOAD_PRESET', 'widgets_unsigned');
define('CLOUDINARY_API_KEY', '699982791523863');
define('CLOUDINARY_API_SECRET', 'frncYM33zozidBf5T35xNNjhl-o');

// Значения ниже используются ТОЛЬКО один раз — как стартовые значения,
// которые записываются в базу при самом первом запуске сайта.
// Дальше название сайта, телефон и ссылки на соцсети мама меняет сама
// в панели управления → раздел «Настройки», без участия программиста.
// Смотри функции getSetting()/setSetting() ниже и admin-x7k9m2/settings.php.
const DEFAULT_SETTINGS = [
    'site_name'             => '', // пусто — мама впишет своё название в панели управления
    'site_phone'             => '+380 96 055 44 85',
    'social_instagram_url'   => 'https://www.instagram.com/nails_master_kurakoluba?utm_source=qr',
    'social_viber_phone'     => '+380960554485',
    'social_telegram_phone'  => '+380960554485',
    'social_phone'           => '+380960554485',
    'site_location_metro'    => 'ХТЗ',
];

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

        // Догоняем схему на уже существующих базах (сайт уже был запущен
        // раньше и обновился новым кодом) — ничего не удаляем и не трогаем
        // существующие данные, только добавляем недостающее.
        migrateSchema($pdo);
    }

    return $pdo;
}

// ==== ДОБАВЛЕНИЕ НЕДОСТАЮЩИХ ТАБЛИЦ/КОЛОНОК НА УЖЕ РАБОТАЮЩЕМ САЙТЕ ====
function migrateSchema(PDO $pdo): void
{
    // Всё оборачиваем в try/catch: миграции добавляют недостающие
    // таблицы/колонки на уже работающем сайте, и одна неудачная (например,
    // из-за временной проблемы с правами на файл базы на хостинге) не
    // должна укладывать ВЕСЬ сайт с ошибкой 500 — лучше отработать тем,
    // что уже есть, и записать причину в лог хостинга (error_log).
    try {
    // Таблица настроек сайта (название, телефон, соцсети) — редактируется
    // мамой в панели управления, без правки файлов на хостинге.
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS site_settings (
            key   TEXT PRIMARY KEY,
            value TEXT NOT NULL DEFAULT ''
        )
    ");
    $seed = $pdo->prepare('INSERT OR IGNORE INTO site_settings (key, value) VALUES (?, ?)');
    foreach (DEFAULT_SETTINGS as $key => $value) {
        $seed->execute([$key, $value]);
    }

    // slot_id в bookings — связывает заявку клиента со слотом в календаре,
    // чтобы мама могла из «Записей» сразу отметить нужное время занятым.
    $columns = $pdo->query('PRAGMA table_info(bookings)')->fetchAll(PDO::FETCH_ASSOC);
    $hasSlotId = false;
    foreach ($columns as $col) {
        if ($col['name'] === 'slot_id') {
            $hasSlotId = true;
            break;
        }
    }
    if (!$hasSlotId) {
        $pdo->exec('ALTER TABLE bookings ADD COLUMN slot_id INTEGER');
    }

    // photo_path в reviews — фото, которое клиент прикрепил к отзыву.
    $reviewColumns = $pdo->query('PRAGMA table_info(reviews)')->fetchAll(PDO::FETCH_ASSOC);
    $hasPhotoPath = false;
    foreach ($reviewColumns as $col) {
        if ($col['name'] === 'photo_path') {
            $hasPhotoPath = true;
            break;
        }
    }
    if (!$hasPhotoPath) {
        $pdo->exec('ALTER TABLE reviews ADD COLUMN photo_path TEXT');
    }

    // remember_token / remember_expires — токен "долгого входа" в панель,
    // чтобы авторизация мамы не слетала (см. issueRememberCookie()).
    $adminColumns = $pdo->query('PRAGMA table_info(admin_users)')->fetchAll(PDO::FETCH_ASSOC);
    $adminColNames = array_column($adminColumns, 'name');
    if (!in_array('remember_token', $adminColNames, true)) {
        $pdo->exec('ALTER TABLE admin_users ADD COLUMN remember_token TEXT');
    }
    if (!in_array('remember_expires', $adminColNames, true)) {
        $pdo->exec('ALTER TABLE admin_users ADD COLUMN remember_expires TEXT');
    }

    // ==== Раздел «О мне» + виджеты + опыт работы + соцсети (добавляются
    // на уже работающем сайте автоматически, без ручного SQL) ====

    // Блок «О мне» — одна строка (id всегда 1): фото, приветствие,
    // заголовок/подзаголовок, текст "о себе", кнопки. РУ/УКР — отдельными
    // колонками *_ua, как и в остальных таблицах сайта.
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS about_me (
            id INTEGER PRIMARY KEY CHECK (id = 1),
            photo_path    TEXT,
            greeting      TEXT NOT NULL DEFAULT '',
            greeting_ua   TEXT,
            title         TEXT NOT NULL DEFAULT '',
            title_ua      TEXT,
            subtitle      TEXT NOT NULL DEFAULT '',
            subtitle_ua   TEXT,
            bio           TEXT NOT NULL DEFAULT '',
            bio_ua        TEXT,
            btn1_text     TEXT,
            btn1_text_ua  TEXT,
            btn1_url      TEXT,
            btn2_text     TEXT,
            btn2_text_ua  TEXT,
            btn2_url      TEXT
        )
    ");
    $pdo->exec("INSERT OR IGNORE INTO about_me (id, greeting, title, subtitle, bio) VALUES (1, '', '', '', '')");

    // Статистика в блоке «О мне» (например «4+ / Года опыта»)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS about_stats (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            value       TEXT NOT NULL,
            label       TEXT NOT NULL,
            label_ua    TEXT,
            sort_order  INTEGER NOT NULL DEFAULT 0
        )
    ");

    // Навыки / инструменты с иконками в блоке «О мне»
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS about_skills (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            name         TEXT NOT NULL,
            name_ua      TEXT,
            icon_text    TEXT,
            icon_image   TEXT,
            sort_order   INTEGER NOT NULL DEFAULT 0
        )
    ");

    // Опыт работы (карточки: период / должность / компания / описание)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS work_experience (
            id               INTEGER PRIMARY KEY AUTOINCREMENT,
            period           TEXT NOT NULL,
            position         TEXT NOT NULL,
            position_ua      TEXT,
            company          TEXT,
            company_ua       TEXT,
            description      TEXT,
            description_ua   TEXT,
            sort_order       INTEGER NOT NULL DEFAULT 0
        )
    ");

    // Соцсети / мессенджеры (свободный список ссылок с иконками)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS social_links (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            platform     TEXT NOT NULL,
            platform_ua  TEXT,
            icon_text    TEXT,
            icon_image   TEXT,
            url          TEXT NOT NULL,
            sort_order   INTEGER NOT NULL DEFAULT 0
        )
    ");
    $socialCols = array_column($pdo->query('PRAGMA table_info(social_links)')->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array('platform_ua', $socialCols, true)) {
        $pdo->exec('ALTER TABLE social_links ADD COLUMN platform_ua TEXT');
    }

    // Кастомные виджеты-категории (галереи / видео / сертификаты PDF)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS widget_categories (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            name         TEXT NOT NULL,
            name_ua      TEXT,
            type         TEXT NOT NULL DEFAULT 'photo',
            sort_order   INTEGER NOT NULL DEFAULT 0,
            created_at   TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Файлы внутри категории-виджета
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS widget_items (
            id               INTEGER PRIMARY KEY AUTOINCREMENT,
            category_id      INTEGER NOT NULL REFERENCES widget_categories(id) ON DELETE CASCADE,
            file_path        TEXT NOT NULL,
            cloud_public_id  TEXT,
            title            TEXT,
            sort_order       INTEGER NOT NULL DEFAULT 0,
            created_at       TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // cloud_public_id — id файла в Cloudinary (для видео, загруженных
    // напрямую из браузера), нужен только чтобы уметь удалить файл из
    // облака при удалении записи в панели. Для старых файлов (на диске
    // хостинга) остаётся NULL — с ними всё работает как раньше.
    $widgetItemCols = $pdo->query('PRAGMA table_info(widget_items)')->fetchAll(PDO::FETCH_ASSOC);
    $widgetItemColNames = array_column($widgetItemCols, 'name');
    if ($widgetItemColNames && !in_array('cloud_public_id', $widgetItemColNames, true)) {
        $pdo->exec('ALTER TABLE widget_items ADD COLUMN cloud_public_id TEXT');
    }

    // ==== Аккаунты обычных посетителей сайта (регистрация перед входом) ====
    // Раньше сайт был открыт всем без входа. Теперь посетитель должен
    // зарегистрироваться (имя, логин, телефон, пароль) или войти под уже
    // созданным аккаунтом — как в Инстаграме. Эти данные в будущем
    // пригодятся для системы записи (не вводить контакты каждый раз).
    // ВАЖНО: это ОТДЕЛЬНАЯ таблица от admin_users (панель управления) —
    // $_SESSION['site_user_id'], не путать с $_SESSION['admin_id'].
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS site_users (
            id               INTEGER PRIMARY KEY AUTOINCREMENT,
            full_name        TEXT NOT NULL,
            login            TEXT NOT NULL,
            login_lower      TEXT NOT NULL UNIQUE,
            phone            TEXT NOT NULL,
            password_hash    TEXT NOT NULL,
            is_admin         INTEGER NOT NULL DEFAULT 0,
            remember_token   TEXT,
            remember_expires TEXT,
            created_at       TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");
    // Мамин аккаунт создаётся сразу, с готовым логином/паролем — регистрацию
    // проходить ей не нужно. is_admin здесь — просто отметка "это владелец"
    // (к самой панели управления admin-x7k9m2 это не относится, для неё
    // свой отдельный вход, см. admin-x7k9m2/login.php).
    $pdo->prepare('
        INSERT OR IGNORE INTO site_users (full_name, login, login_lower, phone, password_hash, is_admin)
        VALUES (?, ?, ?, ?, ?, 1)
    ')->execute(['Любовь', 'lybovk', 'lybovk', normalizePhone('+380 96 055 44 85'), password_hash('606667543', PASSWORD_DEFAULT)]);

    // user_id в bookings — связывает заявку на запись с зарегистрированным
    // аккаунтом клиента (используется формой записи и мини-профилем в шапке).
    $bookingsCols = array_column($pdo->query('PRAGMA table_info(bookings)')->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array('user_id', $bookingsCols, true)) {
        $pdo->exec('ALTER TABLE bookings ADD COLUMN user_id INTEGER');
    }
    // contact_method — как клиенту удобнее связаться (instagram/viber/phone),
    // выбирается в анкете записи (см. select_slot.php и модалку в index.php).
    if (!in_array('contact_method', $bookingsCols, true)) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN contact_method TEXT NOT NULL DEFAULT ''");
    }

    // avatar_path в site_users — фото для мини-профиля клиента в шапке сайта.
    // NULL/пусто — показываем аватар-заглушку с первой буквой имени.
    $siteUserCols = array_column($pdo->query('PRAGMA table_info(site_users)')->fetchAll(PDO::FETCH_ASSOC), 'name');
    if ($siteUserCols && !in_array('avatar_path', $siteUserCols, true)) {
        $pdo->exec('ALTER TABLE site_users ADD COLUMN avatar_path TEXT');
    }

    // ==== Кнопки блока «О мне»: тип поведения + вкл/выкл (тумблер) ====
    // btn{N}_type: 'custom' (своя ссылка, как раньше), 'instagram'
    // (ведёт на Instagram из настроек), 'reviews' (открывает вкладку
    // "Отзывы" на этом же сайте), 'viber' (сразу открывает чат Viber
    // с номером из настроек). btn{N}_enabled — показывать ли кнопку
    // вообще (тумблер в панели «О мне»), по умолчанию включено.
    $aboutColumns = $pdo->query('PRAGMA table_info(about_me)')->fetchAll(PDO::FETCH_ASSOC);
    $aboutColNames = array_column($aboutColumns, 'name');
    foreach ([
        'btn1_type'    => "ALTER TABLE about_me ADD COLUMN btn1_type TEXT NOT NULL DEFAULT 'custom'",
        'btn2_type'    => "ALTER TABLE about_me ADD COLUMN btn2_type TEXT NOT NULL DEFAULT 'custom'",
        'btn1_enabled' => 'ALTER TABLE about_me ADD COLUMN btn1_enabled INTEGER NOT NULL DEFAULT 1',
        'btn2_enabled' => 'ALTER TABLE about_me ADD COLUMN btn2_enabled INTEGER NOT NULL DEFAULT 1',
    ] as $__col => $__sql) {
        if (!in_array($__col, $aboutColNames, true)) {
            $pdo->exec($__sql);
        }
    }

    // Кнопки блока «О мне» — теперь ИХ МОЖНО ДОБАВЛЯТЬ СКОЛЬКО УГОДНО
    // (раньше было жёстко 2 кнопки в самой таблице about_me). Старые
    // столбцы btn1_*/btn2_* оставлены в about_me для обратной совместимости,
    // но больше не используются для отображения — см. бэкафилл ниже.
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS about_buttons (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            text         TEXT NOT NULL,
            text_ua      TEXT,
            type         TEXT NOT NULL DEFAULT 'custom',
            url          TEXT,
            icon_text    TEXT,
            enabled      INTEGER NOT NULL DEFAULT 1,
            sort_order   INTEGER NOT NULL DEFAULT 0
        )
    ");
    // Один раз переносим старые кнопки 1/2 (если они были заполнены и
    // таблица about_buttons ещё пустая) в новую таблицу, чтобы ничего
    // не потерялось при обновлении сайта.
    $__btnCount = (int)$pdo->query('SELECT COUNT(*) FROM about_buttons')->fetchColumn();
    if ($__btnCount === 0) {
        $__legacyAbout = $pdo->query('SELECT * FROM about_me WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        if ($__legacyAbout) {
            $__order = 1;
            foreach ([1, 2] as $__n) {
                $__text = trim((string)($__legacyAbout["btn{$__n}_text"] ?? ''));
                if ($__text === '') {
                    continue;
                }
                $pdo->prepare('INSERT INTO about_buttons (text, text_ua, type, url, icon_text, enabled, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)')
                    ->execute([
                        $__text,
                        $__legacyAbout["btn{$__n}_text_ua"] ?? null,
                        in_array($__legacyAbout["btn{$__n}_type"] ?? '', ['custom', 'instagram', 'reviews', 'viber'], true) ? $__legacyAbout["btn{$__n}_type"] : 'custom',
                        $__legacyAbout["btn{$__n}_url"] ?? null,
                        null,
                        ($__legacyAbout["btn{$__n}_enabled"] ?? 1) ? 1 : 0,
                        $__order++,
                    ]);
            }
        }
    }
    } catch (\Throwable $e) {
        error_log('migrateSchema: ' . $e->getMessage());
    }
}

// ==== НАСТРОЙКИ САЙТА (название, телефон, соцсети) — читаем/пишем из БД ====
function getSetting(string $key, string $default = ''): string
{
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT value FROM site_settings WHERE key = ?');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value !== false ? (string)$value : $default;
}

function setSetting(string $key, string $value): void
{
    $pdo = getDB();
    $stmt = $pdo->prepare('
        INSERT INTO site_settings (key, value) VALUES (?, ?)
        ON CONFLICT(key) DO UPDATE SET value = excluded.value
    ');
    $stmt->execute([$key, $value]);
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
            photo_path TEXT,
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
            slot_id INTEGER,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Настройки сайта (название, телефон, соцсети) — правит мама в панели
    // управления, программист для этого больше не нужен.
    $pdo->exec("
        CREATE TABLE site_settings (
            key   TEXT PRIMARY KEY,
            value TEXT NOT NULL DEFAULT ''
        )
    ");
    $seed = $pdo->prepare('INSERT INTO site_settings (key, value) VALUES (?, ?)');
    foreach (DEFAULT_SETTINGS as $key => $value) {
        $seed->execute([$key, $value]);
    }

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
