<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/includes/auth_check.php';

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfCheck()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_category') {
        // Кнопка "+ Добавить категорию" — здесь создаётся ТОЛЬКО категория,
        // без единой услуги внутри неё. Услуги добавляются отдельно, кнопкой
        // "+" на карточке уже созданной категории.
        $name   = trim($_POST['name'] ?? '');
        $nameUa = trim($_POST['name_ua'] ?? '');
        if ($name !== '') {
            $maxOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM price_categories')->fetchColumn();
            $pdo->prepare('INSERT INTO price_categories (name, name_ua, sort_order) VALUES (?, ?, ?)')
                ->execute([$name, $nameUa ?: null, $maxOrder + 1]);
        }
        redirect('prices.php');
    } elseif ($action === 'edit_category') {
        $id     = (int)($_POST['id'] ?? 0);
        $name   = trim($_POST['name'] ?? '');
        $nameUa = trim($_POST['name_ua'] ?? '');
        if ($id > 0 && $name !== '') {
            $stmt = $pdo->prepare('SELECT name FROM price_categories WHERE id = ?');
            $stmt->execute([$id]);
            $old = $stmt->fetch();
            if ($old) {
                $pdo->prepare('UPDATE price_categories SET name = ?, name_ua = ? WHERE id = ?')
                    ->execute([$name, $nameUa ?: null, $id]);
                // Если название категории изменилось — переносим и уже
                // существующие услуги внутри неё на новое название, чтобы
                // они не "отвязались" от своей категории.
                if ($old['name'] !== $name) {
                    $pdo->prepare('UPDATE price_items SET category = ?, category_ua = ? WHERE category = ?')
                        ->execute([$name, $nameUa ?: null, $old['name']]);
                }
            }
        }
        redirect('prices.php');
    } elseif ($action === 'delete_category') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT name FROM price_categories WHERE id = ?');
        $stmt->execute([$id]);
        $cat = $stmt->fetch();
        if ($cat) {
            // Удаляем категорию вместе со всеми услугами внутри неё.
            $pdo->prepare('DELETE FROM price_items WHERE category = ?')->execute([$cat['name']]);
            $pdo->prepare('DELETE FROM price_categories WHERE id = ?')->execute([$id]);
        }
        redirect('prices.php');
    } elseif ($action === 'add') {
        $category    = trim($_POST['category'] ?? '');
        $categoryUa  = trim($_POST['category_ua'] ?? '');
        $title       = trim($_POST['title'] ?? '');
        $titleUa     = trim($_POST['title_ua'] ?? '');
        $price       = trim($_POST['price'] ?? '');
        if ($category !== '' && $title !== '' && $price !== '') {
            $maxOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM price_items')->fetchColumn();
            $pdo->prepare('INSERT INTO price_items (category, category_ua, title, title_ua, price, sort_order) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$category, $categoryUa ?: null, $title, $titleUa ?: null, $price, $maxOrder + 1]);
        }
        redirect('prices.php');
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

$editCategory = null;
if (isset($_GET['edit_category'])) {
    $stmt = $pdo->prepare('SELECT * FROM price_categories WHERE id = ?');
    $stmt->execute([(int)$_GET['edit_category']]);
    $editCategory = $stmt->fetch() ?: null;
}

// Категории — отдельная таблица, поэтому пустая (только что созданная)
// категория тоже показывается в списке, даже если в ней ещё нет ни одной услуги.
$categories = $pdo->query('SELECT * FROM price_categories ORDER BY sort_order, id')->fetchAll();

$items = $pdo->query('SELECT * FROM price_items ORDER BY category, sort_order')->fetchAll();

// Группируем позиции по категории для карточек внутри аккордеона.
$byCategory = [];
foreach ($items as $__it) {
    $byCategory[$__it['category']][] = $__it;
}

$knownCategoryNames = array_column($categories, 'name');
$categoryIcons = ['Маникюр' => '💅', 'Педикюр' => '🦶', 'Дополнительно' => '✨', 'Наращивание / Коррекция' => '💎'];
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

  <button type="button" class="btn full" id="priceAddCategoryBtn">+ Добавить категорию</button>

  <div class="about-accordion" style="margin-top:18px;">
    <?php foreach ($categories as $cat): ?>
      <?php
        $catId    = (int)$cat['id'];
        $catName  = $cat['name'];
        $catItems = $byCategory[$catName] ?? [];
      ?>
      <div class="about-accordion-item price-accordion-item">
        <div class="about-accordion-header" tabindex="0" role="button">
          <div class="about-accordion-header-text">
            <h3><?= e($categoryIcons[$catName] ?? '🏷️') ?> <?= e($catName) ?><?= $cat['name_ua'] ? ' <span style="color:var(--ink-faint); font-weight:400; font-size:13px;">/ ' . e($cat['name_ua']) . '</span>' : '' ?></h3>
          </div>
          <div class="about-accordion-header-right">
            <span class="price-cat-actions" onclick="event.stopPropagation();">
              <button type="button" class="icon-btn icon-btn--sm"
                data-cat-edit
                data-id="<?= $catId ?>"
                data-name="<?= e($cat['name']) ?>"
                data-name-ua="<?= e($cat['name_ua'] ?? '') ?>"
                title="Изменить категорию">✏️</button>
              <form method="post" onsubmit="return confirm('Удалить категорию вместе со всеми услугами внутри неё?');" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="delete_category">
                <input type="hidden" name="id" value="<?= $catId ?>">
                <button type="submit" class="icon-btn icon-btn--sm" title="Удалить категорию">🗑️</button>
              </form>
            </span>
            <span class="about-accordion-count"><?= count($catItems) ?></span>
            <span class="about-accordion-chevron">›</span>
          </div>
        </div>
        <div class="about-accordion-body">
          <div class="about-accordion-body-inner">
            <div class="about-accordion-content">
              <?php if ($catItems): ?>
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
              <?php else: ?>
                <p style="color:var(--ink-soft); font-size:13px; margin-top:0;">Пока нет ни одной услуги в этой категории.</p>
              <?php endif; ?>

              <button type="button" class="btn ghost full admin-add-tile-btn" style="margin-top:12px;"
                data-price-add-item
                data-category="<?= e($catName) ?>">
                <span class="admin-plus-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg></span>
                <span>Добавить услугу</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$categories): ?>
      <p class="rec-empty">Пока нет ни одной категории — нажмите «+ Добавить категорию» выше, чтобы создать первую (например «Маникюр»).</p>
    <?php endif; ?>
  </div>

  <!-- Модалка добавления/редактирования КАТЕГОРИИ -->
  <div class="modal-overlay<?= $editCategory ? ' open' : '' ?>" id="priceCategoryModal">
    <div class="modal-box">
      <h3 id="priceCategoryModalTitle"><?= $editCategory ? 'Изменить категорию' : 'Новая категория' ?></h3>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="action" id="priceCategoryAction" value="<?= $editCategory ? 'edit_category' : 'add_category' ?>">
        <input type="hidden" name="id" id="priceCategoryId" value="<?= (int)($editCategory['id'] ?? 0) ?>">

        <div class="form-field">
          <label>Название категории, рус.</label>
          <input type="text" id="price_cat_name" name="name" required maxlength="60" value="<?= e($editCategory['name'] ?? '') ?>">
        </div>
        <div class="form-field">
          <label>Название категории, укр. (необязательно)
            <button type="button" class="btn ghost admin-translate-btn" data-translate-from="price_cat_name" data-translate-to="price_cat_name_ua">⇄ Перевести с рус.</button>
          </label>
          <input type="text" id="price_cat_name_ua" name="name_ua" maxlength="60" value="<?= e($editCategory['name_ua'] ?? '') ?>">
        </div>
        <button type="submit" class="btn full" id="priceCategorySubmitBtn"><?= $editCategory ? 'Сохранить' : 'Создать категорию' ?></button>
        <button type="button" class="btn ghost full" style="margin-top:8px;" data-modal-close>Отменить</button>
      </form>
    </div>
  </div>

  <!-- Модалка добавления/редактирования УСЛУГИ -->
  <div class="modal-overlay<?= $editItem ? ' open' : '' ?>" id="priceItemModal">
    <div class="modal-box">
      <h3 id="priceItemModalTitle"><?= $editItem ? 'Изменить услугу' : 'Новая услуга' ?></h3>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="action" id="priceItemAction" value="<?= $editItem ? 'edit' : 'add' ?>">
        <input type="hidden" name="id" id="priceItemId" value="<?= (int)($editItem['id'] ?? 0) ?>">

        <div class="form-field">
          <label>Категория</label>
          <select id="priceItemCategorySelect" name="category">
            <?php foreach ($knownCategoryNames as $cat): ?>
              <option value="<?= e($cat) ?>" <?= (($editItem['category'] ?? '') === $cat) ? 'selected' : '' ?>><?= e($cat) ?></option>
            <?php endforeach; ?>
          </select>
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
       "⇄" могла найти текст выбранной в выпадающем списке категории. -->
  <input type="hidden" id="priceItemCategoryText">
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  // ===== Модалка категории (создать / изменить) =====
  var catModal = document.getElementById('priceCategoryModal');
  var catModalTitle = document.getElementById('priceCategoryModalTitle');
  var catAction = document.getElementById('priceCategoryAction');
  var catIdField = document.getElementById('priceCategoryId');
  var catNameField = document.getElementById('price_cat_name');
  var catNameUaField = document.getElementById('price_cat_name_ua');
  var catSubmitBtn = document.getElementById('priceCategorySubmitBtn');

  document.getElementById('priceAddCategoryBtn').addEventListener('click', function () {
    catModalTitle.textContent = 'Новая категория';
    catAction.value = 'add_category';
    catIdField.value = '';
    catNameField.value = '';
    catNameUaField.value = '';
    catSubmitBtn.textContent = 'Создать категорию';
    catModal.classList.add('open');
  });

  document.querySelectorAll('[data-cat-edit]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      catModalTitle.textContent = 'Изменить категорию';
      catAction.value = 'edit_category';
      catIdField.value = btn.dataset.id;
      catNameField.value = btn.dataset.name || '';
      catNameUaField.value = btn.dataset.nameUa || '';
      catSubmitBtn.textContent = 'Сохранить';
      catModal.classList.add('open');
    });
  });

  // ===== Модалка услуги (создать в конкретной категории / изменить) =====
  var catSelect = document.getElementById('priceItemCategorySelect');
  var catText = document.getElementById('priceItemCategoryText');

  function syncCategoryText() {
    catText.value = catSelect.value;
  }
  catSelect.addEventListener('change', syncCategoryText);

  var modal = document.getElementById('priceItemModal');
  var modalTitle = document.getElementById('priceItemModalTitle');
  var actionField = document.getElementById('priceItemAction');
  var idField = document.getElementById('priceItemId');
  var categoryUaField = document.getElementById('category_ua');
  var titleField = document.getElementById('price_title');
  var titleUaField = document.getElementById('price_title_ua');
  var priceField = document.getElementById('priceItemPrice');
  var submitBtn = document.getElementById('priceItemSubmitBtn');

  // Кнопка "+" на карточке категории — открывает модалку услуги с уже
  // выбранной этой категорией (её всё ещё можно сменить в самой форме).
  document.querySelectorAll('[data-price-add-item]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      modalTitle.textContent = 'Новая услуга';
      actionField.value = 'add';
      idField.value = '';
      if (btn.dataset.category) {
        catSelect.value = btn.dataset.category;
      }
      categoryUaField.value = '';
      titleField.value = '';
      titleUaField.value = '';
      priceField.value = '';
      submitBtn.textContent = 'Добавить';
      syncCategoryText();
      modal.classList.add('open');
    });
  });

  document.querySelectorAll('[data-price-edit]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      modalTitle.textContent = 'Изменить услугу';
      actionField.value = 'edit';
      idField.value = btn.dataset.id;
      catSelect.value = btn.dataset.category;
      categoryUaField.value = btn.dataset.categoryUa || '';
      titleField.value = btn.dataset.title || '';
      titleUaField.value = btn.dataset.titleUa || '';
      priceField.value = btn.dataset.price || '';
      submitBtn.textContent = 'Сохранить';
      syncCategoryText();
      modal.classList.add('open');
    });
  });

  syncCategoryText();
  <?php if ($editItem): ?>
  catText.value = <?= json_encode($editItem['category']) ?>;
  <?php endif; ?>
});
</script>
</body>
</html>
