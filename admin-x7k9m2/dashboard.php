<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/includes/auth_check.php';

$pdo = getDB();
$newBookings    = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'new'")->fetchColumn();
$pendingReviews = $pdo->query("SELECT COUNT(*) FROM reviews WHERE is_approved = 0")->fetchColumn();
$priceCount     = $pdo->query("SELECT COUNT(*) FROM price_items")->fetchColumn();
$freeSlots      = $pdo->query("SELECT COUNT(*) FROM available_slots WHERE is_booked = 0 AND slot_date >= date('now')")->fetchColumn();

// Профиль владелицы сайта — та же учётная запись, что и в шапке клиентской
// части сайта (аватар, имя), см. currentSiteUser() в includes/functions.php.
$__adminSiteUser  = currentSiteUser();
$__adminFullName  = $__adminSiteUser['full_name'] ?? 'Администратор';
$__adminLogin     = $__adminSiteUser['login'] ?? '';
$__adminAvatarRel = $__adminSiteUser ? siteUserAvatarPath($__adminSiteUser) : null;
$__adminAvatarSrc = $__adminAvatarRel ? '../' . ltrim($__adminAvatarRel, '/') : null;
// Строка из admin_users нужна только для одного — показать реальный пароль
// по кнопке-глазку (там он хранится в обратимо-зашифрованном виде, см.
// password_display / encryptAdminPassword()). Раньше искали её по
// $_SESSION['admin_id'], но панель теперь открывается и без отдельного
// входа в admin-x7k9m2/login.php (просто по флагу "администратор" у
// обычного аккаунта на сайте) — тогда admin_id в сессии не выставляется.
// Поэтому ищем ту же запись по логину текущего аккаунта.
$__adminRow = null;
if ($__adminLogin !== '') {
    $__stmt = $pdo->prepare('SELECT username, password_display FROM admin_users WHERE LOWER(username) = LOWER(?)');
    $__stmt->execute([$__adminLogin]);
    $__adminRow = $__stmt->fetch();
}
if ($__adminLogin === '' && $__adminRow === null) {
    $__stmt = $pdo->prepare('SELECT username, password_display FROM admin_users WHERE id = ?');
    $__stmt->execute([$_SESSION['admin_id'] ?? 0]);
    $__adminRow = $__stmt->fetch();
}
if ($__adminLogin === '') {
    $__adminLogin = (string)($__adminRow['username'] ?? '');
}
// Пароль хранится в обратимо-зашифрованном виде в БД (не хэш) — поэтому
// глазок работает всегда, а не только сразу после ручного входа.
$__adminPlainPassword = $__adminRow ? decryptAdminPassword($__adminRow['password_display'] ?? null) : '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Панель управления</title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>
<div class="admin-shell">

  <!-- ===== Профиль по центру — минималистично, без карточки-плашки:
       просто аватар → имя → приветствие → логин → пароль с глазком,
       друг под другом, с мягкой тенью. ===== -->
  <div class="admin-profile-mini">
    <div class="admin-profile-avatar">
      <?php if ($__adminAvatarSrc): ?>
        <img src="<?= e($__adminAvatarSrc) ?>" alt="">
      <?php else: ?>
        <span class="admin-profile-avatar-fallback"><?= e(mb_strtoupper(mb_substr($__adminFullName, 0, 1))) ?></span>
      <?php endif; ?>
    </div>
    <p class="admin-profile-name"><?= e($__adminFullName) ?></p>
    <p class="admin-profile-greeting">Здравствуйте, <?= e($__adminFullName) ?>!</p>

    <div class="admin-profile-line">
      <span class="admin-profile-line-label">Логин</span>
      <span class="admin-profile-line-value"><?= e($__adminLogin) ?></span>
    </div>
    <div class="admin-profile-line">
      <span class="admin-profile-line-label">Пароль</span>
      <span class="admin-profile-line-value is-masked" id="adminProfilePasswordValue">••••••••</span>
      <button type="button"
        class="admin-profile-eye-btn"
        id="adminProfileEyeBtn"
        data-password="<?= e($__adminPlainPassword) ?>"
        title="Показать/скрыть пароль"
        aria-label="Показать/скрыть пароль">
        <svg data-eye-show viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"></path>
          <circle cx="12" cy="12" r="3"></circle>
        </svg>
        <svg data-eye-hide viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
          <path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a18.5 18.5 0 0 1 5.06-5.94M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 7 11 7a18.5 18.5 0 0 1-2.16 3.19M14.12 14.12a3 3 0 1 1-4.24-4.24"></path>
          <path d="M1 1l22 22"></path>
        </svg>
      </button>
    </div>
  </div>

  <?php require __DIR__ . '/includes/nav.php'; ?>

  <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:14px;">
    <div class="card">
      <div style="font-size:13px;color:var(--ink-soft);">Новые записи</div>
      <div style="font-size:32px;font-family:'Manrope',sans-serif;font-weight:800;"><?= (int)$newBookings ?></div>
      <a href="bookings.php">Посмотреть →</a>
    </div>
    <div class="card">
      <div style="font-size:13px;color:var(--ink-soft);">Скрытых отзывов</div>
      <div style="font-size:32px;font-family:'Manrope',sans-serif;font-weight:800;"><?= (int)$pendingReviews ?></div>
      <a href="reviews.php">Посмотреть →</a>
    </div>
    <div class="card">
      <div style="font-size:13px;color:var(--ink-soft);">Позиций в прайсе</div>
      <div style="font-size:32px;font-family:'Manrope',sans-serif;font-weight:800;"><?= (int)$priceCount ?></div>
      <a href="prices.php">Редактировать →</a>
    </div>
    <div class="card">
      <div style="font-size:13px;color:var(--ink-soft);">Свободных слотов (будущих)</div>
      <div style="font-size:32px;font-family:'Manrope',sans-serif;font-weight:800;"><?= (int)$freeSlots ?></div>
      <a href="slots.php">Настроить →</a>
    </div>
  </div>

  <!-- ===== Большая кнопка "Выйти" — только здесь, на "Главной", в самом
       низу панели, как и просили (не на каждой странице). ===== -->
  <div class="admin-logout-block">
    <a href="logout.php" class="admin-logout-btn">
      <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
        <polyline points="16 17 21 12 16 7"></polyline>
        <line x1="21" y1="12" x2="9" y2="12"></line>
      </svg>
      Выйти из панели управления
    </a>
  </div>
</div>
<script src="assets/admin.js?v=<?= filemtime(__DIR__ . '/assets/admin.js') ?>" defer></script>
</body>
</html>
