<?php
// Bootstrap file: loads helpers and sets up application context.
require dirname(__DIR__, 1) . '/vendor/autoload.php';
//require __DIR__ . '/lib/flight/Flight.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/s3.php';

// Ensure runtime directories exist
foreach ([CONFIG_DIR, STORAGE_DIR, dirname(DB_PATH)] as $dir) {
    if ($dir !== '' && !is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
}

$configPath = CONFIG_DIR . '/config.php';
$config = load_config($configPath);

Flight::set('config', $config);
Flight::set('config_path', $configPath);
Flight::set('paths', [
    'root' => APP_ROOT,
    'config' => CONFIG_DIR,
    'storage' => STORAGE_DIR,
    'database' => DB_PATH,
]);
Flight::set('db', get_db(DB_PATH));
