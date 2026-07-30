<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/includes/auth_check.php';

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfCheck()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $category    = trim($_POST['category'] ?? '');
        $categoryUa  = trim($_POST['category_ua'] ?? '');
        $title       = trim($_POST['title'] ?? '');
        $titleUa     = trim($_POST['title_ua'] ?? '');
        $price       = trim($_POST['price'] ?? '');
        if ($category !== '' && $title !== '' && $price !== '') {
            $pdo->prepare('INSERT INTO price_items (category, category_ua, title, title_ua, price, sort_order) VALUES (?, ?, ?, ?, ?, 0)')
                ->execute([$category, $categoryUa ?: null, $title, $titleUa ?: null, $price]);
        }
    } elseif ($action === 'edit') {
        $id          = (int)($_POST['id'] ?? 0);
        $category    = trim($_POST['category'] ?? '');
        $categoryUa  = trim($_POST['category_ua'] ?? '');
        $title       = trim($_POST['title'] ?? '');
        $titleUa     = trim($_POST['title_ua'] ?? '');
        $price       = trim($_POST['price'] ?? '');
        if ($id > 0 && $category !== '' && $title !== '' && $price !== '') {
            $pdo->prepare('UPDATE price_items SET category = ?, category_ua = ?, title = ?, title_ua = ?, price = ? WHERE id = ?')
                ->execute([$category, $categoryUa ?: null, $title, $titleUa ?: null, $price, $id]);
        }
        redirect('prices.php');
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM price_items WHERE id = ?')->execute([$id]);
    }

    redirect('prices.php');
}

$editItem = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM price_items WHERE id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $editItem = $stmt->fetch() ?: null;
}

$items = $pdo->query('SELECT * FROM price_items ORDER BY category, sort_order')->fetchAll();

// Группируем позиции по категории для аккордеона на карточках
// (вкладки «Маникюр» / «Педикюр» / «Дополнительно» и т.д. — состав вкладок
// определяется тем, какие категории реально есть в прайсе).
$byCategory = [];
foreach ($items as $__it) {
    $byCategory[$__it['category']][] = $__it;
}
$categoryOrder = ['Маникюр', 'Педикюр', 'Дополнительно'];
uksort($byCategory, function ($a, $b) use ($categoryOrder) {
    $ia = array_search($a, $categoryOrder, true);
    $ib = array_search($b, $categoryOrder, true);
    if ($ia === false) $ia = 999;
    if ($ib === false) $ib = 999;
    if ($ia === $ib) return strcmp($a, $b);
    return $ia <=> $ib;
});
$knownCategories = array_values(array_unique(array_merge($categoryOrder, array_keys($byCategory))));
$categoryIcons = ['Маникюр' => '💅', 'Педикюр' => '🦶', 'Дополнительно' => '✨'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Прайс — Панель управления</title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
<script>window.ADMIN_CSRF_TOKEN = <?= json_encode(csrfToken()) ?>;</script>
<script src="assets/admin.js?v=<?= filemtime(__DIR__ . '/assets/admin.js') ?>" defer></script>
</head>
<body>
<div class="admin-shell">
  <?php require __DIR__ . '/includes/nav.php'; ?>

  <button type="button" class="btn full" data-modal-open="priceItemModal">+ Добавить услугу</button>

  <div class="about-accordion" style="margin-top:18px;">
    <?php foreach ($byCategory as $catName => $catItems): ?>
      <div class="about-accordion-item price-accordion-item">
        <div class="about-accordion-header" tabindex="0" role="button">
          <div class="about-accordion-header-text">
            <h3><?= e($categoryIcons[$catName] ?? '🏷️') ?> <?= e($catName) ?></h3>
          </div>
          <div class="about-accordion-header-right">
            <span class="about-accordion-count"><?= count($catItems) ?></span>
            <span class="about-accordion-chevron">›</span>
          </div>
        </div>
        <div class="about-accordion-body">
          <div class="about-accordion-body-inner">
            <div class="about-accordion-content">
              <div class="price-service-list">
                <?php foreach ($catItems as $item): ?>
                  <div class="price-service-card">
                    <div class="price-service-card-info">
                      <strong><?= e($item['title']) ?></strong>
                      <?php if ($item['title_ua']): ?>
                        <span class="price-service-card-ua"><?= e($item['title_ua']) ?></span>
                      <?php endif; ?>
                    </div>
                    <div class="price-service-card-price"><?= e($item['price']) ?></div>
                    <div class="price-service-card-actions">
                      <button type="button" class="icon-btn"
                        data-price-edit
                        data-id="<?= (int)$item['id'] ?>"
                        data-category="<?= e($item['category']) ?>"
                        data-category-ua="<?= e($item['category_ua'] ?? '') ?>"
                        data-title="<?= e($item['title']) ?>"
                        data-title-ua="<?= e($item['title_ua'] ?? '') ?>"
                        data-price="<?= e($item['price']) ?>"
                        title="Изменить">✏️</button>
                      <form method="post" onsubmit="return confirm('Удалить позицию?');">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                        <button type="submit" class="icon-btn" title="Удалить">🗑️</button>
                      </form>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$items): ?>
      <p class="rec-empty">Прайс пуст.</p>
    <?php endif; ?>
  </div>

  <!-- Модалка добавления/редактирования услуги -->
  <div class="modal-overlay<?= $editItem ? ' open' : '' ?>" id="priceItemModal">
    <div class="modal-box">
      <h3 id="priceItemModalTitle"><?= $editItem ? 'Изменить услугу' : 'Новая услуга' ?></h3>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="action" id="priceItemAction" value="<?= $editItem ? 'edit' : 'add' ?>">
        <input type="hidden" name="id" id="priceItemId" value="<?= (int)($editItem['id'] ?? 0) ?>">

        <div class="form-field">
          <label>Категория</label>
          <select id="priceItemCategorySelect">
            <?php foreach ($knownCategories as $cat): ?>
              <option value="<?= e($cat) ?>" <?= (($editItem['category'] ?? $knownCategories[0]) === $cat) ? 'selected' : '' ?>><?= e($cat) ?></option>
            <?php endforeach; ?>
            <option value="__custom__">Своя категория…</option>
          </select>
          <input type="text" id="priceItemCategoryCustom" name="category" style="margin-top:8px; display:none;" placeholder="Название категории">
        </div>
        <div class="form-field">
          <label>Категория, укр. (необязательно)
            <button type="button" class="btn ghost admin-translate-btn" data-translate-from="priceItemCategoryText" data-translate-to="category_ua">⇄ Перевести с рус.</button>
          </label>
          <input type="text" id="category_ua" name="category_ua" value="<?= e($editItem['category_ua'] ?? '') ?>">
        </div>
        <div class="form-field">
          <label>Название услуги, рус.</label>
          <input type="text" id="price_title" name="title" required value="<?= e($editItem['title'] ?? '') ?>">
        </div>
        <div class="form-field">
          <label>Название услуги, укр. (необязательно)
            <button type="button" class="btn ghost admin-translate-btn" data-translate-from="price_title" data-translate-to="price_title_ua">⇄ Перевести с рус.</button>
          </label>
          <input type="text" id="price_title_ua" name="title_ua" value="<?= e($editItem['title_ua'] ?? '') ?>">
        </div>
        <div class="form-field">
          <label>Цена (например, «450 грн»)</label>
          <input type="text" name="price" id="priceItemPrice" required value="<?= e($editItem['price'] ?? '') ?>">
        </div>
        <button type="submit" class="btn full" id="priceItemSubmitBtn"><?= $editItem ? 'Сохранить' : 'Добавить' ?></button>
        <button type="button" class="btn ghost full" style="margin-top:8px;" data-modal-close>Отменить</button>
      </form>
    </div>
  </div>

  <!-- Скрытое поле-дублёр названия категории (рус.), чтобы кнопка перевода
       "⇄" могла найти текст независимо от того, выбрана категория в
       выпадающем списке или введена вручную. -->
  <input type="hidden" id="priceItemCategoryText">
