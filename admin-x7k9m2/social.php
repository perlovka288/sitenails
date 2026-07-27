<?php
// Раздел «Соцсети» объединён с вкладкой «О мне» — форма и список ссылок
// теперь находятся там, в виде сворачиваемой категории аккордеона.
// Страница оставлена как редирект, чтобы старые ссылки/закладки не ломались.
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/includes/auth_check.php';
redirect('about.php#about-acc-social');
