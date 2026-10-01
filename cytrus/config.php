<?php
declare(strict_types=1);

// Application configuration. Review before first deployment.
return [
    'app_name'              => 'Cytrus Files',
    'timezone'              => 'Europe/Warsaw',
    'storage_root'          => __DIR__ . '/ftp',
    'data_dir'              => __DIR__ . '/data',
    'session_name'          => 'cytrus_sid',
    'session_lifetime'      => 60 * 60 * 8, // 8h
    'force_https'           => false, // set true once TLS (https) is configured
    'blocked_extensions'    => ['php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'pht', 'exe', 'dll', 'so', 'sh', 'bat', 'cmd', 'cgi'],
    'max_login_attempts'    => 5,
    'login_lockout_seconds' => 300,
    'share_token_bytes'     => 24,
];
