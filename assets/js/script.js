document.addEventListener('DOMContentLoaded', function () {
  // ===== Переключение вкладок Отзывы / Прайс / Запись со сдвигом =====
  var track = document.getElementById('panelsTrack');
  var tabButtons = document.querySelectorAll('.tab-btn');
  var tabOrder = ['reviews', 'price', 'booking'];
  var panels = document.querySelectorAll('.panel');

  function setActiveTab(name, animate) {
    var idx = tabOrder.indexOf(name);
    if (idx === -1) idx = 0;

    if (!animate) {
      track.classList.add('no-anim');
    } else {
      track.classList.remove('no-anim');
    }

    track.style.transform = 'translateX(-' + (idx * (100 / 3)) + '%)';
    track.dataset.active = name;

    tabButtons.forEach(function (btn) {
      btn.classList.toggle('active', btn.dataset.tab === name);
    });

    // Полностью скрываем неактивные разделы (не только сдвигом за экран),
    // чтобы соседний раздел не был виден и не попадал в фокус/скринридер.
    panels.forEach(function (panel) {
      panel.classList.toggle('is-active', panel.dataset.panel === name);
    });

    if (!animate) {
      // возвращаем анимацию сразу после первого (мгновенного) позиционирования
      requestAnimationFrame(function () {
        track.classList.remove('no-anim');
      });
    }
  }

  if (track) {
    tabButtons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        setActiveTab(btn.dataset.tab, true);
      });
    });

    // Начальная позиция — без анимации, чтобы не "проскакивало" при загрузке страницы
    setActiveTab(track.dataset.active || 'reviews', false);
  }

  // ===== Модалка "Как к вам обращаться?" =====
  var overlay = document.getElementById('greetOverlay');
  var form = document.getElementById('greetForm');
  var input = document.getElementById('greetInput');
  var greetName = localStorage.getItem('visitor_name');
  var greetTemplate = window.SITE_GREET_TEMPLATE || 'Здравствуйте, %s!';

  function applyGreeting(name) {
    document.querySelectorAll('[data-greet]').forEach(function (el) {
      el.textContent = greetTemplate.replace('%s', name);
    });
  }

  if (greetName) {
    applyGreeting(greetName);
  } else if (overlay) {
    overlay.style.display = 'flex';
  }

  if (form) {
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var value = input.value.trim();
      if (!value) return;
      localStorage.setItem('visitor_name', value);
      applyGreeting(value);
      overlay.style.display = 'none';
    });
  }

  var skipBtn = document.getElementById('greetSkip');
  if (skipBtn) {
    skipBtn.addEventListener('click', function () {
      overlay.style.display = 'none';
    });
  }

  // ===== Модалка "Оставить отзыв" =====
  var reviewOverlay = document.getElementById('reviewModalOverlay');
  var openReviewBtn = document.getElementById('openReviewModalBtn');
  var closeReviewBtn = document.getElementById('closeReviewModalBtn');

  if (reviewOverlay && openReviewBtn) {
    openReviewBtn.addEventListener('click', function () {
      reviewOverlay.classList.add('open');
    });
    if (closeReviewBtn) {
      closeReviewBtn.addEventListener('click', function () {
        reviewOverlay.classList.remove('open');
      });
    }
    reviewOverlay.addEventListener('click', function (ev) {
      if (ev.target === reviewOverlay) reviewOverlay.classList.remove('open');
    });
  }

  // ===== Выбор рейтинга звёздами в форме отзыва =====
  var starWrap = document.getElementById('starPicker');
  if (starWrap) {
    var ratingInput = document.getElementById('ratingInput');
    var stars = starWrap.querySelectorAll('span');
    stars.forEach(function (star, idx) {
      star.addEventListener('click', function () {
        ratingInput.value = idx + 1;
        stars.forEach(function (s, i) {
          s.style.opacity = i <= idx ? '1' : '.3';
        });
      });
    });
  }

  // ===== Красивая квадратная кнопка "+" для фото отзыва с превью =====
  var reviewPhotoInput = document.getElementById('reviewPhotoInput');
  var reviewPhotoPreview = document.getElementById('reviewPhotoPreview');
  var reviewPhotoPlus = document.getElementById('reviewPhotoPlus');
  var reviewPhotoBox = document.getElementById('reviewPhotoBox');
  if (reviewPhotoInput && reviewPhotoPreview && reviewPhotoBox) {
    reviewPhotoInput.addEventListener('change', function () {
      var file = reviewPhotoInput.files && reviewPhotoInput.files[0];
      if (!file) {
        reviewPhotoPreview.style.display = 'none';
        reviewPhotoPlus.style.display = 'block';
        reviewPhotoBox.classList.remove('has-image');
        return;
      }
      var reader = new FileReader();
      reader.onload = function (ev) {
        reviewPhotoPreview.src = ev.target.result;
        reviewPhotoPreview.style.display = 'block';
        reviewPhotoPlus.style.display = 'none';
        reviewPhotoBox.classList.add('has-image');
      };
      reader.readAsDataURL(file);
    });
  }

  // ===== Модалка "Позиция прайса" (админ: добавить / изменить) =====
  var priceModalOverlay = document.getElementById('priceModalOverlay');
  var priceModalTitle = document.getElementById('priceModalTitle');
  var priceModalAction = document.getElementById('priceModalAction');
  var priceModalId = document.getElementById('priceModalId');
  var priceModalCategory = document.getElementById('priceModalCategory');
  var priceModalCategoryUa = document.getElementById('priceModalCategoryUa');
  var priceModalTitleField = document.getElementById('priceModalTitleField');
  var priceModalTitleUa = document.getElementById('priceModalTitleUa');
  var priceModalPrice = document.getElementById('priceModalPrice');
  var openPriceAddBtn = document.getElementById('openPriceAddBtn');
  var closePriceModalBtn = document.getElementById('closePriceModalBtn');
  var priceEditButtons = document.querySelectorAll('.price-edit-btn');

  function openPriceModal(mode, data) {
    if (!priceModalOverlay) return;
    priceModalAction.value = mode === 'edit' ? 'price_edit' : 'price_add';
    priceModalId.value = data && data.id ? data.id : '';
    priceModalCategory.value = data && data.category ? data.category : '';
    priceModalCategoryUa.value = data && data.categoryUa ? data.categoryUa : '';
    priceModalTitleField.value = data && data.title ? data.title : '';
    priceModalTitleUa.value = data && data.titleUa ? data.titleUa : '';
    priceModalPrice.value = data && data.price ? data.price : '';
    priceModalOverlay.classList.add('open');
  }

  if (openPriceAddBtn) {
    openPriceAddBtn.addEventListener('click', function () {
      openPriceModal('add', null);
    });
  }

  priceEditButtons.forEach(function (btn) {
    btn.addEventListener('click', function () {
      openPriceModal('edit', {
        id: btn.dataset.id,
        category: btn.dataset.category,
        categoryUa: btn.dataset.categoryUa,
        title: btn.dataset.title,
        titleUa: btn.dataset.titleUa,
        price: btn.dataset.price
      });
    });
  });

  if (closePriceModalBtn && priceModalOverlay) {
    closePriceModalBtn.addEventListener('click', function () {
      priceModalOverlay.classList.remove('open');
    });
    priceModalOverlay.addEventListener('click', function (ev) {
      if (ev.target === priceModalOverlay) priceModalOverlay.classList.remove('open');
    });
  }

  // ===== Безопасный fetch->json: не падает, если сервер вернул не-JSON =====
  function fetchJSON(url, options) {
    return fetch(url, options).then(function (r) {
      return r.text().then(function (text) {
        var data;
        try {
          data = JSON.parse(text);
        } catch (e) {
          data = { success: false, error: 'bad_response' };
        }
        return data;
      });
    });
  }

  // ===== Календарь записи: текущая неделя Пн–Вс, без переключения =====
  var calendarGrid = document.getElementById('calendarGrid');
  if (calendarGrid) {
    var calEmpty = document.getElementById('calendarEmpty');
    var selectedText = document.getElementById('selectedSlotText');
    var bookingCta = document.getElementById('bookingCta');
    var bookingContacts = document.getElementById('bookingContacts');
    var labels = window.SITE_BOOKING_LABELS || { none: 'Время не выбрано', selected: 'Вы выбрали: ', booked: 'занято', noSlots: 'Свободного времени нет' };

    var selectedSlot = null; // { id, dateLabel, time }

    // ===== Модалка "Свободное время" (админ: добавить / изменить / удалить) =====
    var slotModalOverlay = document.getElementById('slotModalOverlay');
    var slotModalForm = document.getElementById('slotModalForm');
    var slotModalAction = document.getElementById('slotModalAction');
    var slotModalId = document.getElementById('slotModalId');
    var slotModalDate = document.getElementById('slotModalDate');
    var slotModalTime = document.getElementById('slotModalTime');
    var slotModalStatusField = document.getElementById('slotModalStatusField');
    var slotModalBooked = document.getElementById('slotModalBooked');
    var slotModalDeleteBtn = document.getElementById('slotModalDeleteBtn');
    var slotDeleteForm = document.getElementById('slotDeleteForm');
    var slotDeleteId = document.getElementById('slotDeleteId');
    var closeSlotModalBtn = document.getElementById('closeSlotModalBtn');
    var openSlotAddBtn = document.getElementById('openSlotAddBtn');

    function openSlotEditModal(slot, day) {
      if (!slotModalOverlay) return;
      slotModalAction.value = 'slot_edit';
      slotModalId.value = slot.id;
      slotModalDate.value = day.date;
      slotModalTime.value = slot.time;
      slotModalStatusField.style.display = 'block';
      slotModalBooked.checked = !!slot.booked;
      slotModalDeleteBtn.style.display = 'block';
      slotDeleteId.value = slot.id;
      slotModalOverlay.classList.add('open');
    }

    if (openSlotAddBtn && slotModalOverlay) {
      openSlotAddBtn.addEventListener('click', function () {
        slotModalAction.value = 'slot_add';
        slotModalId.value = '';
        slotModalDate.value = '';
        slotModalTime.value = '';
        slotModalStatusField.style.display = 'none';
        slotModalBooked.checked = false;
        slotModalDeleteBtn.style.display = 'none';
        slotModalOverlay.classList.add('open');
      });
    }

    if (slotModalDeleteBtn && slotDeleteForm) {
      slotModalDeleteBtn.addEventListener('click', function () {
        slotDeleteForm.submit();
      });
    }

    if (closeSlotModalBtn && slotModalOverlay) {
      closeSlotModalBtn.addEventListener('click', function () {
        slotModalOverlay.classList.remove('open');
      });
      slotModalOverlay.addEventListener('click', function (ev) {
        if (ev.target === slotModalOverlay) slotModalOverlay.classList.remove('open');
      });
    }

    function fmtDateLabel(day) {
      return day.day + ' ' + day.month + ' (' + day.weekday + ')';
    }

    function renderWeek(data) {
      calendarGrid.innerHTML = '';

      if (!data || data.success === false || !data.days) {
        calEmpty.style.display = 'block';
        calEmpty.textContent = labels.noSlots;
        return;
      }

      var anySlots = false;

      data.days.forEach(function (day) {
        var row = document.createElement('div');
        row.className = 'cal-day-row' + (day.is_past ? ' is-past' : '');

        var dateBox = document.createElement('div');
        dateBox.className = 'cal-day-date';
        dateBox.innerHTML = '<span class="wd">' + day.weekday + '</span><span class="num">' + day.day + '</span>';
        row.appendChild(dateBox);

        var slotsWrap = document.createElement('div');
        slotsWrap.className = 'cal-day-slots';

        if (!day.slots.length) {
          var empty = document.createElement('span');
          empty.className = 'cal-day-empty';
          empty.textContent = '—';
          slotsWrap.appendChild(empty);
        } else {
          day.slots.forEach(function (slot) {
            anySlots = true;
            var btn = document.createElement('button');
            btn.type = 'button';
            var isAdminMode = !!window.SITE_IS_ADMIN;
            var isDisabled = (slot.booked || day.is_past) && !isAdminMode;
            btn.className = 'cal-slot' + (slot.booked ? ' booked' : '') + (isAdminMode ? ' admin-editable' : '');
            btn.textContent = slot.booked ? (slot.time + ' (' + labels.booked + ')') : slot.time;
            btn.disabled = isDisabled;

            if (selectedSlot && selectedSlot.id === slot.id) {
              btn.classList.add('selected');
            }

            btn.addEventListener('click', function () {
              if (isAdminMode) {
                openSlotEditModal(slot, day);
                return;
              }
              if (isDisabled) return;
              calendarGrid.querySelectorAll('.cal-slot.selected').forEach(function (el) {
                el.classList.remove('selected');
              });
              btn.classList.add('selected');
              selectedSlot = { id: slot.id, dateLabel: fmtDateLabel(day), time: slot.time };
              selectedText.textContent = labels.selected + selectedSlot.dateLabel + ', ' + selectedSlot.time;
              bookingContacts.style.display = 'none';
            });

            slotsWrap.appendChild(btn);
          });
        }

        row.appendChild(slotsWrap);
        calendarGrid.appendChild(row);
      });

      calEmpty.style.display = anySlots ? 'none' : 'block';
      if (!anySlots) calEmpty.textContent = labels.noSlots;
    }

    function loadWeek() {
      fetchJSON('get_slots.php').then(renderWeek);
    }

    var bookingConfirmOverlay = document.getElementById('bookingConfirmOverlay');
    var bookingConfirmYes = document.getElementById('bookingConfirmYes');
    var bookingConfirmNo = document.getElementById('bookingConfirmNo');

    function doBookSlot() {
      var body = new URLSearchParams();
      body.set('csrf_token', window.SITE_CSRF_TOKEN || '');
      body.set('slot_id', selectedSlot.id);
      body.set('visitor_name', localStorage.getItem('visitor_name') || '');

      fetchJSON('select_slot.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
      }).then(function (res) {
        bookingContacts.style.display = 'block';
        if (res && res.success) {
          // Обновляем календарь, чтобы слот стал отмечен как занятый
          loadWeek();
        }
      });
    }

    if (bookingCta) {
      bookingCta.addEventListener('click', function () {
        if (!selectedSlot) {
          // Время не выбрано — всё равно показываем контакты, клиент может
          // просто написать удобное время лично.
          bookingContacts.style.display = 'block';
          return;
        }

        // Спрашиваем подтверждение перед отправкой — чтобы случайный
        // повторный клик по кнопке не создавал заявку по ошибке.
        if (bookingConfirmOverlay) {
          bookingConfirmOverlay.classList.add('open');
        } else {
          doBookSlot();
        }
      });
    }

    if (bookingConfirmYes) {
      bookingConfirmYes.addEventListener('click', function () {
        bookingConfirmOverlay.classList.remove('open');
        if (selectedSlot) doBookSlot();
      });
    }
    if (bookingConfirmNo) {
      bookingConfirmNo.addEventListener('click', function () {
        bookingConfirmOverlay.classList.remove('open');
      });
    }
    if (bookingConfirmOverlay) {
      bookingConfirmOverlay.addEventListener('click', function (ev) {
        if (ev.target === bookingConfirmOverlay) bookingConfirmOverlay.classList.remove('open');
      });
    }

    loadWeek();
  }

  // ===== Плавающая кнопка связи =====
  var fabBtn = document.getElementById('fabContactBtn');
  var fabOverlay = document.getElementById('fabOverlay');
  var fabClose = document.getElementById('fabCloseBtn');

  if (fabBtn && fabOverlay) {
    fabBtn.addEventListener('click', function () {
      fabOverlay.classList.add('open');
    });
    if (fabClose) {
      fabClose.addEventListener('click', function () {
        fabOverlay.classList.remove('open');
      });
    }
    fabOverlay.addEventListener('click', function (ev) {
      if (ev.target === fabOverlay) fabOverlay.classList.remove('open');
    });
  }
});
