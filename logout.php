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

redirect('login.php');
