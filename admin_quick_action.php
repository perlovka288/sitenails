<?php
/**
 * admin_quick_action.php
 *
 * Обрабатывает быстрые действия администратора (мамы), которые она делает
 * ПРЯМО на основном сайте (не заходя в отдельную панель /admin-x7k9m2/):
 *   - review_delete   — удалить отзыв
 *   - price_add       — добавить позицию в прайс
 *   - price_delete    — удалить позицию прайса
 *   - price_import    — заменить весь прайс на список с фото (config.php)
 *
 * Кнопки, которые сюда отправляют запросы, показываются на index.php
 * только если isAdmin() === true (то есть мама уже вошла в /admin-x7k9m2/login.php
 * в этом же браузере — сессия у сайта и у панели общая).
 */

require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';

// Если это не администратор — никаких действий, просто на главную.
if (!isAdmin()) {
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrfCheck()) {
    redirect('index.php');
}

$pdo = getDB();
$action = $_POST['action'] ?? '';
$tab = $_POST['back_tab'] ?? 'reviews';

switch ($action) {
    case 'review_delete':
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM reviews WHERE id = ?')->execute([$id]);
        break;

    case 'price_add':
        $category = trim($_POST['category'] ?? '');
        $title    = trim($_POST['title'] ?? '');
        $price    = trim($_POST['price'] ?? '');
        if ($category !== '' && $title !== '' && $price !== '') {
            $maxOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM price_items')->fetchColumn();
            $pdo->prepare('INSERT INTO price_items (category, title, price, sort_order) VALUES (?, ?, ?, ?)')
                ->execute([$category, $title, $price, $maxOrder + 1]);
        }
        break;

    case 'price_delete':
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM price_items WHERE id = ?')->execute([$id]);
        break;

    case 'price_import':
        // Полностью заменяет текущий прайс на список из config.php (defaultPriceList()).
        $pdo->exec('DELETE FROM price_items');
        $stmt = $pdo->prepare('INSERT INTO price_items (category, title, price, sort_order) VALUES (?, ?, ?, ?)');
        foreach (defaultPriceList() as $i => $item) {
            $stmt->execute([$item['category'], $item['title'], $item['price'], $i]);
        }
        break;
}

redirect('index.php?tab=' . urlencode($tab));
