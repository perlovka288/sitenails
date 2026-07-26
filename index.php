<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/lang.php';

$pdo = getDB();
$lang = currentLang();
$__isAdmin = isAdmin();

// Сортировка отзывов: по дате (новые/старые сначала) или по оценке.
$validReviewSorts = ['new', 'old', 'rating_high', 'rating_low'];
$reviewSort = $_GET['review_sort'] ?? 'new';
if (!in_array($reviewSort, $validReviewSorts, true)) {
    $reviewSort = 'new';
}
$reviewOrderSql = match ($reviewSort) {
    'old'         => 'created_at ASC',
    'rating_high' => 'rating DESC, created_at DESC',
    'rating_low'  => 'rating ASC, created_at DESC',
    default       => 'created_at DESC',
};

// Мама (авторизованная как админ в этом же браузере) видит и скрытые
// отзывы тоже — чтобы могла управлять ими прямо на сайте, не заходя
// в отдельную панель. Обычные посетители видят только опубликованные.
$reviews = $__isAdmin
    ? $pdo->query("SELECT * FROM reviews ORDER BY {$reviewOrderSql}")->fetchAll()
    : $pdo->query("SELECT * FROM reviews WHERE is_approved = 1 ORDER BY {$reviewOrderSql}")->fetchAll();

// Среднестатистическая оценка считается только по опубликованным отзывам
// (даже если мама смотрит страницу и видит скрытые тоже).
$reviewStats = $pdo->query("SELECT COUNT(*) AS cnt, AVG(rating) AS avg_rating FROM reviews WHERE is_approved = 1")->fetch();
$reviewsCount = $reviewStats ? (int)$reviewStats['cnt'] : 0;
$reviewsAvgRating = $reviewsCount > 0 ? round((float)$reviewStats['avg_rating'], 1) : null;

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

    <?php if ($reviewsAvgRating !== null): ?>
      <div class="reviews-summary">
        <span class="reviews-summary-stars"><?= str_repeat('★', (int)round($reviewsAvgRating)) . str_repeat('☆', 5 - (int)round($reviewsAvgRating)) ?></span>
        <span class="reviews-summary-value"><?= e(number_format($reviewsAvgRating, 1)) ?></span>
        <span class="reviews-summary-count">(<?= (int)$reviewsCount ?> <?= e(t('reviews_count_word')) ?>)</span>
      </div>
    <?php endif; ?>

    <?php if ($reviewSent): ?>
      <div class="alert success"><?= e(t('reviews_sent')) ?></div>
    <?php endif; ?>

    <?php if ($reviews): ?>
      <form method="get" class="reviews-sort" id="reviewsSortForm">
        <input type="hidden" name="tab" value="reviews">
        <label for="reviewSortSelect"><?= e(t('reviews_sort_label')) ?></label>
        <select name="review_sort" id="reviewSortSelect" onchange="document.getElementById('reviewsSortForm').submit()">
          <option value="new" <?= $reviewSort === 'new' ? 'selected' : '' ?>><?= e(t('reviews_sort_new')) ?></option>
          <option value="old" <?= $reviewSort === 'old' ? 'selected' : '' ?>><?= e(t('reviews_sort_old')) ?></option>
          <option value="rating_high" <?= $reviewSort === 'rating_high' ? 'selected' : '' ?>><?= e(t('reviews_sort_rating_high')) ?></option>
          <option value="rating_low" <?= $reviewSort === 'rating_low' ? 'selected' : '' ?>><?= e(t('reviews_sort_rating_low')) ?></option>
        </select>
      </form>
    <?php endif; ?>

    <?php if (!$reviews): ?>
      <p><?= e(t('reviews_empty')) ?></p>
    <?php endif; ?>

    <?php foreach ($reviews as $r): ?>
      <?php $__photos = reviewPhotoPaths($r['photo_path']); ?>
      <div class="card review<?= !$r['is_approved'] ? ' review--hidden' : '' ?>">
        <?php if ($__isAdmin && !$r['is_approved']): ?>
          <span class="badge new admin-hidden-badge"><?= e(t('reviews_hidden')) ?></span>
        <?php endif; ?>

        <div class="review-head">
          <span class="review-avatar" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4.4 3.6-7 8-7s8 2.6 8 7"/></svg>
          </span>
          <div class="review-head-info">
            <div class="review-name"><?= e($r['author_name']) ?></div>
            <div class="stars"><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) ?></div>
          </div>
        </div>

        <div class="review-date"><?= e(formatReviewDate($r['created_at'])) ?></div>

        <div class="review-message"><?= nl2br(e($r['message'])) ?></div>

        <?php if ($__photos): ?>
          <div class="review-photos">
            <?php foreach ($__photos as $__p): ?>
              <button type="button" class="review-photo-thumb" data-photo-src="<?= e($__p) ?>">
                <img src="<?= e($__p) ?>" alt="<?= e(t('photo_view_alt')) ?>">
              </button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

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

      <div class="calendar-nav">
        <button type="button" class="cal-nav-btn" id="calPrevBtn" aria-label="<?= e(t('week_prev')) ?>">‹</button>
        <span class="cal-nav-label" id="calWeekLabel"></span>
        <button type="button" class="cal-nav-btn" id="calNextBtn" aria-label="<?= e(t('week_next')) ?>">›</button>
      </div>

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
        <p class="fab-master-name"><?= e(t('fab_master_name')) ?></p>
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
          <div id="starPicker" class="star-picker">
            <span class="star selected">★</span><span class="star selected">★</span><span class="star selected">★</span><span class="star selected">★</span><span class="star selected">★</span>
          </div>
        </div>
        <div class="form-field">
          <label><?= e(t('reviews_text')) ?></label>
          <textarea name="message" required maxlength="600"></textarea>
        </div>
        <div class="form-field">
          <label><?= e(t('reviews_photo')) ?></label>
          <div class="photo-upload-row">
            <?php for ($__i = 1; $__i <= 3; $__i++): ?>
              <div class="photo-upload-slot">
                <label class="photo-upload-box" for="reviewPhotoInput<?= $__i ?>">
                  <img class="photo-upload-preview" style="display:none;" alt="">
                  <span class="photo-upload-plus">
                    <svg viewBox="0 0 24 24" width="26" height="26"><path d="M12 4v16M4 12h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                  </span>
                </label>
                <input type="file" id="reviewPhotoInput<?= $__i ?>" name="photos[]" class="photo-upload-input" accept="image/png,image/jpeg,image/webp,image/gif" style="display:none;">
              </div>
            <?php endfor; ?>
          </div>
          <p class="field-hint"><?= e(t('reviews_photo_hint')) ?></p>
        </div>
        <button type="submit" class="btn full"><?= e(t('reviews_send')) ?></button>
      </form>
      <button type="button" class="modal-close" id="closeReviewModalBtn"><?= e(t('close')) ?></button>
    </div>
  </div>

  <!-- ===== Лайтбокс для просмотра фото отзывов (открыть/закрыть крестиком) ===== -->
  <div class="modal-overlay lightbox-overlay" id="photoLightboxOverlay">
    <button type="button" class="lightbox-close" id="photoLightboxClose" aria-label="<?= e(t('close')) ?>">&times;</button>
    <img src="" alt="" class="lightbox-img" id="photoLightboxImg">
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
