<?php
Flight::route('GET /update', function () {
    $config = Flight::get('config');
    $db = Flight::get('db');
    require_auth($config, $db);
    render('update', ['config' => $config]);
});

Flight::route('POST /update', function () {
    $config = Flight::get('config');
    $db = Flight::get('db');
    require_auth($config, $db);
    verify_csrf();
    if (isset($_POST['fix'])) {
        $warnings = enforce_permissions(CONFIG_DIR . '/config.php', true);
        echo $warnings ? implode(', ', $warnings) : 'Permissions fixed';
        return;
    }
    $config['s3_endpoint'] = trim($_POST['s3_endpoint'] ?? $config['s3_endpoint']);
    $config['s3_region'] = trim($_POST['s3_region'] ?? $config['s3_region']);
    $config['s3_access_key'] = trim($_POST['s3_access_key'] ?? $config['s3_access_key']);
    $config['s3_secret_key'] = trim($_POST['s3_secret_key'] ?? $config['s3_secret_key']);
    $config['bucket'] = trim($_POST['bucket'] ?? $config['bucket']);
    $config['storage'] = $_POST['storage'] ?? $config['storage'];
    save_config(CONFIG_DIR . '/config.php', $config);
    Flight::set('config', $config);
    echo 'Updated';
});
