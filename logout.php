<?php
/**
 * logout.php — выход клиента из аккаунта. Трогает только
 * $_SESSION['site_user_id'] / клиентскую куку, не задевает admin_id
 * (если мама в этом же браузере залогинена и в панель управления).
 */
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';

clearSiteRememberCookie();
unset($_SESSION['site_user_id']);

// Если в этом же браузере ещё открыта панель управления (admin_id) —
// без этого флага currentSiteUser() тут же авто-залогинил бы обратно как
// клиента на следующей же странице (см. includes/functions.php). Флаг
// снимается при обычном входе/регистрации клиента (login.php, register.php).
$_SESSION['site_logged_out'] = true;

redirect('login.php');
