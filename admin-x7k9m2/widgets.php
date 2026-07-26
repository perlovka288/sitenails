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
foreach ($pdo->query('SELECT category_id, COUNT(*) AS cnt FROM widget_items GROUP BY category_id')->fetchAll() as $row) {
    $counts[(int)$row['category_id']] = (int)$row['cnt'];
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
</head>
<body>
<div class="admin-shell">
  <?php require __DIR__ . '/includes/nav.php'; ?>

  <p style="color:var(--ink-soft); font-size:13px;">
    Создайте категорию (например «Портфолио», «Сертификаты», «Видео-отзывы»),
    выберите её тип, а затем в разделе «Файлы» загрузите в неё фото, видео
    или PDF. На сайте содержимое категории листается горизонтально.
  </p>

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

  <table class="admin-table">
    <thead><tr><th>Категория</th><th>Тип</th><th>Файлов</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($categories as $cat): ?>
        <tr>
          <td><?= e($cat['name']) ?><?= $cat['name_ua'] ? ' / ' . e($cat['name_ua']) : '' ?></td>
          <td><?= e($typeLabels[$cat['type']] ?? $cat['type']) ?></td>
          <td><?= $counts[(int)$cat['id']] ?? 0 ?></td>
          <td style="white-space:nowrap;">
            <a href="widget_items.php?category_id=<?= (int)$cat['id'] ?>" class="btn ghost" style="padding:6px 12px;font-size:12px;">Файлы</a>
            <a href="?edit=<?= (int)$cat['id'] ?>" class="btn ghost" style="padding:6px 12px;font-size:12px;">Изменить</a>
            <form method="post" style="display:inline;" onsubmit="return confirm('Удалить категорию вместе со всеми файлами внутри неё?');">
              <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$cat['id'] ?>">
              <button class="btn ghost" style="padding:6px 12px;font-size:12px;">Удалить</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$categories): ?><tr><td colspan="4">Пока нет категорий.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<script src="assets/admin.js" defer></script>
</body>
</html>
