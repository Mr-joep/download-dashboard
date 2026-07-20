<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . '/src/bootstrap.php';

$rawPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path    = rawurldecode(is_string($rawPath) ? $rawPath : '/');

$basePath = rtrim((string) ($config['base_path'] ?? ''), '/');
if ($basePath !== '' && str_starts_with($path, $basePath)) {
    $path = substr($path, strlen($basePath));
    if ($path === '') {
        $path = '/';
    }
}

$ip = client_ip($config);

RequestLogger::finalizeByPathIp($path, $ip, (int) ($_SERVER['BYTES_SENT'] ?? 0));
http_response_code(204);
