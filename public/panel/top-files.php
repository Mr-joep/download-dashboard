<?php

declare(strict_types=1);

require __DIR__ . '/inc/panel.php';

(new FileRepository($config))->syncFromDisk();
$files = Stats::topFiles(200);

panel_header('Top downloads', 'top-files');
?>
<div class="card">
  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
      <thead>
        <tr><th>File</th><th>Size</th><th class="text-end">Downloads</th><th>First download</th><th>Last download</th></tr>
      </thead>
      <tbody>
      <?php foreach ($files as $f): ?>
        <tr>
          <td>
            <span class="d-inline-block text-truncate mw-path"><?= h($f['filename']) ?></span>
            <?php if (!empty($f['missing'])): ?>
              <span class="badge text-bg-secondary">removed</span>
            <?php endif; ?>
          </td>
          <td class="text-nowrap"><?= h(format_bytes((int) $f['size'])) ?></td>
          <td class="text-end fw-semibold"><?= number_format((int) $f['total_downloads']) ?></td>
          <td class="text-nowrap"><?= h($f['first_download'] ?? '-') ?></td>
          <td class="text-nowrap"><?= h($f['last_download'] ?? '-') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($files === []): ?>
        <tr><td colspan="5" class="text-center text-secondary py-4">No files found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php panel_footer();
