<?php
// admin-x7k9m2/login.php
// Отдельный вход в панель управления больше не нужен: доступ в
// admin-x7k9m2 теперь даёт обычный вход на сайте (site_users) с флагом
// "администратор" (Настройки → «Администраторы», см. isAdmin() и
// siteUserHasAdminFlag() в includes/functions.php). Файл оставлен только
// для обратной совместимости со старыми сохранёнными ссылками/закладками
// и просто перенаправляет на обычную страницу входа.
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/functions.php';

if (isAdmin()) {
    redirect('dashboard.php');
}

redirect('../login.php');
