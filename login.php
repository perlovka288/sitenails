<?php
/**
 * login.php
 *
 * Вход обычного посетителя сайта под уже созданным аккаунтом (логин или
 * телефон + пароль). Отдельная система от панели управления — см.
 * register.php и гейт в начале index.php.
 */
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';

$next = $_GET['next'] ?? $_POST['next'] ?? '';
$next = (is_string($next) && str_starts_with($next, '/')) ? $next : 'index.php';

if (isAdmin() || isSiteUser()) {
    redirect($next);
}

$error = '';
$oldIdentifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfCheck()) {
        $error = 'Сессия устарела, обновите страницу и попробуйте ещё раз.';
    } else {
        $identifier = trim($_POST['identifier'] ?? '');
        $password   = $_POST['password'] ?? '';
        $oldIdentifier = $identifier;

        $pdo = getDB();
        $stmt = $pdo->prepare('SELECT * FROM site_users WHERE login_lower = ? OR phone = ?');
        $stmt->execute([strtolower($identifier), normalizePhone($identifier)]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['site_user_id'] = $user['id'];
            issueSiteRememberCookie((int)$user['id']);
            redirect($next);
        } else {
            $error = 'Неверный логин/телефон или пароль.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Вход</title>
<link rel="icon" type="image/png" href="assets/img/social/nails.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,500&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
</head>
<body>
<div class="container login-box">
  <div class="card">
    <h2>Вход</h2>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="next" value="<?= e($next) ?>">
      <div class="form-field">
        <label>Логин или телефон</label>
        <input type="text" name="identifier" value="<?= e($oldIdentifier) ?>" required autofocus>
      </div>
      <div class="form-field">
        <label>Пароль</label>
        <input type="password" name="password" required>
      </div>
      <button type="submit" class="btn full">Войти</button>
    </form>
    <p style="text-align:center; margin-top:16px; color:var(--ink-soft);">
      Нет аккаунта? <a href="register.php?next=<?= urlencode($next) ?>">Зарегистрироваться</a>
    </p>
  </div>
</div>
</body>
</html>
