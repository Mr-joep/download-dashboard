<?php

declare(strict_types=1);

require __DIR__ . '/inc/panel.php';

$filter = (string) ($_GET['filter'] ?? 'all');
if (!in_array($filter, ['all', 'known', 'suspicious'], true)) {
    $filter = 'all';
}

$perPage = 50;
$page    = max(1, (int) ($_GET['page'] ?? 1));
[$rows, $total] = Stats::botRequests($filter, $perPage, ($page - 1) * $perPage);

$tabs = [
    'all'        => 'All',
    'known'      => 'Known bots',
    'suspicious' => 'Suspicious / scanners',
];

panel_header('Bots', 'bots');
?>
<ul class="nav nav-pills mb-3">
  <?php foreach ($tabs as $key => $label): ?>
  <li class="nav-item">
    <a class="nav-link<?= $filter === $key ? ' active' : '' ?>" href="bots.php?filter=<?= h($key) ?>"><?= h($label) ?></a>
  </li>
  <?php endforeach; ?>
</ul>

<div class="card">
  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
      <thead>
        <tr><th>Time</th><th>IP</th><th>Bot / reason</th><th>Type</th><th>URL</th><th>User agent</th></tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="text-nowrap"><?= h($r['requested_at']) ?></td>
          <td class="text-nowrap"><?= h($r['ip']) ?></td>
          <td><?= h($r['bot_name'] ?? $r['suspicious_reason'] ?? '') ?></td>
          <td>
            <?php if (!empty($r['is_suspicious'])): ?>
              <span class="badge text-bg-danger"><?= h($r['suspicious_reason'] ?? 'suspicious') ?></span>
            <?php endif; ?>
            <?php if (!empty($r['is_bot'])): ?>
              <span class="badge text-bg-info"><?= h($r['bot_type'] ?? 'bot') ?></span>
            <?php endif; ?>
          </td>
          <td><span class="d-inline-block text-truncate mw-path"><?= h($r['path']) ?></span></td>
          <td><span class="d-inline-block text-truncate ua" title="<?= h($r['user_agent']) ?>"><?= h($r['user_agent']) ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($rows === []): ?>
        <tr><td colspan="6" class="text-center text-secondary py-4">Nothing detected yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
panel_pagination($total, $page, $perPage, 'bots.php?filter=' . $filter);
panel_footer();
