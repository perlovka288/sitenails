<?php
// includes/push_bell_script.php
//
// Логика кнопки-колокольчика 🔔 (запрос разрешения на push через OneSignal).
// Вынесено из includes/header.php в отдельный файл, чтобы подключать и на
// страницах с собственной шапкой (например profile.php), где раньше этого
// скрипта не было вообще — колокольчик там просто отсутствовал.
//
// Требует, чтобы к этому месту уже была отрисована кнопка
// <button id="notifyPermBtn">, и чтобы была переменная $__onesignalAppId
// (строка App ID или '' если не настроено).
if (!isset($__onesignalAppId)) {
    $__onesignalAppId = getSetting('onesignal_app_id', '');
}
?>
<?php if ($__onesignalAppId !== ''): ?>
<!-- Push-уведомления (OneSignal) — приходят как обычное системное
     уведомление на телефон/в браузер, без бота (см. includes/onesignal.php,
     отправка идёт при подтверждении записи в панели управления). -->
<script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer></script>
<script>
(function () {
  var notifyBtn = document.getElementById('notifyPermBtn');
  if (!notifyBtn) return;

  var NOTIFY_TITLES = {
    default: <?= json_encode(t('notify_permission_title')) ?>,
    granted: <?= json_encode(t('notify_permission_granted_title')) ?>,
    denied: <?= json_encode(t('notify_permission_denied_title')) ?>
  };
  var NOTIFY_DENIED_HINT = <?= json_encode(t('notify_permission_denied_hint')) ?>;

  // ===== iOS: без сохранённого на "Экран Домой" ярлыка Push в принципе
  // недоступен (ограничение Apple, а не этого сайта) — сразу объясняем
  // это понятно, вместо молчаливого "ничего не происходит" по клику.
  var ua = navigator.userAgent || '';
  var isIOS = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  var isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  if (isIOS && !isStandalone) {
    notifyBtn.addEventListener('click', function () {
      alert('На iPhone уведомления работают только для сайта, сохранённого на Экран «Домой». Откройте кнопку "Поделиться" в Safari → "На экран «Домой»", затем откройте сайт с этого нового значка и нажмите на колокольчик ещё раз.');
    });
    return;
  }

  if (!window.Notification) {
    // Браузер вообще не поддерживает Web Push API (редко, но бывает на
    // очень старых версиях/встроенных in-app браузерах вроде Instagram).
    notifyBtn.addEventListener('click', function () {
      alert('Этот браузер не поддерживает push-уведомления. Попробуйте открыть сайт в Chrome или Safari.');
    });
    return;
  }

  function updateNotifyBtnState() {
    var perm = Notification.permission; // 'default' | 'granted' | 'denied'
    notifyBtn.classList.toggle('is-granted', perm === 'granted');
    notifyBtn.classList.toggle('is-denied', perm === 'denied');
    notifyBtn.title = NOTIFY_TITLES[perm] || NOTIFY_TITLES.default;
  }
  updateNotifyBtnState();

  // ===== Готовность OneSignal SDK =====
  // Слушатель вешаем сразу (не ждём инициализации SDK), а сам SDK ждём с
  // таймаутом — иначе клик по кнопке до полной загрузки SDK не делает
  // ничего видимого (частая причина "не работает", особенно если
  // блокировщик рекламы глушит cdn.onesignal.com).
  var oneSignalReady = false;
  var oneSignalFailed = false;
  var OneSignalRef = null;

  window.OneSignalDeferred = window.OneSignalDeferred || [];
  OneSignalDeferred.push(async function (OneSignal) {
    try {
      await OneSignal.init({ appId: <?= json_encode($__onesignalAppId) ?> });
      OneSignalRef = OneSignal;
      oneSignalReady = true;
      if (window.SITE_USER_ID) {
        // Привязываем подписку на пуши к id клиента в site_users — этот же
        // id сервер использует как external_id при отправке уведомления.
        OneSignal.login(String(window.SITE_USER_ID));
      }
      OneSignal.Notifications.addEventListener('permissionChange', updateNotifyBtnState);
      updateNotifyBtnState();
    } catch (err) {
      oneSignalFailed = true;
      console.error('OneSignal init error:', err);
    }
  });

  setTimeout(function () {
    if (!oneSignalReady) oneSignalFailed = true;
  }, 6000);

  notifyBtn.addEventListener('click', function () {
    if (Notification.permission === 'denied') {
      alert(NOTIFY_DENIED_HINT);
      return;
    }
    if (Notification.permission === 'granted') {
      return;
    }
    if (!oneSignalReady) {
      if (oneSignalFailed) {
        alert('Не удалось загрузить сервис уведомлений. Проверьте, не блокирует ли его блокировщик рекламы (uBlock/AdBlock) или расширение приватности в браузере, и попробуйте снова.');
      } else {
        alert('Уведомления ещё загружаются, попробуйте нажать ещё раз через пару секунд.');
      }
      return;
    }
    OneSignalRef.Notifications.requestPermission().then(updateNotifyBtnState);
  });
})();
</script>
<?php endif; ?>
