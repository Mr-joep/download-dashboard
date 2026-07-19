<?php

declare(strict_types=1);

require __DIR__ . '/inc/panel.php';

$ips = Stats::topIps(100);

panel_header('Top IPs', 'top-ips');
?>
<div class="card">
  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
      <thead>
        <tr><th>IP</th><th class="text-end">Downloads</th><th class="text-end">Requests</th><th>Last seen</th></tr>
      </thead>
      <tbody>
      <?php foreach ($ips as $r): ?>
        <tr>
          <td class="text-nowrap"><?= h($r['ip']) ?></td>
          <td class="text-end fw-semibold"><?= number_format((int) $r['downloads']) ?></td>
          <td class="text-end"><?= number_format((int) $r['requests']) ?></td>
          <td class="text-nowrap"><?= h($r['last_seen']) ?> <span class="text-secondary small">(<?= h(time_ago($r['last_seen'])) ?>)</span></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($ips === []): ?>
        <tr><td colspan="4" class="text-center text-secondary py-4">No requests logged yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php panel_footer();
