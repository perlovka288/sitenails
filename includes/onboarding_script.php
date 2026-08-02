<?php
/**
 * includes/onboarding_script.php
 *
 * Приветственный онбординг клиента: показывается один раз сразу после
 * входа/регистрации (и после выбора языка, если модалка языка ещё
 * всплывала при первом визите) и предлагает по очереди:
 *   1) включить push-уведомления о статусе записи;
 *   2) установить сайт на рабочий стол/главный экран как приложение.
 * Оба вопроса — на том языке, который человек только что выбрал (t()).
 * Если человек соглашается — разрешение запрашивается СРАЗУ у самого
 * браузера (настоящее системное окно, а не просто внутренняя галочка
 * сайта), и/или сразу показывается системный диалог установки — ярлык
 * после этого появляется на рабочем столе так же, как от любого другого
 * PWA-сайта. Показывается только вошедшим клиентам (см. require в
 * includes/header.php), не мама-администратору в панели управления.
 */
if (!isset($__onesignalAppId)) {
    $__onesignalAppId = getSetting('onesignal_app_id', '');
}
?>
<div class="greet-overlay" id="onboardingOverlay" style="display:none;">
  <div class="greet-modal">
    <h3 id="onboardingTitle"></h3>
    <p id="onboardingText"></p>
    <div class="lang-select-grid">
      <button type="button" class="btn full" id="onboardingYesBtn"></button>
      <button type="button" class="btn ghost full" id="onboardingNoBtn"></button>
    </div>
  </div>
</div>
<script>
(function () {
  var userId = window.SITE_USER_ID;
  if (!userId) return;

  var overlay = document.getElementById('onboardingOverlay');
  var titleEl = document.getElementById('onboardingTitle');
  var textEl  = document.getElementById('onboardingText');
  var yesBtn  = document.getElementById('onboardingYesBtn');
  var noBtn   = document.getElementById('onboardingNoBtn');
  if (!overlay || !titleEl || !textEl || !yesBtn || !noBtn) return;

  // Флаг "уже спрашивали" — свой на каждого клиента в этом браузере,
  // чтобы при выходе и входе другого человека на том же устройстве
  // вопрос задался заново именно ему, а не считался "уже отвеченным".
  var DONE_KEY = 'sitenails_onboarding_done_' + userId;
  if (localStorage.getItem(DONE_KEY)) return;

  var L = <?= json_encode([
      'notif_title'      => t('onboarding_notif_title'),
      'notif_text'       => t('onboarding_notif_text'),
      'install_title'    => t('onboarding_install_title'),
      'install_text'     => t('onboarding_install_text'),
      'install_ios_text' => t('onboarding_install_ios_text'),
      'yes'              => t('yes'),
      'skip'             => t('onboarding_skip'),
  ], JSON_UNESCAPED_UNICODE) ?>;

  var hasOneSignal  = <?= json_encode($__onesignalAppId !== '') ?>;
  var ua            = navigator.userAgent || '';
  var isIOS         = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  var isStandalone  = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

  // Шаг про уведомления нужен, только если в Настройках вообще подключен
  // OneSignal, браузер поддерживает Web Push, и разрешение ещё ни разу
  // не запрашивалось (не "granted"/"denied" — тогда спрашивать нечего).
  var wantsNotifStep = hasOneSignal && !!window.Notification && Notification.permission === 'default';
  // Шаг про установку не нужен, если сайт уже открыт как установленное
  // приложение (человек и так им уже пользуется).
  var wantsInstallStep = !isStandalone;

  var steps = [];
  if (wantsNotifStep) steps.push('notif');
  if (wantsInstallStep) steps.push('install');

  if (!steps.length) {
    localStorage.setItem(DONE_KEY, '1');
    return;
  }

  var stepIndex = 0;

  function renderStep() {
    var step = steps[stepIndex];
    if (step === 'notif') {
      titleEl.textContent = L.notif_title;
      textEl.textContent = L.notif_text;
    } else {
      titleEl.textContent = L.install_title;
      textEl.textContent = isIOS ? L.install_ios_text : L.install_text;
    }
    yesBtn.textContent = L.yes;
    noBtn.textContent = L.skip;
    // На iOS программной установки не существует (ограничение Apple) —
    // тут только инструкция, поэтому кнопка "Да" не нужна, только "Понятно".
    yesBtn.style.display = (step === 'install' && isIOS) ? 'none' : '';
    noBtn.textContent = (step === 'install' && isIOS) ? L.yes : L.skip;
    overlay.style.display = 'flex';
  }

  function nextStep() {
    stepIndex++;
    if (stepIndex >= steps.length) {
      overlay.style.display = 'none';
      localStorage.setItem(DONE_KEY, '1');
      return;
    }
    renderStep();
  }

  yesBtn.addEventListener('click', function () {
    var step = steps[stepIndex];
    if (step === 'notif' && window.__siteRequestNotificationPermission) {
      window.__siteRequestNotificationPermission();
    } else if (step === 'install' && !isIOS && window.__siteTriggerInstall) {
      window.__siteTriggerInstall();
    }
    nextStep();
  });

  noBtn.addEventListener('click', nextStep);

  function start() {
    stepIndex = 0;
    renderStep();
  }

  // Если модалка выбора языка ещё не пройдена (самый первый визит на
  // сайт вообще) — ждём, пока человек выберет язык (тот же ключ
  // localStorage, что использует langOverlay в assets/js/script.js), и
  // только потом показываем этот вопрос — чтобы окна не накладывались.
  if (!localStorage.getItem('visitor_lang')) {
    var waitForLang = setInterval(function () {
      if (localStorage.getItem('visitor_lang')) {
        clearInterval(waitForLang);
        setTimeout(start, 350);
      }
    }, 200);
  } else {
    start();
  }
})();
</script>
