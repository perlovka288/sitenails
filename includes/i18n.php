<?php
/**
 * includes/i18n.php
 * Простой словарь переводов интерфейса (РУС / УКР) + функция t().
 *
 * Как это работает:
 * - Язык хранится в $_SESSION['lang'] (значит, помнится, пока открыт браузер / сессия).
 * - Переключатель РУС/УКР в шапке ссылкается на ?lang=ru или ?lang=ua —
 *   при заходе с этим параметром язык сохраняется в сессию и в cookie на 1 год,
 *   так что при следующем визите тоже подставится нужный язык.
 * - Тексты, которые вводит сама мама (отзывы клиентов, позиции прайса),
 *   не переводятся автоматически — это её собственный текст на любом языке,
 *   переводится только фиксированный интерфейс сайта (кнопки, заголовки, подписи).
 */

function currentLang(): string
{
    static $resolved = null;
    if ($resolved !== null) {
        return $resolved;
    }

    $allowed = ['ru', 'ua'];

    if (isset($_GET['lang']) && in_array($_GET['lang'], $allowed, true)) {
        $_SESSION['lang'] = $_GET['lang'];
        if (!headers_sent()) {
            setcookie('site_lang', $_GET['lang'], time() + 60 * 60 * 24 * 365, '/');
        }
    }

    if (!isset($_SESSION['lang'])) {
        if (isset($_COOKIE['site_lang']) && in_array($_COOKIE['site_lang'], $allowed, true)) {
            $_SESSION['lang'] = $_COOKIE['site_lang'];
        } else {
            $_SESSION['lang'] = 'ru';
        }
    }

    $resolved = $_SESSION['lang'];
    return $resolved;
}

function t(string $key): string
{
    static $dict = [
        'ru' => [
            'eyebrow'            => 'Добро пожаловать',
            'hero_text'          => 'Здесь вы можете почитать отзывы, посмотреть актуальный прайс и записаться на удобное время.',
            'tab_reviews'        => 'Отзывы',
            'tab_price'          => 'Прайс',
            'tab_booking'        => 'Запись',

            'reviews_title'      => 'Отзывы',
            'review_sent'        => 'Спасибо! Ваш отзыв опубликован.',
            'no_reviews'         => 'Пока нет отзывов — станьте первым!',
            'leave_review'       => 'Оставить отзыв',
            'your_name'          => 'Ваше имя',
            'rating'             => 'Оценка',
            'review_text'        => 'Отзыв',
            'send_review'        => 'Отправить отзыв',
            'delete'             => 'Удалить',
            'confirm_delete_review' => 'Удалить этот отзыв?',

            'price_title'        => 'Прайс',
            'no_price'           => 'Прайс скоро появится.',
            'admin_add_item'     => 'Добавить позицию',
            'category'           => 'Категория',
            'category_ph'        => 'Например, «Манікюр»',
            'service_name'       => 'Название услуги',
            'price_value'        => 'Цена',
            'price_ph'           => 'Например, «450 грн»',
            'add'                => 'Добавить',
            'confirm_delete_price' => 'Удалить эту позицию?',

            'booking_title'      => 'Запись',
            'booking_sent'       => 'Заявка отправлена! Мы свяжемся с вами для подтверждения.',
            'phone'              => 'Телефон',
            'service'            => 'Услуга',
            'service_ph'         => 'Например: маникюр',
            'wanted_date'        => 'Желаемая дата',
            'comment'            => 'Комментарий',
            'send_booking'       => 'Записаться',

            'greet_title'        => 'Как к вам обращаться?',
            'greet_text'         => 'Чтобы мы могли обратиться к вам по имени 🙂',
            'greet_ph'           => 'Например, Мия',
            'continue'           => 'Продолжить',
            'skip'               => 'Пропустить',
            'greeting_template'  => 'Здравствуйте, %s!',

            'admin_mode'         => 'Режим администратора',
        ],
        'ua' => [
            'eyebrow'            => 'Ласкаво просимо',
            'hero_text'          => 'Тут ви можете почитати відгуки, переглянути актуальний прайс і записатися на зручний час.',
            'tab_reviews'        => 'Відгуки',
            'tab_price'          => 'Прайс',
            'tab_booking'        => 'Запис',

            'reviews_title'      => 'Відгуки',
            'review_sent'        => 'Дякуємо! Ваш відгук опубліковано.',
            'no_reviews'         => 'Поки що немає відгуків — станьте першими!',
            'leave_review'       => 'Залишити відгук',
            'your_name'          => "Ваше ім'я",
            'rating'             => 'Оцінка',
            'review_text'        => 'Відгук',
            'send_review'        => 'Надіслати відгук',
            'delete'             => 'Видалити',
            'confirm_delete_review' => 'Видалити цей відгук?',

            'price_title'        => 'Прайс',
            'no_price'           => "Прайс з'явиться найближчим часом.",
            'admin_add_item'     => 'Додати позицію',
            'category'           => 'Категорія',
            'category_ph'        => 'Наприклад, «Манікюр»',
            'service_name'       => 'Назва послуги',
            'price_value'        => 'Ціна',
            'price_ph'           => 'Наприклад, «450 грн»',
            'add'                => 'Додати',
            'confirm_delete_price' => 'Видалити цю позицію?',

            'booking_title'      => 'Запис',
            'booking_sent'       => "Заявку надіслано! Ми зв'яжемося з вами для підтвердження.",
            'phone'              => 'Телефон',
            'service'            => 'Послуга',
            'service_ph'         => 'Наприклад: манікюр',
            'wanted_date'        => 'Бажана дата',
            'comment'            => 'Коментар',
            'send_booking'       => 'Записатися',

            'greet_title'        => 'Як до вас звертатися?',
            'greet_text'         => "Щоб ми могли звернутися до вас на ім'я 🙂",
            'greet_ph'           => 'Наприклад, Мія',
            'continue'           => 'Продовжити',
            'skip'               => 'Пропустити',
            'greeting_template'  => 'Вітаємо, %s!',

            'admin_mode'         => 'Режим адміністратора',
        ],
    ];

    $lang = currentLang();
    return $dict[$lang][$key] ?? $dict['ru'][$key] ?? $key;
}
