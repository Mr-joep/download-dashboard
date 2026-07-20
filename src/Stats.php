<?php

declare(strict_types=1);

/**
 * All read queries for the panel: dashboard counters, page listings and the
 * chart datasets. Time series are gap-filled here so the charts always show
 * a continuous axis.
 */
final class Stats
{
    /** A transfer without a finish ping older than this is shown as "unknown". */
    public const LIVE_WINDOW_HOURS = 24;

    // ---- dashboard -----------------------------------------------------------

    public static function overview(): array
    {
        $today = date('Y-m-d 00:00:00');
        $week  = date('Y-m-d 00:00:00', strtotime('-' . (date('N') - 1) . ' days'));
        $month = date('Y-m-01 00:00:00');

        $downloadsSince = static fn (string $since): int => (int) Database::value(
            'SELECT COUNT(*) FROM downloads WHERE counted = 1 AND requested_at >= ?',
            [$since]
        );

        return [
            'total_downloads' => (int) Database::value('SELECT COUNT(*) FROM downloads WHERE counted = 1'),
            'downloads_today' => $downloadsSince($today),
            'downloads_week'  => $downloadsSince($week),
            'downloads_month' => $downloadsSince($month),
            'total_files'     => (int) Database::value('SELECT COUNT(*) FROM files WHERE missing = 0'),
            'total_requests'  => (int) Database::value('SELECT COUNT(*) FROM downloads'),
            'requests_404'    => (int) Database::value('SELECT COUNT(*) FROM downloads WHERE status = 404'),
            'bot_requests'    => (int) Database::value('SELECT COUNT(*) FROM downloads WHERE is_bot = 1'),
        ];
    }

    public static function recentActivity(int $limit = 15): array
    {
        return Database::fetchAll(
            'SELECT * FROM downloads ORDER BY id DESC LIMIT ' . (int) $limit
        );
    }

    // ---- listings --------------------------------------------------------------

    /** Downloads that started but have not finished yet. */
    public static function liveDownloads(): array
    {
        $since = date('Y-m-d H:i:s', time() - self::LIVE_WINDOW_HOURS * 3600);
        return Database::fetchAll(
            'SELECT * FROM downloads
              WHERE is_download = 1 AND finished_at IS NULL AND requested_at >= ?
              ORDER BY id DESC LIMIT 100',
            [$since]
        );
    }

    /** Visitors who currently have the home page open (see Heartbeat::ping()). */
    public static function liveVisitors(): array
    {
        $since = date('Y-m-d H:i:s', time() - Heartbeat::LIVE_WINDOW_SECONDS);
        return Database::fetchAll(
            'SELECT * FROM heartbeats WHERE last_seen >= ? ORDER BY first_seen DESC LIMIT 100',
            [$since]
        );
    }

    /** @return array{0: array, 1: int} rows + total */
    public static function recentDownloads(int $limit, int $offset): array
    {
        $total = (int) Database::value('SELECT COUNT(*) FROM downloads WHERE is_download = 1');
        $rows  = Database::fetchAll(
            'SELECT * FROM downloads WHERE is_download = 1
              ORDER BY id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset
        );
        return [$rows, $total];
    }

    public static function topFiles(int $limit = 100): array
    {
        return Database::fetchAll(
            'SELECT * FROM files ORDER BY total_downloads DESC, filename ASC LIMIT ' . (int) $limit
        );
    }

    public static function topIps(int $limit = 100): array
    {
        return Database::fetchAll(
            'SELECT ip,
                    SUM(counted)      AS downloads,
                    COUNT(*)          AS requests,
                    MAX(requested_at) AS last_seen
               FROM downloads
              GROUP BY ip
              ORDER BY downloads DESC, requests DESC
              LIMIT ' . (int) $limit
        );
    }

    /** @return array{0: array, 1: int} */
    public static function botRequests(string $filter, int $limit, int $offset): array
    {
        $where = match ($filter) {
            'known'      => 'is_bot = 1',
            'suspicious' => 'is_suspicious = 1',
            default      => '(is_bot = 1 OR is_suspicious = 1)',
        };
        $total = (int) Database::value("SELECT COUNT(*) FROM downloads WHERE $where");
        $rows  = Database::fetchAll(
            "SELECT * FROM downloads WHERE $where
              ORDER BY id DESC LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset
        );
        return [$rows, $total];
    }

    /** @return array{0: array, 1: int} */
    public static function notFoundRequests(int $limit, int $offset): array
    {
        $total = (int) Database::value('SELECT COUNT(*) FROM downloads WHERE status = 404');
        $rows  = Database::fetchAll(
            'SELECT * FROM downloads WHERE status = 404
              ORDER BY id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset
        );
        return [$rows, $total];
    }

    public static function topMissingPaths(int $limit = 10): array
    {
        return Database::fetchAll(
            'SELECT path, COUNT(*) AS hits, MAX(requested_at) AS last_seen
               FROM downloads WHERE status = 404
              GROUP BY path ORDER BY hits DESC LIMIT ' . (int) $limit
        );
    }

