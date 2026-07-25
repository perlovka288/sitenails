<footer>
  <div class="container">
    <p><?= e(SITE_NAME) ?> · <?= e(SITE_PHONE) ?></p>
    <!--
      Скрытая ссылка в админ-панель.
      Она не отображается визуально (opacity: 0), но остаётся в HTML-коде.
      Мама может зайти в панель управления по прямому адресу:
      /admin-x7k9m2/login.php  — эту ссылку лучше просто сохранить в закладки браузера.
    -->
    <a href="admin-x7k9m2/login.php" class="admin-dot" aria-hidden="true" tabindex="-1">•</a>
  </div>
</footer>

<script>window.SITE_LANG = <?= json_encode(currentLang()) ?>;</script>
<script src="assets/js/script.js"></script>
</body>
</html>
