<?php
// Bootstrap file: loads helpers and sets up application context.
require __DIR__ . '/lib/flight/Flight.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/s3.php';

// Ensure data directory exists
if (!is_dir(__DIR__ . '/../data')) {
    mkdir(__DIR__ . '/../data', 0700, true);
}

$config = load_config(CONFIG_DIR . '/config.php');
Flight::set('config', $config);
Flight::set('db', get_db(__DIR__ . '/../data/app.db'));
