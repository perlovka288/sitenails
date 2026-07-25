<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e(SITE_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header class="topbar">
  <div class="container topbar-row">
    <div class="brand"><?= e(SITE_NAME) ?></div>
    <div class="lang-switch">
      <a href="?lang=ru" class="<?= ($_GET['lang'] ?? 'ru') === 'ru' ? 'active' : '' ?>">РУС</a>
      <a href="?lang=ua" class="<?= ($_GET['lang'] ?? 'ru') === 'ua' ? 'active' : '' ?>">УКР</a>
    </div>
  </div>
  <div class="container nav-tabs">
    <button type="button" class="tab-btn" data-tab="reviews">Отзывы</button>
    <button type="button" class="tab-btn" data-tab="price">Прайс</button>
    <button type="button" class="tab-btn" data-tab="booking">Запись</button>
  </div>
</header>

<!-- Модальное окно приветствия -->
<div class="greet-overlay" id="greetOverlay" style="display:none;">
  <div class="greet-modal">
    <h3>Как к вам обращаться?</h3>
    <p>Чтобы мы могли обратиться к вам по имени 🙂</p>
    <form id="greetForm">
      <div class="form-field">
        <input type="text" id="greetInput" placeholder="Например, Мия" required>
      </div>
      <button type="submit" class="btn full">Продолжить</button>
    </form>
    <button type="button" id="greetSkip" class="btn ghost full" style="margin-top:8px;">Пропустить</button>
  </div>
</div>
