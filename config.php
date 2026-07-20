<?php
return [
    'db_dsn'  => 'mysql:host=localhost;dbname=dow;charset=utf8mb4',
    'db_user' => 'dow', //user from the DB
    'db_pass' => 'Urs4HSXXuJf0KJ6LVvr7o5jlF', // password for the database user in the database server (this is a example password, you should change it to a secure one)

    'files_dir'    => '/home/dow/public_html/files',
    'serve_method' => 'xaccel',
    'xaccel_prefix' => '/_protected',
    'public_base_url' => 'https://dow.mr-joep.nl',
    'base_path' => '',

    'upload_enabled' => true,

    'timezone' => 'Europe/Amsterdam',
    'trust_forwarded_for' => false,
    'rate_window' => 60,
    'rate_max' => 40,
];
