<?php
require_once __DIR__ . '/src/api/auth.php';
Auth::logout();
header('Location: /dashboard/login.php');
exit;
