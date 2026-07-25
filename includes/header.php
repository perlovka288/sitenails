<?php $__lang = currentLang(); $__tabParam = isset($_GET['tab']) ? '&tab=' . urlencode($_GET['tab']) : ''; ?>
<!DOCTYPE html>
<html lang="<?= $__lang === 'ua' ? 'uk' : 'ru' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e(SITE_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header class="topbar">
  <div class="container topbar-row">
    <div class="brand"><?= e(SITE_NAME) ?></div>
    <div class="lang-switch">
      <a href="?lang=ru<?= $__tabParam ?>" class="<?= $__lang === 'ru' ? 'active' : '' ?>">РУС</a>
      <a href="?lang=ua<?= $__tabParam ?>" class="<?= $__lang === 'ua' ? 'active' : '' ?>">УКР</a>
    </div>
  </div>
  <div class="container nav-tabs">
    <button type="button" class="tab-btn" data-tab="reviews"><?= e(t('nav_reviews')) ?></button>
    <button type="button" class="tab-btn" data-tab="price"><?= e(t('nav_price')) ?></button>
    <button type="button" class="tab-btn" data-tab="booking"><?= e(t('nav_booking')) ?></button>
  </div>
</header>

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
  window.SITE_BOOKING_LABELS = {
    none: <?= json_encode(t('booking_none')) ?>,
    selected: <?= json_encode(t('booking_selected')) ?>,
    booked: <?= json_encode(t('booking_slot_booked')) ?>,
    noSlots: <?= json_encode(t('booking_no_slots')) ?>
  };
</script>
