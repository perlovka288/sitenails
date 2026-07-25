<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';

$pdo = getDB();

$reviews = $pdo->query("SELECT * FROM reviews WHERE is_approved = 1 ORDER BY created_at DESC")->fetchAll();
$priceItems = $pdo->query("SELECT * FROM price_items ORDER BY category, sort_order")->fetchAll();

$priceByCategory = [];
foreach ($priceItems as $item) {
    $priceByCategory[$item['category']][] = $item;
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
    <span class="eyebrow">Добро пожаловать</span>
    <h1 data-greet><?= e(SITE_NAME) ?></h1>
    <p>Здесь вы можете почитать отзывы, посмотреть актуальный прайс и записаться на удобное время.</p>
  </section>

  <div class="panels-viewport">
  <div class="panels-track no-anim" id="panelsTrack" data-active="<?= e($activeTab) ?>">

  <!-- ===== ОТЗЫВЫ ===== -->
  <section class="panel" id="reviews">
    <h2 class="section-title">Отзывы</h2>

    <?php if ($reviewSent): ?>
      <div class="alert success">Спасибо! Ваш отзыв отправлен и появится на сайте после проверки.</div>
    <?php endif; ?>

    <?php if (!$reviews): ?>
      <p>Пока нет отзывов — станьте первым!</p>
    <?php endif; ?>

    <?php foreach ($reviews as $r): ?>
      <div class="card review">
        <div class="stars"><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) ?></div>
        <div><?= nl2br(e($r['message'])) ?></div>
        <div class="author">— <?= e($r['author_name']) ?></div>
      </div>
    <?php endforeach; ?>

    <div class="card">
      <h3>Оставить отзыв</h3>
      <form action="submit_review.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <div class="form-field">
          <label>Ваше имя</label>
          <input type="text" name="author_name" required maxlength="60">
        </div>
        <div class="form-field">
          <label>Оценка</label>
          <input type="hidden" name="rating" id="ratingInput" value="5">
          <div id="starPicker" style="font-size:26px; color:var(--accent); cursor:pointer;">
            <span>★</span><span>★</span><span>★</span><span>★</span><span>★</span>
          </div>
        </div>
        <div class="form-field">
          <label>Отзыв</label>
          <textarea name="message" required maxlength="600"></textarea>
        </div>
        <button type="submit" class="btn full">Отправить отзыв</button>
      </form>
    </div>
  </section>

  <!-- ===== ПРАЙС ===== -->
  <section class="panel" id="price">
    <h2 class="section-title">Прайс</h2>

    <?php if (!$priceByCategory): ?>
      <p>Прайс скоро появится.</p>
    <?php endif; ?>

    <div class="card">
      <?php foreach ($priceByCategory as $category => $items): ?>
        <div class="price-category"><?= e($category) ?></div>
        <?php foreach ($items as $item): ?>
          <div class="price-row">
            <span class="name"><?= e($item['title']) ?></span>
            <span class="amount"><?= e($item['price']) ?></span>
          </div>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- ===== ЗАПИСЬ ===== -->
  <section class="panel" id="booking">
    <h2 class="section-title">Запись</h2>

    <?php if ($bookingSent): ?>
      <div class="alert success">Заявка отправлена! Мы свяжемся с вами для подтверждения.</div>
    <?php endif; ?>

    <div class="card">
      <form action="submit_booking.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <div class="form-field">
          <label>Ваше имя</label>
          <input type="text" name="client_name" id="bookingName" required maxlength="60">
        </div>
        <div class="form-field">
          <label>Телефон</label>
          <input type="tel" name="phone" required maxlength="30" placeholder="+380...">
        </div>
        <div class="form-field">
          <label>Услуга</label>
          <input type="text" name="service" maxlength="120" placeholder="Например: маникюр">
        </div>
        <div class="form-field">
          <label>Желаемая дата</label>
          <input type="date" name="wanted_date">
        </div>
        <div class="form-field">
          <label>Комментарий</label>
          <textarea name="comment" maxlength="500"></textarea>
        </div>
        <button type="submit" class="btn full">Записаться</button>
      </form>
    </div>
  </section>

  </div>
  </div>

</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
