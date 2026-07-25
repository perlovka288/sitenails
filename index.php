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
    <h1 data-greet><?= e(getSetting('site_name', '')) ?: '&nbsp;' ?></h1>
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
        <?php if (!empty($r['photo_path'])): ?>
          <img src="<?= e($r['photo_path']) ?>" alt="" class="review-photo">
        <?php endif; ?>
        <div><?= nl2br(e($r['message'])) ?></div>
        <div class="author">— <?= e($r['author_name']) ?></div>
      </div>
    <?php endforeach; ?>

    <button type="button" class="btn full open-modal-btn" id="openReviewModalBtn"><?= e(t('reviews_leave')) ?></button>
  </section>

  <!-- ===== ПРАЙС ===== -->
  <section class="panel" id="price" data-panel="price">
    <h2 class="section-title"><?= e(t('price_title')) ?></h2>
    <div class="section-sub"><?= e(t('price_subtitle')) ?></div>

    <?php if (!$priceByCategory): ?>
      <p><?= e(t('price_empty')) ?></p>
    <?php endif; ?>

    <?php foreach ($priceByCategory as $catKey => $cat): ?>
      <?php $isFramed = in_array($catKey, ['Наращивание / Коррекция'], true); ?>
      <div class="price-block<?= $isFramed ? ' price-block--framed' : '' ?>">
        <div class="price-category"><?= e($cat['label']) ?> <span class="heart">♡</span></div>
        <?php foreach ($cat['items'] as $item): ?>
          <?php $title = ($lang === 'ua' && !empty($item['title_ua'])) ? $item['title_ua'] : $item['title']; ?>
          <div class="price-row">
            <span class="name"><?= e($title) ?></span>
            <span class="leader"></span>
            <span class="amount"><?= e($item['price']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>

    <div class="price-note">
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

      <div class="calendar" id="bookingCalendar">
        <div class="calendar-grid" id="calendarGrid">
          <!-- заполняется через JS (get_slots.php): блоки Пн–Вс -->
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
        <?php
          $__idxIgUrl      = getSetting('social_instagram_url', '');
          $__idxViberPhone = getSetting('social_viber_phone', '');
          $__idxTgPhone    = getSetting('social_telegram_phone', '');
          $__idxCallPhone  = getSetting('social_phone', '');
        ?>
        <div class="contact-grid">
          <a class="contact-tile" href="<?= e($__idxIgUrl) ?>" target="_blank" rel="noopener">
            <span class="contact-icon"><img src="assets/img/social/inst.png" alt="" class="social-icon-img"></span><?= e(t('booking_instagram')) ?>
          </a>
          <a class="contact-tile" href="viber://chat?number=%2B<?= e(preg_replace('/\D/', '', $__idxViberPhone)) ?>">
            <span class="contact-icon"><img src="assets/img/social/viber.png" alt="" class="social-icon-img"></span><?= e(t('booking_viber')) ?>
          </a>
          <a class="contact-tile" href="https://t.me/+<?= e(preg_replace('/\D/', '', $__idxTgPhone)) ?>" target="_blank" rel="noopener">
            <span class="contact-icon"><img src="assets/img/social/tg.png" alt="" class="social-icon-img"></span><?= e(t('booking_telegram')) ?>
          </a>
          <a class="contact-tile" href="tel:<?= e($__idxCallPhone) ?>">
            <span class="contact-icon">📞</span><?= e(t('booking_phone')) ?>
          </a>
        </div>
      </div>
    </div>
  </section>

  </div>
  </div>

  <!-- Модальное окно "Оставить отзыв" — вынесено за пределы .panels-track,
       чтобы position:fixed работал относительно всего экрана, а не
       "уезжал" вместе со сдвигом вкладок Отзывы/Прайс/Запись. -->
  <div class="modal-overlay" id="reviewModalOverlay">
    <div class="modal-box">
      <h3><?= e(t('reviews_leave')) ?></h3>
      <form action="submit_review.php" method="post" enctype="multipart/form-data">
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
        <div class="form-field">
          <label><?= e(t('reviews_photo')) ?></label>
          <input type="file" name="photo" accept="image/png,image/jpeg,image/webp,image/gif">
        </div>
        <button type="submit" class="btn full"><?= e(t('reviews_send')) ?></button>
      </form>
      <button type="button" class="modal-close" id="closeReviewModalBtn"><?= e(t('close')) ?></button>
    </div>
  </div>

</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
