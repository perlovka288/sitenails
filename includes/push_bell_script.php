<?php
// includes/push_bell_script.php
//
// Логика колокольчика в шапке сайта:
//  1) Центр уведомлений — выпадающий список (см. get_notifications.php),
//     красная точка на непрочитанные, статус "прочитано" хранится в
//     localStorage (просто дата последнего просмотренного уведомления).
//  2) Внутри дропдауна — кнопка запроса разрешения на push через OneSignal
//     (было раньше отдельным колокольчиком, теперь строка в списке).
//
// Требует кнопку <button id="notifCenterBtn"> и панель
// <div id="notifDropdown"> (см. includes/header.php и profile.php).
// Кнопка push-разрешения <button id="notifyPermBtn"> — необязательна,
// показывается только если настроен OneSignal App ID.
if (!isset($__onesignalAppId)) {
    $__onesignalAppId = getSetting('onesignal_app_id', '');
}
?>
<script>
(function () {
  var bellBtn = document.getElementById('notifCenterBtn');
  var dropdown = document.getElementById('notifDropdown');
  var badge = document.getElementById('notifBadge');
  var list = document.getElementById('notifList');
  if (!bellBtn || !dropdown || !list) return;

  var LAST_SEEN_KEY = 'sitenails_notif_last_seen';

  function renderList(items) {
    list.innerHTML = '';
    if (!items.length) {
      var p = document.createElement('p');
      p.className = 'notif-empty';
      p.textContent = 'Пока нет уведомлений';
      list.appendChild(p);
      return;
    }
    items.forEach(function (n) {
      // Клик по уведомлению о принятой/выполненной записи переносит на
      // страницу профиля прямо к этой записи (дата, время, адрес, телефон
      // мастера — см. #booking-N и подсветку в profile.php). Ссылка ведёт
      // туда же и с других страниц сайта, не только с самого профиля.
      var row = document.createElement(n.id ? 'a' : 'div');
      row.className = 'notif-item';
      if (n.id) {
        row.href = 'profile.php#booking-' + n.id;
      }

      var dot = document.createElement('span');
      dot.className = 'notif-item-dot';
      dot.textContent = n.status === 'done' ? '✅' : '🟢';

      var textWrap = document.createElement('div');
      textWrap.className = 'notif-item-textwrap';
      var text = document.createElement('p');
      text.className = 'notif-item-text';
      text.textContent = n.message;
      var time = document.createElement('p');
      time.className = 'notif-item-time';
      time.textContent = n.time_label || '';
      textWrap.appendChild(text);
      textWrap.appendChild(time);

      row.appendChild(dot);
      row.appendChild(textWrap);

      if (n.id) {
        var arrow = document.createElement('span');
        arrow.className = 'notif-item-arrow';
        arrow.setAttribute('aria-hidden', 'true');
        arrow.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>';
        row.appendChild(arrow);
      }

      list.appendChild(row);
    });
  }

  function updateBadge(items) {
    if (!badge) return;
    var lastSeen = localStorage.getItem(LAST_SEEN_KEY) || '';
    var hasUnread = items.some(function (n) { return (n.updated_at || '') > lastSeen; });
    badge.hidden = !hasUnread;
  }

  function markAllSeen(items) {
    if (!items.length) return;
    var newest = items.reduce(function (max, n) {
      return (n.updated_at || '') > max ? (n.updated_at || '') : max;
    }, '');
    if (newest) localStorage.setItem(LAST_SEEN_KEY, newest);
    if (badge) badge.hidden = true;
  }

  var lastItems = [];
  var loaded = false;

  function loadNotifications() {
    fetch('get_notifications.php', { credentials: 'same-origin' })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        loaded = true;
        lastItems = (data && data.success && data.items) ? data.items : [];
        renderList(lastItems);
        updateBadge(lastItems);
      })
      .catch(function () {
        loaded = true;
        list.innerHTML = '';
        var p = document.createElement('p');
        p.className = 'notif-empty';
        p.textContent = 'Не удалось загрузить уведомления';
        list.appendChild(p);
      });
  }

  loadNotifications();
  // Периодически проверяем новые уведомления, чтобы красная точка
  // появлялась без перезагрузки страницы (например, пока клиент листает сайт).
  setInterval(loadNotifications, 60000);

  bellBtn.addEventListener('click', function (e) {
    e.stopPropagation();
    var willOpen = dropdown.hidden;
    dropdown.hidden = !dropdown.hidden;
    if (willOpen) {
      if (loaded) renderList(lastItems);
      markAllSeen(lastItems);
    }
  });

  document.addEventListener('click', function (e) {
    if (!dropdown.hidden && !dropdown.contains(e.target) && e.target !== bellBtn) {
      dropdown.hidden = true;
    }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') dropdown.hidden = true;
  });
})();
</script>
<?php if ($__onesignalAppId !== ''): ?>
<!-- Push-уведомления (OneSignal) — приходят как обычное системное
     уведомление на телефон/в браузер, без бота (см. includes/onesignal.php,
     отправка идёт при подтверждении записи в панели управления). -->
<script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer></script>
<script>
(function () {
  var notifyBtn = document.getElementById('notifyPermBtn');
  if (!notifyBtn) return;

  var notifyBtnText = document.getElementById('notifyPermBtnText');

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

  // ===== Android: технически push работает и без установки на главный
  // экран (в отличие от iOS), НО если сайт открыт просто вкладкой в
  // Chrome, под текстом уведомления Android показывает домен сайта, а
  // маленький значок — это иконка самого Chrome, а не сайта. Именно так
  // выглядят типичные спам-пуши с рекламных сайтов, поэтому уведомления
  // воспринимаются как реклама, даже если содержание нормальное.
  // Если сайт установлен на главный экран (PWA), уведомление приходит от
  // "приложения" — с его иконкой и названием, без URL — и выглядит как
  // обычное системное уведомление. Поэтому один раз мягко предлагаем
  // установку прямо перед запросом разрешения (не блокируем, просто
  // подсказываем — сам пуш всё равно настроится, даже если откажутся).
  var isAndroid = /Android/.test(ua);
  var deferredInstallPrompt = null;
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferredInstallPrompt = e;
  });
  var ANDROID_INSTALL_HINT_KEY = 'sitenails_android_install_hint_shown';
  function maybeSuggestAndroidInstall() {
    if (!isAndroid || isStandalone) return Promise.resolve();
    if (localStorage.getItem(ANDROID_INSTALL_HINT_KEY)) return Promise.resolve();
    localStorage.setItem(ANDROID_INSTALL_HINT_KEY, '1');
    if (!deferredInstallPrompt) return Promise.resolve();
    var wantsInstall = confirm('Совет: установите сайт как приложение (это займёт секунду) — тогда уведомления будут выглядеть как от обычного приложения, а не от вкладки браузера. Установить сейчас?');
    if (!wantsInstall) return Promise.resolve();
    deferredInstallPrompt.prompt();
    return deferredInstallPrompt.userChoice.then(function () {
      deferredInstallPrompt = null;
    });
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
    var title = NOTIFY_TITLES[perm] || NOTIFY_TITLES.default;
    notifyBtn.title = title;
    if (notifyBtnText) notifyBtnText.textContent = title;
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

  function requestPushPermission() {
    OneSignalRef.Notifications.requestPermission().then(updateNotifyBtnState);
  }
  notifyBtn.addEventListener('click', function (e) {
    e.stopPropagation();
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
    maybeSuggestAndroidInstall().then(requestPushPermission);
  });
})();
</script>
<?php endif; ?>
