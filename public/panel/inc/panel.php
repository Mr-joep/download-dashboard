<?php

declare(strict_types=1);

/** Shared bootstrap for all panel pages. No login required. */

$config = require dirname(__DIR__, 3) . '/src/bootstrap.php';

Auth::start();

require_once __DIR__ . '/layout.php';
