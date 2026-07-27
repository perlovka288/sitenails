<?php
// Раздел «Виджеты» объединён с вкладкой «О мне» — категории и файлы
// теперь находятся там, в виде сворачиваемой категории аккордеона.
// Страница оставлена как редирект, чтобы старые ссылки/закладки не ломались.
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/includes/auth_check.php';
redirect('about.php#about-acc-widgets');
