<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/includes/auth_check.php';

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfCheck()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $period      = trim($_POST['period'] ?? '');
        $position    = trim($_POST['position'] ?? '');
        $positionUa  = trim($_POST['position_ua'] ?? '');
        $company     = trim($_POST['company'] ?? '');
        $companyUa   = trim($_POST['company_ua'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $descriptionUa = trim($_POST['description_ua'] ?? '');

        if ($period !== '' && $position !== '') {
            if ($action === 'add') {
                $maxOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM work_experience')->fetchColumn();
                $pdo->prepare('
                    INSERT INTO work_experience (period, position, position_ua, company, company_ua, description, description_ua, sort_order)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ')->execute([$period, $position, $positionUa ?: null, $company ?: null, $companyUa ?: null, $description ?: null, $descriptionUa ?: null, $maxOrder + 1]);
            } else {
                $id = (int)($_POST['id'] ?? 0);
                $pdo->prepare('
                    UPDATE work_experience SET period = ?, position = ?, position_ua = ?, company = ?, company_ua = ?, description = ?, description_ua = ?
                    WHERE id = ?
                ')->execute([$period, $position, $positionUa ?: null, $company ?: null, $companyUa ?: null, $description ?: null, $descriptionUa ?: null, $id]);
            }
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM work_experience WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
    }

    redirect('experience.php');
}

$editItem = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM work_experience WHERE id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $editItem = $stmt->fetch() ?: null;
}

$items = $pdo->query('SELECT * FROM work_experience ORDER BY sort_order, id DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Опыт работы — Панель управления</title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<script>window.ADMIN_CSRF_TOKEN = <?= json_encode(csrfToken()) ?>;</script>
</head>
<body>
<div class="admin-shell">
  <?php require __DIR__ . '/includes/nav.php'; ?>

  <div class="card">
    <h3><?= $editItem ? 'Изменить запись' : 'Добавить запись об опыте' ?></h3>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action" value="<?= $editItem ? 'edit' : 'add' ?>">
      <?php if ($editItem): ?><input type="hidden" name="id" value="<?= (int)$editItem['id'] ?>"><?php endif; ?>

      <div class="form-field">
        <label>Период (например «2022 — по наст. время»)</label>
        <input type="text" name="period" required maxlength="60" value="<?= e($editItem['period'] ?? '') ?>">
      </div>
      <div class="form-field">
        <label>Должность, рус.</label>
        <input type="text" id="exp_position" name="position" required maxlength="100" value="<?= e($editItem['position'] ?? '') ?>">
      </div>
      <div class="form-field">
        <label>Должность, укр. (необязательно)
          <button type="button" class="btn ghost admin-translate-btn" data-translate-from="exp_position" data-translate-to="exp_position_ua">⇄ Перевести с рус.</button>
        </label>
        <input type="text" id="exp_position_ua" name="position_ua" maxlength="100" value="<?= e($editItem['position_ua'] ?? '') ?>">
      </div>
      <div class="form-field">
        <label>Компания, рус. (необязательно)</label>
        <input type="text" id="exp_company" name="company" maxlength="100" value="<?= e($editItem['company'] ?? '') ?>">
      </div>
      <div class="form-field">
        <label>Компания, укр. (необязательно)
          <button type="button" class="btn ghost admin-translate-btn" data-translate-from="exp_company" data-translate-to="exp_company_ua">⇄ Перевести с рус.</button>
        </label>
        <input type="text" id="exp_company_ua" name="company_ua" maxlength="100" value="<?= e($editItem['company_ua'] ?? '') ?>">
      </div>
      <div class="form-field">
        <label>Описание, рус. (необязательно)</label>
        <textarea id="exp_description" name="description" maxlength="600"><?= e($editItem['description'] ?? '') ?></textarea>
      </div>
      <div class="form-field">
        <label>Описание, укр. (необязательно)
          <button type="button" class="btn ghost admin-translate-btn" data-translate-from="exp_description" data-translate-to="exp_description_ua">⇄ Перевести с рус.</button>
        </label>
        <textarea id="exp_description_ua" name="description_ua" maxlength="600"><?= e($editItem['description_ua'] ?? '') ?></textarea>
      </div>

      <button type="submit" class="btn full"><?= $editItem ? 'Сохранить' : 'Добавить' ?></button>
      <?php if ($editItem): ?>
        <a href="experience.php" class="btn ghost full" style="margin-top:8px; text-align:center;">Отменить</a>
      <?php endif; ?>
    </form>
  </div>

  <table class="admin-table">
    <thead><tr><th>Период</th><th>Должность</th><th>Компания</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($items as $item): ?>
        <tr>
          <td><?= e($item['period']) ?></td>
          <td><?= e($item['position']) ?></td>
          <td><?= e($item['company'] ?: '—') ?></td>
          <td style="white-space:nowrap;">
            <a href="?edit=<?= (int)$item['id'] ?>" class="btn ghost" style="padding:6px 12px;font-size:12px;">Изменить</a>
            <form method="post" style="display:inline;" onsubmit="return confirm('Удалить эту запись?');">
              <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
              <button class="btn ghost" style="padding:6px 12px;font-size:12px;">Удалить</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$items): ?><tr><td colspan="4">Пока нет записей об опыте работы.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<script src="assets/admin.js" defer></script>
</body>
</html>
