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

  // ===== Календарь записи (по неделям) =====
  var calendarGrid = document.getElementById('calendarGrid');
  if (calendarGrid) {
    var calEmpty = document.getElementById('calendarEmpty');
    var prevBtn = document.getElementById('calPrev');
    var nextBtn = document.getElementById('calNext');
    var selectedText = document.getElementById('selectedSlotText');
    var bookingCta = document.getElementById('bookingCta');
    var bookingContacts = document.getElementById('bookingContacts');
    var labels = window.SITE_BOOKING_LABELS || { none: 'Время не выбрано', selected: 'Вы выбрали: ', booked: 'занято', noSlots: 'Свободного времени нет' };

    var currentWeekStart = null;
    var selectedSlot = null; // { id, dateLabel, time }

    function fmtDateLabel(day) {
      return day.day + ' ' + day.month + ' (' + day.weekday + ')';
    }

    function renderWeek(data) {
      currentWeekStart = data.week_start;
      calendarGrid.innerHTML = '';
      var anySlots = false;

      data.days.forEach(function (day) {
        var col = document.createElement('div');
        col.className = 'cal-day';

        var head = document.createElement('div');
        head.className = 'cal-day-head';
        head.innerHTML = day.weekday + '<span class="num">' + day.day + '</span>';
        col.appendChild(head);

        if (!day.slots.length) {
          var empty = document.createElement('div');
          empty.className = 'cal-day-empty';
          empty.textContent = '—';
          col.appendChild(empty);
        } else {
          day.slots.forEach(function (slot) {
            anySlots = true;
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'cal-slot' + (slot.booked ? ' booked' : '');
            btn.textContent = slot.booked ? (slot.time + ' (' + labels.booked + ')') : slot.time;
            btn.disabled = slot.booked;

            if (selectedSlot && selectedSlot.id === slot.id) {
              btn.classList.add('selected');
            }

            btn.addEventListener('click', function () {
              if (slot.booked) return;
              calendarGrid.querySelectorAll('.cal-slot.selected').forEach(function (el) {
                el.classList.remove('selected');
              });
              btn.classList.add('selected');
              selectedSlot = { id: slot.id, dateLabel: fmtDateLabel(day), time: slot.time };
              selectedText.textContent = labels.selected + selectedSlot.dateLabel + ', ' + selectedSlot.time;
              bookingContacts.style.display = 'none';
            });

            col.appendChild(btn);
          });
        }

        calendarGrid.appendChild(col);
      });

      calEmpty.style.display = anySlots ? 'none' : 'block';
      if (!anySlots) calEmpty.textContent = labels.noSlots;
    }

    function loadWeek(weekStart) {
      var url = 'get_slots.php' + (weekStart ? '?week_start=' + encodeURIComponent(weekStart) : '');
      fetch(url)
        .then(function (r) { return r.json(); })
        .then(renderWeek)
        .catch(function () {
          calEmpty.style.display = 'block';
        });
    }

    if (prevBtn) {
      prevBtn.addEventListener('click', function () {
        fetch('get_slots.php' + (currentWeekStart ? '?week_start=' + encodeURIComponent(currentWeekStart) : ''))
          .then(function (r) { return r.json(); })
          .then(function (data) { loadWeek(data.week_prev); });
      });
    }

    if (nextBtn) {
      nextBtn.addEventListener('click', function () {
        fetch('get_slots.php' + (currentWeekStart ? '?week_start=' + encodeURIComponent(currentWeekStart) : ''))
          .then(function (r) { return r.json(); })
          .then(function (data) { loadWeek(data.week_next); });
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

        var body = new URLSearchParams();
        body.set('csrf_token', window.SITE_CSRF_TOKEN || '');
        body.set('slot_id', selectedSlot.id);
        body.set('visitor_name', localStorage.getItem('visitor_name') || '');

        fetch('select_slot.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: body.toString()
        })
          .then(function (r) { return r.json(); })
          .then(function (res) {
            bookingContacts.style.display = 'block';
            if (res.success) {
              // Обновляем календарь, чтобы слот стал отмечен как занятый
              loadWeek(currentWeekStart);
            }
          })
          .catch(function () {
            bookingContacts.style.display = 'block';
          });
      });
    }

    loadWeek();
  }
});
