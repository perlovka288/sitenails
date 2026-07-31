<?php
// Вкладки "Записи" и "Свободное время" объединены в одну — "Запись"
// (см. slots.php). Этот файл оставлен только для обратной совместимости
// со старыми сохранёнными ссылками/закладками и просто перенаправляет туда.
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/includes/auth_check.php';
redirect('slots.php');
