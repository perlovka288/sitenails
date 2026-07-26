<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/includes/auth_check.php';

$pdo = getDB();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfCheck()) {
    $form = $_POST['form'] ?? '';

    // ===== Основной блок «О мне» =====
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
                subtitle = ?, subtitle_ua = ?, bio = ?, bio_ua = ?,
                btn1_text = ?, btn1_text_ua = ?, btn1_url = ?,
                btn2_text = ?, btn2_text_ua = ?, btn2_url = ?
            WHERE id = 1
        ')->execute([
            $photoPath,
            trim($_POST['greeting'] ?? ''), trim($_POST['greeting_ua'] ?? '') ?: null,
            trim($_POST['title'] ?? ''), trim($_POST['title_ua'] ?? '') ?: null,
            trim($_POST['subtitle'] ?? ''), trim($_POST['subtitle_ua'] ?? '') ?: null,
            trim($_POST['bio'] ?? ''), trim($_POST['bio_ua'] ?? '') ?: null,
            trim($_POST['btn1_text'] ?? '') ?: null, trim($_POST['btn1_text_ua'] ?? '') ?: null, trim($_POST['btn1_url'] ?? '') ?: null,
            trim($_POST['btn2_text'] ?? '') ?: null, trim($_POST['btn2_text_ua'] ?? '') ?: null, trim($_POST['btn2_url'] ?? '') ?: null,
        ]);
        $message = 'Блок «О мне» сохранён.';
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
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>О мне — Панель управления</title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<style>
  .admin-translate-btn { padding: 3px 10px; font-size: 11px; margin-left: 8px; vertical-align: middle; }
  .about-live-preview {
    background: var(--surface, #f7f2ec);
    border: 1px dashed var(--line-strong, #ccc);
    border-radius: 12px;
    padding: 18px;
    margin-bottom: 18px;
    display: flex;
    gap: 16px;
    align-items: center;
    flex-wrap: wrap;
  }
  .about-live-preview-photo {
    width: 84px; height: 84px; border-radius: 50%; overflow: hidden;
    background: var(--surface-2, #eee); flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 11px; color: var(--ink-soft, #888);
  }
  .about-live-preview-text { flex: 1 1 260px; }
  .about-live-preview-label { font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: var(--ink-soft, #888); margin-bottom: 6px; }
</style>
<script>window.ADMIN_CSRF_TOKEN = <?= json_encode(csrfToken()) ?>;</script>
<script src="assets/admin.js" defer></script>
</head>
<body>
<div class="admin-shell">
  <?php require __DIR__ . '/includes/nav.php'; ?>

  <?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>

  <div class="card">
    <h3>Блок «О мне» (первый блок сайта)</h3>
    <p style="color:var(--ink-soft); font-size:13px; margin-top:0;">
      Фото слева, текст справа. Поля с пометкой «укр.» необязательны — если
      их не заполнить, на украинской версии сайта будет показан русский текст.
      Если оставить заголовок и текст «о себе» пустыми, блок на сайте вообще
      не появится.
    </p>

    <div class="about-live-preview" id="aboutLivePreview">
      <div class="about-live-preview-photo" data-preview="photo">
        <?php if (!empty($about['photo_path'])): ?>
          <img src="../<?= e($about['photo_path']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
        <?php else: ?>
          нет фото
        <?php endif; ?>
      </div>
      <div class="about-live-preview-text">
        <div class="about-live-preview-label">Так это будет выглядеть на сайте</div>
        <div data-preview="greeting" style="font-size:12px; color:var(--ink-soft);"><?= e($about['greeting'] ?? '') ?></div>
        <div data-preview="title" style="font-family:'Playfair Display',serif; font-weight:700; font-size:20px;"><?= e($about['title'] ?: 'Заголовок появится здесь') ?></div>
        <div data-preview="subtitle" style="color:var(--primary); font-weight:600; font-size:13px;"><?= e($about['subtitle'] ?? '') ?></div>
        <div data-preview="bio" style="font-size:13px; color:var(--ink-soft); margin-top:4px;"><?= e($about['bio'] ?: 'Текст «о себе» появится здесь') ?></div>
      </div>
    </div>

    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="form" value="about_main">

      <div class="form-field">
        <label>Фото / аватар</label>
        <?php if (!empty($about['photo_path'])): ?>
          <div class="admin-upload-current">
            <img src="../<?= e($about['photo_path']) ?>" alt="">
            <label style="display:flex; align-items:center; gap:6px; font-weight:400; text-transform:none;">
              <input type="checkbox" name="remove_photo" value="1" style="width:auto;"> удалить текущее фото
            </label>
          </div>
        <?php endif; ?>
        <input type="file" name="photo" accept="image/png,image/jpeg,image/webp">
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

      <div class="form-field">
        <label>Кнопка 1 — текст (рус.), например «Смотреть работы»</label>
        <input type="text" id="btn1_text" name="btn1_text" value="<?= e($about['btn1_text'] ?? '') ?>" maxlength="40">
      </div>
      <div class="form-field">
        <label>Кнопка 1 — текст (укр., необязательно)
          <button type="button" class="btn ghost admin-translate-btn" data-translate-from="btn1_text" data-translate-to="btn1_text_ua">⇄ Перевести с рус.</button>
        </label>
        <input type="text" id="btn1_text_ua" name="btn1_text_ua" value="<?= e($about['btn1_text_ua'] ?? '') ?>" maxlength="40">
      </div>
      <div class="form-field">
        <label>Кнопка 1 — ссылка (например #widget-1 или https://...)</label>
        <input type="text" name="btn1_url" value="<?= e($about['btn1_url'] ?? '') ?>">
      </div>

      <div class="form-field">
        <label>Кнопка 2 — текст (рус.), например «Связаться»</label>
        <input type="text" id="btn2_text" name="btn2_text" value="<?= e($about['btn2_text'] ?? '') ?>" maxlength="40">
      </div>
      <div class="form-field">
        <label>Кнопка 2 — текст (укр., необязательно)
          <button type="button" class="btn ghost admin-translate-btn" data-translate-from="btn2_text" data-translate-to="btn2_text_ua">⇄ Перевести с рус.</button>
        </label>
        <input type="text" id="btn2_text_ua" name="btn2_text_ua" value="<?= e($about['btn2_text_ua'] ?? '') ?>" maxlength="40">
      </div>
      <div class="form-field">
        <label>Кнопка 2 — ссылка</label>
        <input type="text" name="btn2_url" value="<?= e($about['btn2_url'] ?? '') ?>">
      </div>

      <button type="submit" class="btn full">Сохранить блок «О мне»</button>
    </form>
  </div>

  <div class="card">
    <h3>Статистика (например «4+ — Года опыта»)</h3>
    <form method="post" style="margin-bottom:16px;">
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="form" value="stat_add">
      <div class="form-field">
        <label>Значение (например «4+», «50+»)</label>
        <input type="text" name="value" required maxlength="20">
      </div>
      <div class="form-field">
        <label>Подпись, рус. (например «Года опыта»)</label>
        <input type="text" name="label" required maxlength="60">
      </div>
      <div class="form-field">
        <label>Подпись, укр. (необязательно)</label>
        <input type="text" name="label_ua" maxlength="60">
      </div>
      <button type="submit" class="btn full">Добавить статистику</button>
    </form>

    <table class="admin-table">
      <thead><tr><th>Значение</th><th>Подпись</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($stats as $s): ?>
          <tr>
            <td><?= e($s['value']) ?></td>
            <td><?= e($s['label']) ?><?= $s['label_ua'] ? ' / ' . e($s['label_ua']) : '' ?></td>
            <td>
              <form method="post" onsubmit="return confirm('Удалить эту статистику?');">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="form" value="stat_delete">
                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                <button class="btn ghost" style="padding:6px 12px;font-size:12px;">Удалить</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$stats): ?><tr><td colspan="3">Пока нет статистики.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="card">
    <h3>Навыки / инструменты</h3>
    <form method="post" enctype="multipart/form-data" style="margin-bottom:16px;">
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="form" value="skill_add">
      <div class="form-field">
        <label>Название, рус. (например «Premiere Pro»)</label>
        <input type="text" name="name" required maxlength="60">
      </div>
      <div class="form-field">
        <label>Название, укр. (необязательно)</label>
        <input type="text" name="name_ua" maxlength="60">
      </div>
      <div class="form-field">
        <label>Короткая иконка-текст (например «Pr»), если нет картинки</label>
        <input type="text" name="icon_text" maxlength="4">
      </div>
      <div class="form-field">
        <label>Иконка-картинка (необязательно, заменяет текст выше)</label>
        <input type="file" name="icon_image" accept="image/png,image/jpeg,image/webp">
      </div>
      <button type="submit" class="btn full">Добавить навык</button>
    </form>

    <table class="admin-table">
      <thead><tr><th>Иконка</th><th>Название</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($skills as $sk): ?>
          <tr>
            <td>
              <?php if (!empty($sk['icon_image'])): ?>
                <img src="../<?= e($sk['icon_image']) ?>" style="width:28px;height:28px;object-fit:cover;border-radius:6px;">
              <?php else: ?>
                <?= e($sk['icon_text'] ?: '—') ?>
              <?php endif; ?>
            </td>
            <td><?= e($sk['name']) ?><?= $sk['name_ua'] ? ' / ' . e($sk['name_ua']) : '' ?></td>
            <td>
              <form method="post" onsubmit="return confirm('Удалить этот навык?');">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="form" value="skill_delete">
                <input type="hidden" name="id" value="<?= (int)$sk['id'] ?>">
                <button class="btn ghost" style="padding:6px 12px;font-size:12px;">Удалить</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$skills): ?><tr><td colspan="3">Пока нет навыков.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
