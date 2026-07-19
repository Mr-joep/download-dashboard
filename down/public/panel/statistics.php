<?php

declare(strict_types=1);

require __DIR__ . '/inc/panel.php';

panel_header('Statistics', 'statistics');

$charts = [
    ['chart-daily',     'Downloads per day (30 days)'],
    ['chart-monthly',   'Downloads per month (12 months)'],
    ['chart-top-files', 'Top downloaded files'],
    ['chart-top-ips',   'Top IPs'],
    ['chart-404',       '404 requests per day (30 days)'],
    ['chart-bots',      'Bot activity per day (30 days)'],
];
?>
<div class="row g-3">
  <?php foreach ($charts as [$id, $title]): ?>
  <div class="col-12 col-xl-6">
    <div class="card">
      <div class="card-header"><?= h($title) ?></div>
      <div class="card-body">
        <div class="chart-box"><canvas id="<?= h($id) ?>"></canvas></div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php panel_footer(true);
