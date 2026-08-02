<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/functions.php';

clearRememberCookie();
unset($_SESSION['admin_id']);
session_destroy();
redirect('../login.php');
