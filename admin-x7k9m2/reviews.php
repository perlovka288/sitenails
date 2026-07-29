<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/includes/auth_check.php';

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfCheck()) {
    $id = (int)($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'toggle') {
        $pdo->prepare('UPDATE reviews SET is_approved = 1 - is_approved WHERE id = ?')->execute([$id]);
    } elseif (($_POST['action'] ?? '') === 'delete') {
        $pdo->prepare('DELETE FROM reviews WHERE id = ?')->execute([$id]);
    }
    redirect('reviews.php');
}

$reviews = $pdo->query('SELECT * FROM reviews ORDER BY is_approved ASC, created_at DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Отзывы — Панель управления</title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>
<div class="admin-shell">
  <?php require __DIR__ . '/includes/nav.php'; ?>

  <p style="color:var(--ink-soft); font-size:13px; margin-top:-6px;">
    Новые отзывы публикуются на сайте сразу. Здесь можно временно скрыть
    отзыв (он останется в базе, просто не будет виден посетителям) или
    удалить его совсем.
  </p>

  <table class="admin-table">
    <thead>
      <tr><th>Статус</th><th>Автор</th><th>Оценка</th><th>Дата</th><th>Текст</th><th>Фото</th><th>Действия</th></tr>
    </thead>
    <tbody>
      <?php foreach ($reviews as $r): ?>
        <tr>
          <td><span class="badge <?= $r['is_approved'] ? 'done' : 'new' ?>"><?= $r['is_approved'] ? 'Опубликован' : 'Скрыт' ?></span></td>
          <td><?= e($r['author_name']) ?></td>
          <td><?= str_repeat('★', (int)$r['rating']) ?></td>
          <td style="white-space:nowrap; color:var(--ink-faint); font-size:12px;"><?= e(date('d.m.Y H:i', strtotime($r['created_at']))) ?></td>
          <td><?= e($r['message']) ?></td>
          <td>
            <?php $__adminPhotos = reviewPhotoPaths($r['photo_path']); ?>
            <?php if ($__adminPhotos): ?>
              <div style="display:flex; gap:6px;">
              <?php foreach ($__adminPhotos as $__ap): ?>
                <a href="../<?= e($__ap) ?>" target="_blank">
                  <img src="../<?= e($__ap) ?>" alt="" style="width:56px;height:56px;object-fit:cover;border-radius:8px;border:1px solid var(--line);">
                </a>
              <?php endforeach; ?>
              </div>
            <?php else: ?>
              —
            <?php endif; ?>
          </td>
          <td style="white-space:nowrap;">
            <form method="post" style="display:inline;">
              <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button name="action" value="toggle" class="btn ghost" style="padding:6px 10px;font-size:14px;" title="<?= $r['is_approved'] ? 'Скрыть' : 'Опубликовать' ?>">
                <?= $r['is_approved'] ? '🙈' : '👁️' ?>
              </button>
            </form>
            <form method="post" style="display:inline;" onsubmit="return confirm('Удалить отзыв?');">
              <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button name="action" value="delete" class="btn ghost" style="padding:6px 10px;font-size:14px;" title="Удалить">🗑️</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$reviews): ?>
        <tr><td colspan="6">Отзывов пока нет.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
</body>
</html>
