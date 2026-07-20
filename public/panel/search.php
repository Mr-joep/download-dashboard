<?php

declare(strict_types=1);

require __DIR__ . '/inc/panel.php';

$filters = [
    'filename' => trim((string) ($_GET['filename'] ?? '')),
    'ip'       => trim((string) ($_GET['ip'] ?? '')),
    'from'     => (string) ($_GET['from'] ?? ''),
    'to'       => (string) ($_GET['to'] ?? ''),
    'type'     => (string) ($_GET['type'] ?? ''),
];
// Only accept clean YYYY-MM-DD dates and known types.
foreach (['from', 'to'] as $key) {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters[$key]) !== 1) {
        $filters[$key] = '';
    }
}
if (!in_array($filters['type'], ['', 'downloads', '404', 'bots', 'suspicious'], true)) {
    $filters['type'] = '';
}

$perPage = 50;
$page    = max(1, (int) ($_GET['page'] ?? 1));
[$rows, $total] = Stats::search($filters, $perPage, ($page - 1) * $perPage);

$query = http_build_query(array_filter($filters, static fn (string $v): bool => $v !== ''));
$types = ['' => 'All requests', 'downloads' => 'Downloads', '404' => '404s', 'bots' => 'Bots', 'suspicious' => 'Suspicious'];

panel_header('Search', 'search');
?>
<form class="card mb-3" method="get" action="search.php">
  <div class="card-body row g-2 align-items-end">
    <div class="col-md-3">
      <label class="form-label small" for="f-filename">Filename / path</label>
      <input class="form-control form-control-sm" id="f-filename" name="filename" value="<?= h($filters['filename']) ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label small" for="f-ip">IP</label>
      <input class="form-control form-control-sm" id="f-ip" name="ip" value="<?= h($filters['ip']) ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label small" for="f-from">From</label>
      <input class="form-control form-control-sm" type="date" id="f-from" name="from" value="<?= h($filters['from']) ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label small" for="f-to">To</label>
      <input class="form-control form-control-sm" type="date" id="f-to" name="to" value="<?= h($filters['to']) ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label small" for="f-type">Type</label>
      <select class="form-select form-select-sm" id="f-type" name="type">
        <?php foreach ($types as $value => $label): ?>
        <?php $value = (string) $value; // '404' is an int array key in PHP ?>
        <option value="<?= h($value) ?>"<?= $filters['type'] === $value ? ' selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-1">
      <button class="btn btn-primary btn-sm w-100" type="submit">Search</button>
    </div>
  </div>
</form>

<div class="card">
  <div class="card-header"><?= number_format($total) ?> result<?= $total === 1 ? '' : 's' ?></div>
  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
      <thead>
        <tr><th>Time</th><th>Type</th><th>Path</th><th>IP</th><th>Status</th><th>Info</th></tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="text-nowrap"><?= h($r['requested_at']) ?></td>
          <td><?= request_type_badge($r) ?></td>
          <td><span class="d-inline-block text-truncate mw-path"><?= h($r['path']) ?></span></td>
          <td class="text-nowrap"><?= h($r['ip']) ?></td>
          <td><?= (int) $r['status'] ?></td>
          <td class="text-secondary small"><?= h($r['bot_name'] ?? $r['suspicious_reason'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($rows === []): ?>
        <tr><td colspan="6" class="text-center text-secondary py-4">No matching requests.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
panel_pagination($total, $page, $perPage, 'search.php' . ($query !== '' ? '?' . $query : ''));
panel_footer();
