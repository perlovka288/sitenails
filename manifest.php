<?php
// manifest.php
//
// Веб-манифест приложения. Без него iOS Safari НЕ даёт Web Push вообще —
// значок "На экран Домой" без манифеста с display:standalone это просто
// закладка на вкладку Safari, а не установленное веб-приложение, и
// Notification/PushManager там недоступны в принципе (это ограничение
// Apple, не баг сайта). Файл сделан .php (а не статический .json), чтобы
// название бралось из настроек сайта (админка → Настройки).
//
// ВАЖНО: путь к самому manifest.php прописывается в includes/header.php
// через <link rel="manifest" href="manifest.php">.

require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';

header('Content-Type: application/manifest+json; charset=utf-8');

$siteName = getSetting('site_name', '');
$title = $siteName !== '' ? $siteName : 'Мастер маникюра';

echo json_encode([
    'name'             => $title,
    'short_name'       => mb_substr($title, 0, 12),
    'start_url'        => './index.php',
    'scope'            => './',
    // display=standalone — обязательное требование Apple для Web Push.
    'display'          => 'standalone',
    'background_color' => '#12121a',
    'theme_color'      => '#12121a',
    'icons' => [
        [
            'src'     => 'assets/img/social/nails.png',
            'sizes'   => '1000x1000',
            'type'    => 'image/png',
            'purpose' => 'any',
        ],
        [
            'src'     => 'assets/img/social/nails.png',
            'sizes'   => '1000x1000',
            'type'    => 'image/png',
            'purpose' => 'maskable',
        ],
    ],
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
