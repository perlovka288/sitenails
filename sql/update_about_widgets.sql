-- ============================================================================
-- Обновление базы данных sitenails: блок «О мне», опыт работы, виджеты
-- (галереи фото/видео/PDF), соцсети.
--
-- Этот SQL применять НЕ ОБЯЗАТЕЛЬНО вручную — config.php (функция
-- migrateSchema) создаёт эти таблицы сам при первом же заходе на сайт
-- после обновления файлов, ничего не удаляя из существующих данных.
-- Файл дан для тех, кто хочет применить изменения вручную заранее
-- (например через sqlite3 CLI или phpLiteAdmin на хостинге).
-- ============================================================================

-- Блок «О мне» — единственная строка (id = 1)
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
);
INSERT OR IGNORE INTO about_me (id, greeting, title, subtitle, bio) VALUES (1, '', '', '', '');

-- Статистика в блоке «О мне» (например «4+ / Года опыта»)
CREATE TABLE IF NOT EXISTS about_stats (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    value       TEXT NOT NULL,
    label       TEXT NOT NULL,
    label_ua    TEXT,
    sort_order  INTEGER NOT NULL DEFAULT 0
);

-- Навыки / инструменты с иконками
CREATE TABLE IF NOT EXISTS about_skills (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    name         TEXT NOT NULL,
    name_ua      TEXT,
    icon_text    TEXT,
    icon_image   TEXT,
    sort_order   INTEGER NOT NULL DEFAULT 0
);

-- Опыт работы (карточки: период / должность / компания / описание)
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
);

-- Соцсети / мессенджеры (свободный список ссылок с иконками)
CREATE TABLE IF NOT EXISTS social_links (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    platform     TEXT NOT NULL,
    icon_text    TEXT,
    icon_image   TEXT,
    url          TEXT NOT NULL,
    sort_order   INTEGER NOT NULL DEFAULT 0
);

-- Кастомные виджеты-категории (галереи / видео / сертификаты PDF)
CREATE TABLE IF NOT EXISTS widget_categories (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    name         TEXT NOT NULL,
    name_ua      TEXT,
    type         TEXT NOT NULL DEFAULT 'photo', -- 'photo' | 'video' | 'pdf'
    sort_order   INTEGER NOT NULL DEFAULT 0,
    created_at   TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Файлы внутри категории-виджета
CREATE TABLE IF NOT EXISTS widget_items (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    category_id   INTEGER NOT NULL REFERENCES widget_categories(id) ON DELETE CASCADE,
    file_path     TEXT NOT NULL,
    title         TEXT,
    sort_order    INTEGER NOT NULL DEFAULT 0,
    created_at    TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
