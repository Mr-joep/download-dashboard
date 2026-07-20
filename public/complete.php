<?php

declare(strict_types=1);

/**
 * Called by nginx (post_action subrequest) when a file transfer ends,
 * whether it finished or was aborted. Records how many bytes actually went
 * out, which marks the download as completed (or not) and removes it from
 * the live-visitors view.
 *
 * Not reachable from the outside: the nginx location is `internal`.
 *
 * nginx's post_action subrequest only exposes the *original* client
 * request (before the X-Accel-Redirect internal redirect), so this matches
 * on path + ip against the row serve.php just inserted, rather than a
 * signed id passed through the redirected URI (which post_action can't see).
 */

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
