document.addEventListener('DOMContentLoaded', function () {
  // ===== Переключение вкладок Отзывы / Прайс / Запись со сдвигом =====
  var track = document.getElementById('panelsTrack');
  var tabButtons = document.querySelectorAll('.tab-btn');
  var tabOrder = ['reviews', 'price', 'booking'];
  var panels = document.querySelectorAll('.panel');
  var panelsViewport = document.querySelector('.panels-viewport');

  // Высота блока со вкладками подстраивается под контент активной вкладки,
  // а не под самую высокую из трёх (раньше высота была "статической" —
  // общей для всех вкладок, из-за чего внизу оставался лишний пустой отступ).
  function updateViewportHeight() {
    if (!panelsViewport) return;
    var activePanel = document.querySelector('.panel.is-active');
    if (activePanel) {
      panelsViewport.style.height = activePanel.scrollHeight + 'px';
    }
  }

  if (panelsViewport && panels.length && window.ResizeObserver) {
    var panelsResizeObserver = new ResizeObserver(function () {
      updateViewportHeight();
    });
    panels.forEach(function (panel) { panelsResizeObserver.observe(panel); });
  }
  window.addEventListener('resize', updateViewportHeight);

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

    updateViewportHeight();

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

  function showGreetIfNeeded() {
    if (greetName) {
      applyGreeting(greetName);
    } else if (overlay) {
      overlay.style.display = 'flex';
    }
  }

  // Ручное переключение языка (кнопки РУС/УКР в шапке) тоже запоминаем,
  // чтобы модалка выбора языка больше не всплывала при следующих визитах.
  document.querySelectorAll('.lang-switch a[href]').forEach(function (a) {
    a.addEventListener('click', function () {
      var m = a.getAttribute('href').match(/[?&]lang=(ru|ua)/);
      if (m) localStorage.setItem('visitor_lang', m[1]);
    });
  });

  // ===== Модалка выбора языка (показывается первой, при самом первом визите) =====
  var langOverlay = document.getElementById('langOverlay');
  var savedLang = localStorage.getItem('visitor_lang');
  var serverLang = window.SITE_LANG_CODE || 'ru';

  function goToLang(code) {
    var url = new URL(window.location.href);
    url.searchParams.set('lang', code);
    window.location.href = url.toString();
  }

  if (langOverlay) {
    if (!savedLang) {
      // Язык ещё ни разу не выбирали — спрашиваем.
      langOverlay.style.display = 'flex';
    } else if (savedLang !== serverLang) {
      // Язык выбран раньше, но текущая страница отрисована на другом
      // языке (например, зашли по ссылке без ?lang=) — доводим до нужного.
      goToLang(savedLang);
    } else {
      showGreetIfNeeded();
    }

    langOverlay.querySelectorAll('[data-lang]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var chosen = btn.dataset.lang;
        localStorage.setItem('visitor_lang', chosen);
        langOverlay.style.display = 'none';
        if (chosen !== serverLang) {
          goToLang(chosen);
        } else {
          showGreetIfNeeded();
        }
      });
    });
  } else {
    showGreetIfNeeded();
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

  // ===== Выбор рейтинга звёздами в форме отзыва (жёлтые, "выбранные") =====
  var starWrap = document.getElementById('starPicker');
  if (starWrap) {
    var ratingInput = document.getElementById('ratingInput');
    var stars = starWrap.querySelectorAll('.star');
    stars.forEach(function (star, idx) {
      star.addEventListener('click', function () {
        ratingInput.value = idx + 1;
        stars.forEach(function (s, i) {
          s.classList.toggle('selected', i <= idx);
        });
      });
    });
  }

  // ===== Красивые квадратные кнопки "+" для фото отзыва (до 3 шт.) с превью =====
  document.querySelectorAll('.photo-upload-slot').forEach(function (slot) {
    var input = slot.querySelector('.photo-upload-input');
    var box = slot.querySelector('.photo-upload-box');
    var preview = slot.querySelector('.photo-upload-preview');
    var plus = slot.querySelector('.photo-upload-plus');
    if (!input || !box || !preview || !plus) return;

    input.addEventListener('change', function () {
      var file = input.files && input.files[0];
      if (!file) {
        preview.style.display = 'none';
        plus.style.display = 'flex';
        box.classList.remove('has-image');
        return;
      }
      var reader = new FileReader();
      reader.onload = function (ev) {
        preview.src = ev.target.result;
        preview.style.display = 'block';
        plus.style.display = 'none';
        box.classList.add('has-image');
      };
      reader.readAsDataURL(file);
    });
  });

  // ===== Лайтбокс: открыть фото отзыва на весь экран, закрыть крестиком =====
  var lightboxOverlay = document.getElementById('photoLightboxOverlay');
  var lightboxImg = document.getElementById('photoLightboxImg');
  var lightboxClose = document.getElementById('photoLightboxClose');

  if (lightboxOverlay && lightboxImg) {
    document.querySelectorAll('.review-photo-thumb, .widget-photo-thumb').forEach(function (btn) {
      btn.addEventListener('click', function () {
        lightboxImg.src = btn.dataset.photoSrc;
        lightboxOverlay.classList.add('open');
      });
    });
    if (lightboxClose) {
      lightboxClose.addEventListener('click', function () {
        lightboxOverlay.classList.remove('open');
        lightboxImg.src = '';
      });
    }
    lightboxOverlay.addEventListener('click', function (ev) {
      if (ev.target === lightboxOverlay) {
        lightboxOverlay.classList.remove('open');
        lightboxImg.src = '';
      }
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

      // ===== Стрелочки листания недель (‹ ›), окно примерно в 30 дней =====
      if (calPrevBtn) calPrevBtn.disabled = !data.can_prev;
      if (calNextBtn) calNextBtn.disabled = !data.can_next;
      if (calWeekLabel && data.days.length) {
        var first = data.days[0];
        var last = data.days[data.days.length - 1];
        calWeekLabel.textContent = (first.month === last.month)
          ? (first.day + '–' + last.day + ' ' + last.month)
          : (first.day + ' ' + first.month + ' – ' + last.day + ' ' + last.month);
      }
    }

    var calPrevBtn = document.getElementById('calPrevBtn');
    var calNextBtn = document.getElementById('calNextBtn');
    var calWeekLabel = document.getElementById('calWeekLabel');
    var weekOffset = 0;

    function loadWeek() {
      fetchJSON('get_slots.php?offset=' + weekOffset).then(renderWeek);
    }

    if (calPrevBtn) {
      calPrevBtn.addEventListener('click', function () {
        if (weekOffset > 0) {
          weekOffset--;
          loadWeek();
        }
      });
    }
    if (calNextBtn) {
      calNextBtn.addEventListener('click', function () {
        weekOffset++;
        loadWeek();
      });
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

  // ===== Виджеты: горизонтальные карусели (галереи/видео/сертификаты) =====
  document.querySelectorAll('[data-carousel]').forEach(function (track) {
    var wrap = track.closest('.widget-carousel-wrap');
    if (!wrap) return;
    var prevBtn = wrap.querySelector('[data-carousel-prev]');
    var nextBtn = wrap.querySelector('[data-carousel-next]');
    var scrollStep = function () {
      var firstItem = track.querySelector('.widget-carousel-item');
      return firstItem ? firstItem.getBoundingClientRect().width + 12 : 260;
    };
    if (prevBtn) {
      prevBtn.addEventListener('click', function () {
        track.scrollBy({ left: -scrollStep(), behavior: 'smooth' });
      });
    }
    if (nextBtn) {
      nextBtn.addEventListener('click', function () {
        track.scrollBy({ left: scrollStep(), behavior: 'smooth' });
      });
    }
  });
});