</div>
<script>window.ADMIN_CSRF_TOKEN = <?= json_encode(csrfToken()) ?>;</script>
<script src="assets/admin.js?v=<?= filemtime(__DIR__ . '/assets/admin.js') ?>" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var catSelect = document.getElementById('priceItemCategorySelect');
  var catCustom = document.getElementById('priceItemCategoryCustom');
  var catText = document.getElementById('priceItemCategoryText');

  function syncCategory() {
    if (catSelect.value === '__custom__') {
      catCustom.style.display = '';
      catCustom.name = 'category';
      catText.value = catCustom.value;
    } else {
      catCustom.style.display = 'none';
      catCustom.removeAttribute('name');
      catText.value = catSelect.value;
    }
  }
  catSelect.addEventListener('change', syncCategory);
  catCustom.addEventListener('input', function () { catText.value = catCustom.value; });

  var modal = document.getElementById('priceItemModal');
  var modalTitle = document.getElementById('priceItemModalTitle');
  var actionField = document.getElementById('priceItemAction');
  var idField = document.getElementById('priceItemId');
  var categoryUaField = document.getElementById('category_ua');
  var titleField = document.getElementById('price_title');
  var titleUaField = document.getElementById('price_title_ua');
  var priceField = document.getElementById('priceItemPrice');
  var submitBtn = document.getElementById('priceItemSubmitBtn');

  document.querySelectorAll('[data-modal-open="priceItemModal"]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      modalTitle.textContent = 'Новая услуга';
      actionField.value = 'add';
      idField.value = '';
      catSelect.value = catSelect.options[0].value;
      catCustom.value = '';
      categoryUaField.value = '';
      titleField.value = '';
      titleUaField.value = '';
      priceField.value = '';
      submitBtn.textContent = 'Добавить';
      syncCategory();
    });
  });

  document.querySelectorAll('[data-price-edit]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      modalTitle.textContent = 'Изменить услугу';
      actionField.value = 'edit';
      idField.value = btn.dataset.id;
      var hasOption = Array.prototype.some.call(catSelect.options, function (o) { return o.value === btn.dataset.category; });
      if (hasOption) {
        catSelect.value = btn.dataset.category;
        catCustom.value = '';
      } else {
        catSelect.value = '__custom__';
        catCustom.value = btn.dataset.category;
      }
      categoryUaField.value = btn.dataset.categoryUa || '';
      titleField.value = btn.dataset.title || '';
      titleUaField.value = btn.dataset.titleUa || '';
      priceField.value = btn.dataset.price || '';
      submitBtn.textContent = 'Сохранить';
      syncCategory();
      modal.classList.add('open');
    });
  });

  syncCategory();
  <?php if ($editItem): ?>
  catText.value = <?= json_encode($editItem['category']) ?>;
  <?php endif; ?>
});
</script>
</body>
</html>
