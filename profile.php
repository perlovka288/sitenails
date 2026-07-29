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
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e(t('profile_page_title')) ?></title>
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
    <a href="index.php" class="profile-page-back-link" style="justify-self:end; font-size:13px; color:var(--ink-soft); text-decoration:none;"><?= e(t('profile_back_to_site')) ?></a>
  </div>
</header>

<main class="container" style="max-width:640px; padding-top:24px; padding-bottom:60px;">
  <div class="card">
    <div class="profile-page-head">
      <div class="profile-page-avatar-wrap">
        <?php if ($avatarPath): ?>
          <img src="<?= e($avatarPath) ?>" alt="" class="profile-page-avatar-img">
        <?php else: ?>
          <span class="profile-page-avatar-fallback"><?= e(mb_strtoupper(mb_substr($__siteUser['full_name'], 0, 1))) ?></span>
        <?php endif; ?>
      </div>
      <div>
        <div class="profile-page-name"><?= e($__siteUser['full_name']) ?></div>
        <div class="profile-page-meta">
          <?= e(t('profile_login_label')) ?> <?= e($__siteUser['login']) ?><br>
          <?= e($__siteUser['phone']) ?>
        </div>
      </div>
    </div>

    <form action="update_avatar.php" method="post" enctype="multipart/form-data" id="profileAvatarForm">
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="back_tab" value="profile">
      <label class="profile-page-avatar-edit" for="profileAvatarInput"><?= e(t('profile_change_photo')) ?></label>
      <input type="file" id="profileAvatarInput" name="avatar" accept="image/png,image/jpeg,image/webp,image/gif" style="display:none;" onchange="this.form.submit()">
    </form>

    <a href="logout.php" class="profile-page-logout"><?= e(t('profile_logout')) ?></a>
  </div>

  <div class="card">
    <div class="booking-history-title"><?= e(t('profile_bookings_title')) ?></div>

    <?php if (!$bookings): ?>
      <p class="profile-empty-state"><?= e(t('profile_no_bookings')) ?></p>
    <?php else: ?>
      <?php foreach ($bookings as $__b): ?>
        <div class="booking-card">
          <div class="booking-card-top">
            <span class="booking-card-number"><?= e(t('booking_number_prefix')) ?><?= (int)$__b['id'] ?></span>
            <span class="badge <?= e($__b['status']) ?>"><?= e(bookingStatusLabel($__b['status'], $lang)) ?></span>
          </div>
          <div class="booking-card-row">
            <strong><?= e(t('booking_card_time_label')) ?></strong> <?= e($__b['wanted_date']) ?>
          </div>
          <?php if (!empty($__b['service'])): ?>
            <div class="booking-card-services"><strong><?= e(t('booking_card_services_label')) ?></strong> <?= e($__b['service']) ?></div>
          <?php endif; ?>
          <div class="booking-card-row" style="margin-top:8px;">
            <strong><?= e(t('booking_card_master_phone')) ?></strong> <?= $masterPhone !== '' ? e($masterPhone) : '—' ?>
          </div>
          <div class="booking-card-row">
            <strong><?= e(t('booking_card_address')) ?></strong> <?= $masterAddress !== '' ? e($masterAddress) : e(t('booking_card_address_empty')) ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</main>
</body>
</html>
