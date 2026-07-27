<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/includes/auth_check.php';

$pdo = getDB();
$validTypes = ['photo', 'video', 'pdf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfCheck()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $name    = trim($_POST['name'] ?? '');
        $nameUa  = trim($_POST['name_ua'] ?? '');
        $type    = $_POST['type'] ?? 'photo';
        if (!in_array($type, $validTypes, true)) {
            $type = 'photo';
        }

        if ($name !== '') {
            if ($action === 'add') {
                $maxOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM widget_categories')->fetchColumn();
                $pdo->prepare('INSERT INTO widget_categories (name, name_ua, type, sort_order) VALUES (?, ?, ?, ?)')
                    ->execute([$name, $nameUa ?: null, $type, $maxOrder + 1]);
            } else {
                $id = (int)($_POST['id'] ?? 0);
                // Тип категории не даём менять при редактировании — иначе уже
                // загруженные файлы (фото/видео/PDF) перестанут соответствовать
                // новому типу отображения. Чтобы сменить тип — создайте новую категорию.
                $pdo->prepare('UPDATE widget_categories SET name = ?, name_ua = ? WHERE id = ?')
                    ->execute([$name, $nameUa ?: null, $id]);
            }
        }
        redirect('widgets.php');
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        // Удаляем файлы всех элементов категории с диска перед удалением записей.
        $stmt = $pdo->prepare('SELECT file_path FROM widget_items WHERE category_id = ?');
        $stmt->execute([$id]);
        foreach ($stmt->fetchAll() as $row) {
            deleteUploadedFile($row['file_path']);
        }
        $pdo->prepare('DELETE FROM widget_categories WHERE id = ?')->execute([$id]);
        redirect('widgets.php');
    }
}

$editItem = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM widget_categories WHERE id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $editItem = $stmt->fetch() ?: null;
}

$categories = $pdo->query('SELECT * FROM widget_categories ORDER BY sort_order, id')->fetchAll();
$counts = [];
$itemsByCat = [];
foreach ($pdo->query('SELECT * FROM widget_items ORDER BY category_id, sort_order, id')->fetchAll() as $row) {
    $counts[(int)$row['category_id']] = ($counts[(int)$row['category_id']] ?? 0) + 1;
    $itemsByCat[(int)$row['category_id']][] = $row;
}

$widgetsEnabled = getSetting('widgets_enabled', '1') === '1';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfCheck() && ($_POST['action'] ?? '') === 'toggle_widgets_enabled') {
    setSetting('widgets_enabled', isset($_POST['widgets_enabled']) ? '1' : '0');
    redirect('widgets.php');
}

