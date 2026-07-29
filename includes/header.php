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
      <?php if ($__onesignalAppId !== '' && !empty($__siteUser)): ?>
      <button type="button" class="notify-permission-btn" id="notifyPermBtn" title="<?= e(t('notify_permission_title')) ?>">🔔</button>
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
(function () {
  var notifyBtn = document.getElementById('notifyPermBtn');
  if (!notifyBtn) return;

  var NOTIFY_TITLES = {
    default: <?= json_encode(t('notify_permission_title')) ?>,
    granted: <?= json_encode(t('notify_permission_granted_title')) ?>,
    denied: <?= json_encode(t('notify_permission_denied_title')) ?>
  };
  var NOTIFY_DENIED_HINT = <?= json_encode(t('notify_permission_denied_hint')) ?>;

  // ===== iOS: без сохранённого на "Экран Домой" ярлыка Push в принципе
  // недоступен (ограничение Apple, а не этого сайта) — сразу объясняем
  // это понятно, вместо молчаливого "ничего не происходит" по клику.
  var ua = navigator.userAgent || '';
  var isIOS = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  var isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  if (isIOS && !isStandalone) {
    notifyBtn.addEventListener('click', function () {
      alert('На iPhone уведомления работают только для сайта, сохранённого на Экран «Домой». Откройте кнопку "Поделиться" в Safari → "На экран «Домой»", затем откройте сайт с этого нового значка и нажмите на колокольчик ещё раз.');
    });
    return;
  }

  if (!window.Notification) {
    // Браузер вообще не поддерживает Web Push API (редко, но бывает на
    // очень старых версиях/встроенных in-app браузерах вроде Instagram).
    notifyBtn.addEventListener('click', function () {
      alert('Этот браузер не поддерживает push-уведомления. Попробуйте открыть сайт в Chrome или Safari.');
    });
    return;
  }

  function updateNotifyBtnState() {
    var perm = Notification.permission; // 'default' | 'granted' | 'denied'
    notifyBtn.classList.toggle('is-granted', perm === 'granted');
    notifyBtn.classList.toggle('is-denied', perm === 'denied');
    notifyBtn.title = NOTIFY_TITLES[perm] || NOTIFY_TITLES.default;
  }
  updateNotifyBtnState();

  // ===== Готовность OneSignal SDK =====
  // Раньше клик-обработчик вешался ТОЛЬКО внутри OneSignalDeferred.push(...),
  // то есть до полной загрузки/инициализации SDK кнопка была на странице,
  // но не реагировала на клик вообще — визуально "ничего не происходит".
  // Особенно часто это блокировщики рекламы (uBlock/AdBlock и т.п.), которые
  // очень часто ГЛУШАТ домен cdn.onesignal.com — тогда SDK не загрузится
  // никогда. Поэтому слушатель вешаем сразу, а сам SDK ждём с таймаутом.
  var oneSignalReady = false;
  var oneSignalFailed = false;
  var OneSignalRef = null;

  window.OneSignalDeferred = window.OneSignalDeferred || [];
  OneSignalDeferred.push(async function (OneSignal) {
    try {
      await OneSignal.init({ appId: <?= json_encode($__onesignalAppId) ?> });
      OneSignalRef = OneSignal;
      oneSignalReady = true;
      if (window.SITE_USER_ID) {
        // Привязываем подписку на пуши к id клиента в site_users — этот же
        // id сервер использует как external_id при отправке уведомления.
        OneSignal.login(String(window.SITE_USER_ID));
      }
      OneSignal.Notifications.addEventListener('permissionChange', updateNotifyBtnState);
      updateNotifyBtnState();
    } catch (err) {
      oneSignalFailed = true;
      console.error('OneSignal init error:', err);
    }
  });

  // Если через 6 секунд SDK так и не инициализировался — почти наверняка
  // его заблокировал adblocker/расширение приватности или CDN недоступен
  // у клиента. Отмечаем это явно, чтобы клик давал понятный отклик, а не
  // тишину.
  setTimeout(function () {
    if (!oneSignalReady) oneSignalFailed = true;
  }, 6000);

  notifyBtn.addEventListener('click', function () {
    if (Notification.permission === 'denied') {
      alert(NOTIFY_DENIED_HINT);
      return;
    }
    if (Notification.permission === 'granted') {
      return;
    }
    if (!oneSignalReady) {
      if (oneSignalFailed) {
        alert('Не удалось загрузить сервис уведомлений. Проверьте, не блокирует ли его блокировщик рекламы (uBlock/AdBlock) или расширение приватности в браузере, и попробуйте снова.');
      } else {
        alert('Уведомления ещё загружаются, попробуйте нажать ещё раз через пару секунд.');
      }
      return;
    }
    OneSignalRef.Notifications.requestPermission().then(updateNotifyBtnState);
  });
})();
</script>
<?php endif; ?>
