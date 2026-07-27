<?php
/**
 * admin-x7k9m2/register.php
 *
 * Секретная страница первичной регистрации мамы в панели управления.
 * Открывается только по правильному коду в ссылке:
 *
 *   https://ваш-сайт/admin-x7k9m2/register.php?code=СЕКРЕТНЫЙ_КОД
 *
 * Код задаётся в config.php константой ADMIN_REGISTER_CODE.
 * После того как мама один раз придумала себе логин и пароль, эта
 * страница сама себя отключает (флаг owner_registered в настройках) —
 * повторно попасть на неё по той же ссылке будет уже нельзя, даже
 * зная код. Если доступ всё же понадобится сбросить — админ (или мама
 * через phpMyAdmin/файловый менеджер хостинга) может вручную выставить
 * настройку owner_registered обратно в 0 в таблице site_settings.
 */
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/functions.php';

$pdo = getDB();
$alreadyRegistered = getSetting('owner_registered', '0') === '1';
$code = $_GET['code'] ?? $_POST['code'] ?? '';
$codeOk = hash_equals(ADMIN_REGISTER_CODE, (string)$code);

// Если код неверный — вообще не показываем, что за страница здесь есть.
if (!$codeOk) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

$error = '';

if ($alreadyRegistered) {
    // Регистрация уже была использована — просто отправляем на вход.
    // (ничего не отправляем формой ниже)
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfCheck()) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $repeat   = $_POST['password_repeat'] ?? '';

    if ($username === '' || strlen($username) < 3) {
        $error = 'Логин должен быть не короче 3 символов.';
    } elseif (strlen($password) < 6) {
        $error = 'Пароль должен быть не короче 6 символов.';
    } elseif ($password !== $repeat) {
        $error = 'Пароли не совпадают.';
    } else {
        // Обновляем самый первый (стартовый) аккаунт админа на логин/пароль,
        // которые мама придумала сама — второй, отдельный аккаунт не нужен.
        $firstAdmin = $pdo->query('SELECT id FROM admin_users ORDER BY id ASC LIMIT 1')->fetch();

        if ($firstAdmin) {
            $pdo->prepare('UPDATE admin_users SET username = ?, password_hash = ? WHERE id = ?')
                ->execute([$username, password_hash($password, PASSWORD_DEFAULT), $firstAdmin['id']]);
            $adminId = (int)$firstAdmin['id'];
        } else {
            $pdo->prepare('INSERT INTO admin_users (username, password_hash) VALUES (?, ?)')
                ->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
            $adminId = (int)$pdo->lastInsertId();
        }

        setSetting('owner_registered', '1');

        session_regenerate_id(true);
        $_SESSION['admin_id'] = $adminId;
        issueRememberCookie($adminId);

        redirect('dashboard.php');
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Регистрация владельца — Панель управления</title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>
<div class="container login-box">
  <div class="card">
    <h2>Регистрация в панели</h2>

    <?php if ($alreadyRegistered): ?>
      <p style="color:var(--ink-soft);">Регистрация по этой ссылке уже была выполнена раньше.
      Войдите со своим логином и паролем на обычной странице входа.</p>
      <a href="login.php" class="btn full" style="text-align:center; display:block; margin-top:10px;">Перейти ко входу</a>
    <?php else: ?>
      <p style="color:var(--ink-soft); font-size:13.5px;">
        Придумайте свой логин и пароль — с ними вы будете заходить в панель
        управления сайтом. Эта страница сработает только один раз.
      </p>
      <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="code" value="<?= e($code) ?>">
        <div class="form-field">
          <label>Ваш логин</label>
          <input type="text" name="username" required minlength="3" autofocus>
        </div>
        <div class="form-field">
          <label>Пароль</label>
          <input type="password" name="password" required minlength="6">
        </div>
        <div class="form-field">
          <label>Повторите пароль</label>
          <input type="password" name="password_repeat" required minlength="6">
        </div>
        <button type="submit" class="btn full">Зарегистрироваться и войти</button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
