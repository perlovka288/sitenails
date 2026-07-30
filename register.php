<?php
/**
 * register.php
 *
 * Регистрация обычного посетителя сайта — как в Инстаграме: имя, логин,
 * номер телефона, пароль. Это ОТДЕЛЬНАЯ система от панели управления
 * (admin-x7k9m2/register.php) — здесь регистрируются клиенты, а не мама.
 * Без аккаунта на сам сайт (index.php) попасть нельзя, см. гейт там же.
 */
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';

$next = $_GET['next'] ?? $_POST['next'] ?? '';
$next = (is_string($next) && str_starts_with($next, '/')) ? $next : 'index.php';

// См. подробное объяснение в login.php — проверяем именно "уже клиент",
// а не "есть где-то доступ в админку".
if (isSiteUser()) {
    redirect($next);
}

$error = '';
$oldName = '';
$oldLogin = '';
$oldPhone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfCheck()) {
        $error = 'Сессия устарела, обновите страницу и попробуйте ещё раз.';
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $login    = trim($_POST['login'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $repeat   = $_POST['password_repeat'] ?? '';

        $oldName  = $fullName;
        $oldLogin = $login;
        $oldPhone = $phone;

        $loginLower = strtolower($login);
        $normalizedPhone = normalizePhone($phone);

        if ($fullName === '' || mb_strlen($fullName) < 2) {
            $error = 'Введите ваше имя.';
        } elseif (!isValidLogin($login)) {
            $error = 'Логин: 3–20 символов, только латинские буквы, цифры, точка и подчёркивание, без пробелов.';
        } elseif (mb_strlen($normalizedPhone) < 10) {
            $error = 'Введите номер телефона полностью, с кодом страны.';
        } elseif (strlen($password) < 6) {
            $error = 'Пароль должен быть не короче 6 символов.';
        } elseif ($password !== $repeat) {
            $error = 'Пароли не совпадают.';
        } else {
            $pdo = getDB();
            $exists = $pdo->prepare('SELECT id FROM site_users WHERE login_lower = ?');
            $exists->execute([$loginLower]);
            if ($exists->fetch()) {
                $error = 'Этот логин уже занят, придумайте другой.';
            } else {
                $pdo->prepare('
                    INSERT INTO site_users (full_name, login, login_lower, phone, password_hash)
                    VALUES (?, ?, ?, ?, ?)
                ')->execute([$fullName, $login, $loginLower, $normalizedPhone, password_hash($password, PASSWORD_DEFAULT)]);

                $userId = (int)$pdo->lastInsertId();
                session_regenerate_id(true);
                $_SESSION['site_user_id'] = $userId;
                unset($_SESSION['site_logged_out']);
                issueSiteRememberCookie($userId);

                redirect($next);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Регистрация</title>
<link rel="icon" type="image/png" href="assets/img/social/nails.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,500&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
</head>
<body>
<div class="container login-box">
  <div class="card">
    <h2>Регистрация</h2>
    <p style="color:var(--ink-soft); margin-top:-8px;">Создайте аккаунт, чтобы посмотреть сайт и в будущем записываться онлайн.</p>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="next" value="<?= e($next) ?>">
      <div class="form-field">
        <label>Ваше имя</label>
        <input type="text" name="full_name" value="<?= e($oldName) ?>" maxlength="100" required autofocus>
      </div>
      <div class="form-field">
        <label>Логин</label>
        <input type="text" name="login" value="<?= e($oldLogin) ?>" maxlength="20" placeholder="например, anna_nails" required>
      </div>
      <div class="form-field">
        <label>Номер телефона</label>
        <input type="tel" name="phone" value="<?= e($oldPhone) ?>" placeholder="+380 __ ___ __ __" required>
      </div>
      <div class="form-field">
        <label>Пароль</label>
        <input type="password" name="password" minlength="6" required>
      </div>
      <div class="form-field">
        <label>Повторите пароль</label>
        <input type="password" name="password_repeat" minlength="6" required>
      </div>
      <button type="submit" class="btn full">Зарегистрироваться</button>
    </form>
    <p style="text-align:center; margin-top:16px; color:var(--ink-soft);">
      Уже есть аккаунт? <a href="login.php?next=<?= urlencode($next) ?>">Войти</a>
    </p>
  </div>
</div>
</body>
</html>
