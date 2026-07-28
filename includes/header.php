<?php
$__lang = currentLang();
$__tabParam = isset($_GET['tab']) ? '&tab=' . urlencode($_GET['tab']) : '';
$__siteName = getSetting('site_name', '');
$__siteTitle = $__siteName !== '' ? $__siteName : 'Мастер маникюра';
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
    <?php if ($__siteName !== ''): ?>
    <div class="brand"><?= e($__siteName) ?></div>
    <?php else: ?>
    <div class="brand">&nbsp;</div>
    <?php endif; ?>
    <a href="index.php" class="site-logo" aria-hidden="true" tabindex="-1">
      <img src="assets/img/social/nails.png" alt="<?= e($__siteTitle) ?>">
    </a>
    <div class="lang-switch">
      <a href="?lang=ru<?= $__tabParam ?>" class="<?= $__lang === 'ru' ? 'active' : '' ?>">РУС</a>
      <a href="?lang=ua<?= $__tabParam ?>" class="<?= $__lang === 'ua' ? 'active' : '' ?>">УКР</a>
    </div>
  </div>
  <div class="container nav-tabs">
    <button type="button" class="tab-btn" data-tab="about"><?= e(t('nav_about')) ?></button>
    <button type="button" class="tab-btn" data-tab="reviews"><?= e(t('nav_reviews')) ?></button>
    <button type="button" class="tab-btn" data-tab="price"><?= e(t('nav_price')) ?></button>
    <button type="button" class="tab-btn" data-tab="booking"><?= e(t('nav_booking')) ?></button>
  </div>
  <?php if (!empty($__siteUser)): ?>
  <div class="container user-bar">
    <span>👋 <?= e($__siteUser['full_name']) ?></span>
    <a href="logout.php">Выйти</a>
  </div>
  <?php endif; ?>
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
</script>
