<?php

declare(strict_types=1);

require __DIR__ . '/inc/panel.php';

$repo = new FileRepository($config);
$repo->syncFromDisk();

$baseUrl = rtrim((string) $config['public_base_url'], '/');
$message = null;
$error   = null;
$newLink = null;
$isAjax  = ($_POST['ajax'] ?? '') === '1';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    Auth::validateCsrf($_POST['csrf'] ?? null);

    if (empty($config['upload_enabled'])) {
        $error = 'Uploads are disabled in config.php.';
    } elseif (!isset($_FILES['file']['error']) || is_array($_FILES['file']['error'])) {
        $error = 'No file received.';
    } elseif ((int) $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $error = upload_error_message((int) $_FILES['file']['error']);
    } else {
        $name = sanitize_filename((string) $_FILES['file']['name']);
        if ($name === '') {
            $error = 'Invalid file name.';
        } elseif (preg_match('/\.(php\d?|phtml|phar)(\.|$)/i', $name) === 1) {
            $error = 'PHP files cannot be uploaded.';
        } else {
            $target    = $repo->filesDir() . DIRECTORY_SEPARATOR . $name;
            $overwrite = ($_POST['overwrite'] ?? '') === '1';
            if (is_file($target) && !$overwrite) {
                $error = 'A file named "' . $name . '" already exists. Tick "Overwrite" to replace it.';
            } elseif (!move_uploaded_file((string) $_FILES['file']['tmp_name'], $target)) {
                $error = 'Could not move the upload. Check that the files directory is writable for PHP.';
            } else {
                $size   = (int) filesize($target);
                $fileId = $repo->ensureRecord($name, $size);
                Database::run('UPDATE files SET uploaded_at = ? WHERE id = ?', [now(), $fileId]);
                $newLink = $baseUrl . '/' . rawurlencode($name);
                $message = 'Uploaded "' . $name . '" (' . format_bytes($size) . ').';
            }
        }
    }

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => $error === null,
            'error'   => $error,
            'name'    => $name ?? null,
            'size'    => isset($size) ? format_bytes($size) : null,
            'link'    => $newLink,
        ]);
        exit;
    }
}

$files     = Database::fetchAll('SELECT * FROM files WHERE missing = 0 ORDER BY filename ASC');
$uploadMax = ini_get('upload_max_filesize') . ' (upload_max_filesize)';

panel_header('Upload', 'upload');
?>
<?php if ($error !== null): ?>
<div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>
<?php if ($message !== null): ?>
<div class="alert alert-success mb-3"><?= h($message) ?></div>
<?php endif; ?>
<?php if ($newLink !== null): ?>
<div class="card mb-3">
  <div class="card-body">
    <label class="form-label small text-secondary">Download link</label>
    <div class="input-group">
      <input class="form-control" type="text" readonly value="<?= h($newLink) ?>" onfocus="this.select()">
      <button class="btn btn-outline-secondary" type="button" data-copy="<?= h($newLink) ?>">Copy</button>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-xl-5">
    <div class="card">
      <div class="card-header">Upload a file</div>
      <div class="card-body">
        <form id="upload-form" method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= h(Auth::csrfToken()) ?>">
          <div class="mb-3">
            <label class="form-label" for="file">File(s)</label>
            <input class="form-control" type="file" id="file" name="file" multiple required>
            <div class="form-text">Server upload limit: <?= h($uploadMax) ?> per file. Copy very large files to the server directly (scp/rsync); they are picked up automatically.</div>
          </div>
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="overwrite" name="overwrite" value="1">
            <label class="form-check-label" for="overwrite">Overwrite if the file already exists</label>
          </div>
          <button class="btn btn-primary" type="submit" id="upload-submit">Upload</button>
        </form>
        <div id="upload-progress-list" class="mt-3 d-none"></div>
      </div>
    </div>
  </div>
  <div class="col-xl-7">
    <div class="card">
      <div class="card-header">Files (<?= count($files) ?>)</div>
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead>
            <tr><th>File</th><th>Size</th><th>Uploaded</th><th class="text-end">Link</th></tr>
          </thead>
          <tbody>
          <?php foreach ($files as $f): ?>
            <?php $link = $baseUrl . '/' . implode('/', array_map('rawurlencode', explode('/', $f['filename']))); ?>
            <tr>
              <td><span class="d-inline-block text-truncate mw-path"><?= h($f['filename']) ?></span></td>
              <td class="text-nowrap"><?= h(format_bytes((int) $f['size'])) ?></td>
              <td class="text-nowrap"><?= h($f['uploaded_at'] ?? '-') ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-secondary" href="<?= h($link) ?>" target="_blank" rel="noopener">Open</a>
                <button class="btn btn-sm btn-outline-secondary" type="button" data-copy="<?= h($link) ?>">Copy</button>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if ($files === []): ?>
            <tr><td colspan="4" class="text-center text-secondary py-4">No files yet.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php panel_footer();
