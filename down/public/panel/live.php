<?php

declare(strict_types=1);

require __DIR__ . '/inc/panel.php';

$live = Stats::liveDownloads();

panel_header('Live visitors', 'live');
?>
<p class="text-secondary small mb-3">
  Downloads that are running right now. Refreshes every 5 seconds
  <span id="live-updated"></span>
</p>
<div class="card">
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead>
        <tr><th>Started</th><th>File</th><th>IP</th><th>Size</th><th></th></tr>
      </thead>
      <tbody id="live-rows">
      <?php foreach ($live as $r): ?>
        <tr>
          <td class="text-nowrap"><?= h($r['requested_at']) ?> <span class="text-secondary small">(<?= h(time_ago($r['requested_at'])) ?>)</span></td>
          <td><span class="d-inline-block text-truncate mw-path"><?= h($r['filename'] ?? $r['path']) ?></span></td>
          <td class="text-nowrap"><?= h($r['ip']) ?></td>
          <td class="text-nowrap"><?= $r['file_size'] !== null ? h(format_bytes((int) $r['file_size'])) : '' ?></td>
          <td><span class="badge text-bg-warning">In progress</span></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($live === []): ?>
        <tr><td colspan="5" class="text-center text-secondary py-4">No active downloads.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php panel_footer();
