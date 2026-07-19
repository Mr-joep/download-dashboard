<?php

declare(strict_types=1);

/** HTML-escape a value for output. */
function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Current date/time in SQL format. PHP's timezone is leading everywhere. */
function now(): string
{
    return date('Y-m-d H:i:s');
}

/** Client IP, optionally taken from X-Forwarded-For when behind a proxy. */
function client_ip(array $config): string
{
    if (!empty($config['trust_forwarded_for']) && isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $first = trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) {
            return $first;
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/** 1234567 -> "1.18 MB" */
function format_bytes(int|float|null $bytes): string
{
    $bytes = max(0.0, (float) $bytes);
    foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
        if ($bytes < 1024) {
            return ($unit === 'B' ? (string) round($bytes) : number_format($bytes, 2)) . ' ' . $unit;
        }
        $bytes /= 1024;
    }
    return number_format($bytes, 2) . ' TB';
}

/** Strip an uploaded/requested name down to a safe, plain file name. */
function sanitize_filename(string $name): string
{
    $name = basename(str_replace('\\', '/', trim($name)));
    $name = (string) preg_replace('/[^A-Za-z0-9._()\[\] -]/', '_', $name);
    return trim($name, ' .');
}

/** "3m ago" for panel tables. */
function time_ago(?string $datetime): string
{
    if ($datetime === null || $datetime === '') {
        return '';
    }
    $diff = max(0, time() - (int) strtotime($datetime));
    if ($diff < 60) {
        return $diff . 's ago';
    }
    if ($diff < 3600) {
        return intdiv($diff, 60) . 'm ago';
    }
    if ($diff < 86400) {
        return intdiv($diff, 3600) . 'h ago';
    }
    return intdiv($diff, 86400) . 'd ago';
}

/** Human readable message for an UPLOAD_ERR_* code. */
function upload_error_message(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE
            => 'The file is larger than the server upload limit (upload_max_filesize / post_max_size).',
        UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded, please try again.',
        UPLOAD_ERR_NO_FILE    => 'No file was selected.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server error: missing temporary upload directory.',
        UPLOAD_ERR_CANT_WRITE => 'Server error: could not write the upload to disk.',
        default               => 'Upload failed (error code ' . $code . ').',
    };
}
