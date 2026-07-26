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
        'reviews_photo'    => 'Фото (необязательно)',
        'reviews_send'     => 'Отправить отзыв',
        'reviews_sent'     => 'Спасибо! Ваш отзыв уже опубликован на сайте.',

        'price_title'      => 'Прайс',
        'price_subtitle'   => 'Маникюр и Педикюр',
        'price_empty'      => 'Прайс скоро появится.',
        'price_designs'    => 'Дизайны от 15 до 100 грн',
        'price_location'   => 'Ст. м. ХТЗ',

        'booking_title'    => 'Запись',
        'booking_intro'    => 'Выберите удобное свободное время в календаре, а затем напишите мастеру в любом удобном мессенджере — всё запишем и подтвердим.',
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

        'fab_title'   => 'Связаться с мастером',
        'fab_master_name' => 'Мастер: Любовь',
        'fab_hint'    => 'Выберите удобный способ связи:',
        'close'       => 'Закрыть',

        'week_prev' => 'Предыдущая неделя',
        'week_next' => 'Следующая неделя',
        'reviews_photo_hint' => 'Можно прикрепить до 3 фото',
        'photo_view_alt' => 'Открыть фото',

        'booking_confirm_title'    => 'Подтвердите запись',
        'booking_confirm_question'=> 'Вы действительно хотите выбрать это время?',
        'yes'                      => 'Да',
        'no'                       => 'Нет',

        'admin_panel_button' => 'Панель управления',
        'admin_mode_badge'   => 'Режим администратора',

        'reviews_hide'    => 'Скрыть',
        'reviews_show'    => 'Опубликовать',
        'reviews_delete'  => 'Удалить',
        'reviews_hidden'  => 'Скрыт',
        'reviews_confirm_delete' => 'Удалить этот отзыв?',

        'price_add_btn'     => 'Добавить позицию',
        'price_edit'        => 'Изменить',
        'price_delete'      => 'Удалить',
        'price_confirm_delete' => 'Удалить эту позицию?',
        'price_form_title'  => 'Позиция прайса',
        'price_category_ru' => 'Категория (рус.)',
        'price_category_ua' => 'Категория (укр., необязательно)',
        'price_title_ru'    => 'Название услуги (рус.)',
        'price_title_ua'    => 'Название услуги (укр., необязательно)',
        'price_amount'      => 'Цена',
        'save'              => 'Сохранить',
        'cancel'            => 'Отмена',

        'slot_add_btn'       => 'Добавить время',
        'slot_form_title'    => 'Свободное время',
        'slot_date'          => 'Дата',
        'slot_time'          => 'Время',
        'slot_status'        => 'Статус',
        'slot_status_free'   => 'Свободно',
        'slot_status_booked' => 'Занято',
        'slot_delete'        => 'Удалить',
        'slot_confirm_delete'=> 'Удалить это время из календаря?',
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
        'reviews_photo'    => 'Фото (необов\'язково)',
        'reviews_send'     => 'Надіслати відгук',
        'reviews_sent'     => 'Дякуємо! Ваш відгук вже опубліковано на сайті.',

        'price_title'      => 'Прайс',
        'price_subtitle'   => 'Манікюр та Педикюр',
        'price_empty'      => 'Прайс скоро з\'явиться.',
        'price_designs'    => 'Дизайни від 15 до 100 грн',
        'price_location'   => 'Ст. м. ХТЗ',

        'booking_title'    => 'Запис',
        'booking_intro'    => 'Оберіть зручний вільний час у календарі, а потім напишіть майстру у будь-якому зручному месенджері — все запишемо і підтвердимо.',
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

        'fab_title'   => "Зв'язатися з майстром",
        'fab_master_name' => 'Майстер: Любов',
        'fab_hint'    => 'Оберіть зручний спосіб зв\'язку:',
        'close'       => 'Закрити',

        'week_prev' => 'Попередній тиждень',
        'week_next' => 'Наступний тиждень',
        'reviews_photo_hint' => 'Можна прикріпити до 3 фото',
        'photo_view_alt' => 'Відкрити фото',

        'booking_confirm_title'    => 'Підтвердіть запис',
        'booking_confirm_question'=> 'Ви дійсно хочете обрати цей час?',
        'yes'                      => 'Так',
        'no'                       => 'Ні',

        'admin_panel_button' => 'Панель керування',
        'admin_mode_badge'   => 'Режим адміністратора',

        'reviews_hide'    => 'Приховати',
        'reviews_show'    => 'Опублікувати',
        'reviews_delete'  => 'Видалити',
        'reviews_hidden'  => 'Приховано',
        'reviews_confirm_delete' => 'Видалити цей відгук?',

        'price_add_btn'     => 'Додати позицію',
        'price_edit'        => 'Змінити',
        'price_delete'      => 'Видалити',
        'price_confirm_delete' => 'Видалити цю позицію?',
        'price_form_title'  => 'Позиція прайсу',
        'price_category_ru' => 'Категорія (рос.)',
        'price_category_ua' => 'Категорія (укр., необов\'язково)',
        'price_title_ru'    => 'Назва послуги (рос.)',
        'price_title_ua'    => 'Назва послуги (укр., необов\'язково)',
        'price_amount'      => 'Ціна',
        'save'              => 'Зберегти',
        'cancel'            => 'Скасувати',

        'slot_add_btn'       => 'Додати час',
        'slot_form_title'    => 'Вільний час',
        'slot_date'          => 'Дата',
        'slot_time'          => 'Час',
        'slot_status'        => 'Статус',
        'slot_status_free'   => 'Вільно',
        'slot_status_booked' => 'Зайнято',
        'slot_delete'        => 'Видалити',
        'slot_confirm_delete'=> 'Видалити цей час з календаря?',
    ],
];

function t(string $key)
{
    $lang = currentLang();
    return $GLOBALS['TRANSLATIONS'][$lang][$key] ?? $GLOBALS['TRANSLATIONS']['ru'][$key] ?? $key;
}
