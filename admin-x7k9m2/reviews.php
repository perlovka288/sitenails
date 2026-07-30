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

  <div class="rec-list">
    <?php foreach ($reviews as $r): ?>
      <div class="rec-card">
        <div class="rec-card-head">
          <div class="rec-card-head-name">
            <strong><?= e($r['author_name']) ?></strong>
            <span class="rec-card-stars"><?= str_repeat('★', (int)$r['rating']) ?></span>
          </div>
          <span class="badge <?= $r['is_approved'] ? 'done' : 'new' ?>"><?= $r['is_approved'] ? 'Опубликован' : 'Скрыт' ?></span>
        </div>
        <div class="rec-card-body">
          <div class="rec-card-row rec-card-row-faint"><?= e(date('d.m.Y H:i', strtotime($r['created_at']))) ?></div>
          <?php if ($r['message']): ?>
          <div class="rec-card-review-text"><?= e($r['message']) ?></div>
          <?php endif; ?>
          <?php $__adminPhotos = reviewPhotoPaths($r['photo_path']); ?>
          <?php if ($__adminPhotos): ?>
            <div class="rec-card-photos">
            <?php foreach ($__adminPhotos as $__ap): ?>
              <a href="../<?= e($__ap) ?>" target="_blank">
                <img src="../<?= e($__ap) ?>" alt="">
              </a>
            <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
        <div class="rec-card-actions">
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button name="action" value="toggle" class="btn ghost rec-card-btn">
              <?= $r['is_approved'] ? '🙈 Скрыть' : '👁️ Опубликовать' ?>
            </button>
          </form>
          <form method="post" onsubmit="return confirm('Удалить отзыв?');">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button name="action" value="delete" class="btn ghost rec-card-btn rec-card-btn-danger">🗑️ Удалить</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$reviews): ?>
      <p class="rec-empty">Отзывов пока нет.</p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
