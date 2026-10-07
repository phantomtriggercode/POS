<?php
// Copy to config/config.php (the installer does this for you) and fill in the
// database details from Hostinger hPanel -> Databases -> MySQL Databases.
return [
    'app_name' => 'Motambarea Farmers POS',
    'timezone' => 'Africa/Douala',
    'debug'    => false,

    'db' => [
        'driver' => 'mysql',          // 'mysql' on Hostinger, 'sqlite' for local testing
        'host'   => 'localhost',      // Hostinger shared hosting uses localhost
        'port'   => 3306,
        'name'   => 'u000000000_pos',
        'user'   => 'u000000000_pos',
        'pass'   => '',
        'sqlite_path' => __DIR__ . '/../storage/pos.sqlite',
    ],

    // Websites allowed to read the public price feed (public/api/prices.php).
    'public_price_origins' => [
        'https://motambeareafarmers.com',
        'https://www.motambeareafarmers.com',
    ],
];
