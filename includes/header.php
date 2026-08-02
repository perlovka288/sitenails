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
<?php $__logoVer = filemtime(__DIR__ . '/../assets/img/social/nails.png'); ?>
<link rel="icon" type="image/png" href="assets/img/social/nails.png?v=<?= $__logoVer ?>">
<link rel="apple-touch-icon" href="assets/img/social/nails.png?v=<?= $__logoVer ?>">
<!-- Манифест + apple-теги — БЕЗ этого iOS Safari не даёт Web Push вообще,
     даже если пользователь сохранил ярлык на рабочий стол (см. manifest.php). -->
<link rel="manifest" href="manifest.php">
<meta name="theme-color" content="#12121a">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?= e($__siteTitle) ?>">
<!-- Ставим сохранённую тему ДО загрузки CSS — иначе на долю секунды
     мелькнёт тёмная тема по умолчанию, даже если человек выбрал светлую. -->
<script>
(function () {
  try {
    var saved = localStorage.getItem('site_theme');
    if (saved === 'light' || saved === 'dark') {
      document.documentElement.setAttribute('data-theme', saved);
    }
  } catch (e) {}
})();
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,500&family=Jost:wght@300;400;500;600&family=Tangerine:wght@700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>

<!-- ===== Декоративный фон сайта: приглушённое/размытое фото на заднем
     плане (сейчас файла ещё нет — просто положите картинку с типсами/
     маникюром в assets/img/social/bg-nails.jpg, той же папке, где лежат
     иконки соцсетей, и она подхватится сама, без правки кода). Через
     background-blend-mode затемнение подстраивается под текущую тему
     (тёмную/светлую) автоматически, а не завязано на конкретный цвет. ===== -->
<div class="page-bg-decor" aria-hidden="true"></div>

<header class="topbar">
  <div class="container topbar-row">
    <div class="lang-switch" id="langSwitch">
      <span class="lang-switch-thumb" id="langSwitchThumb" aria-hidden="true"></span>
      <a href="?lang=ru<?= $__tabParam ?>" data-lang="ru" class="<?= $__lang === 'ru' ? 'active' : '' ?>">РУС</a>
      <a href="?lang=ua<?= $__tabParam ?>" data-lang="ua" class="<?= $__lang === 'ua' ? 'active' : '' ?>">УКР</a>
    </div>
    <a href="index.php" class="site-logo" aria-hidden="true" tabindex="-1">
      <img src="assets/img/social/nails.png?v=<?= $__logoVer ?>" alt="<?= e($__siteTitle) ?>">
    </a>

    <div class="topbar-actions">
      <button type="button" class="theme-toggle-btn" id="themeToggleBtn" aria-label="Переключить тему оформления" title="Светлая / тёмная тема">
        <svg class="theme-toggle-icon theme-toggle-icon--sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <circle cx="12" cy="12" r="4"></circle>
          <path d="M12 2v2.5M12 19.5V22M4.22 4.22l1.77 1.77M17.99 17.99l1.77 1.77M2 12h2.5M19.5 12H22M4.22 19.78l1.77-1.77M17.99 6.01l1.77-1.77"></path>
        </svg>
        <svg class="theme-toggle-icon theme-toggle-icon--moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
        </svg>
      </button>
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
    <span class="tab-indicator" id="tabIndicator" aria-hidden="true"></span>
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