    /**
     * Search on filename/path, IP, date range and request type.
     * @return array{0: array, 1: int}
     */
    public static function search(array $f, int $limit, int $offset): array
    {
        $where  = [];
        $params = [];

        if ($f['filename'] !== '') {
            $like = '%' . addcslashes($f['filename'], '%_\\') . '%';
            $where[]  = '(filename LIKE ? OR path LIKE ?)';
            $params[] = $like;
            $params[] = $like;
        }
        if ($f['ip'] !== '') {
            $where[]  = 'ip LIKE ?';
            $params[] = '%' . addcslashes($f['ip'], '%_\\') . '%';
        }
        if ($f['from'] !== '') {
            $where[]  = 'requested_at >= ?';
            $params[] = $f['from'] . ' 00:00:00';
        }
        if ($f['to'] !== '') {
            $where[]  = 'requested_at <= ?';
            $params[] = $f['to'] . ' 23:59:59';
        }
        switch ($f['type']) {
            case 'downloads':  $where[] = 'is_download = 1';   break;
            case '404':        $where[] = 'status = 404';      break;
            case 'bots':       $where[] = 'is_bot = 1';        break;
            case 'suspicious': $where[] = 'is_suspicious = 1'; break;
        }

        $sql   = $where !== [] ? ' WHERE ' . implode(' AND ', $where) : '';
        $total = (int) Database::value('SELECT COUNT(*) FROM downloads' . $sql, $params);
        $rows  = Database::fetchAll(
            'SELECT * FROM downloads' . $sql .
            ' ORDER BY id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
            $params
        );
        return [$rows, $total];
    }

    // ---- chart data --------------------------------------------------------------

    public static function downloadsPerDay(int $days = 30): array
    {
        return self::perDay('counted = 1', $days);
    }

    public static function notFoundPerDay(int $days = 30): array
    {
        return self::perDay('status = 404', $days);
    }

    public static function knownBotsPerDay(int $days = 30): array
    {
        return self::perDay('is_bot = 1', $days);
    }

    public static function suspiciousPerDay(int $days = 30): array
    {
        return self::perDay('is_suspicious = 1', $days);
    }

    public static function downloadsPerMonth(int $months = 12): array
    {
        $start = new DateTimeImmutable(date('Y-m-01'));
        $start = $start->modify('-' . ($months - 1) . ' months');

        $rows = Database::fetchAll(
            "SELECT DATE_FORMAT(requested_at, '%Y-%m') AS m, COUNT(*) AS c
               FROM downloads
              WHERE counted = 1 AND requested_at >= ?
              GROUP BY DATE_FORMAT(requested_at, '%Y-%m')",
            [$start->format('Y-m-d 00:00:00')]
        );
        $byMonth = array_column($rows, 'c', 'm');

        $labels = $counts = [];
        for ($i = 0; $i < $months; $i++) {
            $month    = $start->modify('+' . $i . ' months');
            $labels[] = $month->format('M Y');
            $counts[] = (int) ($byMonth[$month->format('Y-m')] ?? 0);
        }
        return ['labels' => $labels, 'counts' => $counts];
    }

    public static function chartTopFiles(int $limit = 10): array
    {
        $rows = Database::fetchAll(
            'SELECT filename, total_downloads FROM files
              WHERE total_downloads > 0
              ORDER BY total_downloads DESC LIMIT ' . (int) $limit
        );
        return [
            'labels' => array_map(
                static fn (array $r): string => mb_strlen($r['filename']) > 30
                    ? mb_substr($r['filename'], 0, 27) . '...'
                    : $r['filename'],
                $rows
            ),
            'counts' => array_map(static fn (array $r): int => (int) $r['total_downloads'], $rows),
        ];
    }

    public static function chartTopIps(int $limit = 10): array
    {
        $rows = Database::fetchAll(
            'SELECT ip, COUNT(*) AS c FROM downloads
              WHERE counted = 1 GROUP BY ip ORDER BY c DESC LIMIT ' . (int) $limit
        );
        return [
            'labels' => array_column($rows, 'ip'),
            'counts' => array_map(static fn (array $r): int => (int) $r['c'], $rows),
        ];
    }

    /** Requests per day matching a fixed internal condition, gap-filled. */
    private static function perDay(string $where, int $days): array
    {
        $start = new DateTimeImmutable(date('Y-m-d', strtotime('-' . ($days - 1) . ' days')));

        $rows = Database::fetchAll(
            "SELECT DATE(requested_at) AS d, COUNT(*) AS c
               FROM downloads
              WHERE $where AND requested_at >= ?
              GROUP BY DATE(requested_at)",
            [$start->format('Y-m-d 00:00:00')]
        );
        $byDay = array_column($rows, 'c', 'd');

        $labels = $counts = [];
        for ($i = 0; $i < $days; $i++) {
            $day      = $start->modify('+' . $i . ' days');
            $labels[] = $day->format('M j');
            $counts[] = (int) ($byDay[$day->format('Y-m-d')] ?? 0);
        }
        return ['labels' => $labels, 'counts' => $counts];
    }
}
