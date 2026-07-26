<?php
/**
 * admin_quick_action.php
 *
 * Обрабатывает быстрые действия администратора (мамы), которые она делает
 * ПРЯМО на основном сайте (не заходя в отдельную панель /admin-x7k9m2/):
 *
 *   - review_toggle   — опубликовать / скрыть отзыв
 *   - review_delete   — удалить отзыв
 *   - price_add       — добавить позицию в прайс
 *   - price_edit      — изменить позицию прайса
 *   - price_delete    — удалить позицию прайса
 *   - slot_add        — добавить свободное время
 *   - slot_edit        — изменить дату/время/статус слота
 *   - slot_delete      — удалить слот из календаря
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

    // ===== Отзывы =====
    case 'review_toggle':
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE reviews SET is_approved = 1 - is_approved WHERE id = ?')->execute([$id]);
        break;

    case 'review_delete':
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM reviews WHERE id = ?')->execute([$id]);
        break;

    // ===== Прайс =====
    case 'price_add':
        $category   = trim($_POST['category'] ?? '');
        $categoryUa = trim($_POST['category_ua'] ?? '');
        $title      = trim($_POST['title'] ?? '');
        $titleUa    = trim($_POST['title_ua'] ?? '');
        $price      = trim($_POST['price'] ?? '');
        if ($category !== '' && $title !== '' && $price !== '') {
            $maxOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM price_items')->fetchColumn();
            $pdo->prepare('INSERT INTO price_items (category, category_ua, title, title_ua, price, sort_order) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$category, $categoryUa ?: null, $title, $titleUa ?: null, $price, $maxOrder + 1]);
        }
        break;

    case 'price_edit':
        $id         = (int)($_POST['id'] ?? 0);
        $category   = trim($_POST['category'] ?? '');
        $categoryUa = trim($_POST['category_ua'] ?? '');
        $title      = trim($_POST['title'] ?? '');
        $titleUa    = trim($_POST['title_ua'] ?? '');
        $price      = trim($_POST['price'] ?? '');
        if ($id > 0 && $category !== '' && $title !== '' && $price !== '') {
            $pdo->prepare('UPDATE price_items SET category = ?, category_ua = ?, title = ?, title_ua = ?, price = ? WHERE id = ?')
                ->execute([$category, $categoryUa ?: null, $title, $titleUa ?: null, $price, $id]);
        }
        break;

    case 'price_delete':
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM price_items WHERE id = ?')->execute([$id]);
        break;

    // ===== Свободное время (слоты) =====
    case 'slot_add':
        $date = trim($_POST['slot_date'] ?? '');
        $time = trim($_POST['slot_time'] ?? '');
        if ($date !== '' && $time !== '') {
            $exists = $pdo->prepare('SELECT COUNT(*) FROM available_slots WHERE slot_date = ? AND slot_time = ?');
            $exists->execute([$date, $time]);
            if ((int)$exists->fetchColumn() === 0) {
                $pdo->prepare('INSERT INTO available_slots (slot_date, slot_time, is_booked) VALUES (?, ?, ?)')
                    ->execute([$date, $time, !empty($_POST['is_booked']) ? 1 : 0]);
            }
        }
        break;

    case 'slot_edit':
        $id   = (int)($_POST['id'] ?? 0);
        $date = trim($_POST['slot_date'] ?? '');
        $time = trim($_POST['slot_time'] ?? '');
        $isBooked = !empty($_POST['is_booked']) ? 1 : 0;
        if ($id > 0 && $date !== '' && $time !== '') {
            $pdo->prepare('UPDATE available_slots SET slot_date = ?, slot_time = ?, is_booked = ? WHERE id = ?')
                ->execute([$date, $time, $isBooked, $id]);
        }
        break;

    case 'slot_delete':
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM available_slots WHERE id = ?')->execute([$id]);
        break;
}

redirect('index.php?tab=' . urlencode($tab));
