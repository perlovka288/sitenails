document.addEventListener('DOMContentLoaded', function () {
  // ===== Переключение вкладок Отзывы / Прайс / Запись со сдвигом =====
  var track = document.getElementById('panelsTrack');
  var tabButtons = document.querySelectorAll('.tab-btn');
  var tabOrder = ['about', 'reviews', 'price', 'booking'];
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

  var pageHero = document.getElementById('pageHero');

  // ===== Плавное появление блоков при прокрутке =====
  var revealEls = document.querySelectorAll('.reveal-on-scroll');
  var revealObserver = null;
  if (revealEls.length && 'IntersectionObserver' in window) {
    revealObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          revealObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.15, rootMargin: '0px 0px -60px 0px' });
    revealEls.forEach(function (el) { revealObserver.observe(el); });
  } else {
    revealEls.forEach(function (el) { el.classList.add('is-visible'); });
  }

  // Принудительно проявляет блоки текущей активной вкладки — используется
  // сразу после переключения вкладки (см. setActiveTab), чтобы контент не
  // "завис" невидимым из-за особенностей момента срабатывания observer'а.
  function revealVisibleNow() {
    document.querySelectorAll('.panel.is-active .reveal-on-scroll:not(.is-visible)').forEach(function (el) {
      el.classList.add('is-visible');
      if (revealObserver) revealObserver.unobserve(el);
    });
  }

  function updateHeroVisibility(name) {
    if (!pageHero) return;
    // Приветствие скрываем только на вкладке "О мне" — там уже есть своё
    // приветствие внутри карточки. На остальных вкладках (Отзывы/Прайс/
    // Запись) оно остаётся видимым, как и раньше.
    pageHero.style.display = name === 'about' ? 'none' : '';
  }

  function setActiveTab(name, animate) {
    var idx = tabOrder.indexOf(name);
    if (idx === -1) idx = 0;

    updateHeroVisibility(name);

    if (!animate) {
      track.classList.add('no-anim');
    } else {
      track.classList.remove('no-anim');
    }

    track.style.transform = 'translateX(-' + (idx * (100 / tabOrder.length)) + '%)';
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

    // На всякий случай сразу проявляем блоки внутри только что открытой
    // вкладки — она наезжает сдвигом transform, и IntersectionObserver
    // может немного "опоздать" среагировать на этот сдвиг.
    revealVisibleNow();

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
    }

    langOverlay.querySelectorAll('[data-lang]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var chosen = btn.dataset.lang;
        localStorage.setItem('visitor_lang', chosen);
        langOverlay.style.display = 'none';
        if (chosen !== serverLang) {
          goToLang(chosen);
        }
      });
    });
  }

  // ===== Модалка "Оставить отзыв" (тот же модал используется для
  // редактирования своего отзыва — см. .review-edit-btn ниже) =====
  var reviewOverlay = document.getElementById('reviewModalOverlay');
  var openReviewBtn = document.getElementById('openReviewModalBtn');
  var closeReviewBtn = document.getElementById('closeReviewModalBtn');
  var reviewForm = document.getElementById('reviewForm');
  var reviewModalTitle = document.getElementById('reviewModalTitle');
  var reviewSubmitBtn = document.getElementById('reviewSubmitBtn');
  var reviewIdInput = document.getElementById('reviewIdInput');
  var reviewAuthorInput = document.getElementById('reviewAuthorInput');
  var reviewMessageInput = document.getElementById('reviewMessageInput');
  var reviewNewTitle = reviewModalTitle ? reviewModalTitle.textContent : '';
  var reviewNewBtnText = reviewSubmitBtn ? reviewSubmitBtn.textContent : '';

  function setRatingStars(n) {
    var ratingInput = document.getElementById('ratingInput');
    var stars = document.querySelectorAll('#starPicker .star');
    if (ratingInput) ratingInput.value = n;
    stars.forEach(function (s, i) { s.classList.toggle('selected', i <= n - 1); });
  }

  function resetReviewFormToNew() {
    if (!reviewForm) return;
    reviewForm.reset();
    if (reviewIdInput) reviewIdInput.value = '';
    setRatingStars(5);
    if (reviewModalTitle) reviewModalTitle.textContent = reviewNewTitle;
    if (reviewSubmitBtn) reviewSubmitBtn.textContent = reviewNewBtnText;
  }

  if (reviewOverlay && openReviewBtn) {
    openReviewBtn.addEventListener('click', function () {
      resetReviewFormToNew();
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

  // Кнопки ✏️ у своего отзыва (доступны только автору, в течение 1-2
  // часов после публикации — см. reviewOwnedByCurrentUser() в PHP).
  document.querySelectorAll('.review-edit-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (!reviewOverlay) return;
      if (reviewIdInput) reviewIdInput.value = btn.dataset.id || '';
      if (reviewAuthorInput) reviewAuthorInput.value = btn.dataset.name || '';
      if (reviewMessageInput) reviewMessageInput.value = btn.dataset.message || '';
      setRatingStars(parseInt(btn.dataset.rating, 10) || 5);
      if (reviewModalTitle) reviewModalTitle.textContent = window.SITE_REVIEW_EDIT_TITLE || 'Редактировать отзыв';
      if (reviewSubmitBtn) reviewSubmitBtn.textContent = window.SITE_REVIEW_EDIT_SUBMIT || 'Сохранить изменения';
      reviewOverlay.classList.add('open');
    });
  });

  // ===== Выбор рейтинга звёздами в форме отзыва (жёлтые, "выбранные") =====
  var starWrap = document.getElementById('starPicker');
  if (starWrap) {
    var stars = starWrap.querySelectorAll('.star');
    stars.forEach(function (star, idx) {
      star.addEventListener('click', function () {
        setRatingStars(idx + 1);
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

  // ===== Лайтбокс: открыть фото/видео на весь экран, закрыть крестиком =====
  var lightboxOverlay = document.getElementById('photoLightboxOverlay');
  var lightboxImg = document.getElementById('photoLightboxImg');
  var lightboxVideo = document.getElementById('photoLightboxVideo');
  var lightboxClose = document.getElementById('photoLightboxClose');

  function closeLightbox() {
    lightboxOverlay.classList.remove('open', 'lightbox-mode-photo', 'lightbox-mode-video');
    if (lightboxImg) lightboxImg.src = '';
    if (lightboxVideo) {
      lightboxVideo.pause();
      lightboxVideo.src = '';
    }
  }

  if (lightboxOverlay && lightboxImg) {
    document.querySelectorAll('.review-photo-thumb, .widget-photo-thumb').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (lightboxVideo) { lightboxVideo.pause(); lightboxVideo.src = ''; }
        lightboxImg.src = btn.dataset.photoSrc;
        lightboxOverlay.classList.remove('lightbox-mode-video');
        lightboxOverlay.classList.add('open', 'lightbox-mode-photo');
      });
    });
    document.querySelectorAll('.widget-video-thumb').forEach(function (btn) {
      btn.addEventListener('click', function () {
        lightboxImg.src = '';
        if (lightboxVideo) {
          lightboxVideo.src = btn.dataset.videoSrc;
          lightboxOverlay.classList.remove('lightbox-mode-photo');
          lightboxOverlay.classList.add('open', 'lightbox-mode-video');
          lightboxVideo.play().catch(function () {});
        }
      });
    });
    if (lightboxClose) {
      lightboxClose.addEventListener('click', closeLightbox);
    }
    lightboxOverlay.addEventListener('click', function (ev) {
      if (ev.target === lightboxOverlay) closeLightbox();
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
  var openPriceAddBtns = document.querySelectorAll('[data-price-add-open]');
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

  openPriceAddBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      openPriceModal('add', null);
    });
  });

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
      fetchJSON('get_slots.php?offset=' + weekOffset).then(function (data) {
        // Если во время выбора времени (пока открыт сайт) кто-то другой
        // успел занять именно этот слот — не даём тихо остаться с уже
        // недействительным выбором: снимаем его и явно предупреждаем.
        if (selectedSlot && data && data.days) {
          var stillFree = data.days.some(function (day) {
            return day.slots.some(function (s) { return s.id === selectedSlot.id && !s.booked; });
          });
          if (!stillFree) {
            selectedSlot = null;
            if (selectedText) selectedText.textContent = labels.none;
            if (bookingFormOverlay && bookingFormOverlay.classList.contains('open')) {
              closeBookingForm();
            }
            if (bookingFormError) {
              bookingFormError.textContent = window.SITE_BOOKING_SLOT_TAKEN_ERROR || 'Это время только что заняли. Выберите, пожалуйста, другое.';
              bookingFormError.style.display = 'block';
            }
          }
        }
        renderWeek(data);
      });
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

    // ===== Модалка "Анкета записи" (открывается вместо диалога подтверждения) =====
    var bookingFormOverlay = document.getElementById('bookingFormOverlay');
    var bookingForm = document.getElementById('bookingForm');
    var bookingFormTime = document.getElementById('bookingFormTime');
    var bookingFormSlotId = document.getElementById('bookingFormSlotId');
    var bookingFormSubmit = document.getElementById('bookingFormSubmit');
    var bookingFormError = document.getElementById('bookingFormError');
    var bookingServiceError = document.getElementById('bookingServiceError');
    var closeBookingFormBtn = document.getElementById('closeBookingFormBtn');
    var bookingSuccessOverlay = document.getElementById('bookingSuccessOverlay');
    var closeBookingSuccessBtn = document.getElementById('closeBookingSuccessBtn');
    var bookingFormName = document.getElementById('bookingFormName');

    function openBookingForm() {
      if (!bookingFormOverlay || !selectedSlot) return;
      bookingFormSlotId.value = selectedSlot.id;
      bookingFormTime.textContent = selectedSlot.dateLabel + ', ' + selectedSlot.time;
      if (bookingFormName && !bookingFormName.value) {
        bookingFormName.value = localStorage.getItem('visitor_name') || '';
      }
      bookingFormError.style.display = 'none';
      bookingServiceError.style.display = 'none';
      bookingFormOverlay.classList.add('open');
    }

    function closeBookingForm() {
      if (bookingFormOverlay) bookingFormOverlay.classList.remove('open');
    }

    if (bookingCta) {
      bookingCta.addEventListener('click', function () {
        if (!selectedSlot) {
          // Время не выбрано — всё равно показываем контакты, клиент может
          // просто написать удобное время лично.
          bookingContacts.style.display = 'block';
          return;
        }
        openBookingForm();
      });
    }

    if (closeBookingFormBtn) {
      closeBookingFormBtn.addEventListener('click', closeBookingForm);
    }
    if (bookingFormOverlay) {
      bookingFormOverlay.addEventListener('click', function (ev) {
        if (ev.target === bookingFormOverlay) closeBookingForm();
      });
    }

    if (bookingForm) {
      bookingForm.addEventListener('submit', function (ev) {
        ev.preventDefault();

        var manicure = document.getElementById('bookingServiceManicure').value;
        var pedicure = document.getElementById('bookingServicePedicure').value;
        var extra = document.getElementById('bookingServiceExtra').value;

        if (!manicure && !pedicure && !extra) {
          bookingServiceError.style.display = 'block';
          return;
        }
        bookingServiceError.style.display = 'none';

        var contactRadio = bookingForm.querySelector('input[name="contact_method"]:checked');

        var body = new URLSearchParams();
        body.set('csrf_token', window.SITE_CSRF_TOKEN || '');
        body.set('slot_id', bookingFormSlotId.value);
        body.set('client_name', bookingForm.client_name.value);
        body.set('phone', bookingForm.phone.value);
        body.set('service_manicure', manicure);
        body.set('service_pedicure', pedicure);
        body.set('service_extra', extra);
        body.set('contact_method', contactRadio ? contactRadio.value : '');

        bookingFormSubmit.disabled = true;
        bookingFormError.style.display = 'none';

        fetchJSON('select_slot.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: body.toString()
        }).then(function (res) {
          bookingFormSubmit.disabled = false;
          if (res && res.success) {
            localStorage.setItem('visitor_name', bookingForm.client_name.value);
            closeBookingForm();
            if (bookingSuccessOverlay) bookingSuccessOverlay.classList.add('open');
            loadWeek();
            selectedSlot = null;
            selectedText.textContent = labels.none;
          } else {
            bookingFormError.textContent = window.SITE_BOOKING_FORM_ERROR || 'Не получилось отправить заявку, попробуйте ещё раз.';
            bookingFormError.style.display = 'block';
          }
        }).catch(function () {
          bookingFormSubmit.disabled = false;
          bookingFormError.textContent = window.SITE_BOOKING_FORM_ERROR || 'Не получилось отправить заявку, попробуйте ещё раз.';
          bookingFormError.style.display = 'block';
        });
      });
    }

    if (closeBookingSuccessBtn && bookingSuccessOverlay) {
      closeBookingSuccessBtn.addEventListener('click', function () {
        bookingSuccessOverlay.classList.remove('open');
      });
      bookingSuccessOverlay.addEventListener('click', function (ev) {
        if (ev.target === bookingSuccessOverlay) bookingSuccessOverlay.classList.remove('open');
      });
    }

    loadWeek();
    // Обновляем календарь сам по себе, пока клиент выбирает время — без
    // этого свободное время могло "зависнуть" в открытой вкладке даже
    // после того, как кто-то другой на него записался, а мама подтвердила
    // заявку (слот занимается автоматически при подтверждении, см.
    // admin-x7k9m2/bookings.php). Финальная защита всё равно на сервере
    // (select_slot.php перепроверяет слот при самой отправке), это просто
    // чтобы клиент не выбирал уже занятое время, глядя на устаревший экран.
    setInterval(loadWeek, 20000);
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

  // ===== Виджеты: обложка видео (первый кадр вместо чёрного экрана) =====
  // У части браузеров (особенно iOS/Safari) видео с preload="metadata" не
  // рисует кадр, пока не поставить currentTime вручную после loadedmetadata.
  document.querySelectorAll('video[data-video-cover]').forEach(function (video) {
    function nudge() {
      if (video.currentTime === 0) {
        try { video.currentTime = 0.1; } catch (e) { /* ignore */ }
      }
    }
    video.addEventListener('loadedmetadata', nudge);
    if (video.readyState >= 1) nudge();
  });

  // ===== Виджеты: обложка PDF (первая страница вместо иконки 📄) =====
  // Рендерим первую страницу через pdf.js прямо в браузере — без сжатия
  // и без плагинов на хостинге. Библиотека подгружается только если на
  // странице реально есть PDF-виджеты (чтобы не тратить трафик зря).
  var pdfCovers = document.querySelectorAll('[data-pdf-src]');
  if (pdfCovers.length) {
    var pdfjsScript = document.createElement('script');
    pdfjsScript.src = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
    pdfjsScript.onload = function () {
      if (!window.pdfjsLib) return;
      window.pdfjsLib.GlobalWorkerOptions.workerSrc =
        'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

      pdfCovers.forEach(function (cover) {
        var src = cover.dataset.pdfSrc;
        if (!src) return;

        window.pdfjsLib.getDocument(src).promise
          .then(function (pdf) { return pdf.getPage(1); })
          .then(function (page) {
            var targetWidth = cover.clientWidth || 220;
            var targetHeight = cover.clientHeight || 150;
            var baseViewport = page.getViewport({ scale: 1 });
            var scale = Math.max(targetWidth / baseViewport.width, targetHeight / baseViewport.height) * 1.4;
            var viewport = page.getViewport({ scale: scale });

            var canvas = document.createElement('canvas');
            canvas.width = viewport.width;
            canvas.height = viewport.height;

            return page.render({ canvasContext: canvas.getContext('2d'), viewport: viewport }).promise
              .then(function () {
                cover.innerHTML = '';
                cover.appendChild(canvas);
              });
          })
          .catch(function () {
            // Если рендер не удался (нет сети до CDN, битый файл и т.п.) —
            // просто остаётся иконка 📄, ничего не ломается.
          });
      });
    };
    document.head.appendChild(pdfjsScript);
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

    // Стрелки показываем только если контент реально не помещается —
    // иначе при 1-2 карточках стрелка "висела" далеко от единственной
    // карточки и выглядело криво.
    function updateArrows() {
      var scrollable = track.scrollWidth > track.clientWidth + 4;
      if (prevBtn) prevBtn.style.display = scrollable ? 'flex' : 'none';
      if (nextBtn) nextBtn.style.display = scrollable ? 'flex' : 'none';
      if (prevBtn) prevBtn.disabled = track.scrollLeft <= 4;
      if (nextBtn) nextBtn.disabled = track.scrollLeft >= track.scrollWidth - track.clientWidth - 4;
    }

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
    track.addEventListener('scroll', updateArrows);
    window.addEventListener('resize', updateArrows);
    updateArrows();
  });
});
