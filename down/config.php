<?php

/**
 * Local development config (XAMPP / php -S). See config.example.php for the
 * documentation of every option. Do not deploy this file to the server.
 */
return [
    'db_dsn'  => 'mysql:host=127.0.0.1;dbname=downtrack;charset=utf8mb4',
    'db_user' => 'root',
    'db_pass' => '',

    'files_dir'    => __DIR__ . '/storage',
    'serve_method' => 'php',
    'xaccel_prefix' => '/_protected',
    'public_base_url' => 'http://127.0.0.1:8080',
    'base_path' => '',

    'panel_password' => 'dev',
    'upload_enabled' => true,

    'complete_secret' => 'dev-secret-not-for-production',
    'timezone' => 'Europe/Amsterdam',
    'trust_forwarded_for' => false,
    'rate_window' => 60,
    'rate_max' => 40,
];
