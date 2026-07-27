/**
 * assets/js/video-compress.js
 *
 * Загружает видео из раздела "Достижения" НАПРЯМУЮ из браузера в облако
 * (Cloudinary), минуя сам хостинг. Зачем: бесплатные хостинги режут
 * загрузку файлов через PHP (upload_max_filesize/post_max_size) намного
 * жёстче, чем указано в настройках сайта — даже видео весом 20-30 МБ
 * могло быть отклонено. Раньше мы пытались сжать видео в браузере и всё
 * равно отправить его на сервер — но и сжатый файл мог не пройти лимиты
 * хостинга. Теперь видео (по возможности — сжатое, для скорости) летит
 * прямо в Cloudinary через их публичный "unsigned" upload endpoint, а на
 * сервер уходит только маленькая ссылка на готовый файл в текстовых полях
 * формы (cloud_url / cloud_public_id) — лимиты хостинга тут вообще не
 * при чём, так как большой файл сервер даже не видит.
 *
 * Сжатие (через video + canvas + MediaRecorder) остаётся как раньше —
 * оно просто уменьшает файл ПЕРЕД отправкой в облако, чтобы загрузка
 * была быстрее на медленном интернете. Если браузер не поддерживает
 * MediaRecorder (очень старые браузеры) — сжатие пропускается, но
 * загрузка в облако всё равно происходит с оригинальным файлом.
 *
 * Если в браузере вообще отключён JavaScript — форма отправляется как
 * обычная HTML-форма, и сервер обрабатывает файл по старому способу
 * (см. admin-x7k9m2/widget_items.php), с обычными лимитами хостинга.
 *
 * Работает с ЛЮБЫМ количеством форм на странице (.js-widget-upload-form
 * с data-type="video") — и на странице widget_items.php, и в модалках
 * на вкладке «О мне».
 */
