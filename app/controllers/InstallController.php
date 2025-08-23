<?php
Flight::route('GET /install', function () {
    $config = Flight::get('config');
    if (!empty($config)) {
        echo 'Already installed';
        return;
    }
    render('install');
});

Flight::route('POST /install', function () {
    verify_csrf();
    $config = [
        'token' => bin2hex(random_bytes(16)),
        's3_endpoint' => trim($_POST['s3_endpoint'] ?? ''),
        's3_region' => trim($_POST['s3_region'] ?? ''),
        's3_access_key' => trim($_POST['s3_access_key'] ?? ''),
        's3_secret_key' => trim($_POST['s3_secret_key'] ?? ''),
        'bucket' => trim($_POST['bucket'] ?? ''),
        'storage' => $_POST['storage'] ?? 'S3_ONLY',
    ];
    save_config(CONFIG_DIR . '/config.php', $config);
    $warnings = enforce_permissions(CONFIG_DIR . '/config.php');
    $db = Flight::get('db');
    $stmt = $db->prepare('INSERT INTO tokens(token, created_at) VALUES(?, ?)');
    $stmt->execute([$config['token'], gmdate('c')]);
    Flight::set('config', $config);
    if (!head_request($config['s3_endpoint'])) {
        echo 'Installed but S3 endpoint not reachable';
    } elseif ($warnings) {
        echo 'Installed with warnings: ' . implode(', ', $warnings);
    } else {
        echo 'Installed';
    }
});
