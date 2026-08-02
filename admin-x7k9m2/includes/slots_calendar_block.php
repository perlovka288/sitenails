<?php
// Ожидает переменные из slotsPageRenderBlocks(): $weekStart, $weekEnd,
// $prevWeek, $nextWeek, $byDate, $weekdays, $weekdaysShort, $todayKey.
?>
<div class="card">
  <h3 style="margin-bottom:10px;">Добавить свободное время</h3>
  <form method="post" class="slot-quick-add" data-ajax-form>
    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
    <input type="hidden" name="action" value="add_slot">
    <input type="date" name="slot_date" required value="<?= e($weekStart->format('Y-m-d')) ?>">
    <input type="time" name="slot_time" required value="10:00">
    <button type="submit" class="slot-quick-add-btn" title="Добавить время" aria-label="Добавить время">+</button>
  </form>
  <p class="field-hint">
    Добавленное время сразу появится в календаре записи на сайте — клиент сможет
    выбрать его и написать вам в Instagram / Viber / Telegram / по телефону.
  </p>
</div>

<div class="card">
  <div class="slot-week-nav">
    <a class="icon-btn" href="?week=<?= e($prevWeek) ?>" data-week-nav aria-label="Прошлая неделя" title="Прошлая неделя">‹</a>
    <strong><?= e($weekStart->format('d.m')) ?> – <?= e($weekEnd->format('d.m.Y')) ?></strong>
    <a class="icon-btn" href="?week=<?= e($nextWeek) ?>" data-week-nav aria-label="Следующая неделя" title="Следующая неделя">›</a>
  </div>

  <div class="slot-day-list">
    <?php for ($i = 0; $i < 7; $i++): ?>
      <?php
        $d = (clone $weekStart)->modify("+{$i} day");
        $dateKey = $d->format('Y-m-d');
        $dayItems = $byDate[$dateKey] ?? [];
        $bookingCount = count(array_filter($dayItems, fn($it) => $it['type'] === 'booking'));
        $freeCount = count(array_filter($dayItems, fn($it) => $it['type'] === 'slot' && !$it['data']['is_booked']));
        if ($bookingCount > 0) {
            $summary = $bookingCount . ' ' . ($bookingCount === 1 ? 'запись' : ($bookingCount < 5 ? 'записи' : 'записей'));
        } elseif ($freeCount > 0) {
            $summary = $freeCount . ' свободно';
        } else {
            $summary = 'Нет времени на этот день';
        }
      ?>
      <div class="slot-day-item<?= $dateKey === $todayKey ? ' is-today' : '' ?>" data-day-item data-date="<?= e($dateKey) ?>">
        <div class="slot-day-header" data-day-toggle tabindex="0" role="button" aria-expanded="false">
          <span class="slot-day-card-date">
            <span class="wd"><?= e($weekdaysShort[$i]) ?></span>
            <span class="num"><?= e($d->format('d')) ?></span>
          </span>
          <span class="slot-day-header-text">
            <h3><?= e($weekdays[$i]) ?>, <?= e($d->format('d.m')) ?></h3>
            <p data-day-summary><?= e($summary) ?></p>
          </span>
          <span class="slot-day-header-right">
            <button type="button" class="icon-btn icon-btn--sm slot-day-add-btn" data-day-add="<?= e($dateKey) ?>" title="Добавить время на этот день" aria-label="Добавить время">+</button>
            <svg class="slot-day-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 6 15 12 9 18"/></svg>
          </span>
        </div>
        <div class="slot-day-body">
          <div class="slot-day-body-inner">
            <div class="slot-day-content">
              <?php if (!$dayItems): ?>
                <span class="slot-day-empty">Нет времени на этот день</span>
              <?php else: ?>
                <?php foreach ($dayItems as $it): ?>
                  <?php if ($it['type'] === 'booking'): ?>
                    <?php $b = $it['data']; $displayName = bookingAdminDisplayName($b); ?>
                    <div class="rec-slot-item" data-booking-item data-id="<?= (int)$b['id'] ?>">
                      <span class="rec-slot-time"><?= e($it['time']) ?></span>
                      <span class="rec-slot-name" data-name-display><?= e($displayName) ?></span>
                      <?php if ($b['service']): ?><span class="rec-slot-service"><?= e($b['service']) ?></span><?php endif; ?>
                      <button type="button" class="icon-btn icon-btn--sm" data-note-edit-open data-id="<?= (int)$b['id'] ?>" data-current="<?= e($displayName) ?>" title="Заметка / переименовать">✎</button>
                      <form method="post" data-ajax-form data-confirm="Отметить визит выполненным? Клиент пропадёт из списка записи, а ему придёт пуш с просьбой оставить отзыв.">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="action" value="booking_done">
                        <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                        <button type="submit" class="icon-btn icon-btn--sm rec-slot-done-btn" title="Отметить выполненным (клиент получит пуш с просьбой оставить отзыв)" aria-label="Готово">✓</button>
                      </form>
                      <button type="button" class="icon-btn icon-btn--sm icon-btn--danger" data-cancel-open data-id="<?= (int)$b['id'] ?>" title="Отменить запись">✕</button>
                      <form class="note-edit-form" data-note-form data-ajax-form hidden>
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="action" value="save_note">
                        <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                        <input type="text" name="note" maxlength="200" placeholder="Например: Марина 3-й этаж" value="<?= e($b['admin_note'] ?? '') ?>">
                        <button type="submit" class="icon-btn icon-btn--sm" title="Сохранить">✓</button>
                        <button type="button" class="icon-btn icon-btn--sm" data-note-edit-cancel title="Отмена">✕</button>
                      </form>
                    </div>
                  <?php else: ?>
                    <?php $s = $it['data']; ?>
                    <div class="slot-chip">
                      <span class="badge <?= $s['is_booked'] ? 'done' : 'new' ?>"><?= e($it['time']) ?> · <?= $s['is_booked'] ? 'занято' : 'свободно' ?></span>
                      <form method="post" data-ajax-form>
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="action" value="toggle_slot">
                        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                        <button class="icon-btn icon-btn--sm" title="Переключить занято/свободно">⇄</button>
                      </form>
                      <form method="post" data-ajax-form data-confirm="Удалить это время?">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="action" value="delete_slot">
                        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                        <button class="icon-btn icon-btn--sm icon-btn--danger" title="Удалить">✕</button>
                      </form>
                    </div>
                  <?php endif; ?>
                <?php endforeach; ?>
              <?php endif; ?>
              <form method="post" class="slot-inline-add" data-ajax-form data-inline-add-form="<?= e($dateKey) ?>" hidden>
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="add_slot">
                <input type="hidden" name="slot_date" value="<?= e($dateKey) ?>">
                <input type="time" name="slot_time" required>
                <button type="submit" class="icon-btn icon-btn--sm" title="Подтвердить">✓</button>
                <button type="button" class="icon-btn icon-btn--sm" data-day-add-cancel title="Отмена">✕</button>
              </form>
            </div>
          </div>
        </div>
      </div>
    <?php endfor; ?>
  </div>
</div>
