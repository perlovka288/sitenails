<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/includes/auth_check.php';

$pdo = getDB();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfCheck()) {
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
