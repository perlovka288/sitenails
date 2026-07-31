<?php
$__lang = currentLang();
$__tabParam = isset($_GET['tab']) ? '&tab=' . urlencode($_GET['tab']) : '';
$__siteName = getSetting('site_name', '');
$__siteTitle = $__siteName !== '' ? $__siteName : 'Мастер маникюра';

// ==== Мини-профиль клиента (правый верхний угол шапки) — просто ссылка
// на страницу profile.php, вся информация теперь там. ====
$__profileAvatarPath = !empty($__siteUser) ? siteUserAvatarPath($__siteUser) : null;
$__onesignalAppId = getSetting('onesignal_app_id', '');
?>
<!DOCTYPE html>
<html lang="<?= $__lang === 'ua' ? 'uk' : 'ru' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($__siteTitle) ?></title>
<link rel="icon" type="image/png" href="assets/img/social/nails.png">
<link rel="apple-touch-icon" href="assets/img/social/nails.png">
<!-- Манифест + apple-теги — БЕЗ этого iOS Safari не даёт Web Push вообще,
     даже если пользователь сохранил ярлык на рабочий стол (см. manifest.php). -->
<link rel="manifest" href="manifest.php">
<meta name="theme-color" content="#12121a">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?= e($__siteTitle) ?>">
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

    <div class="topbar-actions">
      <?php if (!empty($__siteUser)): ?>
      <div class="notif-center" id="notifCenter">
        <button type="button" class="notif-bell-btn" id="notifCenterBtn" aria-label="Уведомления" title="Уведомления">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"></path>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
          </svg>
          <span class="notif-badge" id="notifBadge" hidden></span>
        </button>
        <div class="notif-dropdown" id="notifDropdown" hidden>
          <div class="notif-dropdown-head">Уведомления</div>
          <?php if ($__onesignalAppId !== ''): ?>
          <button type="button" class="notif-push-row" id="notifyPermBtn" title="<?= e(t('notify_permission_title')) ?>">
            <span>🔔</span><span id="notifyPermBtnText"><?= e(t('notify_permission_title')) ?></span>
          </button>
          <?php endif; ?>
          <div class="notif-list" id="notifList">
            <p class="notif-empty">Загрузка…</p>
          </div>
        </div>
      </div>
      <?php endif; ?>
      <?php if (!empty($__siteUser)): ?>
      <a href="profile.php" class="profile-widget" aria-label="<?= e($__siteUser['full_name']) ?>">
        <?php if ($__profileAvatarPath): ?>
          <img src="<?= e($__profileAvatarPath) ?>" alt="" class="profile-avatar-img">
        <?php else: ?>
          <span class="profile-avatar-fallback"><?= e(mb_strtoupper(mb_substr($__siteUser['full_name'], 0, 1))) ?></span>
        <?php endif; ?>
      </a>
      <?php endif; ?>
    </div>
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
<script>
  window.SITE_CSRF_TOKEN = <?= json_encode(csrfToken()) ?>;
  window.SITE_LANG_CODE = <?= json_encode($__lang) ?>;
  window.SITE_IS_ADMIN = <?= json_encode(isAdmin()) ?>;
  window.SITE_USER_ID = <?= json_encode(!empty($__siteUser) ? (int)$__siteUser['id'] : null) ?>;
  window.SITE_BOOKING_LABELS = {
    none: <?= json_encode(t('booking_none')) ?>,
    selected: <?= json_encode(t('booking_selected')) ?>,
    booked: <?= json_encode(t('booking_slot_booked')) ?>,
    noSlots: <?= json_encode(t('booking_no_slots')) ?>,
    addTime: <?= json_encode(t('slot_add_btn')) ?>
  };
  window.SITE_BOOKING_FORM_ERROR = <?= json_encode(t('booking_form_error_generic')) ?>;
  window.SITE_BOOKING_SLOT_TAKEN_ERROR = <?= json_encode(t('booking_form_error_slot_taken')) ?>;
  window.SITE_REVIEW_EDIT_TITLE = <?= json_encode(t('reviews_edit_title')) ?>;
  window.SITE_REVIEW_EDIT_SUBMIT = <?= json_encode(t('reviews_edit_submit')) ?>;
</script>
<?php require __DIR__ . '/push_bell_script.php'; ?>
