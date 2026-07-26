<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/includes/auth_check.php';

$pdo = getDB();
$message = '';
$error = '';
$siteMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfCheck()) {
    $form = $_POST['form'] ?? '';

    if ($form === 'site_settings') {
        setSetting('site_name', trim((string)($_POST['site_name'] ?? '')));
        setSetting('site_phone', trim((string)($_POST['site_phone'] ?? '')));
        setSetting('social_instagram_url', trim((string)($_POST['social_instagram_url'] ?? '')));
        setSetting('social_viber_phone', trim((string)($_POST['social_viber_phone'] ?? '')));
        setSetting('social_phone', trim((string)($_POST['social_phone'] ?? '')));
        $siteMessage = 'Настройки сайта сохранены.';
    } else {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $repeat  = $_POST['new_password_repeat'] ?? '';

        $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE id = ?');
        $stmt->execute([$_SESSION['admin_id']]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($current, $user['password_hash'])) {
            $error = 'Текущий пароль указан неверно.';
        } elseif (strlen($new) < 6) {
            $error = 'Новый пароль должен быть не короче 6 символов.';
        } elseif ($new !== $repeat) {
            $error = 'Пароли не совпадают.';
        } else {
            $pdo->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            $message = 'Пароль успешно изменён.';
        }
    }
}

$siteName      = getSetting('site_name', '');
$sitePhone     = getSetting('site_phone', '');
$igUrl         = getSetting('social_instagram_url', '');
$viberPhone    = getSetting('social_viber_phone', '');
$callPhone     = getSetting('social_phone', '');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Настройки — Панель управления</title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="admin-shell">
  <?php require __DIR__ . '/includes/nav.php'; ?>

  <div class="card" style="max-width:520px;">
    <h3>Настройки сайта</h3>
    <p style="color:var(--ink-soft); font-size:13px; margin-top:0;">
      Здесь можно менять название сайта, телефон и ссылки на соцсети —
      это сразу видно только вам в этой панели, изменения применяются
      на сайте сразу после сохранения.
    </p>
    <?php if ($siteMessage): ?><div class="alert success"><?= e($siteMessage) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="form" value="site_settings">
      <div class="form-field">
        <label>Название сайта (шапка и подвал)</label>
        <input type="text" name="site_name" value="<?= e($siteName) ?>" placeholder="Например: Маникюр от Марии" maxlength="80">
      </div>
      <div class="form-field">
        <label>Телефон для подвала сайта</label>
        <input type="text" name="site_phone" value="<?= e($sitePhone) ?>" placeholder="+380 XX XXX XX XX">
      </div>
      <div class="form-field">
        <label>Ссылка на Instagram</label>
        <input type="text" name="social_instagram_url" value="<?= e($igUrl) ?>" placeholder="https://www.instagram.com/...">
      </div>
      <div class="form-field">
        <label>Номер для Viber</label>
        <input type="text" name="social_viber_phone" value="<?= e($viberPhone) ?>" placeholder="+380XXXXXXXXX">
      </div>
      <div class="form-field">
        <label>Номер для звонка (кнопка "Позвонить")</label>
        <input type="text" name="social_phone" value="<?= e($callPhone) ?>" placeholder="+380XXXXXXXXX">
      </div>
      <button type="submit" class="btn full">Сохранить настройки сайта</button>
    </form>
  </div>

  <div class="card" style="max-width:420px;">
    <h3>Смена пароля</h3>
    <?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
      <div class="form-field">
        <label>Текущий пароль</label>
        <input type="password" name="current_password" required>
      </div>
      <div class="form-field">
        <label>Новый пароль</label>
        <input type="password" name="new_password" required minlength="6">
      </div>
      <div class="form-field">
        <label>Повторите новый пароль</label>
        <input type="password" name="new_password_repeat" required minlength="6">
      </div>
      <button type="submit" class="btn full">Сохранить</button>
    </form>
  </div>
</div>
</body>
</html>
