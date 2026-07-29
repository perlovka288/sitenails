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
      <?php if ($__onesignalAppId !== '' && !empty($__siteUser)): ?>
      <button type="button" class="notify-permission-btn" id="notifyPermBtn" title="<?= e(t('notify_permission_title')) ?>" style="display:none;">🔔</button>
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
    noSlots: <?= json_encode(t('booking_no_slots')) ?>
  };
  window.SITE_BOOKING_FORM_ERROR = <?= json_encode(t('booking_form_error_generic')) ?>;
  window.SITE_REVIEW_EDIT_TITLE = <?= json_encode(t('reviews_edit_title')) ?>;
  window.SITE_REVIEW_EDIT_SUBMIT = <?= json_encode(t('reviews_edit_submit')) ?>;
</script>
<?php if ($__onesignalAppId !== ''): ?>
<!-- Push-уведомления (OneSignal) — приходят как обычное системное
     уведомление на телефон/в браузер, без бота (см. includes/onesignal.php,
     отправка идёт при подтверждении записи в панели управления). -->
<script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer></script>
<script>
  window.OneSignalDeferred = window.OneSignalDeferred || [];
  OneSignalDeferred.push(async function (OneSignal) {
    await OneSignal.init({ appId: <?= json_encode($__onesignalAppId) ?> });
    if (window.SITE_USER_ID) {
      // Привязываем подписку на пуши к id клиента в site_users — этот же
      // id сервер использует как external_id при отправке уведомления.
      OneSignal.login(String(window.SITE_USER_ID));
    }

    // ===== Кнопка 🔔 в шапке — явный запрос разрешения на уведомления =====
    // Автоматический браузерный prompt не всегда показывается сам (у
    // некоторых браузеров он требует явного клика пользователя, плюс на
    // iOS/десктопных Safari он вообще не всплывает без такого клика).
    // Поэтому вместо того чтобы полагаться на автопоказ, встраиваем кнопку
    // прямо в интерфейс — клиент сам нажимает и разрешает.
    var notifyBtn = document.getElementById('notifyPermBtn');
    if (notifyBtn && window.Notification) {
      function updateNotifyBtnVisibility() {
        // Показываем кнопку только пока разрешение ещё не решено
        // (не 'granted' и не 'denied') — иначе незачем её показывать.
        notifyBtn.style.display = (Notification.permission === 'default') ? 'flex' : 'none';
      }
      updateNotifyBtnVisibility();

      notifyBtn.addEventListener('click', function () {
        OneSignal.Notifications.requestPermission().then(function () {
          updateNotifyBtnVisibility();
        });
      });

      OneSignal.Notifications.addEventListener('permissionChange', updateNotifyBtnVisibility);
    }
  });
</script>
<?php endif; ?>