(function () {
  var CLOUDINARY_CLOUD_NAME = 'ds6buwmpj';
  var CLOUDINARY_UPLOAD_PRESET = 'widgets_unsigned';
  var CLOUDINARY_UPLOAD_URL = 'https://api.cloudinary.com/v1_1/' + CLOUDINARY_CLOUD_NAME + '/video/upload';

  var MAX_DIMENSION = 960;         // сторона по большей стороне кадра при сжатии
  var TARGET_BITRATE = 1_500_000;  // ~1.5 Мбит/с — компромисс качество/размер
  var SOFT_MAX_BYTES = 300 * 1024 * 1024; // защита от случайной загрузки огромного файла

  function pickMimeType() {
    var candidates = ['video/webm;codecs=vp9,opus', 'video/webm;codecs=vp8,opus', 'video/webm'];
    for (var i = 0; i < candidates.length; i++) {
      if (MediaRecorder.isTypeSupported(candidates[i])) return candidates[i];
    }
    return 'video/webm';
  }

  function compressVideo(file) {
    return new Promise(function (resolve, reject) {
      var video = document.createElement('video');
      video.muted = false;
      video.playsInline = true;
      video.src = URL.createObjectURL(file);

      video.addEventListener('loadedmetadata', function () {
        var w = video.videoWidth, h = video.videoHeight;
        var scale = Math.min(1, MAX_DIMENSION / Math.max(w, h));
        var outW = Math.round(w * scale / 2) * 2; // чётные размеры для кодека
        var outH = Math.round(h * scale / 2) * 2;

        var canvas = document.createElement('canvas');
        canvas.width = outW;
        canvas.height = outH;
        var ctx = canvas.getContext('2d');

        var canvasStream = canvas.captureStream(30);

        // Пробуем подмешать оригинальную аудиодорожку через Web Audio API.
        var combinedStream = canvasStream;
        try {
          var audioCtx = new (window.AudioContext || window.webkitAudioContext)();
          var source = audioCtx.createMediaElementSource(video);
          var dest = audioCtx.createMediaStreamDestination();
          source.connect(dest);
          dest.stream.getAudioTracks().forEach(function (track) {
            combinedStream.addTrack(track);
          });
        } catch (e) {
          // Нет доступа к аудио (например, ограничения браузера) —
          // сжимаем без звука, это лучше, чем не сжимать вовсе.
        }

        var recorder = new MediaRecorder(combinedStream, {
          mimeType: pickMimeType(),
          videoBitsPerSecond: TARGET_BITRATE,
        });
        var chunks = [];
        recorder.ondataavailable = function (e) {
          if (e.data && e.data.size) chunks.push(e.data);
        };
        recorder.onstop = function () {
          URL.revokeObjectURL(video.src);
          resolve(new Blob(chunks, { type: 'video/webm' }));
        };
        recorder.onerror = function (e) {
          reject(e.error || new Error('MediaRecorder error'));
        };

        var drawTimer = null;
        function drawFrame() {
          ctx.drawImage(video, 0, 0, outW, outH);
          drawTimer = requestAnimationFrame(drawFrame);
        }

        video.addEventListener('ended', function () {
          cancelAnimationFrame(drawTimer);
          recorder.stop();
        });

        video.play().then(function () {
          recorder.start();
          drawFrame();
        }).catch(reject);
      });

      video.addEventListener('error', function () {
        reject(new Error('Не удалось прочитать видеофайл'));
      });
    });
  }

  // Загружает готовый файл прямо в Cloudinary (unsigned preset) и
  // возвращает Promise с { secure_url, public_id }.
  function uploadToCloudinary(file, onProgress) {
    return new Promise(function (resolve, reject) {
      var xhr = new XMLHttpRequest();
      xhr.open('POST', CLOUDINARY_UPLOAD_URL, true);

      xhr.upload.onprogress = function (e) {
        if (e.lengthComputable && onProgress) {
          onProgress(e.loaded / e.total);
        }
      };

      xhr.onload = function () {
        if (xhr.status >= 200 && xhr.status < 300) {
          try {
            resolve(JSON.parse(xhr.responseText));
          } catch (e) {
            reject(new Error('Некорректный ответ облака'));
          }
        } else {
          var message = 'облако вернуло ошибку ' + xhr.status;
          try {
            var errData = JSON.parse(xhr.responseText);
            if (errData && errData.error && errData.error.message) {
              message = errData.error.message;
            }
          } catch (e) { /* ignore */ }
          reject(new Error(message));
        }
      };

      xhr.onerror = function () {
        reject(new Error('ошибка сети при загрузке в облако'));
      };

      var formData = new FormData();
      formData.append('file', file, file.name);
      formData.append('upload_preset', CLOUDINARY_UPLOAD_PRESET);
      xhr.send(formData);
    });
  }

  // Подключает загрузку в облако к ОДНОЙ форме — вызывается для каждой
  // найденной формы загрузки видео на странице.
  function wireForm(form) {
    var fileInput = form.querySelector('.js-widget-file-input');
    var toggle = form.querySelector('.js-compress-toggle');
    var status = form.querySelector('.js-compress-status');
    var submitBtn = form.querySelector('.js-widget-submit-btn');
    var cloudUrlInput = form.querySelector('.js-cloud-url');
    var cloudPublicIdInput = form.querySelector('.js-cloud-public-id');
    if (!fileInput || !submitBtn || !cloudUrlInput) return;

    function setStatus(text) {
      if (!status) return;
      status.textContent = text;
      status.style.display = text ? 'block' : 'none';
    }

    form.addEventListener('submit', function (ev) {
      if (form.dataset.uploaded === '1') {
        return; // файл уже загружен в облако, ссылка в форме — отправляем как есть
      }

      var file = fileInput.files && fileInput.files[0];
      if (!file || !file.type.startsWith('video/')) {
        return;
      }

      if (file.size > SOFT_MAX_BYTES) {
        ev.preventDefault();
        setStatus('Файл слишком большой (максимум ' + Math.round(SOFT_MAX_BYTES / 1024 / 1024) + ' МБ). Выберите файл поменьше.');
        return;
      }

      ev.preventDefault();
      submitBtn.disabled = true;

      var shouldCompress = (toggle ? toggle.checked : true)
        && window.MediaRecorder
        && file.size > 8 * 1024 * 1024;

      var prepared = shouldCompress
        ? (function () {
            setStatus('Сжимаем видео в браузере, это может занять до минуты…');
            return compressVideo(file).then(function (blob) {
              if (blob.size >= file.size) {
                // Сжатая версия не меньше оригинала (бывает с уже сжатыми
                // видео) — грузим оригинал, чтобы не терять качество зря.
                return file;
              }
              var before = (file.size / 1024 / 1024).toFixed(1);
              var after = (blob.size / 1024 / 1024).toFixed(1);
              setStatus('Сжали: ' + before + ' МБ → ' + after + ' МБ. Загружаем в облако…');
              return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.webm', { type: 'video/webm' });
            }).catch(function (err) {
              console.error('Video compress error:', err);
              setStatus('Не получилось сжать видео в этом браузере — загружаем оригинал.');
              return file;
            });
          })()
        : Promise.resolve(file);

      prepared.then(function (finalFile) {
        setStatus('Загружаем в облако: 0%…');
        return uploadToCloudinary(finalFile, function (pct) {
          setStatus('Загружаем в облако: ' + Math.round(pct * 100) + '%…');
        });
      }).then(function (result) {
        if (!result || !result.secure_url) {
          throw new Error('облако не вернуло ссылку на файл');
        }
        cloudUrlInput.value = result.secure_url;
        if (cloudPublicIdInput) {
          cloudPublicIdInput.value = result.public_id || '';
        }
        // Сам файл на сервер больше не отправляем — очищаем поле, чтобы
        // не гонять его лишний раз (и не упереться в лимиты хостинга).
        fileInput.value = '';
        fileInput.removeAttribute('required');

        setStatus('Готово, сохраняем…');
        form.dataset.uploaded = '1';
        form.submit();
      }).catch(function (err) {
        console.error('Cloudinary upload error:', err);
        setStatus('Не получилось загрузить видео в облако (' + err.message + '). Проверьте интернет и попробуйте ещё раз.');
        submitBtn.disabled = false;
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-widget-upload-form[data-type="video"]').forEach(wireForm);
  });
})();
