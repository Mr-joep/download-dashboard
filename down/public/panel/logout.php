<?php

declare(strict_types=1);

$config = require dirname(__DIR__, 2) . '/src/bootstrap.php';

Auth::start();
Auth::logout();
header('Location: login.php');
exit;
