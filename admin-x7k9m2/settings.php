<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/includes/auth_check.php';

$pdo = getDB();
$message = '';
$error = '';
$siteMessage = '';
$usersWipedMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfCheck()) {
    $form = $_POST['form'] ?? '';

    if ($form === 'site_settings') {
        setSetting('site_name', trim((string)($_POST['site_name'] ?? '')));
        setSetting('site_phone', trim((string)($_POST['site_phone'] ?? '')));
        setSetting('site_address', trim((string)($_POST['site_address'] ?? '')));
        setSetting('social_instagram_url', trim((string)($_POST['social_instagram_url'] ?? '')));
        setSetting('social_viber_phone', trim((string)($_POST['social_viber_phone'] ?? '')));
        setSetting('social_phone', trim((string)($_POST['social_phone'] ?? '')));
        $siteMessage = 'Настройки сайта сохранены.';
    } elseif ($form === 'push_settings') {
        setSetting('onesignal_app_id', trim((string)($_POST['onesignal_app_id'] ?? '')));
        setSetting('onesignal_api_key', trim((string)($_POST['onesignal_api_key'] ?? '')));
        $siteMessage = 'Настройки уведомлений сохранены.';
    } elseif ($form === 'wipe_test_users') {
        // Удаляем всех зарегистрированных клиентов КРОМЕ владелицы сайта
        // (её строка отмечена is_admin = 1 — см. config.php). Записи и
        // отзывы этих клиентов не удаляются, просто отвязываются
        // (user_id = NULL), чтобы не терять историю/статистику отзывов.
        $idsStmt = $pdo->query('SELECT id FROM site_users WHERE is_admin = 0');
        $idsToDelete = $idsStmt->fetchAll(PDO::FETCH_COLUMN);
        if ($idsToDelete) {
            $placeholders = implode(',', array_fill(0, count($idsToDelete), '?'));
            $pdo->prepare("UPDATE bookings SET user_id = NULL WHERE user_id IN ($placeholders)")->execute($idsToDelete);
            $pdo->prepare("UPDATE reviews SET user_id = NULL WHERE user_id IN ($placeholders)")->execute($idsToDelete);
            $pdo->prepare("DELETE FROM push_subscriptions WHERE user_id IN ($placeholders)")->execute($idsToDelete);
            $pdo->prepare("DELETE FROM site_users WHERE id IN ($placeholders)")->execute($idsToDelete);
        }
        $usersWipedMessage = 'Удалено аккаунтов: ' . count($idsToDelete) . '. Ваш собственный аккаунт не тронут — новые посетители теперь будут регистрироваться заново.';
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
$siteAddress   = getSetting('site_address', '');
$igUrl         = getSetting('social_instagram_url', '');
$viberPhone    = getSetting('social_viber_phone', '');
$callPhone     = getSetting('social_phone', '');
$onesignalAppId  = getSetting('onesignal_app_id', '');
$onesignalApiKey = getSetting('onesignal_api_key', '');
$vapidPublicKey  = getSetting('vapid_public_key', '');
$testUsersCount  = (int)$pdo->query('SELECT COUNT(*) c FROM site_users WHERE is_admin = 0')->fetch()['c'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Настройки — Панель управления</title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
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
        <label>Адрес (куда приходить на запись — виден клиенту в его профиле)</label>
        <input type="text" name="site_address" value="<?= e($siteAddress) ?>" placeholder="Например: г. Киев, ул. Примерная, 10, кв. 5">
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

  <div class="card" style="max-width:520px;">
    <h3>🔔 Уведомления о записи (push, без бота)</h3>
    <p style="color:var(--ink-soft); font-size:13px; margin-top:0;">
      Когда вы подтверждаете запись в разделе «Записи», клиенту приходит
      push-уведомление в браузере/на телефон — как системное уведомление,
      без сторонних мессенджеров. Работает через бесплатный сервис
      <strong>OneSignal</strong> (до 10 000 подписчиков бесплатно):
    </p>
    <ol style="color:var(--ink-soft); font-size:13px; padding-left:18px; margin-top:0;">
      <li>Зарегистрируйтесь на <strong>onesignal.com</strong>, создайте приложение с типом Web Push и укажите адрес вашего сайта.</li>
      <li>Скопируйте <strong>App ID</strong> и <strong>REST API Key</strong> (Settings → Keys &amp; IDs) и вставьте их сюда.</li>
      <li>Файл <code>OneSignalSDKWorker.js</code> уже лежит в корне сайта — ничего докачивать не нужно.</li>
    </ol>
    <?php if ($onesignalAppId === ''): ?>
      <div class="alert" style="background:rgba(255,201,77,.12); color:#e8b74e; border:1px solid rgba(255,201,77,.3);">
        Пока не настроено — клиенты не будут получать push-уведомления о подтверждении записи.
      </div>
    <?php endif; ?>
    <p style="color:var(--ink-faint); font-size:12px;">
      ⚠️ На некоторых бесплатных хостингах (например InfinityFree) исходящие
      запросы к внешним сервисам иногда ограничены. Если после настройки
      уведомления всё же не приходят — это единственная вероятная причина,
      напишите нам, посмотрим логи.
    </p>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="form" value="push_settings">
      <div class="form-field">
        <label>OneSignal App ID</label>
        <input type="text" name="onesignal_app_id" value="<?= e($onesignalAppId) ?>" placeholder="8250eaf6-1a58-489e-b136-...">
      </div>
      <div class="form-field">
        <label>OneSignal REST API Key</label>
        <input type="text" name="onesignal_api_key" value="<?= e($onesignalApiKey) ?>" placeholder="os_v2_app_...">
      </div>
      <button type="submit" class="btn full">Сохранить настройки уведомлений</button>
    </form>
  </div>

  <div class="card" style="max-width:520px; border-color: rgba(200,100,100,.35);">
    <h3 style="color:#e4a3a3;">⚠️ Опасная зона</h3>
    <p style="color:var(--ink-soft); font-size:13px; margin-top:0;">
      Удаляет всех зарегистрированных клиентов сайта (сейчас:
      <strong><?= $testUsersCount ?></strong>), кроме вашего собственного
      аккаунта. Все они смогут зарегистрироваться заново с чистого листа.
      Их старые записи и отзывы не пропадут, просто перестанут быть
      привязаны к удалённому аккаунту.
    </p>
    <?php if ($usersWipedMessage): ?><div class="alert success"><?= e($usersWipedMessage) ?></div><?php endif; ?>
    <form method="post" onsubmit="return confirm('Удалить всех зарегистрированных клиентов (кроме вашего аккаунта)? Отменить это будет нельзя.');">
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="form" value="wipe_test_users">
      <button type="submit" class="btn ghost full" style="border-color:rgba(200,100,100,.5); color:#e4a3a3;">Удалить всех клиентов, кроме меня</button>
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
