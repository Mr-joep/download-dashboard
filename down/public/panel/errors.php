<?php

declare(strict_types=1);

require __DIR__ . '/inc/panel.php';

$perPage = 50;
$page    = max(1, (int) ($_GET['page'] ?? 1));
[$rows, $total] = Stats::notFoundRequests($perPage, ($page - 1) * $perPage);
$top = Stats::topMissingPaths(10);

panel_header('404 requests', 'errors');
?>
<div class="row g-3">
  <div class="col-xl-8">
    <div class="card">
      <div class="card-header">All 404 requests (<?= number_format($total) ?>)</div>
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead>
            <tr><th>Time</th><th>Path</th><th>IP</th><th>Info</th></tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td class="text-nowrap"><?= h($r['requested_at']) ?></td>
              <td><span class="d-inline-block text-truncate mw-path"><?= h($r['path']) ?></span></td>
              <td class="text-nowrap"><?= h($r['ip']) ?></td>
              <td class="text-secondary small"><?= h($r['bot_name'] ?? $r['suspicious_reason'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if ($rows === []): ?>
            <tr><td colspan="4" class="text-center text-secondary py-4">No 404 requests logged.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php panel_pagination($total, $page, $perPage, 'errors.php'); ?>
  </div>
  <div class="col-xl-4">
    <div class="card">
      <div class="card-header">Most requested missing paths</div>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead><tr><th>Path</th><th class="text-end">Hits</th></tr></thead>
          <tbody>
          <?php foreach ($top as $r): ?>
            <tr>
              <td><span class="d-inline-block text-truncate mw-path"><?= h($r['path']) ?></span></td>
              <td class="text-end"><?= number_format((int) $r['hits']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if ($top === []): ?>
            <tr><td colspan="2" class="text-center text-secondary py-4">Nothing yet.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php panel_footer();
