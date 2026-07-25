<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/lang.php';

$pdo = getDB();
$lang = currentLang();

$reviews = $pdo->query("SELECT * FROM reviews WHERE is_approved = 1 ORDER BY created_at DESC")->fetchAll();
$priceItems = $pdo->query("SELECT * FROM price_items ORDER BY category, sort_order")->fetchAll();

$priceByCategory = [];
foreach ($priceItems as $item) {
    $catKey = $item['category']; // группируем по русскому названию (стабильный ключ)
    $catLabel = ($lang === 'ua' && !empty($item['category_ua'])) ? $item['category_ua'] : $item['category'];
    if (!isset($priceByCategory[$catKey])) {
        $priceByCategory[$catKey] = ['label' => $catLabel, 'items' => []];
    }
    $priceByCategory[$catKey]['items'][] = $item;
}

$reviewSent  = isset($_GET['review_sent']);
$bookingSent = isset($_GET['booking_sent']);

$validTabs = ['reviews', 'price', 'booking'];
$activeTab = $_GET['tab'] ?? 'reviews';
if (!in_array($activeTab, $validTabs, true)) {
    $activeTab = 'reviews';
}

require __DIR__ . '/includes/header.php';
?>

<main class="container">

  <section class="hero">
    <span class="eyebrow"><?= e(t('hero_eyebrow')) ?></span>
    <h1 data-greet><?= e(SITE_NAME) ?></h1>
    <p><?= e(t('hero_text')) ?></p>
  </section>

  <div class="panels-viewport">
  <div class="panels-track no-anim" id="panelsTrack" data-active="<?= e($activeTab) ?>">

  <!-- ===== ОТЗЫВЫ ===== -->
  <section class="panel" id="reviews" data-panel="reviews">
    <h2 class="section-title"><?= e(t('reviews_title')) ?></h2>

    <?php if ($reviewSent): ?>
      <div class="alert success"><?= e(t('reviews_sent')) ?></div>
    <?php endif; ?>

    <?php if (!$reviews): ?>
      <p><?= e(t('reviews_empty')) ?></p>
    <?php endif; ?>

    <?php foreach ($reviews as $r): ?>
      <div class="card review">
        <div class="stars"><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) ?></div>
        <div><?= nl2br(e($r['message'])) ?></div>
        <div class="author">— <?= e($r['author_name']) ?></div>
      </div>
    <?php endforeach; ?>

    <div class="card">
      <h3><?= e(t('reviews_leave')) ?></h3>
      <form action="submit_review.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <div class="form-field">
          <label><?= e(t('reviews_name')) ?></label>
          <input type="text" name="author_name" required maxlength="60">
        </div>
        <div class="form-field">
          <label><?= e(t('reviews_rating')) ?></label>
          <input type="hidden" name="rating" id="ratingInput" value="5">
          <div id="starPicker" style="font-size:26px; color:var(--accent); cursor:pointer;">
            <span>★</span><span>★</span><span>★</span><span>★</span><span>★</span>
          </div>
        </div>
        <div class="form-field">
          <label><?= e(t('reviews_text')) ?></label>
          <textarea name="message" required maxlength="600"></textarea>
        </div>
        <button type="submit" class="btn full"><?= e(t('reviews_send')) ?></button>
      </form>
    </div>
  </section>

  <!-- ===== ПРАЙС ===== -->
  <section class="panel" id="price" data-panel="price">
    <h2 class="section-title"><?= e(t('price_title')) ?></h2>

    <?php if (!$priceByCategory): ?>
      <p><?= e(t('price_empty')) ?></p>
    <?php endif; ?>

    <div class="card">
      <?php foreach ($priceByCategory as $cat): ?>
        <div class="price-category"><?= e($cat['label']) ?></div>
        <?php foreach ($cat['items'] as $item): ?>
          <?php $title = ($lang === 'ua' && !empty($item['title_ua'])) ? $item['title_ua'] : $item['title']; ?>
          <div class="price-row">
            <span class="name"><?= e($title) ?></span>
            <span class="amount"><?= e($item['price']) ?></span>
          </div>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </div>

    <div class="card price-note">
      <p class="price-note-designs">♡ <?= e(t('price_designs')) ?></p>
      <p class="price-note-location">📍 <?= e(t('price_location')) ?></p>
    </div>
  </section>

  <!-- ===== ЗАПИСЬ ===== -->
  <section class="panel" id="booking" data-panel="booking">
    <h2 class="section-title"><?= e(t('booking_title')) ?></h2>

    <?php if ($bookingSent): ?>
      <div class="alert success"><?= e(t('booking_sent')) ?></div>
    <?php endif; ?>

    <div class="card">
      <p style="color:var(--ink-soft); margin-top:0;"><?= e(t('booking_intro')) ?></p>

      <div class="calendar" id="bookingCalendar" data-week-start="">
        <div class="calendar-nav">
          <button type="button" class="btn ghost" id="calPrev" style="padding:8px 14px;font-size:13px;"><?= e(t('booking_week_prev')) ?></button>
          <button type="button" class="btn ghost" id="calNext" style="padding:8px 14px;font-size:13px;"><?= e(t('booking_week_next')) ?></button>
        </div>
        <div class="calendar-grid" id="calendarGrid">
          <!-- заполняется через JS (get_slots.php) -->
        </div>
        <p class="calendar-empty" id="calendarEmpty" style="display:none;"><?= e(t('booking_no_slots')) ?></p>
      </div>

      <div class="booking-selected">
        <span id="selectedSlotText"><?= e(t('booking_none')) ?></span>
      </div>

      <button type="button" class="btn full" id="bookingCta"><?= e(t('booking_cta')) ?></button>

      <div class="booking-contacts" id="bookingContacts" style="display:none;">
        <h3><?= e(t('booking_contacts_title')) ?></h3>
        <p style="color:var(--ink-soft);"><?= e(t('booking_contacts_hint')) ?></p>
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
      </div>
    </div>
  </section>

  </div>
  </div>

</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
