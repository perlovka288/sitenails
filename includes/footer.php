<?php
$__footSiteName    = getSetting('site_name', '');
$__footSitePhone   = getSetting('site_phone', '');
$__footSiteAddress = getSetting('site_address', '');
$__igUrl      = getSetting('social_instagram_url', '');
$__viberPhone = getSetting('social_viber_phone', '');
$__callPhone  = getSetting('social_phone', '');
// Тот же адрес, что уже подтягивается в профиль клиента (профиль →
// карточка записи) — теперь ещё и в подвале сайта, кликабельно, ведёт
// сразу в Google Карты по этому адресу.
$__footMapsHref = $__footSiteAddress !== '' ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($__footSiteAddress) : '';
?>
<footer>
  <div class="container">
    <div class="footer-social">
      <a href="<?= e($__igUrl) ?>" target="_blank" rel="noopener" title="Instagram">
        <img src="assets/img/social/inst.png" alt="Instagram" class="social-icon-img">
      </a>
      <a href="viber://chat?number=%2B<?= e(preg_replace('/\D/', '', $__viberPhone)) ?>" title="Viber">
        <img src="assets/img/social/viber.png" alt="Viber" class="social-icon-img">
      </a>
      <a href="tel:<?= e($__callPhone) ?>" title="<?= e(t('booking_phone')) ?>">📞</a>
    </div>
    <?php if ($__footSiteName !== '' || $__footSitePhone !== ''): ?>
    <p><?= e($__footSiteName) ?><?= ($__footSiteName !== '' && $__footSitePhone !== '') ? ' · ' : '' ?><?= e($__footSitePhone) ?></p>
    <?php endif; ?>
    <?php if ($__footSiteAddress !== ''): ?>
    <p class="footer-address">
      <?php if ($__footMapsHref !== ''): ?>
        <a href="<?= e($__footMapsHref) ?>" target="_blank" rel="noopener">📍 <?= e($__footSiteAddress) ?></a>
      <?php else: ?>
        📍 <?= e($__footSiteAddress) ?>
      <?php endif; ?>
    </p>
    <?php endif; ?>
    <!--
      Скрытая ссылка в админ-панель.
      Она не отображается визуально (opacity: 0), но остаётся в HTML-коде.
      Мама может зайти в панель управления по прямому адресу:
      /admin-x7k9m2/login.php  — эту ссылку лучше просто сохранить в закладки браузера.
    -->
    <a href="admin-x7k9m2/login.php" class="admin-dot" aria-hidden="true" tabindex="-1">•</a>

    <?php if (isAdmin()): ?>
      <a href="admin-x7k9m2/dashboard.php" class="admin-panel-btn"><?= e(t('admin_panel_button')) ?></a>
    <?php endif; ?>
  </div>
</footer>

<!-- Плавающая кнопка связи -->
<button type="button" class="fab-contact" id="fabContactBtn" aria-label="<?= e(t('fab_title')) ?>">💬</button>
<div class="fab-overlay" id="fabOverlay">
  <div class="fab-modal">
    <h3><?= e(t('fab_title')) ?></h3>
    <p class="fab-master-name"><?= e(t('fab_master_name')) ?></p>
    <p style="text-align:center; color:var(--ink-soft); font-size:13px; margin:6px 0 0;"><?= e(t('fab_hint')) ?></p>
    <div class="contact-grid">
      <a class="contact-tile" href="<?= e($__igUrl) ?>" target="_blank" rel="noopener">
        <span class="contact-icon"><img src="assets/img/social/inst.png" alt="" class="social-icon-img"></span><?= e(t('booking_instagram')) ?>
      </a>
      <a class="contact-tile" href="viber://chat?number=%2B<?= e(preg_replace('/\D/', '', $__viberPhone)) ?>">
        <span class="contact-icon"><img src="assets/img/social/viber.png" alt="" class="social-icon-img"></span><?= e(t('booking_viber')) ?>
      </a>
      <a class="contact-tile" href="tel:<?= e($__callPhone) ?>">
        <span class="contact-icon">📞</span><?= e(t('booking_phone')) ?>
      </a>
    </div>
    <button type="button" class="fab-close" id="fabCloseBtn"><?= e(t('close')) ?></button>
  </div>
</div>

<script>window.SITE_LANG = <?= json_encode(currentLang()) ?>;</script>
<script src="assets/js/script.js?v=<?= filemtime(__DIR__ . '/../assets/js/script.js') ?>"></script>
</body>
</html>
