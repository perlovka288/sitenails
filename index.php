<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/lang.php';

$pdo = getDB();
$lang = currentLang();
$__isAdmin = isAdmin();

// ==== Гейт: обычный посетитель обязан зарегистрироваться/войти, прежде
// чем увидеть сам сайт (см. register.php / login.php). Мама, вошедшая
// в панель управления, проходит сквозь гейт без отдельного клиентского
// аккаунта. Оборачиваем в try/catch: если тут вдруг что-то пойдёт не
// так (например, временная проблема с базой на хостинге), посетителя
// просто отправляем на страницу входа, а не роняем весь сайт с 500.
try {
    $__siteUser = currentSiteUser();
    if (!$__isAdmin && !$__siteUser) {
        requireSiteAccess('login.php');
    }
} catch (\Throwable $e) {
    error_log('access gate: ' . $e->getMessage());
    if (!$__isAdmin) {
        redirect('login.php');
    }
    $__siteUser = null;
}

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
    ? $pdo->query("SELECT r.*, u.avatar_path AS author_avatar_path FROM reviews r LEFT JOIN site_users u ON u.id = r.user_id ORDER BY r.{$reviewOrderSql}")->fetchAll()
    : $pdo->query("SELECT r.*, u.avatar_path AS author_avatar_path FROM reviews r LEFT JOIN site_users u ON u.id = r.user_id WHERE r.is_approved = 1 ORDER BY r.{$reviewOrderSql}")->fetchAll();

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

// ===== Услуги для анкеты записи: 3 выпадающих списка (Маникюр/Педикюр/
// Дополнительно), подтягиваются динамически из текущего прайса. Категория
// «Наращивание / Коррекция» относится к маникюру, поэтому попадает в тот
// же список, что и «Маникюр» (в TЗ предусмотрено ровно 3 списка). =====
$__bookingServiceCategoryGroups = [
    'manicure' => ['Маникюр', 'Наращивание / Коррекция'],
    'pedicure' => ['Педикюр'],
    'extra'    => ['Дополнительно'],
];
$__bookingServiceOptions = ['manicure' => [], 'pedicure' => [], 'extra' => []];
foreach ($priceItems as $item) {
    foreach ($__bookingServiceCategoryGroups as $__groupKey => $__cats) {
        if (!in_array($item['category'], $__cats, true)) {
            continue;
        }
        $__title = ($lang === 'ua' && !empty($item['title_ua'])) ? $item['title_ua'] : $item['title'];
        // Если группа объединяет несколько категорий прайса, добавляем
        // название категории к пункту списка, чтобы не запутать клиента.
        if (count($__cats) > 1 && $item['category'] !== $__cats[0]) {
            $__catLabel = ($lang === 'ua' && !empty($item['category_ua'])) ? $item['category_ua'] : $item['category'];
            $__title = $__catLabel . ' — ' . $__title;
        }
        $__bookingServiceOptions[$__groupKey][] = [
            'id'    => (int)$item['id'],
            'label' => $__title . ' — ' . $item['price'],
        ];
    }
}

// ===== Раздел «О мне» (самый первый блок на сайте) =====
$about = $pdo->query('SELECT * FROM about_me WHERE id = 1')->fetch();
$aboutStats = $pdo->query('SELECT * FROM about_stats ORDER BY sort_order, id')->fetchAll();
$aboutSkills = $pdo->query('SELECT * FROM about_skills ORDER BY sort_order, id')->fetchAll();
$aboutButtons = $pdo->query('SELECT * FROM about_buttons ORDER BY sort_order, id')->fetchAll();
$workExperience = $pdo->query('SELECT * FROM work_experience ORDER BY sort_order, id')->fetchAll();
$aboutHasContent = $about && (
    trim((string)($about['title'] ?? '')) !== ''
    || trim((string)($about['bio'] ?? '')) !== ''
    || !empty($about['photo_path'])
);

