<?php
/**
 * includes/lang.php
 * Простой переключатель языка сайта (РУС / УКР).
 * Использование: t('ключ') — выводит нужную строку в текущем языке.
 */

function currentLang(): string
{
    if (isset($_GET['lang']) && in_array($_GET['lang'], ['ru', 'ua'], true)) {
        $_SESSION['lang'] = $_GET['lang'];
    }
    return $_SESSION['lang'] ?? 'ru';
}

$GLOBALS['TRANSLATIONS'] = [
    'ru' => [
        'nav_reviews'      => 'Отзывы',
        'nav_price'        => 'Прайс',
        'nav_booking'      => 'Запись',
        'hero_eyebrow'     => 'Добро пожаловать',
        'hero_text'        => 'Здесь вы можете почитать отзывы, посмотреть актуальный прайс и записаться на удобное время.',
        'greet_title'      => 'Как к вам обращаться?',
        'greet_text'       => 'Чтобы мы могли обратиться к вам по имени 🙂',
        'greet_placeholder'=> 'Например, Мия',
        'greet_continue'   => 'Продолжить',
        'greet_skip'       => 'Пропустить',
        'greet_hello'      => 'Здравствуйте, %s!',

        'reviews_title'    => 'Отзывы',
        'reviews_empty'    => 'Пока нет отзывов — станьте первым!',
        'reviews_leave'    => 'Оставить отзыв',
        'reviews_name'     => 'Ваше имя',
        'reviews_rating'   => 'Оценка',
        'reviews_text'     => 'Отзыв',
        'reviews_send'     => 'Отправить отзыв',
        'reviews_sent'     => 'Спасибо! Ваш отзыв отправлен и появится на сайте после проверки.',

        'price_title'      => 'Прайс',
        'price_empty'      => 'Прайс скоро появится.',
        'price_designs'    => 'Дизайны от 15 до 100 грн',
        'price_location'   => 'Ст. м. ХТЗ',

        'booking_title'    => 'Запись',
        'booking_intro'    => 'Выберите удобное свободное время в календаре, а затем напишите мастеру в любом удобном мессенджере — всё запишем и подтвердим.',
        'booking_week_prev'=> '← Прошлая неделя',
        'booking_week_next'=> 'Следующая неделя →',
        'booking_selected' => 'Вы выбрали: ',
        'booking_none'     => 'Время пока не выбрано',
        'booking_cta'      => 'Записаться',
        'booking_contacts_title' => 'Напишите мастеру, чтобы подтвердить запись',
        'booking_contacts_hint'  => 'Сообщите выбранное время удобным способом:',
        'booking_instagram'=> 'Instagram',
        'booking_viber'    => 'Viber',
        'booking_telegram' => 'Telegram',
        'booking_phone'    => 'Позвонить',
        'booking_no_slots' => 'На эту неделю свободного времени пока нет',
        'booking_slot_booked' => 'занято',
        'booking_sent'     => 'Заявка отправлена! Мы свяжемся с вами для подтверждения.',

        'weekdays' => ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'],
        'months'   => ['января','февраля','марта','апреля','мая','июня','июля','августа','сентября','октября','ноября','декабря'],
    ],
    'ua' => [
        'nav_reviews'      => 'Відгуки',
        'nav_price'        => 'Прайс',
        'nav_booking'      => 'Запис',
        'hero_eyebrow'     => 'Ласкаво просимо',
        'hero_text'        => 'Тут ви можете прочитати відгуки, переглянути актуальний прайс і записатися на зручний час.',
        'greet_title'      => 'Як до вас звертатися?',
        'greet_text'       => 'Щоб ми могли звернутися до вас на ім\'я 🙂',
        'greet_placeholder'=> 'Наприклад, Мія',
        'greet_continue'   => 'Продовжити',
        'greet_skip'       => 'Пропустити',
        'greet_hello'      => 'Вітаємо, %s!',

        'reviews_title'    => 'Відгуки',
        'reviews_empty'    => 'Поки що немає відгуків — станьте першим!',
        'reviews_leave'    => 'Залишити відгук',
        'reviews_name'     => 'Ваше ім\'я',
        'reviews_rating'   => 'Оцінка',
        'reviews_text'     => 'Відгук',
        'reviews_send'     => 'Надіслати відгук',
        'reviews_sent'     => 'Дякуємо! Ваш відгук надіслано і з\'явиться на сайті після перевірки.',

        'price_title'      => 'Прайс',
        'price_empty'      => 'Прайс скоро з\'явиться.',
        'price_designs'    => 'Дизайни від 15 до 100 грн',
        'price_location'   => 'Ст. м. ХТЗ',

        'booking_title'    => 'Запис',
        'booking_intro'    => 'Оберіть зручний вільний час у календарі, а потім напишіть майстру у будь-якому зручному месенджері — все запишемо і підтвердимо.',
        'booking_week_prev'=> '← Минулий тиждень',
        'booking_week_next'=> 'Наступний тиждень →',
        'booking_selected' => 'Ви обрали: ',
        'booking_none'     => 'Час поки не обрано',
        'booking_cta'      => 'Записатися',
        'booking_contacts_title' => 'Напишіть майстру, щоб підтвердити запис',
        'booking_contacts_hint'  => 'Повідомте обраний час зручним способом:',
        'booking_instagram'=> 'Instagram',
        'booking_viber'    => 'Viber',
        'booking_telegram' => 'Telegram',
        'booking_phone'    => 'Подзвонити',
        'booking_no_slots' => 'На цей тиждень вільного часу поки немає',
        'booking_slot_booked' => 'зайнято',
        'booking_sent'     => 'Заявку надіслано! Ми зв\'яжемося з вами для підтвердження.',

        'weekdays' => ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Нд'],
        'months'   => ['січня','лютого','березня','квітня','травня','червня','липня','серпня','вересня','жовтня','листопада','грудня'],
    ],
];

function t(string $key)
{
    $lang = currentLang();
    return $GLOBALS['TRANSLATIONS'][$lang][$key] ?? $GLOBALS['TRANSLATIONS']['ru'][$key] ?? $key;
}
