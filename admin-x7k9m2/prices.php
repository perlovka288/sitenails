<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/includes/auth_check.php';

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfCheck()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $category    = trim($_POST['category'] ?? '');
        $categoryUa  = trim($_POST['category_ua'] ?? '');
        $title       = trim($_POST['title'] ?? '');
        $titleUa     = trim($_POST['title_ua'] ?? '');
        $price       = trim($_POST['price'] ?? '');
        if ($category !== '' && $title !== '' && $price !== '') {
            $pdo->prepare('INSERT INTO price_items (category, category_ua, title, title_ua, price, sort_order) VALUES (?, ?, ?, ?, ?, 0)')
                ->execute([$category, $categoryUa ?: null, $title, $titleUa ?: null, $price]);
        }
    } elseif ($action === 'edit') {
        $id          = (int)($_POST['id'] ?? 0);
        $category    = trim($_POST['category'] ?? '');
        $categoryUa  = trim($_POST['category_ua'] ?? '');
        $title       = trim($_POST['title'] ?? '');
        $titleUa     = trim($_POST['title_ua'] ?? '');
        $price       = trim($_POST['price'] ?? '');
        if ($id > 0 && $category !== '' && $title !== '' && $price !== '') {
            $pdo->prepare('UPDATE price_items SET category = ?, category_ua = ?, title = ?, title_ua = ?, price = ? WHERE id = ?')
                ->execute([$category, $categoryUa ?: null, $title, $titleUa ?: null, $price, $id]);
        }
        redirect('prices.php');
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM price_items WHERE id = ?')->execute([$id]);
    }

    redirect('prices.php');
}

$editItem = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM price_items WHERE id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $editItem = $stmt->fetch() ?: null;
}

$items = $pdo->query('SELECT * FROM price_items ORDER BY category, sort_order')->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Прайс — Панель управления</title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<script>window.ADMIN_CSRF_TOKEN = <?= json_encode(csrfToken()) ?>;</script>
<script src="assets/admin.js" defer></script>
</head>
<body>
<div class="admin-shell">
  <?php require __DIR__ . '/includes/nav.php'; ?>

  <div class="card">
    <h3><?= $editItem ? 'Изменить позицию' : 'Добавить позицию' ?></h3>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action" value="<?= $editItem ? 'edit' : 'add' ?>">
      <?php if ($editItem): ?>
        <input type="hidden" name="id" value="<?= (int)$editItem['id'] ?>">
      <?php endif; ?>
      <div class="form-field">
        <label>Категория, рус. (например, «Маникюр»)</label>
        <input type="text" id="category" name="category" required value="<?= e($editItem['category'] ?? '') ?>">
      </div>
      <div class="form-field">
        <label>Категория, укр. (необязательно, например «Манікюр»)
          <button type="button" class="btn ghost admin-translate-btn" data-translate-from="category" data-translate-to="category_ua">⇄ Перевести с рус.</button>
        </label>
        <input type="text" id="category_ua" name="category_ua" value="<?= e($editItem['category_ua'] ?? '') ?>">
      </div>
      <div class="form-field">
        <label>Название услуги, рус.</label>
        <input type="text" id="price_title" name="title" required value="<?= e($editItem['title'] ?? '') ?>">
      </div>
      <div class="form-field">
        <label>Название услуги, укр. (необязательно)
          <button type="button" class="btn ghost admin-translate-btn" data-translate-from="price_title" data-translate-to="price_title_ua">⇄ Перевести с рус.</button>
        </label>
        <input type="text" id="price_title_ua" name="title_ua" value="<?= e($editItem['title_ua'] ?? '') ?>">
      </div>
      <div class="form-field">
        <label>Цена (например, «450 грн»)</label>
        <input type="text" name="price" required value="<?= e($editItem['price'] ?? '') ?>">
      </div>
      <button type="submit" class="btn full"><?= $editItem ? 'Сохранить' : 'Добавить' ?></button>
      <?php if ($editItem): ?>
        <a href="prices.php" class="btn ghost full" style="margin-top:8px; text-align:center;">Отменить</a>
      <?php endif; ?>
    </form>
  </div>

  <table class="admin-table">
    <thead><tr><th>Категория</th><th>Услуга</th><th>Укр. перевод</th><th>Цена</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($items as $item): ?>
        <tr>
          <td><?= e($item['category']) ?></td>
          <td><?= e($item['title']) ?></td>
          <td style="color:var(--ink-soft);">
            <?= e($item['category_ua'] ?: '—') ?> / <?= e($item['title_ua'] ?: '—') ?>
          </td>
          <td><?= e($item['price']) ?></td>
          <td style="white-space:nowrap;">
            <a href="?edit=<?= (int)$item['id'] ?>" class="btn ghost" style="padding:6px 12px;font-size:12px;">Изменить</a>
            <form method="post" style="display:inline;" onsubmit="return confirm('Удалить позицию?');">
              <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
              <button class="btn ghost" style="padding:6px 12px;font-size:12px;">Удалить</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$items): ?>
        <tr><td colspan="5">Прайс пуст.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
</body>
</html>
