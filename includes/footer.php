<footer>
  <div class="container">
    <div class="footer-social">
      <a href="<?= e(SOCIAL_INSTAGRAM_URL) ?>" target="_blank" rel="noopener" title="Instagram">📷</a>
      <a href="viber://chat?number=%2B<?= e(preg_replace('/\D/', '', SOCIAL_VIBER_PHONE)) ?>" title="Viber">💜</a>
      <a href="https://t.me/+<?= e(preg_replace('/\D/', '', SOCIAL_TELEGRAM_PHONE)) ?>" target="_blank" rel="noopener" title="Telegram">✈️</a>
      <a href="tel:<?= e(SOCIAL_PHONE) ?>" title="<?= e(t('booking_phone')) ?>">📞</a>
    </div>
    <p><?= e(SITE_NAME) ?> · <?= e(SITE_PHONE) ?></p>
    <!--
      Скрытая ссылка в админ-панель.
      Она не отображается визуально (opacity: 0), но остаётся в HTML-коде.
      Мама может зайти в панель управления по прямому адресу:
      /admin-x7k9m2/login.php  — эту ссылку лучше просто сохранить в закладки браузера.
    -->
    <a href="admin-x7k9m2/login.php" class="admin-dot" aria-hidden="true" tabindex="-1">•</a>
  </div>
</footer>

<!-- Плавающая кнопка связи -->
<button type="button" class="fab-contact" id="fabContactBtn" aria-label="<?= e(t('fab_title')) ?>">💬</button>
<div class="fab-overlay" id="fabOverlay">
  <div class="fab-modal">
    <h3><?= e(t('fab_title')) ?></h3>
    <p style="text-align:center; color:var(--ink-soft); font-size:13px; margin:6px 0 0;"><?= e(t('fab_hint')) ?></p>
    <div class="contact-grid">
      <a class="contact-tile" href="<?= e(SOCIAL_INSTAGRAM_URL) ?>" target="_blank" rel="noopener">
        <span class="contact-icon">📷</span><?= e(t('booking_instagram')) ?>
      </a>
      <a class="contact-tile" href="viber://chat?number=%2B<?= e(preg_replace('/\D/', '', SOCIAL_VIBER_PHONE)) ?>">
        <span class="contact-icon">💜</span><?= e(t('booking_viber')) ?>
      </a>
      <a class="contact-tile" href="https://t.me/+<?= e(preg_replace('/\D/', '', SOCIAL_TELEGRAM_PHONE)) ?>" target="_blank" rel="noopener">
        <span class="contact-icon">✈️</span><?= e(t('booking_telegram')) ?>
      </a>
      <a class="contact-tile" href="tel:<?= e(SOCIAL_PHONE) ?>">
        <span class="contact-icon">📞</span><?= e(t('booking_phone')) ?>
      </a>
    </div>
    <button type="button" class="fab-close" id="fabCloseBtn"><?= e(t('close')) ?></button>
  </div>
</div>

<script>window.SITE_LANG = <?= json_encode(currentLang()) ?>;</script>
<script src="assets/js/script.js"></script>
</body>
</html>
