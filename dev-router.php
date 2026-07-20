<?php

declare(strict_types=1);

$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

if (preg_match('#^/panel(/|$)#', $path) === 1) {
    return false; // let the built-in server serve the panel
}

require __DIR__ . '/public/serve.php';
