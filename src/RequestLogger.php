<?php

declare(strict_types=1);

/**
 * Writes rows to the `downloads` table (which stores every request) and
 * finalizes them when a transfer ends.
 */
final class RequestLogger
{
    private const DEFAULTS = [
        'requested_at'      => null,
        'path'              => '',
        'filename'          => null,
        'file_id'           => null,
        'ip'                => '',
        'user_agent'        => '',
        'referer'           => '',
        'method'            => 'GET',
        'status'            => 200,
        'is_download'       => 0,
        'counted'           => 0,
        'range_request'     => 0,
        'is_bot'            => 0,
        'bot_name'          => null,
        'bot_type'          => null,
        'is_suspicious'     => 0,
        'suspicious_reason' => null,
        'file_size'         => null,
        'bytes_sent'        => 0,
        'completed'         => 0,
        'finished_at'       => null,
    ];

    /** Insert a request row; unknown keys are ignored. Returns the row id. */
    public static function log(array $data): int
    {
        $row = array_merge(self::DEFAULTS, array_intersect_key($data, self::DEFAULTS));
        $row['requested_at'] ??= now();
        $row['path']       = mb_substr((string) $row['path'], 0, 500);
        $row['user_agent'] = mb_substr((string) $row['user_agent'], 0, 500);
        $row['referer']    = mb_substr((string) $row['referer'], 0, 500);

        $columns = array_keys(self::DEFAULTS);
        $sql = 'INSERT INTO downloads (' . implode(', ', $columns) . ')
                VALUES (:' . implode(', :', $columns) . ')';
        Database::run($sql, $row);
        return Database::lastId();
    }

    /**
     * Record the end of a transfer. A download counts as completed when at
     * least the whole file went out (headers are not part of bytes_sent).
     */
    public static function finalize(int $id, int $bytesSent): void
    {
        Database::run(
            'UPDATE downloads
                SET bytes_sent  = :bytes,
                    finished_at = :finished,
                    completed   = IF(file_size IS NOT NULL AND file_size > 0 AND :bytes2 >= file_size, 1, 0)
              WHERE id = :id',
            ['bytes' => $bytesSent, 'finished' => now(), 'bytes2' => $bytesSent, 'id' => $id]
        );
    }

    /**
     * Same as finalize(), but for nginx's post_action callback: nginx's
     * post_action subrequest only exposes the *original* client request
     * (method/path/headers), not the X-Accel-Redirect target's query string
     * or the response headers PHP set on it - so there is no signed dlid to
     * key off. Instead this matches the most recent still-open row for the
     * same path + ip, which is unambiguous at this app's scale (~20 files,
     * a handful of downloads/day).
     */
    public static function finalizeByPathIp(string $path, string $ip, int $bytesSent): void
    {
        Database::run(
            'UPDATE downloads
                SET bytes_sent  = :bytes,
                    finished_at = :finished,
                    completed   = IF(file_size IS NOT NULL AND file_size > 0 AND :bytes2 >= file_size, 1, 0)
              WHERE path = :path AND ip = :ip AND finished_at IS NULL
              ORDER BY id DESC
              LIMIT 1',
            ['bytes' => $bytesSent, 'finished' => now(), 'bytes2' => $bytesSent, 'path' => $path, 'ip' => $ip]
        );
    }
}
