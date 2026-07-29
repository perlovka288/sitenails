<?php
/**
 * delete_own_review.php
 *
 * Владелец отзыва удаляет СВОЙ отзыв в течение 1-2 часов после публикации
 * (см. reviewOwnedByCurrentUser() в includes/functions.php). Это отдельно
 * от admin_quick_action.php — там удаляет только администратор без
 * ограничения по времени.
 */
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrfCheck()) {
    redirect('index.php');
}

$siteUser = currentSiteUser();
if (!$siteUser) {
    redirect('login.php');
}

$id = (int)($_POST['id'] ?? 0);
$pdo = getDB();

$stmt = $pdo->prepare('SELECT * FROM reviews WHERE id = ?');
$stmt->execute([$id]);
$review = $stmt->fetch();

if ($review && reviewOwnedByCurrentUser($review, $siteUser)) {
    foreach (reviewPhotoPaths($review['photo_path']) as $__photo) {
        $__file = __DIR__ . '/' . $__photo;
        if (is_file($__file)) {
            @unlink($__file);
        }
    }
    $pdo->prepare('DELETE FROM reviews WHERE id = ?')->execute([$id]);
}

redirect('index.php?tab=reviews');
