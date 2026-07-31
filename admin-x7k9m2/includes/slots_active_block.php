<?php
// Ожидает переменную $activeBookings (массив заявок со status='new').
// Подключается через include внутри slotsPageRenderBlocks() —
// переменные функции видны напрямую.
?>
<div class="card slots-section">
  <div class="slots-section-head">
    <h3>Активные заявки</h3>
    <?php if ($activeBookings): ?>
      <span class="about-accordion-count"><?= count($activeBookings) ?></span>
    <?php endif; ?>
  </div>
  <?php if (!$activeBookings): ?>
    <p class="rec-empty">Новых заявок пока нет — как появится запись с сайта, она покажется здесь.</p>
  <?php else: ?>
    <div class="rec-list">
      <?php foreach ($activeBookings as $b): ?>
        <div class="rec-card">
          <div class="rec-card-head">
            <div class="rec-card-head-left">
              <span class="review-avatar rec-card-avatar" aria-hidden="true">
                <?php if (!empty($b['client_avatar_path'])): ?>
                  <img src="<?= e(widgetAdminSrc($b['client_avatar_path'])) ?>" alt="" class="review-avatar-img">
                <?php else: ?>
                  <span class="review-avatar-fallback"><?= e(mb_strtoupper(mb_substr($b['client_name'], 0, 1))) ?></span>
                <?php endif; ?>
              </span>
              <div class="rec-card-head-name">
                <span class="rec-card-id">#<?= (int)$b['id'] ?></span>
                <strong><?= e($b['client_name']) ?></strong>
              </div>
            </div>
            <span class="badge <?= e($b['status']) ?>"><?= e(bookingStatusLabel($b['status'], 'ru')) ?></span>
          </div>
          <div class="rec-card-body">
            <?php if ($b['wanted_date']): ?>
            <div class="rec-card-row"><span class="rec-card-icon">📅</span><span><?= e(formatBookingDateTime($b['wanted_date'])) ?></span></div>
            <?php endif; ?>
            <?php if ($b['service']): ?>
            <div class="rec-card-row"><span class="rec-card-icon">💅</span><span><?= e($b['service']) ?></span></div>
            <?php endif; ?>
            <?php if ($b['phone']): ?>
            <div class="rec-card-row"><span class="rec-card-icon">📞</span><a href="tel:<?= e(preg_replace('/\s+/', '', $b['phone'])) ?>" class="rec-card-tel"><?= e($b['phone']) ?></a></div>
            <?php endif; ?>
            <?php if ($b['comment']): ?>
            <div class="rec-card-row"><span class="rec-card-icon">💬</span><span><?= e($b['comment']) ?></span></div>
            <?php endif; ?>
          </div>
          <div class="rec-card-actions">
            <form method="post" data-ajax-form>
              <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
              <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
              <button name="action" value="confirm" class="btn rec-card-btn">Подтвердить</button>
            </form>
            <button type="button" class="btn ghost rec-card-btn rec-card-btn-danger" data-cancel-open data-id="<?= (int)$b['id'] ?>">Отклонить</button>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
