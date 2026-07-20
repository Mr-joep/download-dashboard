<?php

declare(strict_types=1);

require __DIR__ . '/inc/panel.php';

$repo = new FileRepository($config);
$repo->syncFromDisk();

$baseUrl = rtrim((string) $config['public_base_url'], '/');
$message = null;
$error   = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    Auth::validateCsrf($_POST['csrf'] ?? null);
    $action = (string) ($_POST['action'] ?? '');
    // resolve() (not sanitize_filename(), which strips directories via
    // basename()) so files_dir subdirectories keep working here too.
    $file = $repo->resolve((string) ($_POST['name'] ?? ''));

    if ($file === null) {
        $error = 'File not found.';
    } elseif ($action === 'delete') {
        unlink($file['path']);
        $repo->syncFromDisk();
        $message = 'Deleted "' . $file['filename'] . '".';
    } elseif ($action === 'rename') {
        $newName   = sanitize_filename((string) ($_POST['new_name'] ?? ''));
        $newTarget = $repo->filesDir() . DIRECTORY_SEPARATOR . $newName;
        if ($newName === '') {
            $error = 'Invalid new file name.';
        } elseif (preg_match('/\.(php\d?|phtml|phar)(\.|$)/i', $newName) === 1) {
            $error = 'PHP files cannot be served, so they cannot be uploaded or renamed to.';
        } elseif ($newName === $file['filename']) {
            $error = 'That is already the current name.';
        } elseif (is_file($newTarget)) {
            $error = 'A file named "' . $newName . '" already exists.';
        } elseif (Database::fetch('SELECT id FROM files WHERE filename = ?', [$newName]) !== null) {
            $error = 'That name was used before and its history is still on record. Pick a different name.';
        } elseif (!rename($file['path'], $newTarget)) {
            $error = 'Could not rename the file. Check that the files directory is writable for PHP.';
        } else {
            // Keep the stats (downloads, first/last seen) attached under the new name.
            Database::run('UPDATE files SET filename = ? WHERE filename = ?', [$newName, $file['filename']]);
            $message = 'Renamed "' . $file['filename'] . '" to "' . $newName . '".';
        }
    } else {
        $error = 'Unknown action.';
    }
}

$files = Database::fetchAll('SELECT * FROM files WHERE missing = 0 ORDER BY filename ASC');

panel_header('Storage', 'storage');
?>
<?php if ($error !== null): ?>
<div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>
<?php if ($message !== null): ?>
<div class="alert alert-success"><?= h($message) ?></div>
<?php endif; ?>

<div class="card">
  <div class="card-header">Files (<?= count($files) ?>)</div>
  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
      <thead>
        <tr>
          <th>File</th>
          <th>Size</th>
          <th class="text-end">Downloads</th>
          <th>Uploaded / first seen</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($files as $f): ?>
        <?php $link = $baseUrl . '/' . implode('/', array_map('rawurlencode', explode('/', $f['filename']))); ?>
        <tr>
          <td><span class="d-inline-block text-truncate mw-path"><?= h($f['filename']) ?></span></td>
          <td class="text-nowrap"><?= h(format_bytes((int) $f['size'])) ?></td>
          <td class="text-end"><?= number_format((int) $f['total_downloads']) ?></td>
          <td class="text-nowrap"><?= h($f['uploaded_at'] ?? $f['first_seen'] ?? '-') ?></td>
          <td class="text-end text-nowrap">
            <a class="btn btn-sm btn-outline-secondary" href="<?= h($link) ?>">Download</a>
            <button class="btn btn-sm btn-outline-secondary" type="button"
                    data-rename-name="<?= h($f['filename']) ?>">Rename</button>
            <form class="d-inline" method="post"
                  data-confirm="Delete &quot;<?= h($f['filename']) ?>&quot;? This cannot be undone.">
              <input type="hidden" name="csrf" value="<?= h(Auth::csrfToken()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="name" value="<?= h($f['filename']) ?>">
              <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if ($files === []): ?>
        <tr><td colspan="5" class="text-center text-secondary py-4">No files yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="rename-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <div class="modal-header">
          <h5 class="modal-title">Rename file</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="csrf" value="<?= h(Auth::csrfToken()) ?>">
          <input type="hidden" name="action" value="rename">
          <input type="hidden" name="name" id="rename-name">
          <label class="form-label" for="rename-new-name">New name</label>
          <input class="form-control" type="text" id="rename-new-name" name="new_name" required>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Rename</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php panel_footer();
