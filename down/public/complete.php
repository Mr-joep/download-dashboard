<?php

declare(strict_types=1);

/**
 * Called by nginx (post_action subrequest) when a file transfer ends,
 * whether it finished or was aborted. Records how many bytes actually went
 * out, which marks the download as completed (or not) and removes it from
 * the live-visitors view.
 *
 * Not reachable from the outside: the nginx location is `internal` and the
 * download id is signed with complete_secret.
 */

$config = require dirname(__DIR__) . '/src/bootstrap.php';

$args = (string) ($_SERVER['DL_ARGS'] ?? $_SERVER['QUERY_STRING'] ?? '');
parse_str($args, $params);

$id    = (int) ($params['dlid'] ?? 0);
$token = (string) ($params['tok'] ?? '');

$expected = hash_hmac('sha256', (string) $id, (string) $config['complete_secret']);
if ($id <= 0 || !hash_equals($expected, $token)) {
    http_response_code(403);
    exit;
}

RequestLogger::finalize($id, (int) ($_SERVER['BYTES_SENT'] ?? 0));
http_response_code(204);
