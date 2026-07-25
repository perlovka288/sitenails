<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/includes/auth_check.php';

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfCheck()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $category = trim($_POST['category'] ?? '');
        $title    = trim($_POST['title'] ?? '');
        $price    = trim($_POST['price'] ?? '');
        if ($category !== '' && $title !== '' && $price !== '') {
            $pdo->prepare('INSERT INTO price_items (category, title, price, sort_order) VALUES (?, ?, ?, 0)')
                ->execute([$category, $title, $price]);
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM price_items WHERE id = ?')->execute([$id]);
    }

    redirect('prices.php');
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
</head>
<body>
<div class="admin-shell">
  <?php require __DIR__ . '/includes/nav.php'; ?>

  <div class="card">
    <h3>Добавить позицию</h3>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action" value="add">
      <div class="form-field">
        <label>Категория (например, «Маникюр»)</label>
        <input type="text" name="category" required>
      </div>
      <div class="form-field">
        <label>Название услуги</label>
        <input type="text" name="title" required>
      </div>
      <div class="form-field">
        <label>Цена (например, «450 грн»)</label>
        <input type="text" name="price" required>
      </div>
      <button type="submit" class="btn full">Добавить</button>
    </form>
  </div>

  <table class="admin-table">
    <thead><tr><th>Категория</th><th>Услуга</th><th>Цена</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($items as $item): ?>
        <tr>
          <td><?= e($item['category']) ?></td>
          <td><?= e($item['title']) ?></td>
          <td><?= e($item['price']) ?></td>
          <td>
            <form method="post" onsubmit="return confirm('Удалить позицию?');">
              <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
              <button class="btn ghost" style="padding:6px 12px;font-size:12px;">Удалить</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$items): ?>
        <tr><td colspan="4">Прайс пуст.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
</body>
</html>
