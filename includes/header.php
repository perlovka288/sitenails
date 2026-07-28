<?php
$__lang = currentLang();
$__tabParam = isset($_GET['tab']) ? '&tab=' . urlencode($_GET['tab']) : '';
$__siteName = getSetting('site_name', '');
$__siteTitle = $__siteName !== '' ? $__siteName : 'Мастер маникюра';

// ==== Мини-профиль клиента (правый верхний угол шапки) ====
$__profileAvatarPath = null;
$__profileLatestBooking = null;
$__profileBackTab = isset($activeTab) && in_array($activeTab, ['about', 'reviews', 'price', 'booking'], true) ? $activeTab : 'about';
if (!empty($__siteUser)) {
    $__profileAvatarPath = siteUserAvatarPath($__siteUser);
    $__profileLatestBooking = latestBookingForUser((int)$__siteUser['id']);
}
?>
<!DOCTYPE html>
<html lang="<?= $__lang === 'ua' ? 'uk' : 'ru' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($__siteTitle) ?></title>
<link rel="icon" type="image/png" href="assets/img/social/nails.png">
<link rel="apple-touch-icon" href="assets/img/social/nails.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,500&family=Jost:wght@300;400;500;600&family=Tangerine:wght@700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>

<header class="topbar">
  <div class="container topbar-row">
    <div class="lang-switch">
      <a href="?lang=ru<?= $__tabParam ?>" class="<?= $__lang === 'ru' ? 'active' : '' ?>">РУС</a>
      <a href="?lang=ua<?= $__tabParam ?>" class="<?= $__lang === 'ua' ? 'active' : '' ?>">УКР</a>
    </div>
    <a href="index.php" class="site-logo" aria-hidden="true" tabindex="-1">
      <img src="assets/img/social/nails.png" alt="<?= e($__siteTitle) ?>">
    </a>

    <?php if (!empty($__siteUser)): ?>
    <div class="profile-widget">
      <button type="button" class="profile-avatar-btn" id="profileToggleBtn" aria-haspopup="true" aria-expanded="false" aria-label="<?= e($__siteUser['full_name']) ?>">
        <?php if ($__profileAvatarPath): ?>
          <img src="<?= e($__profileAvatarPath) ?>" alt="" class="profile-avatar-img">
        <?php else: ?>
          <span class="profile-avatar-fallback"><?= e(mb_strtoupper(mb_substr($__siteUser['full_name'], 0, 1))) ?></span>
        <?php endif; ?>
      </button>

      <div class="profile-dropdown" id="profileDropdown">
        <form action="update_avatar.php" method="post" enctype="multipart/form-data" id="profileAvatarForm">
          <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
          <input type="hidden" name="back_tab" value="<?= e($__profileBackTab) ?>">
          <label class="profile-avatar-edit" for="profileAvatarInput">
            <?php if ($__profileAvatarPath): ?>
              <img src="<?= e($__profileAvatarPath) ?>" alt="" class="profile-avatar-img profile-avatar-img--lg">
            <?php else: ?>
              <span class="profile-avatar-fallback profile-avatar-fallback--lg"><?= e(mb_strtoupper(mb_substr($__siteUser['full_name'], 0, 1))) ?></span>
            <?php endif; ?>
            <span class="profile-avatar-edit-label"><?= e(t('profile_change_photo')) ?></span>
          </label>
          <input type="file" id="profileAvatarInput" name="avatar" accept="image/png,image/jpeg,image/webp,image/gif" style="display:none;" onchange="this.form.submit()">
        </form>

        <div class="profile-dropdown-body">
          <div class="profile-name"><?= e($__siteUser['full_name']) ?></div>
          <div class="profile-meta"><?= e(t('profile_login_label')) ?> <?= e($__siteUser['login']) ?></div>
          <div class="profile-meta"><?= e($__siteUser['phone']) ?></div>
        </div>

        <div class="profile-status">
          <div class="profile-status-title"><?= e(t('profile_status_title')) ?></div>
          <?php if ($__profileLatestBooking): ?>
            <span class="badge <?= e($__profileLatestBooking['status']) ?>"><?= e(bookingStatusLabel($__profileLatestBooking['status'], $__lang)) ?></span>
          <?php else: ?>
            <span class="profile-status-empty"><?= e(t('profile_status_empty')) ?></span>
          <?php endif; ?>
        </div>

        <a href="logout.php" class="profile-logout"><?= e(t('profile_logout')) ?></a>
      </div>
    </div>
    <?php endif; ?>
  </div>
  <div class="container nav-tabs">
    <button type="button" class="tab-btn" data-tab="about"><?= e(t('nav_about')) ?></button>
    <button type="button" class="tab-btn" data-tab="reviews"><?= e(t('nav_reviews')) ?></button>
    <button type="button" class="tab-btn" data-tab="price"><?= e(t('nav_price')) ?></button>
    <button type="button" class="tab-btn" data-tab="booking"><?= e(t('nav_booking')) ?></button>
  </div>
</header>

<!-- Модальное окно выбора языка (показывается первым, до имени) -->
<div class="greet-overlay" id="langOverlay" style="display:none;">
  <div class="greet-modal">
    <h3 style="text-align:center;">Выберите язык сайта<br>Оберіть мову сайту</h3>
    <div class="lang-select-grid">
      <button type="button" class="btn full" data-lang="ru">Русский</button>
      <button type="button" class="btn full" data-lang="ua">Українська</button>
    </div>
  </div>
</div>

<!-- Модальное окно приветствия -->
<div class="greet-overlay" id="greetOverlay" style="display:none;">
  <div class="greet-modal">
    <h3><?= e(t('greet_title')) ?></h3>
    <p><?= e(t('greet_text')) ?></p>
    <form id="greetForm">
      <div class="form-field">
        <input type="text" id="greetInput" placeholder="<?= e(t('greet_placeholder')) ?>" required>
      </div>
      <button type="submit" class="btn full"><?= e(t('greet_continue')) ?></button>
    </form>
    <button type="button" id="greetSkip" class="btn ghost full" style="margin-top:8px;"><?= e(t('greet_skip')) ?></button>
  </div>
</div>
<script>
  window.SITE_GREET_TEMPLATE = <?= json_encode(t('greet_hello')) ?>;
  window.SITE_CSRF_TOKEN = <?= json_encode(csrfToken()) ?>;
  window.SITE_LANG_CODE = <?= json_encode($__lang) ?>;
  window.SITE_IS_ADMIN = <?= json_encode(isAdmin()) ?>;
  window.SITE_BOOKING_LABELS = {
    none: <?= json_encode(t('booking_none')) ?>,
    selected: <?= json_encode(t('booking_selected')) ?>,
    booked: <?= json_encode(t('booking_slot_booked')) ?>,
    noSlots: <?= json_encode(t('booking_no_slots')) ?>
  };
  window.SITE_BOOKING_FORM_ERROR = <?= json_encode(t('booking_form_error_generic')) ?>;
</script>
