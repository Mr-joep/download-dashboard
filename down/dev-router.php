<?php

declare(strict_types=1);

/**
 * Router for PHP's built-in development server. Emulates the nginx routing:
 *
 *   /panel/*   -> real files under public/panel
 *   the rest   -> public/serve.php (the download tracker)
 *
 * Run from the project root:
 *
 *   php -S 127.0.0.1:8080 -t public dev-router.php
 */

$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

if (preg_match('#^/panel(/|$)#', $path) === 1) {
    return false; // let the built-in server serve the panel
}

require __DIR__ . '/public/serve.php';
