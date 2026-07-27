<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/includes/auth_check.php';

$pdo = getDB();
$message = '';
$btnTypeLabels = [
    'custom'    => 'Своя ссылка',
    'instagram' => 'Instagram',
    'reviews'   => 'Раздел «Отзывы» на сайте',
    'viber'     => 'Открыть Viber-чат',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfCheck()) {
    $form = $_POST['form'] ?? '';

    // ===== Основной блок «О мне»: фото + приветствие/заголовок/текст =====
    if ($form === 'about_main') {
        $current = $pdo->query('SELECT * FROM about_me WHERE id = 1')->fetch();

        $photoPath = $current['photo_path'] ?? null;
        if (!empty($_POST['remove_photo'])) {
            deleteUploadedFile($photoPath);
            $photoPath = null;
        }
        $uploaded = saveUploadedFile(
            'photo',
            'assets/uploads/about',
            ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
            5 * 1024 * 1024,
            'avatar'
        );
        if ($uploaded !== null) {
            deleteUploadedFile($photoPath);
            $photoPath = $uploaded;
        }

        $pdo->prepare('
            UPDATE about_me SET
                photo_path = ?, greeting = ?, greeting_ua = ?, title = ?, title_ua = ?,
                subtitle = ?, subtitle_ua = ?, bio = ?, bio_ua = ?
            WHERE id = 1
        ')->execute([
            $photoPath,
            trim($_POST['greeting'] ?? ''), trim($_POST['greeting_ua'] ?? '') ?: null,
            trim($_POST['title'] ?? ''), trim($_POST['title_ua'] ?? '') ?: null,
            trim($_POST['subtitle'] ?? ''), trim($_POST['subtitle_ua'] ?? '') ?: null,
            trim($_POST['bio'] ?? ''), trim($_POST['bio_ua'] ?? '') ?: null,
        ]);
        redirect('about.php');
    }

    // ===== Кнопки: добавить или изменить =====
    if ($form === 'btn_save') {
        $id = (int)($_POST['id'] ?? 0);
        $text = trim($_POST['text'] ?? '');
        $textUa = trim($_POST['text_ua'] ?? '');
        $type = in_array($_POST['type'] ?? '', ['custom', 'instagram', 'reviews', 'viber'], true) ? $_POST['type'] : 'custom';
        $url = trim($_POST['url'] ?? '');
        $iconText = trim($_POST['icon_text'] ?? '');

        if ($text !== '') {
            if ($id > 0) {
                $pdo->prepare('UPDATE about_buttons SET text = ?, text_ua = ?, type = ?, url = ?, icon_text = ? WHERE id = ?')
                    ->execute([$text, $textUa ?: null, $type, $url ?: null, $iconText ?: null, $id]);
            } else {
                $maxOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM about_buttons')->fetchColumn();
                $pdo->prepare('INSERT INTO about_buttons (text, text_ua, type, url, icon_text, enabled, sort_order) VALUES (?, ?, ?, ?, ?, 1, ?)')
                    ->execute([$text, $textUa ?: null, $type, $url ?: null, $iconText ?: null, $maxOrder + 1]);
            }
        }
        redirect('about.php');
    }

    // ===== Кнопки: вкл/выкл тумблером прямо из списка =====
    if ($form === 'btn_toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE about_buttons SET enabled = ? WHERE id = ?')
            ->execute([isset($_POST['enabled']) ? 1 : 0, $id]);
        redirect('about.php');
    }

    // ===== Кнопки: удалить =====
    if ($form === 'btn_delete') {
        $pdo->prepare('DELETE FROM about_buttons WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
        redirect('about.php');
    }

    // ===== Статистика: добавить =====
    if ($form === 'stat_add') {
        $value = trim($_POST['value'] ?? '');
        $label = trim($_POST['label'] ?? '');
        $labelUa = trim($_POST['label_ua'] ?? '');
        if ($value !== '' && $label !== '') {
            $maxOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM about_stats')->fetchColumn();
            $pdo->prepare('INSERT INTO about_stats (value, label, label_ua, sort_order) VALUES (?, ?, ?, ?)')
                ->execute([$value, $label, $labelUa ?: null, $maxOrder + 1]);
        }
        redirect('about.php');
    }

    // ===== Статистика: удалить =====
    if ($form === 'stat_delete') {
        $pdo->prepare('DELETE FROM about_stats WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
        redirect('about.php');
    }

    // ===== Навык: добавить =====
    if ($form === 'skill_add') {
        $name = trim($_POST['name'] ?? '');
        $nameUa = trim($_POST['name_ua'] ?? '');
        $iconText = trim($_POST['icon_text'] ?? '');
        if ($name !== '') {
            $iconImage = saveUploadedFile(
                'icon_image',
                'assets/uploads/skills',
                ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
                2 * 1024 * 1024,
                'skill'
            );
            $maxOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM about_skills')->fetchColumn();
            $pdo->prepare('INSERT INTO about_skills (name, name_ua, icon_text, icon_image, sort_order) VALUES (?, ?, ?, ?, ?)')
                ->execute([$name, $nameUa ?: null, $iconText ?: null, $iconImage, $maxOrder + 1]);
        }
        redirect('about.php');
    }

    // ===== Навык: удалить =====
    if ($form === 'skill_delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT icon_image FROM about_skills WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row) {
            deleteUploadedFile($row['icon_image']);
        }
        $pdo->prepare('DELETE FROM about_skills WHERE id = ?')->execute([$id]);
        redirect('about.php');
    }

    // ===== Опыт работы: добавить / изменить =====
    // (перенесено из отдельного experience.php — теперь это подкатегория
    // внутри вкладки «О мне»)
    if ($form === 'exp_save') {
        $period        = trim($_POST['period'] ?? '');
        $position      = trim($_POST['position'] ?? '');
        $positionUa    = trim($_POST['position_ua'] ?? '');
        $company       = trim($_POST['company'] ?? '');
        $companyUa     = trim($_POST['company_ua'] ?? '');
        $description   = trim($_POST['description'] ?? '');
        $descriptionUa = trim($_POST['description_ua'] ?? '');
        $id = (int)($_POST['id'] ?? 0);

        if ($period !== '' && $position !== '') {
            if ($id > 0) {
                $pdo->prepare('
                    UPDATE work_experience SET period = ?, position = ?, position_ua = ?, company = ?, company_ua = ?, description = ?, description_ua = ?
                    WHERE id = ?
                ')->execute([$period, $position, $positionUa ?: null, $company ?: null, $companyUa ?: null, $description ?: null, $descriptionUa ?: null, $id]);
            } else {
                $maxOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM work_experience')->fetchColumn();
                $pdo->prepare('
                    INSERT INTO work_experience (period, position, position_ua, company, company_ua, description, description_ua, sort_order)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ')->execute([$period, $position, $positionUa ?: null, $company ?: null, $companyUa ?: null, $description ?: null, $descriptionUa ?: null, $maxOrder + 1]);
            }
        }
        redirect('about.php#about-acc-experience');
    }

    // ===== Опыт работы: удалить =====
    if ($form === 'exp_delete') {
        $pdo->prepare('DELETE FROM work_experience WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
        redirect('about.php#about-acc-experience');
    }

    // ===== Виджеты: показывать/скрывать блок «Достижения» на сайте =====
    if ($form === 'widgets_toggle_enabled') {
        setSetting('widgets_enabled', isset($_POST['widgets_enabled']) ? '1' : '0');
        redirect('about.php#about-acc-widgets');
    }

    // ===== Виджеты: категория — добавить / изменить =====
    if ($form === 'widgetcat_save') {
        $name   = trim($_POST['name'] ?? '');
        $nameUa = trim($_POST['name_ua'] ?? '');
        $type   = $_POST['type'] ?? 'photo';
        if (!in_array($type, ['photo', 'video', 'pdf'], true)) {
            $type = 'photo';
        }
        $id = (int)($_POST['id'] ?? 0);

        if ($name !== '') {
            if ($id > 0) {
                // Тип категории не даём менять при редактировании — иначе уже
                // загруженные файлы (фото/видео/PDF) перестанут соответствовать
                // новому типу отображения. Чтобы сменить тип — создайте новую категорию.
                $pdo->prepare('UPDATE widget_categories SET name = ?, name_ua = ? WHERE id = ?')
                    ->execute([$name, $nameUa ?: null, $id]);
            } else {
                $maxOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM widget_categories')->fetchColumn();
                $pdo->prepare('INSERT INTO widget_categories (name, name_ua, type, sort_order) VALUES (?, ?, ?, ?)')
                    ->execute([$name, $nameUa ?: null, $type, $maxOrder + 1]);
            }
        }
        redirect('about.php#about-acc-widgets');
    }

    // ===== Виджеты: категория — удалить (вместе со всеми файлами) =====
    if ($form === 'widgetcat_delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT file_path FROM widget_items WHERE category_id = ?');
        $stmt->execute([$id]);
        foreach ($stmt->fetchAll() as $row) {
            deleteUploadedFile($row['file_path']);
        }
        $pdo->prepare('DELETE FROM widget_categories WHERE id = ?')->execute([$id]);
        redirect('about.php#about-acc-widgets');
    }

    // ===== Соцсети: ссылка — добавить / изменить =====
    if ($form === 'social_save') {
        $platform   = trim($_POST['platform'] ?? '');
        $platformUa = trim($_POST['platform_ua'] ?? '');
        $url        = trim($_POST['url'] ?? '');
        $iconText   = trim($_POST['icon_text'] ?? '');
        $id = (int)($_POST['id'] ?? 0);

        if ($platform !== '' && $url !== '') {
            $iconImage = saveUploadedFile(
                'icon_image',
                'assets/uploads/social',
                ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
                2 * 1024 * 1024,
                'social'
            );

            if ($id > 0) {
                if ($iconImage !== null) {
                    $stmt = $pdo->prepare('SELECT icon_image FROM social_links WHERE id = ?');
                    $stmt->execute([$id]);
                    $old = $stmt->fetch();
                    if ($old) {
                        deleteUploadedFile($old['icon_image']);
                    }
                    $pdo->prepare('UPDATE social_links SET platform = ?, platform_ua = ?, icon_text = ?, icon_image = ?, url = ? WHERE id = ?')
                        ->execute([$platform, $platformUa ?: null, $iconText ?: null, $iconImage, $url, $id]);
                } else {
                    $pdo->prepare('UPDATE social_links SET platform = ?, platform_ua = ?, icon_text = ?, url = ? WHERE id = ?')
                        ->execute([$platform, $platformUa ?: null, $iconText ?: null, $url, $id]);
                }
            } else {
                $maxOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM social_links')->fetchColumn();
                $pdo->prepare('INSERT INTO social_links (platform, platform_ua, icon_text, icon_image, url, sort_order) VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([$platform, $platformUa ?: null, $iconText ?: null, $iconImage, $url, $maxOrder + 1]);
            }
        }
        redirect('about.php#about-acc-social');
    }

    // ===== Соцсети: удалить =====
    if ($form === 'social_delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT icon_image FROM social_links WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row) {
            deleteUploadedFile($row['icon_image']);
        }
        $pdo->prepare('DELETE FROM social_links WHERE id = ?')->execute([$id]);
        redirect('about.php#about-acc-social');
    }
}

$about = $pdo->query('SELECT * FROM about_me WHERE id = 1')->fetch();
$stats = $pdo->query('SELECT * FROM about_stats ORDER BY sort_order, id')->fetchAll();
$skills = $pdo->query('SELECT * FROM about_skills ORDER BY sort_order, id')->fetchAll();
$buttons = $pdo->query('SELECT * FROM about_buttons ORDER BY sort_order, id')->fetchAll();

// ===== Данные подкатегории «Опыт работы» =====
$expEditItem = null;
if (isset($_GET['edit_exp'])) {
    $stmt = $pdo->prepare('SELECT * FROM work_experience WHERE id = ?');
    $stmt->execute([(int)$_GET['edit_exp']]);
    $expEditItem = $stmt->fetch() ?: null;
}
$expItems = $pdo->query('SELECT * FROM work_experience ORDER BY sort_order, id DESC')->fetchAll();

// ===== Данные подкатегории «Виджеты» =====
$widgetTypeLabels = ['photo' => 'Фото (галерея)', 'video' => 'Видео', 'pdf' => 'PDF (сертификаты)'];
$widgetTypeAccept = [
    'photo' => 'image/jpeg,image/png,image/webp,image/gif',
    'video' => 'video/mp4,video/webm,video/quicktime',
    'pdf'   => 'application/pdf',
];
$widgetCatEditItem = null;
if (isset($_GET['edit_widgetcat'])) {
    $stmt = $pdo->prepare('SELECT * FROM widget_categories WHERE id = ?');
    $stmt->execute([(int)$_GET['edit_widgetcat']]);
    $widgetCatEditItem = $stmt->fetch() ?: null;
}
$widgetCategories = $pdo->query('SELECT * FROM widget_categories ORDER BY sort_order, id')->fetchAll();
$widgetItemsByCat = [];
foreach ($pdo->query('SELECT * FROM widget_items ORDER BY category_id, sort_order, id')->fetchAll() as $row) {
    $widgetItemsByCat[(int)$row['category_id']][] = $row;
}
$widgetsEnabled = getSetting('widgets_enabled', '1') === '1';

// ===== Данные подкатегории «Соцсети» =====
$socialEditItem = null;
if (isset($_GET['edit_social'])) {
    $stmt = $pdo->prepare('SELECT * FROM social_links WHERE id = ?');
    $stmt->execute([(int)$_GET['edit_social']]);
    $socialEditItem = $stmt->fetch() ?: null;
}
$socialItems = $pdo->query('SELECT * FROM social_links ORDER BY sort_order, id')->fetchAll();

// Какую секцию аккордеона раскрыть автоматически при загрузке страницы
// (например, если перешли по ссылке "Изменить" из списка внутри секции).
$autoOpenSection = null;
if ($expEditItem) { $autoOpenSection = 'about-acc-experience'; }
if ($widgetCatEditItem) { $autoOpenSection = 'about-acc-widgets'; }
if ($socialEditItem) { $autoOpenSection = 'about-acc-social'; }
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>О мне (опыт, виджеты, соцсети) — Панель управления</title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
<script>window.ADMIN_CSRF_TOKEN = <?= json_encode(csrfToken()) ?>;</script>
</head>
<body>
<div class="admin-shell">
  <?php require __DIR__ . '/includes/nav.php'; ?>

  <?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>

  <p style="color:var(--ink-soft); font-size:13px;">
    Всё содержимое вкладки «О мне» — включая опыт работы, виджеты и соцсети —
    собрано здесь по категориям. Нажмите на плашку категории, чтобы раскрыть
    её и отредактировать содержимое; открытые категории можно свернуть обратно
    точно так же.
  </p>

  <div class="about-accordion">

    <?php
      // Небольшой помощник для рендера заголовка (плашки) категории —
      // одна и та же разметка для всех 7 подкатегорий, чтобы клик и
      // анимация раскрытия/сворачивания работали одинаково везде.
      function about_accordion_header(string $title, string $sub, ?string $count = null): void {
    ?>
      <button type="button" class="about-accordion-header" aria-expanded="false">
        <span class="about-accordion-header-text">
          <h3><?= e($title) ?></h3>
          <p><?= e($sub) ?></p>
        </span>
        <span class="about-accordion-header-right">
          <?php if ($count !== null): ?><span class="about-accordion-count"><?= e($count) ?></span><?php endif; ?>
          <svg class="about-accordion-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 6 15 12 9 18"/></svg>
        </span>
      </button>
    <?php
      }
    ?>

    <!-- ==================== 1. ИНФОРМАЦИЯ ==================== -->
    <div class="about-accordion-item" id="about-acc-info">
      <?php about_accordion_header('Информация', 'Фото, приветствие, заголовок, подзаголовок и текст «о себе».'); ?>
      <div class="about-accordion-body">
        <div class="about-accordion-body-inner">
          <div class="about-accordion-content">
            <div class="about-overview-preview" style="border-top:none; padding-top:0; margin-top:0;">
              <div class="about-overview-preview-photo">
                <?php if (!empty($about['photo_path'])): ?>
                  <img src="../<?= e($about['photo_path']) ?>" alt="">
                <?php else: ?>
                  <span>нет фото</span>
                <?php endif; ?>
              </div>
              <div class="about-overview-preview-text">
                <div class="about-overview-preview-title"><?= e($about['title'] ?: 'Заголовок пока не задан') ?></div>
                <div class="about-overview-preview-sub"><?= e($about['subtitle'] ?? '') ?></div>
              </div>
            </div>
            <button type="button" class="btn ghost" style="margin-top:14px;" data-modal-open="modalInfo">⚙ Настроить</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== 2. КНОПКИ ==================== -->
    <div class="about-accordion-item" id="about-acc-buttons">
      <?php about_accordion_header('Кнопки', 'Кнопок можно добавить сколько угодно — каждую можно включить/выключить.', $buttons ? (string)count($buttons) : null); ?>
      <div class="about-accordion-body">
        <div class="about-accordion-body-inner">
          <div class="about-accordion-content">
            <?php if ($buttons): ?>
              <div class="settings-group">
                <?php foreach ($buttons as $b): ?>
                  <div class="settings-row">
                    <div style="display:flex; align-items:center; gap:10px; min-width:0;">
                      <span class="about-btn-row-icon"><?= e($b['icon_text'] ?: '🔘') ?></span>
                      <div style="min-width:0;">
                        <div class="settings-row-label"><?= e($b['text']) ?><?= $b['text_ua'] ? ' / ' . e($b['text_ua']) : '' ?></div>
                        <div class="settings-row-sub"><?= e($btnTypeLabels[$b['type']] ?? $b['type']) ?></div>
                      </div>
                    </div>
                    <div style="display:flex; align-items:center; gap:10px; flex-shrink:0;">
                      <button type="button" class="icon-btn icon-btn--sm"
                        data-btn-edit
                        data-id="<?= (int)$b['id'] ?>"
                        data-text="<?= e($b['text']) ?>"
                        data-text-ua="<?= e($b['text_ua'] ?? '') ?>"
                        data-type="<?= e($b['type']) ?>"
                        data-url="<?= e($b['url'] ?? '') ?>"
                        data-icon="<?= e($b['icon_text'] ?? '') ?>"
                        aria-label="Изменить">✎</button>
                      <form method="post" onsubmit="return confirm('Удалить эту кнопку?');">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="form" value="btn_delete">
                        <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                        <button type="submit" class="icon-btn icon-btn--sm" aria-label="Удалить">✕</button>
                      </form>
                      <form method="post" onchange="this.submit()">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="form" value="btn_toggle">
                        <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                        <label class="switch">
                          <input type="checkbox" name="enabled" value="1" <?= $b['enabled'] ? 'checked' : '' ?>>
                          <span class="switch-slider"></span>
                        </label>
                      </form>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
            <button type="button" class="admin-add-tile-btn" data-btn-add-open><span class="admin-plus-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg></span><span>Добавить кнопку</span></button>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== 3. СТАТИСТИКА ==================== -->
    <div class="about-accordion-item" id="about-acc-stats">
      <?php about_accordion_header('Статистика', 'Например «4+ — Года опыта».', $stats ? (string)count($stats) : null); ?>
      <div class="about-accordion-body">
        <div class="about-accordion-body-inner">
          <div class="about-accordion-content">
            <?php if ($stats): ?>
              <div class="settings-group">
                <?php foreach ($stats as $s): ?>
                  <div class="settings-row">
                    <div class="settings-row-label"><?= e($s['value']) ?> — <?= e($s['label']) ?><?= $s['label_ua'] ? ' / ' . e($s['label_ua']) : '' ?></div>
                    <form method="post" onsubmit="return confirm('Удалить эту статистику?');">
                      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                      <input type="hidden" name="form" value="stat_delete">
                      <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                      <button type="submit" class="icon-btn icon-btn--sm" aria-label="Удалить">✕</button>
                    </form>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
            <button type="button" class="admin-add-tile-btn" data-modal-open="modalStatAdd"><span class="admin-plus-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg></span><span>Добавить статистику</span></button>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== 4. НАВЫКИ / ИНСТРУМЕНТЫ ==================== -->
    <div class="about-accordion-item" id="about-acc-skills">
      <?php about_accordion_header('Навыки / инструменты', 'Иконки с подписью — например используемые инструменты.', $skills ? (string)count($skills) : null); ?>
      <div class="about-accordion-body">
        <div class="about-accordion-body-inner">
          <div class="about-accordion-content">
            <?php if ($skills): ?>
              <div class="settings-group">
                <?php foreach ($skills as $sk): ?>
                  <div class="settings-row">
                    <div style="display:flex; align-items:center; gap:10px;">
                      <?php if (!empty($sk['icon_image'])): ?>
                        <img src="../<?= e($sk['icon_image']) ?>" style="width:26px;height:26px;object-fit:cover;border-radius:6px;">
                      <?php else: ?>
                        <span class="about-btn-row-icon"><?= e($sk['icon_text'] ?: '★') ?></span>
                      <?php endif; ?>
                      <div class="settings-row-label"><?= e($sk['name']) ?><?= $sk['name_ua'] ? ' / ' . e($sk['name_ua']) : '' ?></div>
                    </div>
                    <form method="post" onsubmit="return confirm('Удалить этот навык?');">
                      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                      <input type="hidden" name="form" value="skill_delete">
                      <input type="hidden" name="id" value="<?= (int)$sk['id'] ?>">
                      <button type="submit" class="icon-btn icon-btn--sm" aria-label="Удалить">✕</button>
                    </form>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
            <button type="button" class="admin-add-tile-btn" data-modal-open="modalSkillAdd"><span class="admin-plus-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg></span><span>Добавить навык</span></button>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== 5. ОПЫТ РАБОТЫ ==================== -->
    <div class="about-accordion-item" id="about-acc-experience">
      <?php about_accordion_header('Опыт работы', 'Периоды, должности и компании — список на сайте.', $expItems ? (string)count($expItems) : null); ?>
      <div class="about-accordion-body">
        <div class="about-accordion-body-inner">
          <div class="about-accordion-content">

            <div class="card" style="margin-top:0;">
              <h3><?= $expEditItem ? 'Изменить запись' : 'Добавить запись об опыте' ?></h3>
              <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="form" value="exp_save">
                <?php if ($expEditItem): ?><input type="hidden" name="id" value="<?= (int)$expEditItem['id'] ?>"><?php endif; ?>

                <div class="form-field">
                  <label>Период (например «2022 — по наст. время»)</label>
                  <input type="text" name="period" required maxlength="60" value="<?= e($expEditItem['period'] ?? '') ?>">
                </div>
                <div class="form-field">
                  <label>Должность, рус.</label>
                  <input type="text" id="exp_position" name="position" required maxlength="100" value="<?= e($expEditItem['position'] ?? '') ?>">
                </div>
                <div class="form-field">
                  <label>Должность, укр. (необязательно)
                    <button type="button" class="btn ghost admin-translate-btn" data-translate-from="exp_position" data-translate-to="exp_position_ua">⇄ Перевести с рус.</button>
                  </label>
                  <input type="text" id="exp_position_ua" name="position_ua" maxlength="100" value="<?= e($expEditItem['position_ua'] ?? '') ?>">
                </div>
                <div class="form-field">
                  <label>Компания, рус. (необязательно)</label>
                  <input type="text" id="exp_company" name="company" maxlength="100" value="<?= e($expEditItem['company'] ?? '') ?>">
                </div>
                <div class="form-field">
                  <label>Компания, укр. (необязательно)
                    <button type="button" class="btn ghost admin-translate-btn" data-translate-from="exp_company" data-translate-to="exp_company_ua">⇄ Перевести с рус.</button>
                  </label>
                  <input type="text" id="exp_company_ua" name="company_ua" maxlength="100" value="<?= e($expEditItem['company_ua'] ?? '') ?>">
                </div>
                <div class="form-field">
                  <label>Описание, рус. (необязательно)</label>
                  <textarea id="exp_description" name="description" maxlength="600"><?= e($expEditItem['description'] ?? '') ?></textarea>
                </div>
                <div class="form-field">
                  <label>Описание, укр. (необязательно)
                    <button type="button" class="btn ghost admin-translate-btn" data-translate-from="exp_description" data-translate-to="exp_description_ua">⇄ Перевести с рус.</button>
                  </label>
                  <textarea id="exp_description_ua" name="description_ua" maxlength="600"><?= e($expEditItem['description_ua'] ?? '') ?></textarea>
                </div>

                <div class="admin-form-actions">
                  <?php if ($expEditItem): ?>
                    <a href="about.php#about-acc-experience" class="btn ghost">Отменить</a>
                  <?php endif; ?>
                  <button type="submit" class="btn"><?= $expEditItem ? 'Сохранить' : 'Добавить' ?></button>
                </div>
              </form>
            </div>

            <?php if ($expItems): ?>
              <div class="settings-group">
                <?php foreach ($expItems as $item): ?>
                  <div class="settings-row">
                    <div style="min-width:0;">
                      <div class="settings-row-label"><?= e($item['position']) ?><?= $item['company'] ? ' — ' . e($item['company']) : '' ?></div>
                      <div class="settings-row-sub"><?= e($item['period']) ?></div>
                    </div>
                    <div style="display:flex; align-items:center; gap:10px; flex-shrink:0;">
                      <a href="about.php?edit_exp=<?= (int)$item['id'] ?>#about-acc-experience" class="icon-btn icon-btn--sm" aria-label="Изменить">✎</a>
                      <form method="post" onsubmit="return confirm('Удалить эту запись?');">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="form" value="exp_delete">
                        <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                        <button type="submit" class="icon-btn icon-btn--sm" aria-label="Удалить">✕</button>
                      </form>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <p style="color:var(--ink-soft); font-size:13px;">Пока нет записей об опыте работы.</p>
            <?php endif; ?>

          </div>
        </div>
      </div>
    </div>

    <!-- ==================== 6. ВИДЖЕТЫ ==================== -->
    <div class="about-accordion-item" id="about-acc-widgets">
      <?php about_accordion_header('Виджеты', 'Категории фото, видео и PDF-сертификатов — карусель на сайте.', $widgetCategories ? (string)count($widgetCategories) : null); ?>
      <div class="about-accordion-body">
        <div class="about-accordion-body-inner">
          <div class="about-accordion-content">

            <p style="color:var(--ink-soft); font-size:13px; margin-top:0;">
              Создайте категорию (например «Портфолио», «Сертификаты», «Видео-отзывы»),
              выберите её тип, а затем нажмите на квадратную плитку «+» на карточке
              категории ниже, чтобы загрузить в неё фото, видео или PDF — окно
              загрузки откроется прямо здесь. На сайте содержимое категории
              листается горизонтально (карусель).
            </p>

            <div class="settings-group">
              <form method="post" id="widgetsEnabledForm" class="settings-row">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="form" value="widgets_toggle_enabled">
                <div>
                  <div class="settings-row-label">Показывать блок «Достижения» на сайте</div>
                  <div class="settings-row-sub">Общий выключатель — если выключить, вся карусель категорий пропадёт с вкладки «О мне», сами категории и файлы не удаляются.</div>
                </div>
                <label class="switch settings-row-control">
                  <input type="checkbox" name="widgets_enabled" value="1" onchange="document.getElementById('widgetsEnabledForm').submit()" <?= $widgetsEnabled ? 'checked' : '' ?>>
                  <span class="switch-slider"></span>
                </label>
              </form>
            </div>

            <div class="card">
              <h3><?= $widgetCatEditItem ? 'Изменить категорию' : 'Новая категория' ?></h3>
              <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="form" value="widgetcat_save">
                <?php if ($widgetCatEditItem): ?><input type="hidden" name="id" value="<?= (int)$widgetCatEditItem['id'] ?>"><?php endif; ?>

                <div class="form-field">
                  <label>Название категории, рус.</label>
                  <input type="text" id="widget_cat_name" name="name" required maxlength="60" value="<?= e($widgetCatEditItem['name'] ?? '') ?>">
                </div>
                <div class="form-field">
                  <label>Название категории, укр. (необязательно)
                    <button type="button" class="btn ghost admin-translate-btn" data-translate-from="widget_cat_name" data-translate-to="widget_cat_name_ua">⇄ Перевести с рус.</button>
                  </label>
                  <input type="text" id="widget_cat_name_ua" name="name_ua" maxlength="60" value="<?= e($widgetCatEditItem['name_ua'] ?? '') ?>">
                </div>
                <div class="form-field">
                  <label>Тип содержимого<?= $widgetCatEditItem ? ' (нельзя изменить после создания)' : '' ?></label>
                  <?php if ($widgetCatEditItem): ?>
                    <input type="text" value="<?= e($widgetTypeLabels[$widgetCatEditItem['type']] ?? $widgetCatEditItem['type']) ?>" disabled>
                  <?php else: ?>
                    <select name="type">
                      <?php foreach ($widgetTypeLabels as $val => $label): ?>
                        <option value="<?= e($val) ?>"><?= e($label) ?></option>
                      <?php endforeach; ?>
                    </select>
                  <?php endif; ?>
                </div>

                <div class="admin-form-actions">
                  <?php if ($widgetCatEditItem): ?>
                    <a href="about.php#about-acc-widgets" class="btn ghost">Отменить</a>
                  <?php endif; ?>
                  <button type="submit" class="btn"><?= $widgetCatEditItem ? 'Сохранить' : 'Создать категорию' ?></button>
                </div>
              </form>
            </div>

            <?php foreach ($widgetCategories as $cat): ?>
              <?php
                $__catId = (int)$cat['id'];
                $__catItems = $widgetItemsByCat[$__catId] ?? [];
                $__catName = e($cat['name']) . ($cat['name_ua'] ? ' / ' . e($cat['name_ua']) : '');
              ?>
              <div class="card widget-cat-card">
                <div class="widget-cat-card-head">
                  <div>
                    <h3 style="margin-bottom:2px;"><?= $__catName ?></h3>
                    <span class="widget-cat-card-meta"><?= e($widgetTypeLabels[$cat['type']] ?? $cat['type']) ?> · файлов: <?= count($__catItems) ?></span>
                  </div>
                  <div class="widget-cat-card-actions">
                    <a href="about.php?edit_widgetcat=<?= $__catId ?>#about-acc-widgets" class="btn ghost" style="padding:6px 12px;font-size:12px;">Изменить</a>
                    <form method="post" style="display:inline;" onsubmit="return confirm('Удалить категорию вместе со всеми файлами внутри неё?');">
                      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                      <input type="hidden" name="form" value="widgetcat_delete">
                      <input type="hidden" name="id" value="<?= $__catId ?>">
                      <button class="btn ghost" style="padding:6px 12px;font-size:12px;">Удалить</button>
                    </form>
                  </div>
                </div>
                <div class="admin-widget-item-grid">
                  <?php foreach ($__catItems as $__it): ?>
                    <button type="button" class="admin-widget-item-card"
                      data-item-edit
                      data-id="<?= (int)$__it['id'] ?>"
                      data-category-id="<?= $__catId ?>"
                      data-title="<?= e($__it['title'] ?? '') ?>">
                      <?php if ($cat['type'] === 'photo'): ?>
                        <img src="../<?= e($__it['file_path']) ?>" alt="">
                      <?php elseif ($cat['type'] === 'video'): ?>
                        <video src="../<?= e($__it['file_path']) ?>#t=0.1" preload="metadata" muted></video>
                      <?php else: ?>
                        <div class="admin-widget-item-card-pdf">📄</div>
                      <?php endif; ?>
                    </button>
                  <?php endforeach; ?>
                  <button type="button" class="admin-square-add-tile" data-modal-open="addItemModal-<?= $__catId ?>" aria-label="Добавить файл"><span class="admin-plus-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg></span><span>Добавить</span></button>
                </div>
              </div>
            <?php endforeach; ?>
            <?php if (!$widgetCategories): ?>
              <p style="color:var(--ink-soft); font-size:13px;">Пока нет ни одной категории — создайте первую в форме выше.</p>
            <?php endif; ?>

          </div>
        </div>
      </div>
    </div>

    <!-- ==================== 7. СОЦСЕТИ ==================== -->
    <div class="about-accordion-item" id="about-acc-social">
      <?php about_accordion_header('Соцсети', 'Отдельный настраиваемый блок ссылок на соцсети/мессенджеры (виджет «Соцсети»).', $socialItems ? (string)count($socialItems) : null); ?>
      <div class="about-accordion-body">
        <div class="about-accordion-body-inner">
          <div class="about-accordion-content">

            <p style="color:var(--ink-soft); font-size:13px; margin-top:0;">
              Он не связан с иконками Instagram/Viber/звонок в шапке и подвале
              сайта — те по-прежнему настраиваются в разделе «Настройки».
            </p>

            <div class="card">
              <h3><?= $socialEditItem ? 'Изменить ссылку' : 'Добавить ссылку' ?></h3>
              <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="form" value="social_save">
                <?php if ($socialEditItem): ?><input type="hidden" name="id" value="<?= (int)$socialEditItem['id'] ?>"><?php endif; ?>

                <div class="form-field">
                  <label>Название (например «Instagram», «WhatsApp»)</label>
                  <input type="text" id="social_platform" name="platform" required maxlength="40" value="<?= e($socialEditItem['platform'] ?? '') ?>">
                </div>
                <div class="form-field">
                  <label>Название (укр., необязательно)
                    <button type="button" class="btn ghost admin-translate-btn" data-translate-from="social_platform" data-translate-to="social_platform_ua">⇄ Перевести с рус.</button>
                  </label>
                  <input type="text" id="social_platform_ua" name="platform_ua" maxlength="40" value="<?= e($socialEditItem['platform_ua'] ?? '') ?>">
                </div>
                <div class="form-field">
                  <label>Ссылка (полный адрес, например https://t.me/username)</label>
                  <input type="text" name="url" required value="<?= e($socialEditItem['url'] ?? '') ?>">
                </div>
                <div class="form-field">
                  <label>Иконка-эмодзи (если нет картинки), например 📷</label>
                  <input type="text" name="icon_text" maxlength="4" value="<?= e($socialEditItem['icon_text'] ?? '') ?>">
                </div>
                <div class="form-field">
                  <label>Иконка-картинка (необязательно, заменяет эмодзи)</label>
                  <?php if (!empty($socialEditItem['icon_image'])): ?>
                    <div class="admin-upload-current"><img src="../<?= e($socialEditItem['icon_image']) ?>" alt=""></div>
                  <?php endif; ?>
                  <label class="file-input-styled">
                    <span>Выбрать файл</span>
                    <input type="file" name="icon_image" accept="image/png,image/jpeg,image/webp">
                  </label>
                </div>

                <div class="admin-form-actions">
                  <?php if ($socialEditItem): ?>
                    <a href="about.php#about-acc-social" class="btn ghost">Отменить</a>
                  <?php endif; ?>
                  <button type="submit" class="btn"><?= $socialEditItem ? 'Сохранить' : 'Добавить' ?></button>
                </div>
              </form>
            </div>

            <?php if ($socialItems): ?>
              <div class="settings-group">
                <?php foreach ($socialItems as $item): ?>
                  <div class="settings-row">
                    <div style="display:flex; align-items:center; gap:10px; min-width:0;">
                      <?php if (!empty($item['icon_image'])): ?>
                        <img src="../<?= e($item['icon_image']) ?>" style="width:26px;height:26px;object-fit:cover;border-radius:6px;flex-shrink:0;">
                      <?php else: ?>
                        <span class="about-btn-row-icon"><?= e($item['icon_text'] ?: '🔗') ?></span>
                      <?php endif; ?>
                      <div style="min-width:0;">
                        <div class="settings-row-label"><?= e($item['platform']) ?><?= $item['platform_ua'] ? ' / ' . e($item['platform_ua']) : '' ?></div>
                        <div class="settings-row-sub" style="word-break:break-all;"><?= e($item['url']) ?></div>
                      </div>
                    </div>
                    <div style="display:flex; align-items:center; gap:10px; flex-shrink:0;">
                      <a href="about.php?edit_social=<?= (int)$item['id'] ?>#about-acc-social" class="icon-btn icon-btn--sm" aria-label="Изменить">✎</a>
                      <form method="post" onsubmit="return confirm('Удалить эту ссылку?');">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="form" value="social_delete">
                        <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                        <button type="submit" class="icon-btn icon-btn--sm" aria-label="Удалить">✕</button>
                      </form>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <p style="color:var(--ink-soft); font-size:13px;">Пока нет ссылок.</p>
            <?php endif; ?>

          </div>
        </div>
      </div>
    </div>

  </div>

  <!-- ==================== МОДАЛКА: Информация ==================== -->
  <div class="modal-overlay" id="modalInfo">
    <div class="modal-box" style="max-width:600px; text-align:left;">
      <button type="button" class="modal-close" data-modal-close style="position:static; margin:0 0 8px auto; display:block;">✕</button>
      <h3 style="text-align:left;">Информация</h3>
      <p style="color:var(--ink-soft); font-size:13px; margin-top:0;">
        Поля с пометкой «укр.» необязательны — если не заполнить, на украинской
        версии сайта будет показан русский текст. Если оставить заголовок и
        текст «о себе» пустыми, блок на сайте вообще не появится.
      </p>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="form" value="about_main">

        <div class="form-field">
          <label>Фото / аватар</label>
          <div class="admin-avatar-field">
            <div class="admin-avatar-frame" id="aboutPhotoFrame" role="button" tabindex="0" aria-label="Выбрать/сменить фото">
              <?php if (!empty($about['photo_path'])): ?>
                <img src="../<?= e($about['photo_path']) ?>" alt="" id="aboutPhotoFrameImg">
              <?php else: ?>
                <span class="admin-avatar-frame-placeholder" id="aboutPhotoFramePlaceholder">
                  <svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4.4 3.6-7 8-7s8 2.6 8 7"/></svg>
                </span>
              <?php endif; ?>
              <span class="admin-avatar-frame-hint">Изменить</span>
            </div>
            <div class="admin-avatar-field-actions">
              <label class="file-input-styled">
                <span>Выбрать файл</span>
                <input type="file" name="photo" id="aboutPhotoInput" accept="image/png,image/jpeg,image/webp">
              </label>
              <?php if (!empty($about['photo_path'])): ?>
                <label class="switch-field">
                  <span class="switch-field-label">Удалить текущее фото</span>
                  <span class="switch">
                    <input type="checkbox" name="remove_photo" value="1">
                    <span class="switch-slider"></span>
                  </span>
                </label>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="form-field">
          <label>Приветствие (рус.), например «Привет, я»</label>
          <input type="text" id="greeting" name="greeting" value="<?= e($about['greeting'] ?? '') ?>" maxlength="60">
        </div>
        <div class="form-field">
          <label>Приветствие (укр., необязательно)
            <button type="button" class="btn ghost admin-translate-btn" data-translate-from="greeting" data-translate-to="greeting_ua">⇄ Перевести с рус.</button>
          </label>
          <input type="text" id="greeting_ua" name="greeting_ua" value="<?= e($about['greeting_ua'] ?? '') ?>" maxlength="60">
        </div>

        <div class="form-field">
          <label>Заголовок (рус.), например «Меня зовут Мария»</label>
          <input type="text" id="title" name="title" value="<?= e($about['title'] ?? '') ?>" maxlength="100">
        </div>
        <div class="form-field">
          <label>Заголовок (укр., необязательно)
            <button type="button" class="btn ghost admin-translate-btn" data-translate-from="title" data-translate-to="title_ua">⇄ Перевести с рус.</button>
          </label>
          <input type="text" id="title_ua" name="title_ua" value="<?= e($about['title_ua'] ?? '') ?>" maxlength="100">
        </div>

        <div class="form-field">
          <label>Подзаголовок (рус.), например «Мастер маникюра»</label>
          <input type="text" id="subtitle" name="subtitle" value="<?= e($about['subtitle'] ?? '') ?>" maxlength="120">
        </div>
        <div class="form-field">
          <label>Подзаголовок (укр., необязательно)
            <button type="button" class="btn ghost admin-translate-btn" data-translate-from="subtitle" data-translate-to="subtitle_ua">⇄ Перевести с рус.</button>
          </label>
          <input type="text" id="subtitle_ua" name="subtitle_ua" value="<?= e($about['subtitle_ua'] ?? '') ?>" maxlength="120">
        </div>

        <div class="form-field">
          <label>Текст «О себе» (рус.)</label>
          <textarea id="bio" name="bio" maxlength="800"><?= e($about['bio'] ?? '') ?></textarea>
        </div>
        <div class="form-field">
          <label>Текст «О себе» (укр., необязательно)
            <button type="button" class="btn ghost admin-translate-btn" data-translate-from="bio" data-translate-to="bio_ua">⇄ Перевести с рус.</button>
          </label>
          <textarea id="bio_ua" name="bio_ua" maxlength="800"><?= e($about['bio_ua'] ?? '') ?></textarea>
        </div>

        <div class="admin-form-actions">
          <button type="button" class="btn ghost" id="aboutPreviewBtn">👁 Предпросмотр</button>
          <button type="submit" class="btn">Сохранить</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ==================== МОДАЛКА: Предпросмотр блока «О мне» ====================
       Показывает точную копию публичного блока «О мне» (та же вёрстка/классы,
       что и на сайте), обновляется в реальном времени по мере ввода текста —
       ничего сохранять и никуда переходить для этого не нужно. -->
  <div class="modal-overlay" id="modalPreview">
    <div class="modal-box" style="max-width:640px; text-align:left;">
      <button type="button" class="modal-close" data-modal-close style="position:static; margin:0 0 8px auto; display:block;">✕</button>
      <h3 style="text-align:left;">Предпросмотр</h3>
      <p style="color:var(--ink-soft); font-size:13px; margin-top:0;">
        Так этот блок будет выглядеть на сайте прямо сейчас, с учётом
        текущих несохранённых изменений в форме.
      </p>
      <div class="admin-preview-frame" id="aboutLivePreview">
        <div class="about-me">
          <div class="about-me-photo">
            <span data-preview="photo">
              <?php if (!empty($about['photo_path'])): ?>
                <img src="../<?= e($about['photo_path']) ?>" alt="">
              <?php else: ?>
                <div class="about-me-photo-placeholder" aria-hidden="true">
                  <svg viewBox="0 0 24 24" width="48" height="48" fill="currentColor"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4.4 3.6-7 8-7s8 2.6 8 7"/></svg>
                </div>
              <?php endif; ?>
            </span>
          </div>
          <div class="about-me-content">
            <span class="about-me-eyebrow" data-preview="greeting"><?= e($about['greeting'] ?? '') ?></span>
            <h1 class="about-me-title" data-preview="title"><?= e($about['title'] ?: 'Заголовок появится здесь') ?></h1>
            <p class="about-me-subtitle" data-preview="subtitle"><?= e($about['subtitle'] ?? '') ?></p>
            <p class="about-me-bio" data-preview="bio"><?= nl2br(e($about['bio'] ?: 'Текст «о себе» появится здесь')) ?></p>

            <?php if ($stats): ?>
              <div class="about-me-stats">
                <?php foreach ($stats as $__s): ?>
                  <div class="about-me-stat">
                    <div class="about-me-stat-value"><?= e($__s['value']) ?></div>
                    <div class="about-me-stat-label"><?= e($__s['label']) ?></div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <?php if ($skills): ?>
              <div class="about-me-skills">
                <?php foreach ($skills as $__sk): ?>
                  <div class="about-me-skill">
                    <?php if (!empty($__sk['icon_image'])): ?>
                      <span class="about-me-skill-icon about-me-skill-icon--img"><img src="../<?= e($__sk['icon_image']) ?>" alt=""></span>
                    <?php else: ?>
                      <span class="about-me-skill-icon"><?= e($__sk['icon_text'] ?: '★') ?></span>
                    <?php endif; ?>
                    <span><?= e($__sk['name']) ?></span>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <?php
              $__previewBtns = [];
              foreach ($buttons as $__b) {
                  if (!$__b['enabled']) continue;
                  $__previewBtns[] = $__b['text'];
              }
            ?>
            <?php if ($__previewBtns): ?>
              <div class="about-me-actions">
                <?php foreach ($__previewBtns as $__i => $__t): ?>
                  <span class="btn<?= $__i > 0 ? ' ghost' : '' ?>" style="pointer-events:none;"><?= e($__t) ?></span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ==================== МОДАЛКА: Кнопка (добавить / изменить) ==================== -->
  <div class="modal-overlay" id="modalButton">
    <div class="modal-box" style="max-width:480px; text-align:left;">
      <button type="button" class="modal-close" data-modal-close style="position:static; margin:0 0 8px auto; display:block;">✕</button>
      <h3 id="modalButtonTitle" style="text-align:left;">Новая кнопка</h3>
      <form method="post" id="buttonForm">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="form" value="btn_save">
        <input type="hidden" name="id" id="btn_id" value="">

        <div class="form-field">
          <label>Название (рус.), например «Смотреть работы»</label>
          <input type="text" id="btn_text" name="text" required maxlength="40">
        </div>
        <div class="form-field">
          <label>Название (укр., необязательно)
            <button type="button" class="btn ghost admin-translate-btn" data-translate-from="btn_text" data-translate-to="btn_text_ua">⇄ Перевести с рус.</button>
          </label>
          <input type="text" id="btn_text_ua" name="text_ua" maxlength="40">
        </div>
        <div class="form-field">
          <label>Иконка (эмодзи, необязательно), например 📸</label>
          <input type="text" id="btn_icon_text" name="icon_text" maxlength="4">
        </div>
        <div class="form-field">
          <label>Куда ведёт</label>
          <select name="type" id="btn_type" class="admin-btn-type-select" data-url-field="btn_url_field">
            <?php foreach ($btnTypeLabels as $__val => $__label): ?>
              <option value="<?= e($__val) ?>"><?= e($__label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-field" id="btn_url_field">
          <label>Ссылка (для типа «Своя ссылка»)</label>
          <input type="text" id="btn_url" name="url" placeholder="например #widget-1 или https://...">
        </div>

        <button type="submit" class="btn full">Сохранить кнопку</button>
      </form>
    </div>
  </div>

  <!-- ==================== МОДАЛКА: Добавить статистику ==================== -->
  <div class="modal-overlay" id="modalStatAdd">
    <div class="modal-box" style="max-width:420px; text-align:left;">
      <button type="button" class="modal-close" data-modal-close style="position:static; margin:0 0 8px auto; display:block;">✕</button>
      <h3 style="text-align:left;">Новая статистика</h3>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="form" value="stat_add">
        <div class="form-field">
          <label>Значение (например «4+», «50+»)</label>
          <input type="text" name="value" required maxlength="20">
        </div>
        <div class="form-field">
          <label>Подпись, рус. (например «Года опыта»)</label>
          <input type="text" id="stat_label" name="label" required maxlength="60">
        </div>
        <div class="form-field">
          <label>Подпись, укр. (необязательно)
            <button type="button" class="btn ghost admin-translate-btn" data-translate-from="stat_label" data-translate-to="stat_label_ua">⇄ Перевести с рус.</button>
          </label>
          <input type="text" id="stat_label_ua" name="label_ua" maxlength="60">
        </div>
        <button type="submit" class="btn full">Добавить</button>
      </form>
    </div>
  </div>

  <!-- ==================== МОДАЛКА: Добавить навык ==================== -->
  <div class="modal-overlay" id="modalSkillAdd">
    <div class="modal-box" style="max-width:420px; text-align:left;">
      <button type="button" class="modal-close" data-modal-close style="position:static; margin:0 0 8px auto; display:block;">✕</button>
      <h3 style="text-align:left;">Новый навык</h3>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="form" value="skill_add">
        <div class="form-field">
          <label>Название, рус. (например «Premiere Pro»)</label>
          <input type="text" id="skill_name" name="name" required maxlength="60">
        </div>
        <div class="form-field">
          <label>Название, укр. (необязательно)
            <button type="button" class="btn ghost admin-translate-btn" data-translate-from="skill_name" data-translate-to="skill_name_ua">⇄ Перевести с рус.</button>
          </label>
          <input type="text" id="skill_name_ua" name="name_ua" maxlength="60">
        </div>
        <div class="form-field">
          <label>Короткая иконка-текст (например «Pr»), если нет картинки</label>
          <input type="text" name="icon_text" maxlength="4">
        </div>
        <div class="form-field">
          <label>Иконка-картинка (необязательно, заменяет текст выше)</label>
          <label class="file-input-styled">
            <span>Выбрать файл</span>
            <input type="file" name="icon_image" accept="image/png,image/jpeg,image/webp">
          </label>
        </div>
        <button type="submit" class="btn full">Добавить</button>
      </form>
    </div>
  </div>

  <!-- ==================== МОДАЛКИ: Виджеты — загрузка файла в категорию ====================
       По одной на категорию (у каждой свой допустимый тип файла и лимит размера). -->
  <?php foreach ($widgetCategories as $cat): ?>
    <?php
      $__catId = (int)$cat['id'];
      $__catMaxBytes = $cat['type'] === 'video' ? 60 * 1024 * 1024 : 8 * 1024 * 1024;
    ?>
    <div class="modal-overlay" id="addItemModal-<?= $__catId ?>">
      <div class="modal-box" style="max-width:420px; text-align:left;">
        <button type="button" class="modal-close" data-modal-close style="position:static; margin:0 0 8px auto; display:block;">✕</button>
        <h3 style="text-align:left;">Добавить в «<?= e($cat['name']) ?>»</h3>
        <form method="post" action="widget_items.php" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
          <input type="hidden" name="action" value="upload">
          <input type="hidden" name="category_id" value="<?= $__catId ?>">
          <div class="form-field">
            <label>Подпись (необязательно)</label>
            <input type="text" name="title" maxlength="100">
          </div>
          <div class="form-field">
            <label>Файл (<?= e($widgetTypeLabels[$cat['type']] ?? '') ?>), максимум <?= round($__catMaxBytes / 1024 / 1024) ?> МБ</label>
            <label class="file-input-styled">
              <span>Выбрать файл</span>
              <input type="file" name="file" accept="<?= e($widgetTypeAccept[$cat['type']] ?? '') ?>" required>
            </label>
          </div>
          <button type="submit" class="btn full">Загрузить</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>

  <!-- ==================== МОДАЛКА: Виджеты — файл (переименовать / удалить) ==================== -->
  <div class="modal-overlay" id="editItemModal">
    <div class="modal-box" style="max-width:400px; text-align:left;">
      <button type="button" class="modal-close" data-modal-close style="position:static; margin:0 0 8px auto; display:block;">✕</button>
      <h3 style="text-align:left;">Файл</h3>
      <form method="post" action="widget_items.php" id="itemUpdateForm">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="action" value="update_title">
        <input type="hidden" name="category_id" id="editItemCategoryId" value="">
        <input type="hidden" name="id" id="editItemId" value="">
        <div class="form-field">
          <label>Подпись</label>
          <input type="text" name="title" id="editItemTitle" maxlength="100">
        </div>
        <button type="submit" class="btn full">Сохранить</button>
      </form>
      <form method="post" action="widget_items.php" id="itemDeleteForm" onsubmit="return confirm('Удалить этот файл?');" style="margin-top:8px;">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="category_id" id="deleteItemCategoryId" value="">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteItemId" value="">
        <button type="submit" class="btn ghost full">Удалить файл</button>
      </form>
    </div>
  </div>

</div>
<script>
  // Секцию аккордеона, которую нужно раскрыть автоматически при загрузке
  // страницы (например после клика "Изменить" внутри одной из подкатегорий),
  // сообщаем скрипту через глобальную переменную — сам аккордеон/анимация
  // реализованы в assets/admin.js.
  window.ADMIN_ABOUT_AUTOOPEN = <?= json_encode($autoOpenSection) ?>;
</script>
<script src="assets/admin.js?v=<?= filemtime(__DIR__ . '/assets/admin.js') ?>" defer></script>
</body>
</html>
