<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/functions.php';

unset($_SESSION['admin_id']);
redirect('login.php');
