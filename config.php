<?php

/**
 * Production configuration for dow.mr-joep.nl. See config.example.php for
 * the documentation of every option.
 */
return [
    'db_dsn'  => 'mysql:host=localhost;dbname=dow;charset=utf8mb4',
    'db_user' => 'dow',
    'db_pass' => 'Urs4HSXXuJf0KJ6LVvr7o5jlF',

    'files_dir'    => '/home/dow/public_html/files',
    'serve_method' => 'xaccel',
    'xaccel_prefix' => '/_protected',
    'public_base_url' => 'https://dow.mr-joep.nl',
    'base_path' => '',

    'panel_password' => '$2y$12$aLUZOSaZkbmquj5A..JHkuyz/uQA6Xjpq/rznYGdy2ZoVW2cdn2eS',
    'upload_enabled' => true,

    'complete_secret' => 'cfee53beff9f23fd3f0a712a0b9dd5a99b69b3d872ba2b6fc7b98986eed44223',
    'timezone' => 'Europe/Amsterdam',
    'trust_forwarded_for' => false,
    'rate_window' => 60,
    'rate_max' => 40,
];