// ===== Виджеты (галереи фото/видео/PDF-сертификаты) =====
$widgetsEnabled = getSetting('widgets_enabled', '1') === '1';
$widgetCategories = $widgetsEnabled ? $pdo->query('SELECT * FROM widget_categories ORDER BY sort_order, id')->fetchAll() : [];
$widgetItemsByCategory = [];
if ($widgetCategories) {
    $itemsStmt = $pdo->query('SELECT * FROM widget_items ORDER BY category_id, sort_order, id');
    foreach ($itemsStmt->fetchAll() as $__wi) {
        $widgetItemsByCategory[(int)$__wi['category_id']][] = $__wi;
    }
}

// ===== Соцсети / мессенджеры (свободный список из админки) =====
$socialLinksList = $pdo->query('SELECT * FROM social_links ORDER BY sort_order, id')->fetchAll();

$reviewSent  = isset($_GET['review_sent']);
$bookingSent = isset($_GET['booking_sent']);

$validTabs = ['about', 'reviews', 'price', 'booking'];
$activeTab = $_GET['tab'] ?? 'about';
if (!in_array($activeTab, $validTabs, true)) {
    $activeTab = 'about';
}

require __DIR__ . '/includes/header.php';
?>

<main class="container">

  <!-- ===== Приветствие: показывается на всех вкладках, КРОМЕ "О мне" —
       там уже есть своё приветствие (about-me-eyebrow), два подряд
       выглядели избыточно. Скрывается/показывается через JS при
       переключении вкладок (см. setActiveTab в script.js). ===== -->
  <section class="hero" id="pageHero" <?= $activeTab === 'about' ? 'style="display:none;"' : '' ?>>
    <span class="eyebrow"><?= e(t('hero_eyebrow')) ?></span>
    <?php if (!empty($__siteUser)): ?>
      <h1><?= e(sprintf(t('greet_hello'), $__siteUser['full_name'])) ?></h1>
    <?php else: ?>
      <h1><?= e(getSetting('site_name', '')) ?: '&nbsp;' ?></h1>
    <?php endif; ?>
    <p><?= e(t('hero_text')) ?></p>
  </section>

  <div class="panels-viewport">
  <div class="panels-track no-anim" id="panelsTrack" data-active="<?= e($activeTab) ?>">

  <!-- ===== О МНЕ ===== -->
  <section class="panel" id="about" data-panel="about">
    <?php if ($aboutHasContent): ?>
      <div class="about-me reveal-on-scroll">
        <div class="about-me-photo">
          <?php if (!empty($about['photo_path'])): ?>
            <img src="<?= e($about['photo_path']) ?>" alt="<?= e($about['title'] ?? '') ?>">
          <?php else: ?>
            <div class="about-me-photo-placeholder" aria-hidden="true">
              <svg viewBox="0 0 24 24" width="48" height="48" fill="currentColor"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4.4 3.6-7 8-7s8 2.6 8 7"/></svg>
            </div>
          <?php endif; ?>
        </div>
        <div class="about-me-content">
          <?php
            $__aboutGreeting = ($lang === 'ua' && !empty($about['greeting_ua'])) ? $about['greeting_ua'] : ($about['greeting'] ?? '');
            $__aboutTitle    = ($lang === 'ua' && !empty($about['title_ua']))    ? $about['title_ua']    : ($about['title'] ?? '');
            $__aboutSubtitle = ($lang === 'ua' && !empty($about['subtitle_ua'])) ? $about['subtitle_ua'] : ($about['subtitle'] ?? '');
            $__aboutBio      = ($lang === 'ua' && !empty($about['bio_ua']))      ? $about['bio_ua']      : ($about['bio'] ?? '');
          ?>
          <?php if ($__aboutGreeting !== ''): ?><span class="about-me-eyebrow"><?= e($__aboutGreeting) ?></span><?php endif; ?>
          <?php if ($__aboutTitle !== ''): ?><h1 class="about-me-title"><?= e($__aboutTitle) ?></h1><?php endif; ?>
          <?php if ($__aboutSubtitle !== ''): ?><p class="about-me-subtitle"><?= e($__aboutSubtitle) ?></p><?php endif; ?>
          <?php if ($__aboutBio !== ''): ?><p class="about-me-bio"><?= nl2br(e($__aboutBio)) ?></p><?php endif; ?>

          <?php if ($aboutStats): ?>
            <div class="about-me-stats">
              <?php foreach ($aboutStats as $__s): ?>
                <div class="about-me-stat">
                  <div class="about-me-stat-value"><?= e($__s['value']) ?></div>
                  <div class="about-me-stat-label"><?= e(($lang === 'ua' && !empty($__s['label_ua'])) ? $__s['label_ua'] : $__s['label']) ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <?php if ($aboutSkills): ?>
            <div class="about-me-skills">
              <?php foreach ($aboutSkills as $__sk): ?>
                <div class="about-me-skill">
                  <?php if (!empty($__sk['icon_image'])): ?>
                    <span class="about-me-skill-icon about-me-skill-icon--img"><img src="<?= e($__sk['icon_image']) ?>" alt=""></span>
                  <?php else: ?>
                    <span class="about-me-skill-icon"><?= e($__sk['icon_text'] ?: '★') ?></span>
                  <?php endif; ?>
                  <span><?= e(($lang === 'ua' && !empty($__sk['name_ua'])) ? $__sk['name_ua'] : $__sk['name']) ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <?php
            // Кнопки блока «О мне» теперь в отдельной таблице — их можно
            // добавлять сколько угодно в панели управления. У каждой —
            // свой тумблер вкл/выкл и тип назначения ссылки.
            $__aboutBtnsToShow = [];
            foreach ($aboutButtons as $__b) {
              if (!$__b['enabled']) continue;
              $__bText = ($lang === 'ua' && !empty($__b['text_ua'])) ? $__b['text_ua'] : $__b['text'];
              $__bHref = aboutButtonHref($__b['type'], $__b['url']);
              if ($__bText === '' || $__bHref === '') continue;
              $__aboutBtnsToShow[] = ['text' => $__bText, 'href' => $__bHref];
            }
          ?>
          <?php if ($__aboutBtnsToShow): ?>
            <div class="about-me-actions">
              <?php foreach ($__aboutBtnsToShow as $__i => $__b): ?>
                <a href="<?= e($__b['href']) ?>" class="btn<?= $__i > 0 ? ' ghost' : '' ?>"<?= aboutButtonIsExternal($__b['href']) ? ' target="_blank" rel="noopener"' : '' ?>><?= e($__b['text']) ?></a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($workExperience): ?>
        <div class="work-experience reveal-on-scroll">
          <h2 class="section-title" style="font-size:17px;"><?= e(t('experience_title')) ?></h2>
          <div class="experience-list">
            <?php foreach ($workExperience as $__exp): ?>
              <?php
                $__expPosition = ($lang === 'ua' && !empty($__exp['position_ua'])) ? $__exp['position_ua'] : $__exp['position'];
                $__expCompany = ($lang === 'ua' && !empty($__exp['company_ua'])) ? $__exp['company_ua'] : ($__exp['company'] ?? '');
                $__expDesc = ($lang === 'ua' && !empty($__exp['description_ua'])) ? $__exp['description_ua'] : ($__exp['description'] ?? '');
              ?>
              <div class="experience-card reveal-on-scroll">
                <div class="experience-period"><?= e($__exp['period']) ?></div>
                <div class="experience-position"><?= e($__expPosition) ?></div>
                <?php if ($__expCompany !== ''): ?><div class="experience-company"><?= e($__expCompany) ?></div><?php endif; ?>
                <?php if ($__expDesc !== ''): ?><div class="experience-desc"><?= nl2br(e($__expDesc)) ?></div><?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <p><?= e(t('about_empty')) ?></p>
    <?php endif; ?>

    <?php if ($socialLinksList): ?>
      <!-- ===== СОЦСЕТИ / МЕССЕНДЖЕРЫ (свободный список из админки) — теперь
           только на вкладке "О мне", а не на всех сразу, и размещены
           ВЫШЕ блока "Достижения". ===== -->
      <section class="social-widget reveal-on-scroll" id="social">
        <h2 class="section-title"><?= e(t('social_title')) ?></h2>
        <div class="social-widget-grid">
          <?php foreach ($socialLinksList as $__soc): ?>
            <?php $__socName = ($lang === 'ua' && !empty($__soc['platform_ua'])) ? $__soc['platform_ua'] : $__soc['platform']; ?>
            <a class="social-widget-tile" href="<?= e($__soc['url']) ?>" target="_blank" rel="noopener">
              <?php if (!empty($__soc['icon_image'])): ?>
                <img src="<?= e($__soc['icon_image']) ?>" alt="" class="social-icon-img">
              <?php else: ?>
                <span class="social-widget-icon"><?= e($__soc['icon_text'] ?: '🔗') ?></span>
              <?php endif; ?>
              <span><?= e($__socName) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($widgetCategories): ?>
      <!-- ===== ВИДЖЕТЫ: ГАЛЕРЕИ / ВИДЕО / СЕРТИФИКАТЫ =====
           Раньше эти блоки выводились ПОСЛЕ .panels-track и поэтому были
           видны на всех вкладках (Отзывы/Прайс/Запись) одновременно.
           Теперь они — часть вкладки "О мне" и показываются только на ней. -->
      <?php foreach ($widgetCategories as $__cat): ?>
        <?php
          $__catItems = $widgetItemsByCategory[(int)$__cat['id']] ?? [];
          if (!$__catItems) continue;
          $__catName = ($lang === 'ua' && !empty($__cat['name_ua'])) ? $__cat['name_ua'] : $__cat['name'];
          // Если фото/видео/PDF меньше 3 штук — центрируем их, а не
          // прижимаем к левому краю (как для полной прокручиваемой ленты).
          $__catFew = count($__catItems) < 3;
        ?>
        <section class="widget-block reveal-on-scroll" id="widget-<?= (int)$__cat['id'] ?>">
          <h2 class="section-title"><?= e($__catName ?: t('widgets_title_default')) ?></h2>
          <div class="widget-carousel-wrap">
            <?php if (!$__catFew): ?>
            <button type="button" class="widget-carousel-arrow widget-carousel-arrow--prev" data-carousel-prev aria-label="←">&#8249;</button>
            <?php endif; ?>
            <div class="widget-carousel<?= $__catFew ? ' widget-carousel--few' : '' ?>" data-carousel>
              <?php foreach ($__catItems as $__item): ?>
                <div class="widget-carousel-item">
                  <?php if ($__cat['type'] === 'photo'): ?>
                    <button type="button" class="widget-photo-thumb" data-photo-src="<?= e($__item['file_path']) ?>">
                      <img src="<?= e($__item['file_path']) ?>" alt="<?= e($__item['title'] ?? '') ?>" loading="lazy">
                    </button>
                    <?php if (!empty($__item['title'])): ?><div class="widget-item-caption"><?= e($__item['title']) ?></div><?php endif; ?>
                  <?php elseif ($__cat['type'] === 'video'): ?>
                    <button type="button" class="widget-video-thumb" data-video-src="<?= e($__item['file_path']) ?>">
                      <video src="<?= e($__item['file_path']) ?>#t=0.1" preload="metadata" playsinline muted data-video-cover></video>
                      <span class="widget-video-play" aria-hidden="true"></span>
                    </button>
                    <?php if (!empty($__item['title'])): ?><div class="widget-item-caption"><?= e($__item['title']) ?></div><?php endif; ?>
                  <?php else: ?>
                    <a class="widget-pdf-tile" href="<?= e($__item['file_path']) ?>" target="_blank" rel="noopener">
                      <span class="widget-pdf-cover" data-pdf-src="<?= e($__item['file_path']) ?>">
                        <span class="widget-pdf-icon" aria-hidden="true">📄</span>
                      </span>
                      <span class="widget-pdf-title"><?= e($__item['title'] ?: 'PDF') ?></span>
                    </a>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
            <?php if (!$__catFew): ?>
            <button type="button" class="widget-carousel-arrow widget-carousel-arrow--next" data-carousel-next aria-label="→">&#8250;</button>
            <?php endif; ?>
          </div>
        </section>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>

  <!-- ===== ОТЗЫВЫ ===== -->
  <section class="panel" id="reviews-panel" data-panel="reviews">
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
      <div class="card review reveal-on-scroll<?= !$r['is_approved'] ? ' review--hidden' : '' ?>">
        <?php if ($__isAdmin && !$r['is_approved']): ?>
          <span class="badge new admin-hidden-badge"><?= e(t('reviews_hidden')) ?></span>
        <?php endif; ?>

        <div class="review-head">
          <span class="review-avatar" aria-hidden="true">
            <?php if (!empty($r['author_avatar_path'])): ?>
              <img src="<?= e($r['author_avatar_path']) ?>" alt="" class="review-avatar-img">
            <?php else: ?>
              <span class="review-avatar-fallback"><?= e(mb_strtoupper(mb_substr($r['author_name'], 0, 1))) ?></span>
            <?php endif; ?>
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
            <button type="submit" class="icon-btn" title="<?= $r['is_approved'] ? ($lang === 'ua' ? 'Приховати' : 'Скрыть') : ($lang === 'ua' ? 'Опублікувати' : 'Опубликовать') ?>"><?= $r['is_approved'] ? e(t('reviews_hide')) : e(t('reviews_show')) ?></button>
          </form>
          <form method="post" action="admin_quick_action.php" onsubmit="return confirm(<?= json_encode(t('reviews_confirm_delete')) ?>);">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="review_delete">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <input type="hidden" name="back_tab" value="reviews">
            <button type="submit" class="icon-btn icon-btn--danger" title="<?= $lang === 'ua' ? 'Видалити' : 'Удалить' ?>"><?= e(t('reviews_delete')) ?></button>
          </form>
        </div>
        <?php elseif (reviewOwnedByCurrentUser($r, $__siteUser)): ?>
        <div class="admin-inline-actions">
          <button type="button" class="icon-btn review-edit-btn"
            title="<?= $lang === 'ua' ? 'Редагувати' : 'Редактировать' ?>"
            data-id="<?= (int)$r['id'] ?>"
            data-name="<?= e($r['author_name']) ?>"
            data-rating="<?= (int)$r['rating'] ?>"
            data-message="<?= e($r['message']) ?>"
          >✏️</button>
          <form method="post" action="delete_own_review.php" onsubmit="return confirm(<?= json_encode(t('reviews_confirm_delete')) ?>);">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button type="submit" class="icon-btn icon-btn--danger" title="<?= $lang === 'ua' ? 'Видалити' : 'Удалить' ?>">🗑️</button>
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

    <?php if ($__isAdmin): ?>
      <button type="button" class="btn full open-modal-btn price-add-open-btn" data-price-add-open>+ <?= e(t('price_add_btn')) ?></button>
    <?php endif; ?>

    <?php if (!$priceByCategory): ?>
      <p><?= e(t('price_empty')) ?></p>
    <?php endif; ?>

    <?php foreach ($priceByCategory as $catKey => $cat): ?>
      <?php $isFramed = in_array($catKey, ['Наращивание / Коррекция'], true); ?>
      <div class="price-block reveal-on-scroll<?= $isFramed ? ' price-block--framed' : '' ?>">
        <div class="price-category"><?= e($cat['label']) ?> <span class="heart">♡</span></div>
        <?php foreach ($cat['items'] as $item): ?>
          <?php $title = ($lang === 'ua' && !empty($item['title_ua'])) ? $item['title_ua'] : $item['title']; ?>
          <div class="price-row">
            <div class="price-row-main">
              <span class="name"><?= e($title) ?></span>
              <span class="leader"></span>
              <span class="amount"><?= e($item['price']) ?></span>
            </div>
            <?php if ($__isAdmin): ?>
            <div class="price-row-admin-actions">
              <button type="button" class="icon-btn icon-btn--sm price-edit-btn"
                title="<?= $lang === 'ua' ? 'Змінити' : 'Изменить' ?>"
                data-id="<?= (int)$item['id'] ?>"
                data-category="<?= e($item['category']) ?>"
                data-category-ua="<?= e($item['category_ua'] ?? '') ?>"
                data-title="<?= e($item['title']) ?>"
                data-title-ua="<?= e($item['title_ua'] ?? '') ?>"
                data-price="<?= e($item['price']) ?>"
              ><?= e(t('price_edit')) ?></button>
              <form method="post" action="admin_quick_action.php" onsubmit="return confirm(<?= json_encode(t('price_confirm_delete')) ?>);">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="price_delete">
                <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                <input type="hidden" name="back_tab" value="price">
                <button type="submit" class="icon-btn icon-btn--sm icon-btn--danger" title="<?= $lang === 'ua' ? 'Видалити' : 'Удалить' ?>"><?= e(t('price_delete')) ?></button>
              </form>
            </div>
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
      <button type="button" class="btn full open-modal-btn" data-price-add-open><?= e(t('price_add_btn')) ?></button>
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
          $__idxCallPhone  = getSetting('social_phone', '');
        ?>
        <div class="contact-grid">
          <a class="contact-tile" href="<?= e($__idxIgUrl) ?>" target="_blank" rel="noopener">
            <span class="contact-icon"><img src="assets/img/social/inst.png" alt="" class="social-icon-img"></span><?= e(t('booking_instagram')) ?>
          </a>
          <a class="contact-tile" href="viber://chat?number=%2B<?= e(preg_replace('/\D/', '', $__idxViberPhone)) ?>">
            <span class="contact-icon"><img src="assets/img/social/viber.png" alt="" class="social-icon-img"></span><?= e(t('booking_viber')) ?>
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
      <h3 id="reviewModalTitle"><?= e(t('reviews_leave')) ?></h3>
      <form action="submit_review.php" method="post" enctype="multipart/form-data" id="reviewForm">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="review_id" id="reviewIdInput" value="">
        <div class="form-field">
          <label><?= e(t('reviews_name')) ?></label>
          <input type="text" name="author_name" id="reviewAuthorInput" required maxlength="60">
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
          <textarea name="message" id="reviewMessageInput" required maxlength="600"></textarea>
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
        <button type="submit" class="btn full" id="reviewSubmitBtn"><?= e(t('reviews_send')) ?></button>
      </form>
      <button type="button" class="modal-close" id="closeReviewModalBtn"><?= e(t('close')) ?></button>
    </div>
  </div>

  <!-- ===== Лайтбокс для просмотра фото отзывов (открыть/закрыть крестиком) ===== -->
  <div class="modal-overlay lightbox-overlay" id="photoLightboxOverlay">
    <button type="button" class="lightbox-close" id="photoLightboxClose" aria-label="<?= e(t('close')) ?>">&times;</button>
    <img src="" alt="" class="lightbox-img" id="photoLightboxImg">
    <video src="" controls playsinline class="lightbox-video" id="photoLightboxVideo"></video>
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

  <!-- ===== Модалка "Анкета записи" — открывается после выбора времени
       в календаре и нажатия кнопки "Записатися" ===== -->
  <div class="modal-overlay" id="bookingFormOverlay">
    <div class="modal-box">
      <h3><?= e(t('booking_form_title')) ?></h3>
      <p style="text-align:center; color:var(--ink-soft); margin-top:-8px;">
        <?= e(t('booking_form_time_label')) ?> <strong id="bookingFormTime" style="color:var(--ink);"></strong>
      </p>

      <form id="bookingForm" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="slot_id" id="bookingFormSlotId" value="">

        <div class="form-field">
          <label><?= e(t('booking_form_name')) ?></label>
          <input type="text" name="client_name" id="bookingFormName" value="<?= e($__siteUser['full_name'] ?? '') ?>" maxlength="100" required>
        </div>

        <div class="form-field">
          <label><?= e(t('booking_form_phone')) ?></label>
          <input type="tel" name="phone" id="bookingFormPhone" value="<?= e($__siteUser['phone'] ?? '') ?>" placeholder="+380 __ ___ __ __" required>
        </div>

        <div class="form-field">
          <label><?= e(t('booking_form_services')) ?></label>
          <div class="booking-service-selects">
            <select name="service_manicure" id="bookingServiceManicure">
              <option value=""><?= e(t('booking_form_manicure')) ?>: <?= e(t('booking_form_service_none')) ?></option>
              <?php foreach ($__bookingServiceOptions['manicure'] as $__opt): ?>
                <option value="<?= (int)$__opt['id'] ?>"><?= e($__opt['label']) ?></option>
              <?php endforeach; ?>
            </select>
            <select name="service_pedicure" id="bookingServicePedicure">
              <option value=""><?= e(t('booking_form_pedicure')) ?>: <?= e(t('booking_form_service_none')) ?></option>
              <?php foreach ($__bookingServiceOptions['pedicure'] as $__opt): ?>
                <option value="<?= (int)$__opt['id'] ?>"><?= e($__opt['label']) ?></option>
              <?php endforeach; ?>
            </select>
            <select name="service_extra" id="bookingServiceExtra">
              <option value=""><?= e(t('booking_form_extra')) ?>: <?= e(t('booking_form_service_none')) ?></option>
              <?php foreach ($__bookingServiceOptions['extra'] as $__opt): ?>
                <option value="<?= (int)$__opt['id'] ?>"><?= e($__opt['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <p class="field-hint booking-service-error" id="bookingServiceError" style="display:none; color:#e4a3a3;"><?= e(t('booking_form_service_error')) ?></p>
        </div>

        <div class="form-field">
          <label><?= e(t('booking_form_contact_title')) ?></label>
          <div class="contact-method-chips">
            <label class="contact-method-chip">
              <input type="radio" name="contact_method" value="instagram">
              <img src="assets/img/social/inst.png" alt="" class="social-icon-img"><?= e(t('booking_instagram')) ?>
            </label>
            <label class="contact-method-chip">
              <input type="radio" name="contact_method" value="viber">
              <img src="assets/img/social/viber.png" alt="" class="social-icon-img"><?= e(t('booking_viber')) ?>
            </label>
            <label class="contact-method-chip">
              <input type="radio" name="contact_method" value="phone" checked>
              <span class="contact-method-chip-icon">📞</span><?= e(t('booking_form_contact_call')) ?>
            </label>
          </div>
        </div>

        <p class="alert error" id="bookingFormError" style="display:none;"></p>

        <button type="submit" class="btn full" id="bookingFormSubmit"><?= e(t('booking_form_submit')) ?></button>
      </form>
      <button type="button" class="modal-close" id="closeBookingFormBtn"><?= e(t('cancel')) ?></button>
    </div>
  </div>

  <!-- ===== Модалка успешной записи ===== -->
  <div class="modal-overlay" id="bookingSuccessOverlay">
    <div class="modal-box" style="text-align:center;">
      <h3><?= e(t('booking_success_title')) ?></h3>
      <p style="color:var(--ink-soft);"><?= e(t('booking_success_text')) ?></p>
      <button type="button" class="btn full" id="closeBookingSuccessBtn"><?= e(t('close')) ?></button>
    </div>
  </div>

</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
