<?php

declare(strict_types=1);

require __DIR__ . '/inc/panel.php';

// Keep the file list in sync with the download directory (~20 files, cheap).
(new FileRepository($config))->syncFromDisk();

$stats    = Stats::overview();
$activity = Stats::recentActivity(15);

$cards = [
    ['Total downloads', $stats['total_downloads']],
    ['Downloads today', $stats['downloads_today']],
    ['Downloads this week', $stats['downloads_week']],
    ['Downloads this month', $stats['downloads_month']],
    ['Files', $stats['total_files']],
    ['Total requests', $stats['total_requests']],
    ['404 requests', $stats['requests_404']],
    ['Bot requests', $stats['bot_requests']],
];

panel_header('Dashboard', 'dashboard');
?>
<div class="row g-3 mb-4">
  <?php foreach ($cards as [$label, $value]): ?>
  <div class="col-6 col-md-4 col-xl-3">
    <div class="card stat-card h-100">
      <div class="card-body">
        <div class="stat-value"><?= number_format((int) $value) ?></div>
        <div class="stat-label"><?= h($label) ?></div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="card-header">Recent activity</div>
  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
      <thead>
        <tr><th>Time</th><th>Type</th><th>Path</th><th>IP</th><th>Info</th></tr>
      </thead>
      <tbody>
      <?php foreach ($activity as $r): ?>
        <tr>
          <td class="text-nowrap"><?= h($r['requested_at']) ?></td>
          <td><?= request_type_badge($r) ?></td>
          <td><span class="d-inline-block text-truncate mw-path"><?= h($r['path']) ?></span></td>
          <td class="text-nowrap"><?= h($r['ip']) ?></td>
          <td class="text-secondary small"><?= h($r['bot_name'] ?? $r['suspicious_reason'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($activity === []): ?>
        <tr><td colspan="5" class="text-center text-secondary py-4">No requests logged yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php panel_footer();
