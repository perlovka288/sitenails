<?php
$current = basename($_SERVER['SCRIPT_NAME']);
$__aboutPages = ['about.php', 'experience.php', 'widgets.php', 'widget_items.php', 'social.php'];
$__clientsBookingPages = ['slots.php', 'bookings.php'];
?>
<div class="admin-nav-return">
  <a href="../index.php">← Вернуться на сайт</a>
</div>

<div class="admin-nav-groups">
  <div class="admin-nav-group">
    <div class="admin-nav-group-label">Статистика</div>
    <div class="admin-nav">
      <span class="admin-nav-indicator" aria-hidden="true"></span>
      <a href="dashboard.php" class="<?= $current === 'dashboard.php' ? 'active' : '' ?>">Главная</a>
    </div>
  </div>

  <div class="admin-nav-group">
    <div class="admin-nav-group-label">Кастомизация сайта</div>
    <div class="admin-nav">
      <span class="admin-nav-indicator" aria-hidden="true"></span>
      <a href="settings.php" class="<?= $current === 'settings.php' ? 'active' : '' ?>">Настройки</a>
      <a href="about.php" class="<?= in_array($current, $__aboutPages, true) ? 'active' : '' ?>">О мне</a>
      <a href="reviews.php" class="<?= $current === 'reviews.php' ? 'active' : '' ?>">Отзывы</a>
    </div>
  </div>

  <div class="admin-nav-group">
    <div class="admin-nav-group-label">Клиенты</div>
    <div class="admin-nav">
      <span class="admin-nav-indicator" aria-hidden="true"></span>
      <a href="prices.php" class="<?= $current === 'prices.php' ? 'active' : '' ?>">Прайс</a>
      <a href="slots.php" class="<?= in_array($current, $__clientsBookingPages, true) ? 'active' : '' ?>">Запись</a>
    </div>
  </div>
</div>
