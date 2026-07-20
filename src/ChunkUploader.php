<?php

declare(strict_types=1);

/**
 * Assembles files uploaded in pieces (needed because the Cloudflare proxy in
 * front of this site caps a single request body at 100 MB). Chunks are
 * staged in a directory next to files_dir that FileRepository never scans,
 * then concatenated in order once the last chunk arrives.
 */
final class ChunkUploader
{
    public function __construct(private readonly string $chunksDir)
    {
    }

    public static function forConfig(array $config): self
    {
        $filesDir = rtrim((string) $config['files_dir'], '/\\');
        return new self(dirname($filesDir) . DIRECTORY_SEPARATOR . '.upload_chunks');
    }

    /** Client-supplied upload session id: a safe, fixed-charset token only. */
    public static function isValidId(string $id): bool
    {
        return preg_match('/^[A-Za-z0-9_-]{8,64}$/', $id) === 1;
    }

    /** Store one chunk. Returns null on success, or an error message. */
    public function storeChunk(string $uploadId, int $chunkIndex, string $tmpName): ?string
    {
        $dir = $this->dirFor($uploadId);
        if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
            return 'Could not create a temp directory for the upload.';
        }
        $target = $dir . DIRECTORY_SEPARATOR . $chunkIndex . '.part';
        if (!move_uploaded_file($tmpName, $target)) {
            return 'Could not store chunk ' . $chunkIndex . '.';
        }
        return null;
    }

    /** Concatenate all chunks into $target, in order. Returns null on success, or an error message. */
    public function assemble(string $uploadId, int $totalChunks, string $target): ?string
    {
        $dir = $this->dirFor($uploadId);
        for ($i = 0; $i < $totalChunks; $i++) {
            if (!is_file($dir . DIRECTORY_SEPARATOR . $i . '.part')) {
                $this->discard($uploadId);
                return 'Upload incomplete: chunk ' . $i . ' of ' . $totalChunks . ' is missing.';
            }
        }

        $out = fopen($target, 'wb');
        if ($out === false) {
            return 'Could not open the destination file for writing.';
        }
        for ($i = 0; $i < $totalChunks; $i++) {
            $in = fopen($dir . DIRECTORY_SEPARATOR . $i . '.part', 'rb');
            if ($in === false || stream_copy_to_stream($in, $out) === false) {
                fclose($out);
                return 'Could not read chunk ' . $i . '.';
            }
            fclose($in);
        }
        fclose($out);

        $this->discard($uploadId);
        return null;
    }

    /** Remove a chunk session's staged parts and its directory. */
    public function discard(string $uploadId): void
    {
        $dir = $this->dirFor($uploadId);
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*.part') ?: [] as $part) {
            @unlink($part);
        }
        @rmdir($dir);
    }

    /** Remove upload sessions that were abandoned (tab closed mid-upload, etc). */
    public function cleanupStale(int $maxAgeSeconds = 86400): void
    {
        if (!is_dir($this->chunksDir)) {
            return;
        }
        foreach (scandir($this->chunksDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $dir = $this->chunksDir . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($dir) && filemtime($dir) < time() - $maxAgeSeconds) {
                foreach (glob($dir . DIRECTORY_SEPARATOR . '*.part') ?: [] as $part) {
                    @unlink($part);
                }
                @rmdir($dir);
            }
        }
    }

    private function dirFor(string $uploadId): string
    {
        return $this->chunksDir . DIRECTORY_SEPARATOR . $uploadId;
    }
}
