<?php

declare(strict_types=1);

/**
 * Tracks visitors who currently have the home page open, via a small ping
 * from client-side JS (see serve.php's landing page). There is no explicit
 * "goodbye" signal for a closed tab - presence is just "pinged recently",
 * so it naturally expires once the pings stop.
 */
final class Heartbeat
{
    /** A ping counts as live for this many seconds; pings fire well inside this window. */
    public const LIVE_WINDOW_SECONDS = 15;

    private const TOKEN_PATTERN = '/^[A-Za-z0-9_-]{16,64}$/';

    public static function isValidToken(string $token): bool
    {
        return preg_match(self::TOKEN_PATTERN, $token) === 1;
    }

    /** Record/refresh one visitor's presence, and reap long-abandoned rows. */
    public static function ping(string $token, string $ip, string $userAgent, string $path): void
    {
        $now = now();
        $ua  = mb_substr($userAgent, 0, 500);
        $p   = mb_substr($path, 0, 500);

        Database::run(
            'INSERT INTO heartbeats (token, ip, user_agent, path, first_seen, last_seen)
                 VALUES (:token, :ip, :ua, :path, :now1, :now2)
             ON DUPLICATE KEY UPDATE ip = :ip2, user_agent = :ua2, path = :path2, last_seen = :now3',
            [
                'token' => $token, 'ip' => $ip, 'ua' => $ua, 'path' => $p,
                'now1'  => $now, 'now2' => $now,
                'ip2'   => $ip, 'ua2' => $ua, 'path2' => $p, 'now3' => $now,
            ]
        );
        // A tab that goes silent for 2 minutes is long past the live window; reap it here
        // rather than running a cron for what is, at this app's scale, a tiny table.
        Database::run('DELETE FROM heartbeats WHERE last_seen < ?', [date('Y-m-d H:i:s', time() - 120)]);
    }
}
