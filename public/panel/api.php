<?php

declare(strict_types=1);

/** JSON endpoints for the panel (live refresh, chart data, upload checks). */

$config = require dirname(__DIR__, 2) . '/src/bootstrap.php';

Auth::start();
header('Content-Type: application/json; charset=utf-8');

switch ($_GET['action'] ?? '') {
    case 'live':
        $rows = array_map(static fn (array $r): array => [
            'started' => $r['requested_at'],
            'ago'     => time_ago($r['requested_at']),
            'file'    => $r['filename'] ?? $r['path'],
            'ip'      => $r['ip'],
            'size'    => $r['file_size'] !== null ? format_bytes((int) $r['file_size']) : '',
        ], Stats::liveDownloads());
        echo json_encode(['rows' => $rows, 'updated' => date('H:i:s')]);
        break;

    case 'charts':
        $days = 30;
        echo json_encode([
            'daily'    => Stats::downloadsPerDay($days),
            'monthly'  => Stats::downloadsPerMonth(12),
            'topFiles' => Stats::chartTopFiles(10),
            'topIps'   => Stats::chartTopIps(10),
            'notFound' => Stats::notFoundPerDay($days),
            'bots'     => [
                'labels'     => Stats::knownBotsPerDay($days)['labels'],
                'known'      => Stats::knownBotsPerDay($days)['counts'],
                'suspicious' => Stats::suspiciousPerDay($days)['counts'],
            ],
        ]);
        break;

    case 'file_exists':
        $name = sanitize_filename((string) ($_GET['name'] ?? ''));
        $repo = new FileRepository($config);
        echo json_encode([
            'name'   => $name,
            'exists' => $name !== '' && is_file($repo->filesDir() . DIRECTORY_SEPARATOR . $name),
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}
