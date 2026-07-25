document.addEventListener('DOMContentLoaded', function () {
  // ===== Переключение вкладок Отзывы / Прайс / Запись со сдвигом =====
  var track = document.getElementById('panelsTrack');
  var tabButtons = document.querySelectorAll('.tab-btn');
  var tabOrder = ['reviews', 'price', 'booking'];

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

  function applyGreeting(name) {
    document.querySelectorAll('[data-greet]').forEach(function (el) {
      el.textContent = 'Здравствуйте, ' + name + '!';
    });
    // Автоматически подставляем имя в поле "Имя" формы записи, если оно пустое
    var bookingName = document.getElementById('bookingName');
    if (bookingName && !bookingName.value) {
      bookingName.value = name;
    }
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
});
