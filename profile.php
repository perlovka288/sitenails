<?php
/**
 * profile.php
 *
 * Полноценная страница профиля клиента — вынесена из маленькой выпадашки
 * в шапке (см. includes/header.php, там теперь просто ссылка-аватар сюда).
 * Показывает данные аккаунта и ВСЮ историю записей, каждая в отдельном
 * блоке: номер, статус, время, телефон мастера и адрес.
 */
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/lang.php';

requireSiteAccess('login.php');

$__siteUser = currentSiteUser();
if (!$__siteUser) {
    redirect('login.php');
}

$lang = currentLang();
$avatarPath = siteUserAvatarPath($__siteUser);
$bookings = bookingsForUser((int)$__siteUser['id']);

$masterPhone = getSetting('site_phone', '');
$masterAddress = getSetting('site_address', '');
$__onesignalAppId = getSetting('onesignal_app_id', '');

// ==== Готовые ссылки для карточки записи — звонок мастеру и переход в
// мессенджеры одним тапом, вместо просто текста с номером ====
$__phoneDigits = preg_replace('/\D/', '', $masterPhone);
$__telHref = $__phoneDigits !== '' ? 'tel:+' . ltrim($__phoneDigits, '0') : '';
// Номер мастера уже известен (одна и та же кнопка "позвонить" по сайту),
// поэтому viber/telegram строим по той же логике, что и в includes/functions.php.
$__viberHref = $__phoneDigits !== '' ? 'viber://chat?number=%2B' . $__phoneDigits : '';
$__telegramHref = $__phoneDigits !== '' ? 'https://t.me/+' . $__phoneDigits : '';
$__mapsHref = $masterAddress !== '' ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($masterAddress) : '';
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e(t('profile_page_title')) ?></title>
<link rel="icon" type="image/png" href="assets/img/social/nails.png">
<link rel="apple-touch-icon" href="assets/img/social/nails.png">
<link rel="manifest" href="manifest.php">
<meta name="theme-color" content="#12121a">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Manrope:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
</head>
<body>
<header class="topbar">
  <div class="container topbar-row">
    <div class="lang-switch">
      <a href="?lang=ru" class="<?= $lang === 'ru' ? 'active' : '' ?>">РУС</a>
      <a href="?lang=ua" class="<?= $lang === 'ua' ? 'active' : '' ?>">УКР</a>
    </div>
    <a href="index.php" class="site-logo" aria-hidden="true" tabindex="-1">
      <img src="assets/img/social/nails.png" alt="">
    </a>
    <div class="topbar-actions">
      <?php if ($__siteUser): ?>
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
      <a href="index.php" class="profile-page-back-link" style="font-size:13px; color:var(--ink-soft); text-decoration:none;"><?= e(t('profile_back_to_site')) ?></a>
    </div>
  </div>
</header>
<script>
  window.SITE_USER_ID = <?= json_encode((int)$__siteUser['id']) ?>;
</script>

