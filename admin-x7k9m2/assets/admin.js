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
  var preview = document.getElementById('aboutLivePreview');
  if (preview) {
    var pTitle = preview.querySelector('[data-preview="title"]');
    var pGreeting = preview.querySelector('[data-preview="greeting"]');
    var pSubtitle = preview.querySelector('[data-preview="subtitle"]');
    var pBio = preview.querySelector('[data-preview="bio"]');
    var pPhoto = preview.querySelector('[data-preview="photo"]');

    function bindText(fieldId, target, fallback) {
      var field = document.getElementById(fieldId);
      if (!field || !target) return;
      var update = function () {
        var val = (field.value || '').trim();
        target.textContent = val !== '' ? val : (fallback || '');
        target.style.display = val !== '' ? '' : (fallback ? '' : 'none');
      };
      field.addEventListener('input', update);
      update();
    }

    bindText('greeting', pGreeting, '');
    bindText('title', pTitle, 'Заголовок появится здесь');
    bindText('subtitle', pSubtitle, '');
    bindText('bio', pBio, 'Текст «о себе» появится здесь');

    var photoInput = document.querySelector('input[name="photo"]');
    if (photoInput && pPhoto) {
      photoInput.addEventListener('change', function () {
        var file = photoInput.files && photoInput.files[0];
        if (!file) return;
        var reader = new FileReader();
        reader.onload = function (ev) {
          pPhoto.innerHTML = '<img src="' + ev.target.result + '" alt="" style="width:100%;height:100%;object-fit:cover;">';
        };
        reader.readAsDataURL(file);
      });
    }
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
});
