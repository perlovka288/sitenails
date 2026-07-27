// admin-x7k9m2/assets/admin.js
// Общие мелочи для панели управления:
//  1) кнопки "⇄ Перевести с рус." — автоматически подставляют украинский
//     вариант текста в соответствующее поле (черновик, можно поправить руками).
//  2) живой предпросмотр блока «О мне» (карточка сверху формы обновляется
//     на лету по мере ввода текста, ещё до нажатия "Сохранить").

document.addEventListener('DOMContentLoaded', function () {
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