<main class="container" style="max-width:640px; padding-top:24px; padding-bottom:60px;">
  <div class="card">
    <div class="profile-page-head">
      <form action="update_avatar.php" method="post" enctype="multipart/form-data" id="profileAvatarForm">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="back_tab" value="profile">
        <label class="profile-page-avatar-wrap" for="profileAvatarInput" title="<?= e(t('profile_change_photo')) ?>">
          <?php if ($avatarPath): ?>
            <img src="<?= e($avatarPath) ?>" alt="" class="profile-page-avatar-img">
          <?php else: ?>
            <span class="profile-page-avatar-fallback"><?= e(mb_strtoupper(mb_substr($__siteUser['full_name'], 0, 1))) ?></span>
          <?php endif; ?>
          <!-- SVG вместо символа "✎" — раньше на некоторых устройствах (iOS)
               этот символ не рендерился нужным шрифтом и badge выглядел
               просто белым кружком без иконки. -->
          <span class="profile-page-avatar-badge" aria-hidden="true">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M4 8.5C4 7.67157 4.67157 7 5.5 7H7.5L8.5 5H15.5L16.5 7H18.5C19.3284 7 20 7.67157 20 8.5V17.5C20 18.3284 19.3284 19 18.5 19H5.5C4.67157 19 4 18.3284 4 17.5V8.5Z" stroke="white" stroke-width="1.6" stroke-linejoin="round"/>
              <circle cx="12" cy="13" r="3.2" stroke="white" stroke-width="1.6"/>
            </svg>
          </span>
        </label>
        <input type="file" id="profileAvatarInput" name="avatar" accept="image/png,image/jpeg,image/webp,image/gif" style="display:none;" onchange="this.form.submit()">
      </form>
      <div class="profile-page-head-info">
        <div class="profile-page-name"><?= e($__siteUser['full_name']) ?></div>
        <div class="profile-page-meta">
          @<?= e($__siteUser['login']) ?><br>
          <?= e($__siteUser['phone']) ?>
        </div>
      </div>
      <a href="logout.php" class="profile-page-logout-inline" onclick="return confirm(<?= e(json_encode(t('profile_logout_confirm'), JSON_UNESCAPED_UNICODE)) ?>);" title="<?= e(t('profile_logout')) ?>">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M15 17L20 12M20 12L15 7M20 12H8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M12 4H5C4.44772 4 4 4.44772 4 5V19C4 19.5523 4.44772 20 5 20H12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </a>
    </div>
  </div>

  <div class="card">
    <div class="booking-history-title"><?= e(t('profile_bookings_title')) ?></div>

    <?php if (!$bookings): ?>
      <p class="profile-empty-state"><?= e(t('profile_no_bookings')) ?></p>
    <?php else: ?>
      <?php foreach ($bookings as $__b): ?>
        <div class="booking-card">
          <div class="booking-card-top">
            <span class="badge <?= e($__b['status']) ?>"><?= e(bookingStatusLabel($__b['status'], $lang)) ?></span>
          </div>
          <div class="booking-card-row">
            <strong><?= e(t('booking_card_time_label')) ?></strong> <?= e($__b['wanted_date']) ?>
          </div>
          <?php if (!empty($__b['service'])): ?>
            <div class="booking-card-services"><strong><?= e(t('booking_card_services_label')) ?></strong> <?= e($__b['service']) ?></div>
          <?php endif; ?>
          <div class="booking-card-row" style="margin-top:8px;">
            <strong><?= e(t('booking_card_master_phone')) ?></strong>
            <?php if ($__telHref !== ''): ?>
              <a href="<?= e($__telHref) ?>" class="booking-card-link"><?= e($masterPhone) ?></a>
            <?php else: ?>—<?php endif; ?>
          </div>
          <div class="booking-card-row">
            <strong><?= e(t('booking_card_address')) ?></strong>
            <?php if ($__mapsHref !== ''): ?>
              <a href="<?= e($__mapsHref) ?>" target="_blank" rel="noopener" class="booking-card-link"><?= e($masterAddress) ?></a>
            <?php else: ?>
              <?= e(t('booking_card_address_empty')) ?>
            <?php endif; ?>
          </div>
          <?php if ($__viberHref !== '' || $__telegramHref !== ''): ?>
          <div class="booking-card-quick-links">
            <?php if ($__viberHref !== ''): ?>
              <a href="<?= e($__viberHref) ?>" class="booking-card-quick-btn" aria-label="Viber">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12.1 2C7.1 2 3.3 5.4 3.3 9.9c0 2.5 1.2 4.8 3.2 6.3-.1.9-.5 2.7-.6 3.1-.1.4.1.4.3.3.2-.1 2.4-1.6 3.3-2.2.5.1 1.1.1 1.6.1 5 0 8.8-3.4 8.8-7.9C19.9 5.4 16.1 2 12.1 2z"/></svg>
                Viber
              </a>
            <?php endif; ?>
            <?php if ($__telegramHref !== ''): ?>
              <a href="<?= e($__telegramHref) ?>" target="_blank" rel="noopener" class="booking-card-quick-btn" aria-label="Telegram">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M21.9 3.3 2.9 10.9c-1.3.5-1.3 1.2-.2 1.6l4.9 1.5 1.9 5.8c.2.6.4.9.9.9.4 0 .6-.2.9-.5l2.2-2.1 4.6 3.4c.8.5 1.4.2 1.6-.7l2.9-13.6c.3-1.2-.4-1.7-1.1-1.4Z"/></svg>
                Telegram
              </a>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</main>
<?php require __DIR__ . '/includes/push_bell_script.php'; ?>
</body>
</html>
