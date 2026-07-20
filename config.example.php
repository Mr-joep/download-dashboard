<?php

/**
 * Configuration for the down.mr-joep.nl download tracking system.
 *
 * Copy this file to config.php (next to this file, one level above public/)
 * and adjust the values. config.php is the only file you need to edit.
 */
return [

    // ---- database -----------------------------------------------------------
    'db_dsn'  => 'mysql:host=127.0.0.1;dbname=downtrack;charset=utf8mb4',
    'db_user' => 'downtrack',
    'db_pass' => 'change-me',

    // ---- files --------------------------------------------------------------

    // Absolute path of the directory that holds the downloadable files.
    'files_dir' => '/home/dow/public_html',

    // How files are sent to the client:
    //   'xaccel' - PHP logs the request, nginx serves the file (production).
    //   'php'    - PHP streams the file itself in small chunks. Only for
    //              development or non-nginx setups; xaccel is much faster.
    'serve_method' => 'xaccel',

    // The internal nginx location that maps to files_dir.
    // Must match the "location /_protected/" block in the nginx config.
    'xaccel_prefix' => '/_protected',

    // Public base URL, used to build the download links on the upload page.
    'public_base_url' => 'https://down.mr-joep.nl',

    // Only needed when the app runs in a subdirectory during development
    // (e.g. XAMPP at http://localhost/down/public -> '/down/public').
    // Leave empty in production.
    'base_path' => '',

    // ---- dashboard ----------------------------------------------------------

    // Password for the /panel/ dashboard. Either plain text or, preferably,
    // a hash generated with:  php -r "echo password_hash('secret', PASSWORD_DEFAULT);"
    'panel_password' => 'change-me',

    // Allow uploads through the panel.
    'upload_enabled' => true,

    // ---- tracking -----------------------------------------------------------

    // Secret used to sign the download id that nginx passes to complete.php.
    // Set this to a long random string:  php -r "echo bin2hex(random_bytes(32));"
    'complete_secret' => 'change-me-long-random-string',

    // All timestamps are stored and displayed in this timezone.
    'timezone' => 'Europe/Amsterdam',

    // Only enable when nginx sits behind another proxy that sets
    // X-Forwarded-For. When false the direct connection address is used.
    'trust_forwarded_for' => false,

    // More than rate_max requests from one IP within rate_window seconds
    // is logged as suspicious ("high request rate"). Detection only, no bans.
    'rate_window' => 60,
    'rate_max'    => 40,
];
