<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/includes/auth_check.php';

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfCheck()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $platform   = trim($_POST['platform'] ?? '');
        $platformUa = trim($_POST['platform_ua'] ?? '');
        $url        = trim($_POST['url'] ?? '');
        $iconText   = trim($_POST['icon_text'] ?? '');

        if ($platform !== '' && $url !== '') {
            $iconImage = saveUploadedFile(
                'icon_image',
                'assets/uploads/social',
                ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
                2 * 1024 * 1024,
                'social'
            );

            if ($action === 'add') {
                $maxOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM social_links')->fetchColumn();
                $pdo->prepare('INSERT INTO social_links (platform, platform_ua, icon_text, icon_image, url, sort_order) VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([$platform, $platformUa ?: null, $iconText ?: null, $iconImage, $url, $maxOrder + 1]);
            } else {
                $id = (int)($_POST['id'] ?? 0);
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
            }
        }
        redirect('social.php');
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT icon_image FROM social_links WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row) {
            deleteUploadedFile($row['icon_image']);
        }
        $pdo->prepare('DELETE FROM social_links WHERE id = ?')->execute([$id]);
        redirect('social.php');
    }
}

$editItem = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM social_links WHERE id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $editItem = $stmt->fetch() ?: null;
}

$items = $pdo->query('SELECT * FROM social_links ORDER BY sort_order, id')->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Соцсети — Панель управления</title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<script>window.ADMIN_CSRF_TOKEN = <?= json_encode(csrfToken()) ?>;</script>
</head>
<body>
<div class="admin-shell">
  <?php require __DIR__ . '/includes/nav.php'; ?>

  <p style="color:var(--ink-soft); font-size:13px;">
    Это отдельный настраиваемый блок ссылок на соцсети/мессенджеры на сайте
    (виджет «Соцсети»). Он не связан с иконками Instagram/Viber/звонок
    в шапке и подвале сайта — те по-прежнему настраиваются в разделе
    «Настройки».
  </p>

  <div class="card">
    <h3><?= $editItem ? 'Изменить ссылку' : 'Добавить ссылку' ?></h3>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action" value="<?= $editItem ? 'edit' : 'add' ?>">
      <?php if ($editItem): ?><input type="hidden" name="id" value="<?= (int)$editItem['id'] ?>"><?php endif; ?>

      <div class="form-field">
        <label>Название (например «Instagram», «WhatsApp»)</label>
        <input type="text" id="social_platform" name="platform" required maxlength="40" value="<?= e($editItem['platform'] ?? '') ?>">
      </div>
      <div class="form-field">
        <label>Название (укр., необязательно)
          <button type="button" class="btn ghost admin-translate-btn" data-translate-from="social_platform" data-translate-to="social_platform_ua">⇄ Перевести с рус.</button>
        </label>
        <input type="text" id="social_platform_ua" name="platform_ua" maxlength="40" value="<?= e($editItem['platform_ua'] ?? '') ?>">
      </div>
      <div class="form-field">
        <label>Ссылка (полный адрес, например https://t.me/username)</label>
        <input type="text" name="url" required value="<?= e($editItem['url'] ?? '') ?>">
      </div>
      <div class="form-field">
        <label>Иконка-эмодзи (если нет картинки), например 📷</label>
        <input type="text" name="icon_text" maxlength="4" value="<?= e($editItem['icon_text'] ?? '') ?>">
      </div>
      <div class="form-field">
        <label>Иконка-картинка (необязательно, заменяет эмодзи)</label>
        <?php if (!empty($editItem['icon_image'])): ?>
          <div class="admin-upload-current"><img src="../<?= e($editItem['icon_image']) ?>" alt=""></div>
        <?php endif; ?>
        <label class="file-input-styled">
          <span>Выбрать файл</span>
          <input type="file" name="icon_image" accept="image/png,image/jpeg,image/webp">
        </label>
      </div>

      <button type="submit" class="btn full"><?= $editItem ? 'Сохранить' : 'Добавить' ?></button>
      <?php if ($editItem): ?>
        <a href="social.php" class="btn ghost full" style="margin-top:8px; text-align:center;">Отменить</a>
      <?php endif; ?>
    </form>
  </div>

  <table class="admin-table">
    <thead><tr><th>Иконка</th><th>Название</th><th>Ссылка</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($items as $item): ?>
        <tr>
          <td>
            <?php if (!empty($item['icon_image'])): ?>
              <img src="../<?= e($item['icon_image']) ?>" style="width:26px;height:26px;object-fit:cover;border-radius:6px;">
            <?php else: ?>
              <?= e($item['icon_text'] ?: '—') ?>
            <?php endif; ?>
          </td>
          <td><?= e($item['platform']) ?><?= $item['platform_ua'] ? ' / ' . e($item['platform_ua']) : '' ?></td>
          <td style="word-break:break-all; max-width:220px;"><?= e($item['url']) ?></td>
          <td style="white-space:nowrap;">
            <a href="?edit=<?= (int)$item['id'] ?>" class="btn ghost" style="padding:6px 12px;font-size:12px;">Изменить</a>
            <form method="post" style="display:inline;" onsubmit="return confirm('Удалить эту ссылку?');">
              <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
              <button class="btn ghost" style="padding:6px 12px;font-size:12px;">Удалить</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$items): ?><tr><td colspan="4">Пока нет ссылок.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<script src="assets/admin.js" defer></script>
</body>
</html>
