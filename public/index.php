<?php
// Front controller: resolve config dir and boot application

if (!defined('CONFIG_DIR')) {
    $cfg = getenv('BACKUPAPP_CONFIG_DIR');
    if (!$cfg) {
        $home = getenv('HOME') ?: __DIR__ . '/..';
        $cfg = $home . '/.backupapp';
    }
    define('CONFIG_DIR', $cfg);
}
require __DIR__ . '/../app/bootstrap.php';

// Register routes
require __DIR__ . '/../app/controllers/InstallController.php';
require __DIR__ . '/../app/controllers/UpdateController.php';
require __DIR__ . '/../app/controllers/CleanupController.php';
require __DIR__ . '/../app/controllers/BackupController.php';
require __DIR__ . '/../app/controllers/RestoreController.php';

Flight::start();
