<?php

declare(strict_types=1);

$config = require dirname(__DIR__, 2) . '/src/bootstrap.php';

Auth::start();
if (Auth::check()) {
    header('Location: index.php');
    exit;
}

$error = null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    Auth::validateCsrf($_POST['csrf'] ?? null);
    if (Auth::login((string) ($_POST['password'] ?? ''), $config)) {
        header('Location: index.php');
        exit;
    }
    usleep(500000); // slow down password guessing
    $error = 'Wrong password.';
}
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Log in · down.mr-joep.nl</title>
<link rel="stylesheet" href="assets/bootstrap.min.css">
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="d-grid align-items-center" style="min-height:100vh">
<main class="mx-auto w-100 p-3" style="max-width:22rem">
  <div class="card">
    <div class="card-body p-4">
      <h1 class="h5 mb-1">down.mr-joep.nl</h1>
      <p class="text-secondary small mb-3">Download statistics panel</p>
      <?php if ($error !== null): ?>
      <div class="alert alert-danger py-2"><?= h($error) ?></div>
      <?php endif; ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= h(Auth::csrfToken()) ?>">
        <div class="mb-3">
          <label class="form-label" for="password">Password</label>
          <input class="form-control" type="password" id="password" name="password"
                 autofocus autocomplete="current-password" required>
        </div>
        <button class="btn btn-primary w-100" type="submit">Log in</button>
      </form>
    </div>
  </div>
</main>
</body>
</html>
