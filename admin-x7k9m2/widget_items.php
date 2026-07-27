<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/includes/auth_check.php';

$pdo = getDB();

$categoryId = (int)($_GET['category_id'] ?? $_POST['category_id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM widget_categories WHERE id = ?');
$stmt->execute([$categoryId]);
$category = $stmt->fetch();

if (!$category) {
    redirect('about.php#about-acc-widgets');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES)) {
    // Когда файл больше post_max_size, PHP полностью очищает $_POST и
    // $_FILES ещё до того, как скрипт успевает их прочитать — раньше это
    // выглядело так, будто "ничего не произошло". Теперь показываем причину.
    $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLength > 0) {
        $uploadError = 'Файл слишком большой для загрузки на этот хостинг. '
            . 'Попробуйте сжать видео или выбрать файл поменьше (см. допустимый размер под формой).';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfCheck()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {
        $title = trim($_POST['title'] ?? '');
        $allowed = widgetAllowedMime($category['type']);
        $maxBytes = $category['type'] === 'video' ? 60 * 1024 * 1024 : 8 * 1024 * 1024;
        $serverLimitBytes = currentServerUploadLimitBytes();

        $fileErrorCode = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($fileErrorCode === UPLOAD_ERR_INI_SIZE || $fileErrorCode === UPLOAD_ERR_FORM_SIZE) {
            // PHP отклонил файл ещё на уровне php.ini/.user.ini хостинга —
            // это НЕ лимит раздела (60 МБ), а более строгий лимит сервера.
            // Показываем правду, чтобы не выглядело "видео 23 МБ, почему отказ".
            if ($serverLimitBytes > 0 && $serverLimitBytes < $maxBytes) {
                $uploadError = 'Хостинг сейчас пропускает файлы только до '
                    . round($serverLimitBytes / 1024 / 1024) . ' МБ (лимит сервера upload_max_filesize/post_max_size), '
                    . 'хотя лимит этого раздела — ' . round($maxBytes / 1024 / 1024) . ' МБ. '
                    . 'Подождите немного, если недавно меняли .user.ini/.htaccess, либо попросите хостинг поднять эти лимиты, '
                    . 'либо сожмите файл до ' . round($serverLimitBytes / 1024 / 1024) . ' МБ.';
            } else {
                $uploadError = 'Файл слишком большой. Максимум для этого раздела — '
                    . round($maxBytes / 1024 / 1024) . ' МБ. Сожмите видео и попробуйте снова.';
            }
        } elseif ($fileErrorCode !== UPLOAD_ERR_OK && $fileErrorCode !== UPLOAD_ERR_NO_FILE) {
            $uploadError = 'Загрузка не удалась (код ошибки ' . (int)$fileErrorCode . '). Попробуйте ещё раз.';
        } else {
            $filePath = saveUploadedFile(
                'file',
                'assets/uploads/widgets/' . $categoryId,
                $allowed,
                $maxBytes,
                $category['type']
            );

            if ($filePath !== null) {
                $maxOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM widget_items WHERE category_id = ' . (int)$categoryId)->fetchColumn();
                $pdo->prepare('INSERT INTO widget_items (category_id, file_path, title, sort_order) VALUES (?, ?, ?, ?)')
                    ->execute([$categoryId, $filePath, $title ?: null, $maxOrder + 1]);
                redirect('about.php#about-acc-widgets');
            } elseif (($_FILES['file']['size'] ?? 0) > $maxBytes) {
                $uploadError = 'Файл слишком большой. Максимум для этого раздела — '
                    . round($maxBytes / 1024 / 1024) . ' МБ.';
            } else {
                $uploadError = 'Файл не загружен — проверьте формат (' . implode(', ', $allowed) . ') и размер файла.';
            }
        }
    } elseif ($action === 'update_title') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $pdo->prepare('UPDATE widget_items SET title = ? WHERE id = ? AND category_id = ?')
            ->execute([$title ?: null, $id, $categoryId]);
        redirect('about.php#about-acc-widgets');
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $itemStmt = $pdo->prepare('SELECT file_path FROM widget_items WHERE id = ? AND category_id = ?');
        $itemStmt->execute([$id, $categoryId]);
        $item = $itemStmt->fetch();
        if ($item) {
            deleteUploadedFile($item['file_path']);
            $pdo->prepare('DELETE FROM widget_items WHERE id = ?')->execute([$id]);
        }
        redirect('about.php#about-acc-widgets');
    } elseif ($action === 'move_up' || $action === 'move_down') {
        $id = (int)($_POST['id'] ?? 0);
        $items = $pdo->prepare('SELECT id, sort_order FROM widget_items WHERE category_id = ? ORDER BY sort_order, id');
        $items->execute([$categoryId]);
        $list = $items->fetchAll();
        $index = null;
        foreach ($list as $i => $row) {
            if ((int)$row['id'] === $id) {
                $index = $i;
                break;
            }
        }
        $swapWith = $action === 'move_up' ? $index - 1 : $index + 1;
        if ($index !== null && isset($list[$swapWith])) {
            $a = $list[$index];
            $b = $list[$swapWith];
            $pdo->prepare('UPDATE widget_items SET sort_order = ? WHERE id = ?')->execute([$b['sort_order'], $a['id']]);
            $pdo->prepare('UPDATE widget_items SET sort_order = ? WHERE id = ?')->execute([$a['sort_order'], $b['id']]);
        }
        redirect('about.php#about-acc-widgets');
    }
}

$items = $pdo->prepare('SELECT * FROM widget_items WHERE category_id = ? ORDER BY sort_order, id');
$items->execute([$categoryId]);
$items = $items->fetchAll();

