<?php
Flight::route('GET /cleanup', function () {
    $config = Flight::get('config');
    if (!empty($config)) {
        $db = Flight::get('db');
        require_auth($config, $db);
    }
    render('cleanup');
});

Flight::route('POST /cleanup', function () {
    $config = Flight::get('config');
    if (!empty($config)) {
        $db = Flight::get('db');
        require_auth($config, $db);
    }
    verify_csrf();
    if (($_POST['confirm'] ?? '') !== 'WIPE') {
        echo 'Confirmation mismatch';
        return;
    }
    @unlink(CONFIG_DIR . '/config.php');
    if (!empty($_POST['wipe_db'])) {
        @unlink(__DIR__ . '/../../data/app.db');
    }
    echo 'Cleaned';
});
