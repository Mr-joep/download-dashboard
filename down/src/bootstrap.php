<?php

declare(strict_types=1);

/**
 * Common bootstrap: loads config, registers the class autoloader and the
 * helper functions, prepares the database connection. Every entry point
 * starts with:
 *
 *   $config = require dirname(__DIR__) . '/src/bootstrap.php';
 */

$config = require dirname(__DIR__) . '/config.php';

date_default_timezone_set($config['timezone'] ?? 'UTC');
mb_internal_encoding('UTF-8');

spl_autoload_register(static function (string $class): void {
    $file = __DIR__ . '/' . $class . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require_once __DIR__ . '/helpers.php';

Database::init($config);

return $config;
