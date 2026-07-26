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
});
