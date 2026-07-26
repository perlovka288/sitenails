/**
 * assets/js/video-compress.js
 *
 * Сжимает видео прямо в браузере перед загрузкой в раздел "Достижения".
 * Зачем: некоторые бесплатные хостинги режут загрузку файлов гораздо
 * жёстче, чем указано в настройках сайта (.user.ini/.htaccess) — например,
 * даже видео весом 20-30 МБ может быть отклонено. Пережимая видео в
 * браузере до меньшего разрешения/битрейта, мы уменьшаем размер файла
 * ДО того, как он вообще уйдёт на сервер — это обходит любые серверные
 * лимиты, так как на сервер отправляется уже маленький файл.
 *
 * Работает через стандартные браузерные API (video + canvas +
 * MediaRecorder) — никаких сторонних библиотек и серверных зависимостей
 * не требуется. Результат сохраняется в формате WebM (VP8/9 + Opus),
 * который прекрасно воспроизводится во всех современных браузерах.
 *
 * Если браузер не поддерживает MediaRecorder (очень старые браузеры) —
 * форма просто отправляет исходный файл как раньше, ничего не ломается.
 */
(function () {
  var form = document.getElementById('widgetUploadForm');
  var fileInput = document.getElementById('widgetFileInput');
  var toggle = document.getElementById('compressVideoToggle');
  var status = document.getElementById('compressVideoStatus');
  var submitBtn = document.getElementById('widgetUploadSubmitBtn');

  if (!form || !fileInput || !toggle || !window.MediaRecorder) {
    return;
  }

  var MAX_DIMENSION = 960;   // сторона по большей стороне кадра
  var TARGET_BITRATE = 1_500_000; // ~1.5 Мбит/с — компромисс качество/размер

  function setStatus(text) {
    if (!status) return;
    status.textContent = text;
    status.style.display = text ? 'block' : 'none';
  }

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

  form.addEventListener('submit', function (ev) {
    if (form.dataset.compressed === '1') {
      return; // уже сжали, отправляем как есть
    }
    if (!toggle.checked) {
      return; // сжатие отключено пользователем — грузим оригинал
    }
    var file = fileInput.files && fileInput.files[0];
    if (!file || !file.type.startsWith('video/')) {
      return;
    }
    // Если файл и так маленький — сжимать незачем, не тратим время.
    if (file.size <= 8 * 1024 * 1024) {
      return;
    }

    ev.preventDefault();
    submitBtn.disabled = true;
    setStatus('Сжимаем видео в браузере, это может занять до минуты…');

    compressVideo(file).then(function (blob) {
      if (blob.size >= file.size) {
        // Сжатая версия не меньше оригинала (бывает с уже сжатыми видео) —
        // грузим оригинал, чтобы не терять качество зря.
        setStatus('Сжатие не дало выигрыша в размере — загружаем оригинал.');
        form.dataset.compressed = '1';
        form.submit();
        return;
      }
      var newFile = new File([blob], file.name.replace(/\.[^.]+$/, '') + '.webm', { type: 'video/webm' });
      var dt = new DataTransfer();
      dt.items.add(newFile);
      fileInput.files = dt.files;

      var before = (file.size / 1024 / 1024).toFixed(1);
      var after = (blob.size / 1024 / 1024).toFixed(1);
      setStatus('Готово: ' + before + ' МБ → ' + after + ' МБ. Загружаем…');

      form.dataset.compressed = '1';
      form.submit();
    }).catch(function (err) {
      console.error('Video compress error:', err);
      setStatus('Не получилось сжать видео в этом браузере — загружаем оригинал файла.');
      form.dataset.compressed = '1';
      form.submit();
    }).finally(function () {
      submitBtn.disabled = false;
    });
  });
})();
