<?php

declare(strict_types=1);

/** Shared guard for all panel pages: bootstrap + login required. */

$config = require dirname(__DIR__, 3) . '/src/bootstrap.php';

Auth::start();
if (!Auth::check()) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/layout.php';