$typeLabels = ['photo' => 'Фото (галерея)', 'video' => 'Видео', 'pdf' => 'PDF (сертификаты)'];
$typeAccept = [
    'photo' => 'image/jpeg,image/png,image/webp,image/gif',
    'video' => 'video/mp4,video/webm,video/quicktime',
    'pdf'   => 'application/pdf',
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Файлы категории — Панель управления</title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>
<div class="admin-shell">
  <?php require __DIR__ . '/includes/nav.php'; ?>

  <a href="widgets.php" class="btn ghost" style="padding:6px 12px;font-size:12px;">← Все категории</a>

  <div class="card" style="margin-top:14px;">
    <h3><?= e($category['name']) ?> — <?= e($typeLabels[$category['type']] ?? $category['type']) ?></h3>

    <?php if (!empty($uploadError)): ?><div class="alert error"><?= e($uploadError) ?></div><?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="js-widget-upload-form" data-type="<?= e($category['type']) ?>">
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action" value="upload">
      <input type="hidden" name="category_id" value="<?= (int)$categoryId ?>">
      <div class="form-field">
        <label>Подпись (необязательно)</label>
        <input type="text" name="title" maxlength="100">
      </div>
      <?php if ($category['type'] === 'video'): ?>
      <label class="switch-field switch-field--row">
        <span class="switch-field-label" style="text-transform:none; font-weight:400;">Сжать видео перед загрузкой (рекомендуется — многие бесплатные хостинги режут большие файлы сильнее, чем указано в настройках)</span>
        <span class="switch">
          <input type="checkbox" class="js-compress-toggle" checked>
          <span class="switch-slider"></span>
        </span>
      </label>
      <p class="field-hint js-compress-status" style="display:none;"></p>
      <?php endif; ?>
      <div class="form-field">
        <label>Файл (<?= e($typeLabels[$category['type']] ?? '') ?>)</label>
        <label class="file-input-styled">
          <span>Выбрать файл</span>
          <input type="file" name="file" class="js-widget-file-input" accept="<?= e($typeAccept[$category['type']] ?? '') ?>" required>
        </label>
        <?php
          $__catMaxBytes = $category['type'] === 'video' ? 60 * 1024 * 1024 : 8 * 1024 * 1024;
          $__serverLimitBytes = currentServerUploadLimitBytes();
          $__effectiveBytes = $__serverLimitBytes > 0 ? min($__catMaxBytes, $__serverLimitBytes) : $__catMaxBytes;
        ?>
        <p class="field-hint">
          Максимальный размер файла: <?= round($__effectiveBytes / 1024 / 1024) ?> МБ
          (лимит раздела — <?= round($__catMaxBytes / 1024 / 1024) ?> МБ,
          текущий лимит хостинга сейчас — <?= $__serverLimitBytes > 0 ? round($__serverLimitBytes / 1024 / 1024) . ' МБ' : 'неизвестен' ?>).
          Если хостинг режет файл сильнее, чем нужно, — подождите пару минут после загрузки
          <code>.user.ini</code>/<code>.htaccess</code> на сервер (лимиты применяются не мгновенно)
          или обратитесь в поддержку хостинга с просьбой поднять <code>upload_max_filesize</code>
          и <code>post_max_size</code>. Иначе — сожмите файл и попробуйте снова.
        </p>
      </div>
      <button type="submit" class="btn full js-widget-submit-btn">Загрузить</button>
    </form>
  </div>

  <?php if ($category['type'] === 'video'): ?>
  <script src="../assets/js/video-compress.js"></script>
  <?php endif; ?>
  <script src="assets/admin.js?v=<?= filemtime(__DIR__ . '/assets/admin.js') ?>" defer></script>

  <div class="admin-widget-item-grid">
    <?php foreach ($items as $i => $item): ?>
      <div class="admin-widget-item-card">
        <?php if ($category['type'] === 'photo'): ?>
          <img src="../<?= e($item['file_path']) ?>" alt="">
        <?php elseif ($category['type'] === 'video'): ?>
          <video src="../<?= e($item['file_path']) ?>" controls></video>
        <?php else: ?>
          <a href="../<?= e($item['file_path']) ?>" target="_blank" rel="noopener" style="display:block; padding:20px 0; font-size:30px;">📄</a>
        <?php endif; ?>
        <div style="font-size:12px; color:var(--ink-soft); margin-top:6px; word-break:break-word;"><?= e($item['title'] ?: '') ?></div>
        <form method="post" style="display:flex; gap:4px; justify-content:center; flex-wrap:wrap;">
          <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
          <input type="hidden" name="category_id" value="<?= (int)$categoryId ?>">
          <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
          <button type="submit" name="action" value="move_up" class="btn ghost" style="padding:4px 8px;font-size:11px;" <?= $i === 0 ? 'disabled' : '' ?>>↑</button>
          <button type="submit" name="action" value="move_down" class="btn ghost" style="padding:4px 8px;font-size:11px;" <?= $i === count($items) - 1 ? 'disabled' : '' ?>>↓</button>
        </form>
        <form method="post" onsubmit="return confirm('Удалить этот файл?');">
          <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
          <input type="hidden" name="category_id" value="<?= (int)$categoryId ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
          <button type="submit" class="btn ghost" style="padding:4px 8px;font-size:11px; width:100%;">Удалить</button>
        </form>
      </div>
    <?php endforeach; ?>
    <?php if (!$items): ?><p style="color:var(--ink-soft);">В этой категории пока нет файлов.</p><?php endif; ?>
  </div>
</div>
</body>
</html>
