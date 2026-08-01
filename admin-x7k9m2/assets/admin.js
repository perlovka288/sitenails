// admin-x7k9m2/assets/admin.js
// Общие мелочи для панели управления:
//  1) кнопки "⇄ Перевести с рус." — автоматически подставляют украинский
//     вариант текста в соответствующее поле (черновик, можно поправить руками).
//  2) живой предпросмотр блока «О мне» (карточка сверху формы обновляется
//     на лету по мере ввода текста, ещё до нажатия "Сохранить").

document.addEventListener('DOMContentLoaded', function () {
  // ===== Запоминаем позицию скролла перед уходом со страницы (переход по
  // ссылке внутри панели или отправка формы) — восстанавливается в самом
  // начале includes/nav.php на той же странице после перезагрузки, чтобы
  // сохранение настроек / переход между разделами не кидало наверх. =====
  function saveAdminScroll() {
    try {
      sessionStorage.setItem('admin_scroll::' + location.pathname, String(window.scrollY));
    } catch (e) {}
  }
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a[href]');
    if (!a) return;
    // Только для обычных переходов внутри панели (не новая вкладка, не modifier-клик).
    if (a.target === '_blank' || e.metaKey || e.ctrlKey || e.shiftKey || a.hasAttribute('download')) return;
    saveAdminScroll();
  }, true);
  document.addEventListener('submit', saveAdminScroll, true);
  window.addEventListener('pagehide', saveAdminScroll);

  // ===== Автоперевод РУС -> УКР =====
  document.querySelectorAll('[data-translate-from]').forEach(function (btn) {
    var fromField = document.getElementById(btn.dataset.translateFrom);
    var toField = document.getElementById(btn.dataset.translateTo);
    if (!fromField || !toField) return;

    btn.addEventListener('click', function () {
      var text = (fromField.value || '').trim();
      if (text === '') {
        alert('Сначала заполните русский вариант — переводить пока нечего.');
        return;
      }

      var originalLabel = btn.textContent;
      btn.disabled = true;
      btn.textContent = 'Переводим…';

      // Направление определяем по имени поля-получателя: если оно
      // оканчивается на "_ua" — переводим на украинский, иначе — на русский.
      // Это позволяет одной и той же кнопке работать в обе стороны.
      var toLang = /_ua$/.test(btn.dataset.translateTo) ? 'uk' : 'ru';

      var body = new URLSearchParams();
      body.set('csrf_token', window.ADMIN_CSRF_TOKEN || '');
      body.set('text', text);
      body.set('to', toLang);

      fetch('translate.php', { method: 'POST', body: body })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (data.ok) {
            toField.value = data.translated;
            toField.dispatchEvent(new Event('input'));
          } else {
            alert(data.error || 'Не получилось перевести. Впишите украинский текст вручную.');
          }
        })
        .catch(function () {
          alert('Не получилось перевести (нет связи с сервером). Впишите украинский текст вручную.');
        })
        .finally(function () {
          btn.disabled = false;
          btn.textContent = originalLabel;
        });
    });
  });

  // ===== Живой предпросмотр «О мне» =====
  // Кнопка "Предпросмотр" открывает модалку с точной копией публичного
  // блока «О мне» — она обновляется по мере ввода текста в форме, ещё
  // до сохранения, чтобы сразу видеть как всё будет смотреться на сайте.
  var preview = document.getElementById('aboutLivePreview');
  if (preview) {
    var pTitle = preview.querySelector('[data-preview="title"]');
    var pGreeting = preview.querySelector('[data-preview="greeting"]');
    var pSubtitle = preview.querySelector('[data-preview="subtitle"]');
    var pBio = preview.querySelector('[data-preview="bio"]');
    var pPhoto = preview.querySelector('[data-preview="photo"]');
    var pPhotoOriginalHtml = pPhoto ? pPhoto.innerHTML : '';

    function bindText(fieldId, target, fallback, isBlock) {
      var field = document.getElementById(fieldId);
      if (!field || !target) return;
      var update = function () {
        var val = (field.value || '').trim();
        if (isBlock) {
          target.innerHTML = (val !== '' ? val : (fallback || '')).replace(/\n/g, '<br>');
        } else {
          target.textContent = val !== '' ? val : (fallback || '');
        }
        target.style.display = val !== '' ? '' : (fallback ? '' : 'none');
      };
      field.addEventListener('input', update);
      update();
    }

    bindText('greeting', pGreeting, '');
    bindText('title', pTitle, 'Заголовок появится здесь');
    bindText('subtitle', pSubtitle, '');
    bindText('bio', pBio, 'Текст «о себе» появится здесь', true);

    var photoInput = document.getElementById('aboutPhotoInput');
    if (photoInput && pPhoto) {
      photoInput.addEventListener('change', function () {
        var file = photoInput.files && photoInput.files[0];
        if (!file) {
          pPhoto.innerHTML = pPhotoOriginalHtml;
          return;
        }
        var reader = new FileReader();
        reader.onload = function (ev) {
          pPhoto.innerHTML = '<img src="' + ev.target.result + '" alt="" style="width:100%;height:100%;object-fit:cover;">';
        };
        reader.readAsDataURL(file);
      });
    }

    var previewBtn = document.getElementById('aboutPreviewBtn');
    var previewModal = document.getElementById('modalPreview');
    if (previewBtn && previewModal) {
      previewBtn.addEventListener('click', function () {
        previewModal.classList.add('open');
      });
    }
  }

  // ===== Клик по рамке аватарки — открывает выбор файла напрямую,
  // без необходимости нажимать отдельную кнопку "Выбрать файл". =====
  var avatarFrame = document.getElementById('aboutPhotoFrame');
  var avatarInput = document.getElementById('aboutPhotoInput');
  if (avatarFrame && avatarInput) {
    avatarFrame.addEventListener('click', function () {
      avatarInput.click();
    });
    avatarFrame.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        avatarInput.click();
      }
    });
    avatarInput.addEventListener('change', function () {
      var file = avatarInput.files && avatarInput.files[0];
      if (!file) return;
      var reader = new FileReader();
      reader.onload = function (ev) {
        var placeholder = document.getElementById('aboutPhotoFramePlaceholder');
        var existingImg = document.getElementById('aboutPhotoFrameImg');
        if (existingImg) {
          existingImg.src = ev.target.result;
        } else {
          var img = document.createElement('img');
          img.id = 'aboutPhotoFrameImg';
          img.src = ev.target.result;
          img.alt = '';
          if (placeholder) placeholder.replaceWith(img);
          else avatarFrame.insertBefore(img, avatarFrame.firstChild);
        }
      };
      reader.readAsDataURL(file);
    });
  }

  // ===== Кнопки блока «О мне»: показываем поле "своя ссылка" только
  // когда выбран тип "Своя ссылка" — для остальных типов ссылка
  // считается автоматически (Instagram/Viber из настроек, или вкладка
  // "Отзывы" этого же сайта), вводить её вручную не нужно. =====
  document.querySelectorAll('.admin-btn-type-select').forEach(function (select) {
    var urlField = document.getElementById(select.dataset.urlField);
    if (!urlField) return;
    var sync = function () {
      urlField.style.display = select.value === 'custom' ? '' : 'none';
    };
    select.addEventListener('change', sync);
    sync();
  });

  // ===== Модальные окна панели управления (карточки "О мне", виджеты
  // и т.д.) — открытие/закрытие без перехода на другую страницу. =====
  function openModal(modal) {
    if (!modal) return;
    modal.classList.add('open');
  }
  function closeModal(modal) {
    if (!modal) return;
    modal.classList.remove('open');
  }

  document.querySelectorAll('[data-modal-open]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      openModal(document.getElementById(btn.dataset.modalOpen));
    });
  });
  document.querySelectorAll('[data-modal-close]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      closeModal(btn.closest('.modal-overlay'));
    });
  });
  document.querySelectorAll('.modal-overlay').forEach(function (overlay) {
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) closeModal(overlay);
    });
  });

  // ===== Кнопки блока «О мне»: одна и та же модалка используется и для
  // добавления новой кнопки, и для редактирования существующей — при
  // клике на карандаш поля заполняются текущими значениями. =====
  var buttonModal = document.getElementById('modalButton');
  if (buttonModal) {
    var btnModalTitle = document.getElementById('modalButtonTitle');
    var btnIdField = document.getElementById('btn_id');
    var btnTextField = document.getElementById('btn_text');
    var btnTextUaField = document.getElementById('btn_text_ua');
    var btnIconField = document.getElementById('btn_icon_text');
    var btnTypeField = document.getElementById('btn_type');
    var btnUrlField = document.getElementById('btn_url');

    document.querySelectorAll('[data-btn-add-open]').forEach(function (el) {
      el.addEventListener('click', function () {
        btnModalTitle.textContent = 'Новая кнопка';
        btnIdField.value = '';
        btnTextField.value = '';
        btnTextUaField.value = '';
        btnIconField.value = '';
        btnTypeField.value = 'custom';
        btnTypeField.dispatchEvent(new Event('change'));
        btnUrlField.value = '';
        openModal(buttonModal);
      });
    });

    document.querySelectorAll('[data-btn-edit]').forEach(function (el) {
      el.addEventListener('click', function () {
        btnModalTitle.textContent = 'Изменить кнопку';
        btnIdField.value = el.dataset.id || '';
        btnTextField.value = el.dataset.text || '';
        btnTextUaField.value = el.dataset.textUa || '';
        btnIconField.value = el.dataset.icon || '';
        btnTypeField.value = el.dataset.type || 'custom';
        btnTypeField.dispatchEvent(new Event('change'));
        btnUrlField.value = el.dataset.url || '';
        openModal(buttonModal);
      });
    });
  }

  // ===== Виджеты: клик по существующему файлу открывает окно
  // "переименовать / удалить" вместо перехода на отдельную страницу. =====
  var editItemModal = document.getElementById('editItemModal');
  if (editItemModal) {
    var editItemId = document.getElementById('editItemId');
    var editItemCategoryId = document.getElementById('editItemCategoryId');
    var editItemTitle = document.getElementById('editItemTitle');
    var deleteItemId = document.getElementById('deleteItemId');
    var deleteItemCategoryId = document.getElementById('deleteItemCategoryId');

    document.querySelectorAll('[data-item-edit]').forEach(function (el) {
      el.addEventListener('click', function () {
        editItemId.value = el.dataset.id || '';
        editItemCategoryId.value = el.dataset.categoryId || '';
        editItemTitle.value = el.dataset.title || '';
        deleteItemId.value = el.dataset.id || '';
        deleteItemCategoryId.value = el.dataset.categoryId || '';
        openModal(editItemModal);
      });
    });
  }

  // ===== Аккордеон вкладки «О мне» (Информация / Кнопки / Статистика /
  // Навыки / Опыт работы / Виджеты / Соцсети) — клик по плашке раскрывает
  // или сворачивает категорию, анимация — на CSS (grid-template-rows). =====
  var accordionItems = document.querySelectorAll('.about-accordion-item');
  if (accordionItems.length) {
    accordionItems.forEach(function (item) {
      var header = item.querySelector('.about-accordion-header');
      if (!header) return;
      header.setAttribute('aria-expanded', item.classList.contains('open') ? 'true' : 'false');
      header.addEventListener('click', function () {
        var willOpen = !item.classList.contains('open');
        item.classList.toggle('open', willOpen);
        header.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
      });
    });

    // Автоматически раскрыть нужную секцию: либо по хэшу в адресе
    // (например about.php#about-acc-widgets), либо по значению,
    // которое сервер передал через window.ADMIN_ABOUT_AUTOOPEN — это
    // происходит, когда мы вернулись на страницу после редактирования
    // записи (опыт работы / категория виджетов / соцсеть) через ссылку
    // "Изменить" и форма уже заполнена нужными значениями.
    var autoOpenId = (window.location.hash || '').replace('#', '') || window.ADMIN_ABOUT_AUTOOPEN || null;
    if (autoOpenId) {
      var target = document.getElementById(autoOpenId);
      if (target && target.classList.contains('about-accordion-item')) {
        target.classList.add('open');
        var targetHeader = target.querySelector('.about-accordion-header');
        if (targetHeader) targetHeader.setAttribute('aria-expanded', 'true');
        window.requestAnimationFrame(function () {
          target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
      }
    }
  }

  // ===== Показываем имя выбранного файла под кнопкой "Выбрать файл" =====
  // Работает для ЛЮБОГО input[type=file] внутри .file-input-styled на
  // странице — не нужно отдельно дописывать разметку под каждую форму
  // (аватар, иконка навыка/соцсети, файл виджета и т.д.).
  // ===== Вкладка "Запись" (slots.php): активные заявки + календарь-
  // аккордеон работают через AJAX — любое действие (подтвердить,
  // отклонить, добавить/переключить/удалить время, сохранить заметку,
  // переключить неделю) отправляется fetch'ем, а оба блока страницы
  // мгновенно перерисовываются свежим HTML от сервера, без перезагрузки
  // и без "прыжка" страницы наверх. =====
  var slotsActiveBlock = document.getElementById('activeBookingsBlock');
  var slotsCalendarBlock = document.getElementById('calendarBlock');
  if (slotsActiveBlock || slotsCalendarBlock) {
    var slotsCancelModal = document.getElementById('cancelModal');
    var slotsCancelId = document.getElementById('cancelBookingId');
    var slotsCancelReason = document.getElementById('cancelReason');

    // Сохраняем, какие дни сейчас раскрыты, чтобы после обновления
    // блока календаря вернуть их в раскрытом виде (иначе аккордеон
    // "прыгал" бы обратно в свёрнутое состояние после каждого действия).
    function slotsApplyBlocks(data) {
      var openDates = [];
      if (slotsCalendarBlock) {
        slotsCalendarBlock.querySelectorAll('[data-day-item].open').forEach(function (el) {
          openDates.push(el.dataset.date);
        });
      }
      if (slotsActiveBlock && typeof data.active === 'string') {
        slotsActiveBlock.innerHTML = data.active;
      }
      if (slotsCalendarBlock && typeof data.calendar === 'string') {
        slotsCalendarBlock.innerHTML = data.calendar;
        openDates.forEach(function (date) {
          var el = slotsCalendarBlock.querySelector('[data-day-item][data-date="' + date + '"]');
          if (!el) return;
          el.classList.add('open');
          var header = el.querySelector('[data-day-toggle]');
          if (header) header.setAttribute('aria-expanded', 'true');
        });
      }
    }

    function slotsRefreshBlocks(week) {
      var url = 'slots.php?ajax_blocks=1' + (week ? '&week=' + encodeURIComponent(week) : '');
      fetch(url, { headers: { 'X-Requested-With': 'fetch' } })
        .then(function (res) { return res.json(); })
        .then(function (data) { if (data && data.success) slotsApplyBlocks(data); })
        .catch(function () { /* тихо — неделя просто не переключится */ });
    }

    // Любая форма с data-ajax-form на этой странице отправляется фетчем.
    document.addEventListener('submit', function (e) {
      var form = e.target;
      if (!form.matches || !form.matches('[data-ajax-form]')) return;

      var confirmMsg = form.getAttribute('data-confirm');
      if (confirmMsg && !window.confirm(confirmMsg)) {
        e.preventDefault();
        return;
      }
      e.preventDefault();

      var fd = new FormData(form);
      // Кнопка "Подтвердить" в заявке передаёт action через name="action"
      // value="confirm" на самой кнопке — FormData(form) без обычного
      // сабмита это не подхватывает, добавляем вручную.
      var submitter = e.submitter;
      if (submitter && submitter.name === 'action' && submitter.value) {
        fd.set('action', submitter.value);
      }
      fd.set('ajax', '1');
      if (!fd.get('csrf_token')) fd.set('csrf_token', window.ADMIN_CSRF_TOKEN || '');

      var submitBtn = submitter && submitter.tagName === 'BUTTON' ? submitter : null;
      if (submitBtn) submitBtn.disabled = true;

      fetch('slots.php' + (window.location.search || ''), {
        method: 'POST',
        headers: { 'X-Requested-With': 'fetch' },
        body: fd
      })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (data && data.success) {
            slotsApplyBlocks(data);
            if (slotsCancelModal) slotsCancelModal.classList.remove('open');
          } else {
            alert('Не получилось выполнить действие. Попробуйте ещё раз.');
          }
        })
        .catch(function () {
          alert('Не получилось выполнить действие (нет связи с сервером).');
        })
        .finally(function () {
          if (submitBtn) submitBtn.disabled = false;
        });
    });

    // Enter в поле заметки — сразу сохранить, без отдельного клика на ✓.
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && e.target.matches && e.target.matches('[data-note-form] input[name="note"]')) {
        e.preventDefault();
        e.target.closest('form').requestSubmit();
      }
    });

    // Делегированные клики — работают и после перерисовки блоков, так как
    // слушатель висит на document, а не на конкретных элементах.
    document.addEventListener('click', function (e) {
      // "Отклонить" (блок 1) / "✕" у записи в календаре — открыть модалку причины
      var cancelBtn = e.target.closest('[data-cancel-open]');
      if (cancelBtn && slotsCancelModal) {
        slotsCancelId.value = cancelBtn.dataset.id;
        slotsCancelReason.value = '';
        slotsCancelModal.classList.add('open');
        return;
      }

      // "+" на карточке дня — раскрыть день (если свёрнут) и показать
      // инлайн-форму добавления времени. Отдельная проверка ДО общего
      // тоггла аккордеона, т.к. кнопка физически лежит внутри заголовка.
      var dayAddBtn = e.target.closest('[data-day-add]');
      if (dayAddBtn) {
        var dayItem = dayAddBtn.closest('[data-day-item]');
        if (dayItem && !dayItem.classList.contains('open')) {
          dayItem.classList.add('open');
          var dayHeader = dayItem.querySelector('[data-day-toggle]');
          if (dayHeader) dayHeader.setAttribute('aria-expanded', 'true');
        }
        var addRow = dayItem ? dayItem.querySelector('[data-inline-add-form="' + dayAddBtn.dataset.dayAdd + '"]') : null;
        if (addRow) {
          addRow.hidden = false;
          dayAddBtn.hidden = true;
          var timeInput = addRow.querySelector('input[type="time"]');
          if (timeInput) timeInput.focus();
        }
        return;
      }
      var dayAddCancelBtn = e.target.closest('[data-day-add-cancel]');
      if (dayAddCancelBtn) {
        var addRow2 = dayAddCancelBtn.closest('.slot-inline-add');
        if (addRow2) {
          addRow2.hidden = true;
          var dateVal = addRow2.querySelector('[name="slot_date"]').value;
          var origAddBtn = addRow2.closest('[data-day-item]').querySelector('[data-day-add="' + dateVal + '"]');
          if (origAddBtn) origAddBtn.hidden = false;
        }
        return;
      }

      // Карандаш — инлайн-редактирование имени/заметки записи.
      var noteOpenBtn = e.target.closest('[data-note-edit-open]');
      if (noteOpenBtn) {
        var itemWrap = noteOpenBtn.closest('[data-booking-item]');
        var noteForm = itemWrap ? itemWrap.querySelector('[data-note-form]') : null;
        if (noteForm) {
          noteForm.hidden = false;
          var noteInput = noteForm.querySelector('input[name="note"]');
          if (noteInput) { noteInput.focus(); noteInput.select(); }
        }
        return;
      }
      var noteCancelBtn = e.target.closest('[data-note-edit-cancel]');
      if (noteCancelBtn) {
        var noteForm2 = noteCancelBtn.closest('[data-note-form]');
        if (noteForm2) noteForm2.hidden = true;
        return;
      }

      // Переключение недели в календаре — без перезагрузки страницы.
      var weekNavLink = e.target.closest('[data-week-nav]');
      if (weekNavLink) {
        e.preventDefault();
        var linkUrl = new URL(weekNavLink.href, window.location.href);
        var week = linkUrl.searchParams.get('week') || '';
        history.replaceState(null, '', 'slots.php' + (week ? '?week=' + encodeURIComponent(week) : ''));
        window.SLOTS_CURRENT_WEEK = week;
        slotsRefreshBlocks(week);
        return;
      }

      // Клик по самому заголовку дня (не по кнопкам внутри) — раскрыть/
      // свернуть аккордеон этого дня.
      var dayToggle = e.target.closest('[data-day-toggle]');
      if (dayToggle) {
        var toggleItem = dayToggle.closest('[data-day-item]');
        if (toggleItem) {
          var willOpen = !toggleItem.classList.contains('open');
          toggleItem.classList.toggle('open', willOpen);
          dayToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        }
      }
    });

    // Открытие/закрытие аккордеона дня клавиатурой (Enter/Space), т.к.
    // заголовок — это div[role=button], а не нативная кнопка.
    document.addEventListener('keydown', function (e) {
      if ((e.key === 'Enter' || e.key === ' ') && e.target.matches && e.target.matches('[data-day-toggle]')) {
        e.preventDefault();
        e.target.click();
      }
    });
  }

  // ===== Скользящая плашка-индикатор под активным пунктом навигации
  // панели управления — визуально тот же приём, что и у вкладок на
  // главном сайте (см. updateTabIndicator() в assets/js/script.js).
  // Переходы между разделами админки — это переходы между отдельными
  // PHP-страницами (полная перезагрузка), поэтому "скользить" плашка
  // может только при отрисовке уже открытой страницы — здесь она
  // плавно "наезжает" на активную кнопку сразу после загрузки. =====
  document.querySelectorAll('.admin-nav').forEach(function (navEl) {
    var indicator = navEl.querySelector('.admin-nav-indicator');
    var activeBtn = navEl.querySelector('a.active');
    if (!indicator || !activeBtn) return;

    function place(animate) {
      var navRect = navEl.getBoundingClientRect();
      var btnRect = activeBtn.getBoundingClientRect();
      if (!animate) indicator.style.transition = 'none';
      indicator.style.width = btnRect.width + 'px';
      indicator.style.height = btnRect.height + 'px';
      indicator.style.transform = 'translate(' + (btnRect.left - navRect.left) + 'px,' + (btnRect.top - navRect.top) + 'px)';
      if (!animate) {
        requestAnimationFrame(function () { indicator.style.transition = ''; });
      }
    }

    // Стартуем с нулевой ширины и сразу "выезжаем" на активную кнопку —
    // получается лёгкая анимация появления, а не жёсткий моментальный блок.
    place(false);
    requestAnimationFrame(function () { place(true); });
    window.addEventListener('resize', function () { place(false); });
  });

  // ===== Кнопка-глазок для показа/скрытия пароля в профиль-карточке
  // на "Главной" странице панели управления (см. dashboard.php). =====
  var profileEyeBtn = document.getElementById('adminProfileEyeBtn');
  if (profileEyeBtn) {
    var profileEyeValue = document.getElementById('adminProfilePasswordValue');
    var profileEyeIconShow = profileEyeBtn.querySelector('[data-eye-show]');
    var profileEyeIconHide = profileEyeBtn.querySelector('[data-eye-hide]');
    var realPassword = profileEyeBtn.dataset.password || '';
    var maskedPassword = '••••••••';
    var isRevealed = false;

    profileEyeBtn.addEventListener('click', function () {
      isRevealed = !isRevealed;
      if (profileEyeValue) {
        profileEyeValue.textContent = isRevealed ? (realPassword || maskedPassword) : maskedPassword;
        profileEyeValue.classList.toggle('is-masked', !isRevealed);
      }
      if (profileEyeIconShow) profileEyeIconShow.style.display = isRevealed ? 'none' : '';
      if (profileEyeIconHide) profileEyeIconHide.style.display = isRevealed ? '' : 'none';
    });
  }

  // ===== Переключатель "Информация / Функционал" на странице Настроек —
  // тот же скользящий сегмент-контрол, что и язык/вкладки на сайте. =====
  var settingsSegment = document.getElementById('settingsSegment');
  if (settingsSegment) {
    var segThumb = document.getElementById('settingsSegmentThumb');
    var segButtons = settingsSegment.querySelectorAll('button[data-pane]');
    var panes = document.querySelectorAll('.settings-pane');

    function placeSegThumb(animate) {
      var activeBtn = settingsSegment.querySelector('button.active');
      if (!activeBtn || !segThumb) return;
      if (!animate) segThumb.style.transition = 'none';
      var wrapRect = settingsSegment.getBoundingClientRect();
      var btnRect = activeBtn.getBoundingClientRect();
      segThumb.style.width = btnRect.width + 'px';
      segThumb.style.transform = 'translateX(' + (btnRect.left - wrapRect.left - 4) + 'px)';
      if (!animate) {
        requestAnimationFrame(function () { segThumb.style.transition = ''; });
      }
    }

    function activatePane(name) {
      segButtons.forEach(function (b) { b.classList.toggle('active', b.dataset.pane === name); });
      panes.forEach(function (p) { p.classList.toggle('is-active', p.dataset.pane === name); });
      placeSegThumb(true);
      try { localStorage.setItem('admin_settings_pane', name); } catch (e) {}
    }

    segButtons.forEach(function (btn) {
      btn.addEventListener('click', function () { activatePane(btn.dataset.pane); });
    });

    var savedPane = null;
    try { savedPane = localStorage.getItem('admin_settings_pane'); } catch (e) {}
    if (savedPane && settingsSegment.querySelector('button[data-pane="' + savedPane + '"]')) {
      segButtons.forEach(function (b) { b.classList.toggle('active', b.dataset.pane === savedPane); });
      panes.forEach(function (p) { p.classList.toggle('is-active', p.dataset.pane === savedPane); });
    }
    placeSegThumb(false);
    window.addEventListener('resize', function () { placeSegThumb(false); });
  }

  document.querySelectorAll('.file-input-styled input[type="file"]').forEach(function (input) {
    var wrapper = input.closest('.file-input-styled');
    if (!wrapper) return;
    var nameEl = document.createElement('span');
    nameEl.className = 'file-input-name';
    wrapper.insertAdjacentElement('afterend', nameEl);
    input.addEventListener('change', function () {
      var file = input.files && input.files[0];
      if (!file) {
        nameEl.textContent = '';
        nameEl.classList.remove('has-file');
        return;
      }
      var sizeMb = (file.size / 1024 / 1024).toFixed(1);
      nameEl.textContent = '✓ ' + file.name + ' (' + sizeMb + ' МБ)';
      nameEl.classList.add('has-file');
    });
  });
});
