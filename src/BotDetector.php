<?php

declare(strict_types=1);

/**
 * Identifies known bots by user agent (signatures live in the `bots` table)
 * and flags suspicious requests: scanner paths, PHP probes and high request
 * rates. Detection only - nothing is ever blocked automatically.
 */
final class BotDetector
{
    /** Path fragments commonly probed by scanners => reason that is logged. */
    private const SUSPICIOUS_PATHS = [
        '/wp-admin'       => 'wordpress probe',
        '/wp-login'       => 'wordpress probe',
        '/wp-content'     => 'wordpress probe',
        '/wp-includes'    => 'wordpress probe',
        '/xmlrpc.php'     => 'wordpress probe',
        '/wlwmanifest'    => 'wordpress probe',
        '/.env'           => 'config/dotfile probe',
        '/.git'           => 'config/dotfile probe',
        '/.svn'           => 'config/dotfile probe',
        '/.aws'           => 'config/dotfile probe',
        '/.ssh'           => 'config/dotfile probe',
        'id_rsa'          => 'config/dotfile probe',
        '/.htaccess'      => 'config/dotfile probe',
        '/.htpasswd'      => 'config/dotfile probe',
        '/.ds_store'      => 'config/dotfile probe',
        '/config'         => 'config/dotfile probe',
        '/etc/passwd'     => 'path traversal',
        'boot.ini'        => 'path traversal',
        'win.ini'         => 'path traversal',
        '/phpmyadmin'     => 'admin panel probe',
        '/adminer'        => 'admin panel probe',
        '/administrator'  => 'admin panel probe',
        '/admin'          => 'admin panel probe',
        '/manager/html'   => 'admin panel probe',
        '/jenkins'        => 'admin panel probe',
        '/owa'            => 'exploit probe',
        '/autodiscover'   => 'exploit probe',
        '/actuator'       => 'exploit probe',
        '/solr'           => 'exploit probe',
        '/cgi-bin'        => 'exploit probe',
        '/vendor/phpunit' => 'exploit probe',
        'eval-stdin'      => 'exploit probe',
        '/hnap1'          => 'exploit probe',
        '/gponform'       => 'exploit probe',
        '/boaform'        => 'exploit probe',
        '/shell'          => 'exploit probe',
        '/_profiler'      => 'exploit probe',
        '/_ignition'      => 'exploit probe',
        '/telescope'      => 'exploit probe',
        '/server-status'  => 'exploit probe',
        '/backup'         => 'backup probe',
        '.sql'            => 'backup probe',
        '.bak'            => 'backup probe',
    ];

    /**
     * Match the user agent against the known bot signatures.
     * Returns ['name' => ..., 'type' => ...] or null for regular clients.
     */
    public static function detectBot(string $userAgent): ?array
    {
        if (trim($userAgent) === '') {
            return ['name' => 'Empty user agent', 'type' => 'other'];
        }

        static $signatures = null;
        if ($signatures === null) {
            $signatures = Database::fetchAll(
                'SELECT name, ua_pattern, type FROM bots WHERE enabled = 1'
            );
        }
        foreach ($signatures as $sig) {
            if ($sig['ua_pattern'] !== '' && stripos($userAgent, $sig['ua_pattern']) !== false) {
                return ['name' => $sig['name'], 'type' => $sig['type']];
            }
        }

        // Generic fallback for crawlers that are not in the signature list.
        foreach (['bot', 'crawler', 'spider'] as $word) {
            if (stripos($userAgent, $word) !== false) {
                return ['name' => 'Unknown bot', 'type' => 'other'];
            }
        }
        return null;
    }

    /** Does the requested path look like directory brute forcing / scanning? */
    public static function suspiciousPath(string $path): ?string
    {
        $p = strtolower($path);
        if (str_contains($p, "\0") || str_contains($p, '..')) {
            return 'path traversal';
        }
        foreach (self::SUSPICIOUS_PATHS as $fragment => $reason) {
            if (str_contains($p, $fragment)) {
                return $reason;
            }
        }
        // A plain download server has no public PHP files, so any .php
        // request that reaches serve.php is a probe.
        if (str_ends_with($p, '.php')) {
            return 'php probe';
        }
        return null;
    }

    /** More than rate_max already-logged requests from this IP in the last rate_window seconds? */
    public static function highRequestRate(string $ip, array $config): bool
    {
        $window = (int) ($config['rate_window'] ?? 60);
        $max    = (int) ($config['rate_max'] ?? 40);
        if ($max <= 0 || $window <= 0) {
            return false;
        }
        $since = date('Y-m-d H:i:s', time() - $window);
        $count = (int) Database::value(
            'SELECT COUNT(*) FROM downloads WHERE ip = ? AND requested_at >= ?',
            [$ip, $since]
        );
        return $count >= $max;
    }
}
