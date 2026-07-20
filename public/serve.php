<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . '/src/bootstrap.php';

$method  = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$rawPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path    = rawurldecode(is_string($rawPath) ? $rawPath : '/');

$basePath = rtrim((string) ($config['base_path'] ?? ''), '/');
if ($basePath !== '' && str_starts_with($path, $basePath)) {
    $path = substr($path, strlen($basePath));
    if ($path === '') {
        $path = '/';
    }
}

$ip        = client_ip($config);
$userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
$referer   = (string) ($_SERVER['HTTP_REFERER'] ?? '');

// A ping from the landing page's own JS, keeping the "home page open" list
// on the panel's live-visitors page up to date. Handled before bot/rate
// checks (which cost a DB query) since a ping every few seconds per open
// tab would otherwise pay for that on every single ping. Not a real page
// view either, so it is not logged to `downloads`.
if ($path === '/heartbeat' && $method === 'POST') {
    $token       = (string) ($_POST['token'] ?? '');
    $visitedPath = (string) ($_POST['path'] ?? '/');
    if ($visitedPath === '') {
        $visitedPath = '/';
    }
    if (Heartbeat::isValidToken($token)) {
        Heartbeat::ping($token, $ip, $userAgent, $visitedPath);
    }
    http_response_code(204);
    exit;
}

$bot              = BotDetector::detectBot($userAgent);
$suspiciousReason = BotDetector::suspiciousPath($path);
if ($suspiciousReason === null && $bot !== null && $bot['type'] === 'scanner') {
    $suspiciousReason = 'scanner user agent';
}
if ($suspiciousReason === null && BotDetector::highRequestRate($ip, $config)) {
    $suspiciousReason = 'high request rate';
}

$base = [
    'path'              => $path,
    'ip'                => $ip,
    'user_agent'        => $userAgent,
    'referer'           => $referer,
    'method'            => $method,
    'is_bot'            => $bot !== null ? 1 : 0,
    'bot_name'          => $bot['name'] ?? null,
    'bot_type'          => $bot['type'] ?? null,
    'is_suspicious'     => $suspiciousReason !== null ? 1 : 0,
    'suspicious_reason' => $suspiciousReason,
];

// The landing page and robots.txt are generated; everything else is a file.
if ($path === '/' || $path === '/robots.txt') {
    RequestLogger::log($base + ['status' => 200]);
    if ($path === '/robots.txt') {
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\nDisallow: /panel/\n";
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo simple_page(
            'dow.mr-joep.nl',
            'File download server.',
            heartbeatUrl: $basePath . '/heartbeat',
            heartbeatPage: '/'
        );
    }
    exit;
}

$repo = new FileRepository($config);
$file = ($method === 'GET' || $method === 'HEAD') ? $repo->resolve($path) : null;

if ($file === null) {
    RequestLogger::log($base + ['status' => 404]);
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo simple_page(
        '404',
        'File not found.',
        heartbeatUrl: $basePath . '/heartbeat',
        heartbeatPage: $path
    );
    exit;
}

// The path resolved to a real download, so a path-based "suspicious" match
// (e.g. a file that happens to be called backup.sql) does not apply.
if ($suspiciousReason !== null
    && $suspiciousReason !== 'high request rate'
    && $suspiciousReason !== 'scanner user agent') {
    $base['is_suspicious']     = 0;
    $base['suspicious_reason'] = null;
}

// ---------------------------------------------------------------------------
// Access control hook.
// Future features (password protected downloads, signed / expiring /
// one-time links) plug in here: decide on $file + query string, and exit
// with a 403 page before the request is logged as a download.
// ---------------------------------------------------------------------------

$range = (string) ($_SERVER['HTTP_RANGE'] ?? '');

// Count a download once: resumes and download-manager segments (Range
// requests that do not start at byte 0) do not bump the counters again.
$counted = $method === 'GET' && ($range === '' || preg_match('/^bytes=0-/', $range) === 1);

$fileId = $repo->ensureRecord($file['filename'], $file['size']);
$logId  = RequestLogger::log($base + [
    'status'        => 200,
    'filename'      => $file['filename'],
    'file_id'       => $fileId,
    'is_download'   => 1,
    'counted'       => $counted ? 1 : 0,
    'range_request' => $range !== '' ? 1 : 0,
    'file_size'     => $file['size'],
]);
if ($counted) {
    $repo->recordDownload($fileId);
}