$typeLabels = ['photo' => 'Фото (галерея)', 'video' => 'Видео', 'pdf' => 'PDF (сертификаты)'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Виджеты — Панель управления</title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<script>window.ADMIN_CSRF_TOKEN = <?= json_encode(csrfToken()) ?>;</script>
</head>
<body>
<div class="admin-shell">
  <?php require __DIR__ . '/includes/nav.php'; ?>

  <p style="color:var(--ink-soft); font-size:13px;">
    Создайте категорию (например «Портфолио», «Сертификаты», «Видео-отзывы»),
    выберите её тип, а затем нажмите на квадратную плитку «+» на карточке
    категории ниже, чтобы загрузить в неё фото, видео или PDF — окно
    загрузки откроется прямо здесь, переходить никуда не нужно. На сайте
    содержимое категории листается горизонтально (карусель).
  </p>

  <div class="settings-group">
    <form method="post" id="widgetsEnabledForm" class="settings-row">
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action" value="toggle_widgets_enabled">
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
    <h3><?= $editItem ? 'Изменить категорию' : 'Новая категория' ?></h3>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action" value="<?= $editItem ? 'edit' : 'add' ?>">
      <?php if ($editItem): ?><input type="hidden" name="id" value="<?= (int)$editItem['id'] ?>"><?php endif; ?>

      <div class="form-field">
        <label>Название категории, рус.</label>
        <input type="text" id="widget_cat_name" name="name" required maxlength="60" value="<?= e($editItem['name'] ?? '') ?>">
      </div>
      <div class="form-field">
        <label>Название категории, укр. (необязательно)
          <button type="button" class="btn ghost admin-translate-btn" data-translate-from="widget_cat_name" data-translate-to="widget_cat_name_ua">⇄ Перевести с рус.</button>
        </label>
        <input type="text" id="widget_cat_name_ua" name="name_ua" maxlength="60" value="<?= e($editItem['name_ua'] ?? '') ?>">
      </div>
      <div class="form-field">
        <label>Тип содержимого<?= $editItem ? ' (нельзя изменить после создания)' : '' ?></label>
        <?php if ($editItem): ?>
          <input type="text" value="<?= e($typeLabels[$editItem['type']] ?? $editItem['type']) ?>" disabled>
        <?php else: ?>
          <select name="type">
            <?php foreach ($typeLabels as $val => $label): ?>
              <option value="<?= e($val) ?>"><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>
      </div>

      <button type="submit" class="btn full"><?= $editItem ? 'Сохранить' : 'Создать категорию' ?></button>
      <?php if ($editItem): ?>
        <a href="widgets.php" class="btn ghost full" style="margin-top:8px; text-align:center;">Отменить</a>
      <?php endif; ?>
    </form>
  </div>

  <?php foreach ($categories as $cat): ?>
    <?php
      $__catId = (int)$cat['id'];
      $__catItems = $itemsByCat[$__catId] ?? [];
      $__catName = e($cat['name']) . ($cat['name_ua'] ? ' / ' . e($cat['name_ua']) : '');
    ?>
    <div class="card widget-cat-card">
      <div class="widget-cat-card-head">
        <div>
          <h3 style="margin-bottom:2px;"><?= $__catName ?></h3>
          <span class="widget-cat-card-meta"><?= e($typeLabels[$cat['type']] ?? $cat['type']) ?> · файлов: <?= count($__catItems) ?></span>
        </div>
        <div class="widget-cat-card-actions">
          <a href="?edit=<?= $__catId ?>" class="btn ghost" style="padding:6px 12px;font-size:12px;">Изменить</a>
          <form method="post" style="display:inline;" onsubmit="return confirm('Удалить категорию вместе со всеми файлами внутри неё?');">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="delete">
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
        <button type="button" class="admin-square-add-tile" data-modal-open="addItemModal-<?= $__catId ?>" aria-label="Добавить файл">+</button>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$categories): ?>
    <p style="color:var(--ink-soft); font-size:13px;">Пока нет ни одной категории — создайте первую в форме выше.</p>
  <?php endif; ?>

  <!-- ==== Модалки "Добавить файл" — по одной на категорию (у каждой свой
       допустимый тип файла и лимит размера) ==== -->
  <?php foreach ($categories as $cat): ?>
    <?php
      $__catId = (int)$cat['id'];
      $__catMaxBytes = $cat['type'] === 'video' ? 60 * 1024 * 1024 : 8 * 1024 * 1024;
      $__typeAccept = [
          'photo' => 'image/jpeg,image/png,image/webp,image/gif',
          'video' => 'video/mp4,video/webm,video/quicktime',
          'pdf'   => 'application/pdf',
      ];
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
            <label>Файл (<?= e($typeLabels[$cat['type']] ?? '') ?>), максимум <?= round($__catMaxBytes / 1024 / 1024) ?> МБ</label>
            <label class="file-input-styled">
              <span>Выбрать файл</span>
              <input type="file" name="file" accept="<?= e($__typeAccept[$cat['type']] ?? '') ?>" required>
            </label>
          </div>
          <button type="submit" class="btn full">Загрузить</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>

  <!-- ==== Модалка редактирования файла (переименовать / удалить) ==== -->
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
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="category_id" id="deleteItemCategoryId" value="">
        <input type="hidden" name="id" id="deleteItemId" value="">
        <button type="submit" class="btn ghost full">Удалить файл</button>
      </form>
    </div>
  </div>
</div>
<script src="assets/admin.js" defer></script>
</body>
</html>
