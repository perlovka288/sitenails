<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/functions.php';

if (isAdmin()) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfCheck()) {
        $error = 'Сессия устарела, попробуйте ещё раз.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        // Жёсткая привязка: в панель управления может войти только один
        // конкретный аккаунт (lybovk). Любой другой логин отклоняется
        // ещё до обращения к базе — независимо от того, что там хранится.
        if (strtolower($username) !== 'lybovk') {
            $error = 'Неверный логин или пароль.';
        } else {
            $pdo = getDB();
            $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE username = ?');
            $stmt->execute(['lybovk']);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['admin_id'] = $user['id'];
                issueRememberCookie($user['id']);
                redirect('dashboard.php');
            } else {
                $error = 'Неверный логин или пароль.';
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
<title>Вход в панель управления</title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>
<div class="container login-box">
  <div class="card">
    <h2>Вход в панель</h2>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
      <div class="form-field">
        <label>Логин</label>
        <input type="text" name="username" required autofocus>
      </div>
      <div class="form-field">
        <label>Пароль</label>
        <input type="password" name="password" required>
      </div>
      <button type="submit" class="btn full">Войти</button>
    </form>
  </div>
</div>
</body>
</html>
