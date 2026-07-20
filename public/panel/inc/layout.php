<?php

declare(strict_types=1);

/**
 * "assets/panel.js" -> "assets/panel.js?v=<mtime>" so a deploy invalidates
 * whatever the browser cached, instead of the panel silently running stale JS
 * against fresh HTML (e.g. mismatched table columns after an edit here).
 */
function panel_asset(string $path): string
{
    $file = __DIR__ . '/../' . $path;
    $v    = is_file($file) ? (string) filemtime($file) : (string) time();
    return $path . '?v=' . $v;
}

/** Navigation: slug => [file, label]. */
function panel_nav(): array
{
    return [
        'dashboard'  => ['index.php', 'Dashboard'],
        'live'       => ['live.php', 'Live visitors'],
        'recent'     => ['recent.php', 'Recent downloads'],
        'top-files'  => ['top-files.php', 'Top downloads'],
        'top-ips'    => ['top-ips.php', 'Top IPs'],
        'bots'       => ['bots.php', 'Bots'],
        'errors'     => ['errors.php', '404 requests'],
        'search'     => ['search.php', 'Search'],
        'statistics' => ['statistics.php', 'Statistics'],
        'upload'     => ['upload.php', 'Upload'],
        'storage'    => ['storage.php', 'Storage'],
    ];
}

function panel_header(string $title, string $active): void
{
    ?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> · dow.mr-joep.nl</title>
<link rel="stylesheet" href="assets/bootstrap.min.css">
<link rel="stylesheet" href="<?= h(panel_asset('assets/style.css')) ?>">
</head>
<body data-page="<?= h($active) ?>">
<div class="container-fluid">
  <div class="row">
    <aside class="col-lg-2 p-0 sidebar-col">
      <div class="offcanvas-lg offcanvas-start sidebar" tabindex="-1" id="sidebar" aria-label="Menu">
        <div class="offcanvas-header border-bottom">
          <span class="fw-semibold">dow.mr-joep.nl</span>
          <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body d-lg-flex flex-column p-2 pt-lg-3">
          <div class="d-none d-lg-block px-2 pb-3 brand">dow.mr-joep.nl</div>
          <ul class="nav nav-pills flex-column gap-1">
            <?php foreach (panel_nav() as $slug => [$href, $label]): ?>
            <li class="nav-item">
              <a class="nav-link<?= $slug === $active ? ' active' : '' ?>" href="<?= h($href) ?>"><?= h($label) ?></a>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </aside>
    <main class="col-lg-10 px-3 px-lg-4 py-3">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 mb-0"><?= h($title) ?></h1>
        <button class="btn btn-sm btn-outline-secondary d-lg-none" type="button"
                data-bs-toggle="offcanvas" data-bs-target="#sidebar">Menu</button>
      </div>
    <?php
}

function panel_footer(bool $withCharts = false): void
{
    ?>
    </main>
  </div>
</div>
<script src="assets/bootstrap.bundle.min.js"></script>
<?php if ($withCharts): ?>
<script src="assets/chart.umd.min.js"></script>
<?php endif; ?>
<script src="<?= h(panel_asset('assets/panel.js')) ?>"></script>
</body>
</html>
    <?php
}

/** Type badge for a request row (dashboard activity, search results). */
function request_type_badge(array $r): string
{
    if (!empty($r['is_suspicious'])) {
        return '<span class="badge text-bg-danger">Suspicious</span>';
    }
    if (!empty($r['is_bot'])) {
        return '<span class="badge text-bg-info">Bot</span>';
    }
    if ((int) $r['status'] === 404) {
        return '<span class="badge text-bg-warning">404</span>';
    }
    if (!empty($r['is_download'])) {
        return '<span class="badge text-bg-success">Download</span>';
    }
    return '<span class="badge text-bg-secondary">Other</span>';
}

/** Transfer status badge for a download row. */
function download_status_badge(array $r): string
{
    if ((int) $r['status'] === 404) {
        return '<span class="badge text-bg-danger">404</span>';
    }
    if (!empty($r['completed'])) {
        return '<span class="badge text-bg-success">Completed</span>';
    }
    if ($r['finished_at'] === null) {
        $recent = strtotime((string) $r['requested_at']) >= time() - Stats::LIVE_WINDOW_HOURS * 3600;
        return $recent
            ? '<span class="badge text-bg-warning">In progress</span>'
            : '<span class="badge text-bg-secondary">Unknown</span>';
    }
    return '<span class="badge text-bg-secondary">Partial</span>';
}

/** Simple prev/next pagination. $baseUrl must not contain a page parameter. */
function panel_pagination(int $total, int $page, int $perPage, string $baseUrl): void
{
    $pages = max(1, (int) ceil($total / $perPage));
    if ($pages <= 1) {
        return;
    }
    $sep = str_contains($baseUrl, '?') ? '&' : '?';
    echo '<nav class="d-flex align-items-center gap-2 mt-3">';
    if ($page > 1) {
        echo '<a class="btn btn-sm btn-outline-secondary" href="'
            . h($baseUrl . $sep . 'page=' . ($page - 1)) . '">&laquo; Newer</a>';
    }
    echo '<span class="text-secondary small">Page ' . $page . ' of ' . $pages . '</span>';
    if ($page < $pages) {
        echo '<a class="btn btn-sm btn-outline-secondary" href="'
            . h($baseUrl . $sep . 'page=' . ($page + 1)) . '">Older &raquo;</a>';
    }
    echo '</nav>';
}
