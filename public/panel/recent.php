<?php

declare(strict_types=1);

require __DIR__ . '/inc/panel.php';

$perPage = 50;
$page    = max(1, (int) ($_GET['page'] ?? 1));
[$rows, $total] = Stats::recentDownloads($perPage, ($page - 1) * $perPage);

panel_header('Recent downloads', 'recent');
?>
<div class="card">
  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
      <thead>
        <tr><th>Time</th><th>File</th><th>IP</th><th>Size</th><th>Sent</th><th>Status</th></tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="text-nowrap"><?= h($r['requested_at']) ?></td>
          <td><span class="d-inline-block text-truncate mw-path"><?= h($r['filename'] ?? $r['path']) ?></span></td>
          <td class="text-nowrap"><?= h($r['ip']) ?></td>
          <td class="text-nowrap"><?= $r['file_size'] !== null ? h(format_bytes((int) $r['file_size'])) : '' ?></td>
          <td class="text-nowrap"><?= (int) $r['bytes_sent'] > 0 ? h(format_bytes((int) $r['bytes_sent'])) : '' ?></td>
          <td><?= download_status_badge($r) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($rows === []): ?>
        <tr><td colspan="6" class="text-center text-secondary py-4">No downloads yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
panel_pagination($total, $page, $perPage, 'recent.php');
panel_footer();
