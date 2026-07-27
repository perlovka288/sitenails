<?php $current = basename($_SERVER['SCRIPT_NAME']); ?>
<div class="admin-header">
  <h2 style="margin:0;">Панель управления</h2>
  <a href="logout.php" class="btn ghost">Выйти</a>
</div>
<div class="admin-nav">
  <a href="dashboard.php" class="<?= $current === 'dashboard.php' ? 'active' : '' ?>">Главная</a>
  <a href="about.php" class="<?= $current === 'about.php' ? 'active' : '' ?>">О мне</a>
  <a href="experience.php" class="<?= $current === 'experience.php' ? 'active' : '' ?>">Опыт работы</a>
  <a href="widgets.php" class="<?= in_array($current, ['widgets.php', 'widget_items.php'], true) ? 'active' : '' ?>">Виджеты</a>
  <a href="social.php" class="<?= $current === 'social.php' ? 'active' : '' ?>">Соцсети</a>
  <a href="reviews.php" class="<?= $current === 'reviews.php' ? 'active' : '' ?>">Отзывы</a>
  <a href="prices.php" class="<?= $current === 'prices.php' ? 'active' : '' ?>">Прайс</a>
  <a href="slots.php" class="<?= $current === 'slots.php' ? 'active' : '' ?>">Свободное время</a>
  <a href="bookings.php" class="<?= $current === 'bookings.php' ? 'active' : '' ?>">Записи</a>
  <a href="settings.php" class="<?= $current === 'settings.php' ? 'active' : '' ?>">Настройки</a>
  <a href="../index.php" target="_blank">Открыть сайт ↗</a>
</div>
