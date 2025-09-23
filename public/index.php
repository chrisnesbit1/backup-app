<?php
// Front controller: resolve config/storage locations and boot application

if (!defined('APP_ROOT')) {
    $root = realpath(__DIR__ . '/..') ?: (__DIR__ . '/..');
    define('APP_ROOT', rtrim($root, "\\/"));
}

$resolvePath = static function (string $path): string {
    if ($path === '') {
        return '';
    }
    if (preg_match('#^(?:[A-Za-z]:)?[\\/]#', $path)) {
        return rtrim($path, "\\/");
    }
    return rtrim(APP_ROOT . '/' . ltrim($path, "\\/"), "\\/");
};

if (!defined('CONFIG_DIR')) {
    $cfg = getenv('BACKUPAPP_CONFIG_DIR') ?: 'data/config';
    define('CONFIG_DIR', $resolvePath($cfg));
}

if (!defined('STORAGE_DIR')) {
    $storage = getenv('BACKUPAPP_STORAGE_DIR') ?: 'data/storage';
    define('STORAGE_DIR', $resolvePath($storage));
}

if (!defined('DB_PATH')) {
    $db = getenv('BACKUPAPP_DB_PATH') ?: CONFIG_DIR . '/app.db';
    define('DB_PATH', $resolvePath($db));
}

require __DIR__ . '/../app/bootstrap.php';

// Register routes
require __DIR__ . '/../app/controllers/InstallController.php';
require __DIR__ . '/../app/controllers/UpdateController.php';
require __DIR__ . '/../app/controllers/CleanupController.php';
require __DIR__ . '/../app/controllers/BackupController.php';
require __DIR__ . '/../app/controllers/RestoreController.php';

Flight::route('GET /health', fn() => 'ok');

Flight::start();
