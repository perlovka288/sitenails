<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/lang.php';

$pdo = getDB();
$lang = currentLang();
$__isAdmin = isAdmin();

// Мама (авторизованная как админ в этом же браузере) видит и скрытые
// отзывы тоже — чтобы могла управлять ими прямо на сайте, не заходя
// в отдельную панель. Обычные посетители видят только опубликованные.
$reviews = $__isAdmin
    ? $pdo->query("SELECT * FROM reviews ORDER BY created_at DESC")->fetchAll()
    : $pdo->query("SELECT * FROM reviews WHERE is_approved = 1 ORDER BY created_at DESC")->fetchAll();

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
      <div class="card review<?= !$r['is_approved'] ? ' review--hidden' : '' ?>">
        <?php if ($__isAdmin && !$r['is_approved']): ?>
          <span class="badge new admin-hidden-badge"><?= e(t('reviews_hidden')) ?></span>
        <?php endif; ?>
        <div class="stars"><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) ?></div>
        <?php if (!empty($r['photo_path'])): ?>
          <img src="<?= e($r['photo_path']) ?>" alt="" class="review-photo">
        <?php endif; ?>
        <div><?= nl2br(e($r['message'])) ?></div>
        <div class="author">— <?= e($r['author_name']) ?></div>

        <?php if ($__isAdmin): ?>
        <div class="admin-inline-actions">
          <form method="post" action="admin_quick_action.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="review_toggle">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <input type="hidden" name="back_tab" value="reviews">
            <button type="submit" class="icon-btn"><?= $r['is_approved'] ? e(t('reviews_hide')) : e(t('reviews_show')) ?></button>
          </form>
          <form method="post" action="admin_quick_action.php" onsubmit="return confirm(<?= json_encode(t('reviews_confirm_delete')) ?>);">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="review_delete">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <input type="hidden" name="back_tab" value="reviews">
            <button type="submit" class="icon-btn icon-btn--danger"><?= e(t('reviews_delete')) ?></button>
          </form>
        </div>
        <?php endif; ?>
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
            <?php if ($__isAdmin): ?>
            <span class="admin-inline-actions admin-inline-actions--row">
              <button type="button" class="icon-btn price-edit-btn"
                data-id="<?= (int)$item['id'] ?>"
                data-category="<?= e($item['category']) ?>"
                data-category-ua="<?= e($item['category_ua'] ?? '') ?>"
                data-title="<?= e($item['title']) ?>"
                data-title-ua="<?= e($item['title_ua'] ?? '') ?>"
                data-price="<?= e($item['price']) ?>"
              ><?= e(t('price_edit')) ?></button>
              <form method="post" action="admin_quick_action.php" style="display:inline;" onsubmit="return confirm(<?= json_encode(t('price_confirm_delete')) ?>);">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="price_delete">
                <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                <input type="hidden" name="back_tab" value="price">
                <button type="submit" class="icon-btn icon-btn--danger"><?= e(t('price_delete')) ?></button>
              </form>
            </span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>

    <div class="price-note">
      <p class="price-note-designs">♡ <?= e(t('price_designs')) ?></p>
      <p class="price-note-location">📍 <?= e(t('price_location')) ?></p>
    </div>

    <?php if ($__isAdmin): ?>
      <button type="button" class="btn full open-modal-btn" id="openPriceAddBtn"><?= e(t('price_add_btn')) ?></button>
    <?php endif; ?>
  </section>

  <!-- ===== ЗАПИСЬ ===== -->
  <section class="panel" id="booking" data-panel="booking">
    <h2 class="section-title"><?= e(t('booking_title')) ?></h2>

    <?php if ($bookingSent): ?>
      <div class="alert success"><?= e(t('booking_sent')) ?></div>
    <?php endif; ?>

    <div class="card">
      <p style="color:var(--ink-soft); margin-top:0;"><?= e(t('booking_intro')) ?></p>

      <?php if ($__isAdmin): ?>
        <p class="admin-mode-hint"><?= e(t('admin_mode_badge')) ?>: <?= e(t('slot_form_title')) ?> — <?= $lang === 'ua' ? 'натисніть на будь-який час у календарі, щоб редагувати' : 'нажмите на любое время в календаре, чтобы его отредактировать' ?>.</p>
      <?php endif; ?>

      <div class="calendar" id="bookingCalendar">
        <div class="calendar-grid" id="calendarGrid">
          <!-- заполняется через JS (get_slots.php): блоки Пн–Вс -->
        </div>
        <p class="calendar-empty" id="calendarEmpty" style="display:none;"><?= e(t('booking_no_slots')) ?></p>
      </div>

      <?php if ($__isAdmin): ?>
        <button type="button" class="btn ghost full open-modal-btn" id="openSlotAddBtn"><?= e(t('slot_add_btn')) ?></button>
      <?php endif; ?>

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
          <label class="photo-upload-box" id="reviewPhotoBox" for="reviewPhotoInput">
            <img id="reviewPhotoPreview" class="photo-upload-preview" style="display:none;" alt="">
            <span class="photo-upload-plus" id="reviewPhotoPlus">+</span>
          </label>
          <input type="file" id="reviewPhotoInput" name="photo" accept="image/png,image/jpeg,image/webp,image/gif" style="display:none;">
        </div>
        <button type="submit" class="btn full"><?= e(t('reviews_send')) ?></button>
      </form>
      <button type="button" class="modal-close" id="closeReviewModalBtn"><?= e(t('close')) ?></button>
    </div>
  </div>

  <?php if ($__isAdmin): ?>
  <!-- ===== Модалка "Позиция прайса" (добавить/изменить) — только для админа ===== -->
  <div class="modal-overlay" id="priceModalOverlay">
    <div class="modal-box">
      <h3 id="priceModalTitle"><?= e(t('price_form_title')) ?></h3>
      <form action="admin_quick_action.php" method="post" id="priceModalForm">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="action" value="price_add" id="priceModalAction">
        <input type="hidden" name="id" value="" id="priceModalId">
        <input type="hidden" name="back_tab" value="price">
        <div class="form-field">
          <label><?= e(t('price_category_ru')) ?></label>
          <input type="text" name="category" id="priceModalCategory" required>
        </div>
        <div class="form-field">
          <label><?= e(t('price_category_ua')) ?></label>
          <input type="text" name="category_ua" id="priceModalCategoryUa">
        </div>
        <div class="form-field">
          <label><?= e(t('price_title_ru')) ?></label>
          <input type="text" name="title" id="priceModalTitleField" required>
        </div>
        <div class="form-field">
          <label><?= e(t('price_title_ua')) ?></label>
          <input type="text" name="title_ua" id="priceModalTitleUa">
        </div>
        <div class="form-field">
          <label><?= e(t('price_amount')) ?></label>
          <input type="text" name="price" id="priceModalPrice" required placeholder="450 грн">
        </div>
        <button type="submit" class="btn full"><?= e(t('save')) ?></button>
      </form>
      <button type="button" class="modal-close" id="closePriceModalBtn"><?= e(t('cancel')) ?></button>
    </div>
  </div>

  <!-- ===== Модалка "Свободное время" (добавить/изменить/удалить) — только для админа ===== -->
  <div class="modal-overlay" id="slotModalOverlay">
    <div class="modal-box">
      <h3><?= e(t('slot_form_title')) ?></h3>
      <form action="admin_quick_action.php" method="post" id="slotModalForm">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="action" value="slot_add" id="slotModalAction">
        <input type="hidden" name="id" value="" id="slotModalId">
        <input type="hidden" name="back_tab" value="booking">
        <div class="form-field">
          <label><?= e(t('slot_date')) ?></label>
          <input type="date" name="slot_date" id="slotModalDate" required>
        </div>
        <div class="form-field">
          <label><?= e(t('slot_time')) ?></label>
          <input type="time" name="slot_time" id="slotModalTime" required>
        </div>
        <div class="form-field" id="slotModalStatusField" style="display:none;">
          <label><?= e(t('slot_status')) ?></label>
          <label style="display:flex; align-items:center; gap:8px; font-weight:400; text-transform:none; letter-spacing:0; font-size:14px; color:var(--ink);">
            <input type="checkbox" name="is_booked" id="slotModalBooked" value="1" style="width:auto;">
            <?= e(t('slot_status_booked')) ?>
          </label>
        </div>
        <button type="submit" class="btn full"><?= e(t('save')) ?></button>
      </form>
      <button type="button" class="btn ghost full" id="slotModalDeleteBtn" style="display:none; margin-top:8px;"><?= e(t('slot_delete')) ?></button>
      <form action="admin_quick_action.php" method="post" id="slotDeleteForm" style="display:none;">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="action" value="slot_delete">
        <input type="hidden" name="id" value="" id="slotDeleteId">
        <input type="hidden" name="back_tab" value="booking">
      </form>
      <button type="button" class="modal-close" id="closeSlotModalBtn"><?= e(t('cancel')) ?></button>
    </div>
  </div>
  <?php endif; ?>

  <!-- ===== Модалка подтверждения записи ===== -->
  <div class="modal-overlay" id="bookingConfirmOverlay">
    <div class="modal-box" style="text-align:center;">
      <h3><?= e(t('booking_confirm_title')) ?></h3>
      <p style="color:var(--ink-soft);" id="bookingConfirmText"><?= e(t('booking_confirm_question')) ?></p>
      <div style="display:flex; gap:10px; margin-top:14px;">
        <button type="button" class="btn full" id="bookingConfirmYes"><?= e(t('yes')) ?></button>
        <button type="button" class="btn ghost full" id="bookingConfirmNo"><?= e(t('no')) ?></button>
      </div>
    </div>
  </div>

</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