if (($config['serve_method'] ?? 'xaccel') === 'xaccel') {
    // Hand the transfer to nginx. complete.php is pinged when it ends and
    // matches this download by path + ip (see RequestLogger::finalizeByPathIp);
    // dlid is carried along for log readability, not looked up by anything.
    $encoded = implode('/', array_map('rawurlencode', explode('/', $file['filename'])));

    // No Content-Type from PHP: nginx picks the right one from mime.types.
    ini_set('default_mimetype', '');
    header_remove('Content-Type');
    header(
        'X-Accel-Redirect: ' . rtrim((string) $config['xaccel_prefix'], '/') . '/'
        . $encoded . '?dlid=' . $logId
    );
    exit;
}

serve_with_php($file, $logId, $method, $range);

/**
 * Development fallback: stream the file from PHP in 256 KB chunks with
 * basic single-range support. The whole file is never held in memory.
 */
function serve_with_php(array $file, int $logId, string $method, string $range): void
{
    $size  = $file['size'];
    $start = 0;
    $end   = max(0, $size - 1);
    $status = 200;

    if ($range !== '' && $size > 0 && preg_match('/^bytes=(\d*)-(\d*)$/', $range, $m) === 1) {
        if ($m[1] !== '') {
            $start = (int) $m[1];
            if ($m[2] !== '') {
                $end = min((int) $m[2], $size - 1);
            }
        } elseif ($m[2] !== '') {
            $start = max(0, $size - (int) $m[2]);
        }
        if ($start >= $size || $start > $end) {
            header('Content-Range: bytes */' . $size);
            http_response_code(416);
            RequestLogger::finalize($logId, 0);
            exit;
        }
        $status = 206;
    }

    http_response_code($status);
    header('Content-Type: ' . guess_mime($file['filename']));
    header('Accept-Ranges: bytes');
    header('Content-Length: ' . ($end - $start + 1));
    if ($status === 206) {
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
    }

    if ($method === 'HEAD' || $size === 0) {
        RequestLogger::finalize($logId, 0);
        exit;
    }

    set_time_limit(0);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $fp = fopen($file['path'], 'rb');
    if ($fp === false) {
        RequestLogger::finalize($logId, 0);
        exit;
    }
    fseek($fp, $start);
    $sent = 0;
    $left = $end - $start + 1;
    while ($left > 0 && !feof($fp) && !connection_aborted()) {
        $chunk = fread($fp, (int) min(262144, $left));
        if ($chunk === false || $chunk === '') {
            break;
        }
        echo $chunk;
        flush();
        $sent += strlen($chunk);
        $left -= strlen($chunk);
    }
    fclose($fp);

    RequestLogger::finalize($logId, $sent);
}

/** Minimal type map for the PHP serving mode (nginx uses mime.types). */
function guess_mime(string $filename): string
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return match ($ext) {
        'txt', 'md', 'log' => 'text/plain; charset=utf-8',
        'pdf'              => 'application/pdf',
        'png'              => 'image/png',
        'jpg', 'jpeg'      => 'image/jpeg',
        'gif'              => 'image/gif',
        'mp4'              => 'video/mp4',
        'mp3'              => 'audio/mpeg',
        'zip'              => 'application/zip',
        'json'             => 'application/json',
        default            => 'application/octet-stream',
    };
}

/** Tiny dark page used for the landing page and 404s. */
function simple_page(string $title, string $text, ?string $heartbeatUrl = null, string $heartbeatPage = '/'): string
{
    $title     = h($title);
    $text      = h($text);
    $heartbeat = $heartbeatUrl !== null ? heartbeat_script($heartbeatUrl, $heartbeatPage) : '';
    return <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>$title</title>
<style>
body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0d0d0d;
color:#c3c2b7;font:16px/1.6 system-ui,-apple-system,"Segoe UI",sans-serif}
main{text-align:center;padding:2rem}
h1{color:#fff;font-size:1.6rem;margin:0 0 .5rem}
</style>
</head>
<body><main><h1>$title</h1><p>$text</p></main>$heartbeat</body>
</html>
HTML;
}

/**
 * Pings /heartbeat every 5s while this tab is open, so the panel's
 * live-visitors page can show "home page open right now" in near real time.
 * Presence has no explicit end signal - a closed tab just stops pinging and
 * ages out of the live window server-side (see Heartbeat::LIVE_WINDOW_SECONDS).
 */
function heartbeat_script(string $url, string $page): string
{
    $url  = json_encode($url, JSON_UNESCAPED_SLASHES);
    $page = json_encode($page, JSON_UNESCAPED_SLASHES);
    return <<<HTML
<script>
(function () {
    var token = (window.crypto && crypto.randomUUID)
        ? crypto.randomUUID()
        : 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'.replace(/x/g, function () {
            return Math.floor(Math.random() * 16).toString(16);
        });

    var ping = function () {
        var body = new URLSearchParams({ token: token, path: $page });
        fetch($url, { method: 'POST', body: body, keepalive: true }).catch(function () {});
    };

    ping();
    setInterval(ping, 5000);
})();
</script>
HTML;
}
