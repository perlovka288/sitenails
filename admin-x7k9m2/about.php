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
}

$about = $pdo->query('SELECT * FROM about_me WHERE id = 1')->fetch();
$stats = $pdo->query('SELECT * FROM about_stats ORDER BY sort_order, id')->fetchAll();
$skills = $pdo->query('SELECT * FROM about_skills ORDER BY sort_order, id')->fetchAll();
$buttons = $pdo->query('SELECT * FROM about_buttons ORDER BY sort_order, id')->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>О мне — Панель управления</title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<script>window.ADMIN_CSRF_TOKEN = <?= json_encode(csrfToken()) ?>;</script>
</head>
<body>
<div class="admin-shell">
  <?php require __DIR__ . '/includes/nav.php'; ?>

  <?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>

  <p style="color:var(--ink-soft); font-size:13px;">
    Блок «О мне» состоит из 4 частей. У каждой — своя карточка ниже:
    нажмите на значок <strong>⚙</strong>, чтобы открыть окно редактирования,
    ничего никуда переходить не нужно.
  </p>

  <div class="about-overview-grid">

    <!-- ==================== 1. ИНФОРМАЦИЯ ==================== -->
    <div class="card about-overview-card">
      <div class="about-overview-card-head">
        <div>
          <h3>Информация</h3>
          <p class="about-overview-card-sub">Фото, приветствие, заголовок, подзаголовок и текст «о себе».</p>
        </div>
        <button type="button" class="icon-btn" data-modal-open="modalInfo" aria-label="Настроить">⚙</button>
      </div>
      <div class="about-overview-preview">
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
    </div>

    <!-- ==================== 2. КНОПКИ ==================== -->
    <div class="card about-overview-card">
      <div class="about-overview-card-head">
        <div>
          <h3>Кнопки</h3>
          <p class="about-overview-card-sub">Кнопок можно добавить сколько угодно — каждую можно включить/выключить.</p>
        </div>
      </div>

      <?php if ($buttons): ?>
        <div class="settings-group" style="margin-top:10px;">
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

      <button type="button" class="admin-add-tile-btn" data-btn-add-open><span class="admin-plus-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg></span><span>Добавить кнопку</span></button>
    </div>

    <!-- ==================== 3. СТАТИСТИКА ==================== -->
    <div class="card about-overview-card">
      <div class="about-overview-card-head">
        <div>
          <h3>Статистика</h3>
          <p class="about-overview-card-sub">Например «4+ — Года опыта».</p>
        </div>
      </div>
      <?php if ($stats): ?>
        <div class="settings-group" style="margin-top:10px;">
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
      <button type="button" class="admin-add-tile-btn" data-modal-open="modalStatAdd"><span class="admin-plus-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg></span><span>Добавить статистику</span></button>
    </div>

    <!-- ==================== 4. НАВЫКИ / ИНСТРУМЕНТЫ ==================== -->
    <div class="card about-overview-card">
      <div class="about-overview-card-head">
        <div>
          <h3>Навыки / инструменты</h3>
          <p class="about-overview-card-sub">Иконки с подписью — например используемые инструменты.</p>
        </div>
      </div>
      <?php if ($skills): ?>
        <div class="settings-group" style="margin-top:10px;">
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
      <button type="button" class="admin-add-tile-btn" data-modal-open="modalSkillAdd"><span class="admin-plus-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg></span><span>Добавить навык</span></button>
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

</div>
<script src="assets/admin.js" defer></script>
</body>
</html>
