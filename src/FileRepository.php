<?php

declare(strict_types=1);

/**
 * Maps request paths to real files in files_dir and keeps the `files` table
 * in sync with the directory. No manual importing: rows are created the
 * moment a file is downloaded, uploaded or seen by syncFromDisk().
 */
final class FileRepository
{
    public function __construct(private readonly array $config)
    {
    }

    public function filesDir(): string
    {
        return rtrim($this->config['files_dir'], '/\\');
    }

    /**
     * Resolve a URL path to a file inside files_dir.
     * Returns ['filename' => relative name, 'path' => absolute path, 'size' => bytes] or null.
     */
    public function resolve(string $urlPath): ?array
    {
        $root = realpath($this->filesDir());
        if ($root === false) {
            return null;
        }

        $rel = trim($urlPath, '/');
        if ($rel === '' || str_contains($rel, "\0") || str_contains($rel, '\\') || str_contains($rel, '..')) {
            return null;
        }
        // Hidden files and dot-directories are never served.
        foreach (explode('/', $rel) as $segment) {
            if ($segment === '' || $segment[0] === '.') {
                return null;
            }
        }

        $full = realpath($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel));
        if ($full === false || !is_file($full)) {
            return null;
        }
        // Must stay inside the download directory.
        if (!str_starts_with($full, $root . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return [
            'filename' => $rel,
            'path'     => $full,
            'size'     => (int) filesize($full),
        ];
    }

    /** Make sure a `files` row exists for this file; returns its id. */
    public function ensureRecord(string $filename, int $size): int
    {
        $row = Database::fetch('SELECT id FROM files WHERE filename = ?', [$filename]);
        if ($row !== null) {
            Database::run('UPDATE files SET size = ?, missing = 0 WHERE id = ?', [$size, $row['id']]);
            return (int) $row['id'];
        }
        Database::run(
            'INSERT INTO files (filename, size, first_seen) VALUES (?, ?, ?)',
            [$filename, $size, now()]
        );
        return Database::lastId();
    }

    /** Bump the download counters of a file. */
    public function recordDownload(int $fileId): void
    {
        Database::run(
            'UPDATE files
                SET total_downloads = total_downloads + 1,
                    first_download  = COALESCE(first_download, ?),
                    last_download   = ?
              WHERE id = ?',
            [now(), now(), $fileId]
        );
    }

    /**
     * Scan files_dir and sync the files table: new files get a row, files
     * that disappeared are flagged missing (history is kept). With ~20 files
     * this is cheap enough to run on every dashboard load.
     */
    public function syncFromDisk(): void
    {
        $root = realpath($this->filesDir());
        if ($root === false) {
            return;
        }

        $found = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $entry) {
            if (!$entry->isFile()) {
                continue;
            }
            $rel = str_replace('\\', '/', substr($entry->getPathname(), strlen($root) + 1));
            if (str_starts_with(basename($rel), '.')) {
                continue;
            }
            $found[$rel] = (int) $entry->getSize();
        }

        foreach ($found as $rel => $size) {
            $this->ensureRecord($rel, $size);
        }
        foreach (Database::fetchAll('SELECT id, filename FROM files WHERE missing = 0') as $row) {
            if (!isset($found[$row['filename']])) {
                Database::run('UPDATE files SET missing = 1 WHERE id = ?', [$row['id']]);
            }
        }
    }
}
