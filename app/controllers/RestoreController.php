<?php
// Restore and admin endpoints

Flight::route('POST /api/restore', function () {
    $config = Flight::get('config');
    $db = Flight::get('db');
    require_auth($config, $db);
    $payload = json_decode(file_get_contents('php://input'), true);
    $id = $payload['backup_id'] ?? $payload['id'] ?? null;
    $target = $payload['target'] ?? __DIR__ . '/../../data/restore';
    $verifyOnly = !empty($payload['verify_only']);
    $restoreCmd = $payload['restore_cmd'] ?? '';
    $restoreDb = !empty($payload['restore_db']);
    $stmt = $db->prepare('SELECT filename FROM backups WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        Flight::json(['error' => 'not_found'], 404);
        return;
    }
    $fn = $row['filename'];
    $tmp = sys_get_temp_dir() . '/restore_' . bin2hex(random_bytes(4));
    mkdir($tmp, 0700, true);
    $zipPath = "$tmp/archive.zip";
    $hashPath = "$tmp/archive.sha256";
    if (!s3_get(s3_presign_get($config['bucket'], $fn . '.zip', $config), $zipPath) ||
        !s3_get(s3_presign_get($config['bucket'], $fn . '.sha256', $config), $hashPath)) {
        rrmdir($tmp);
        Flight::json(['error' => 'download'], 500);
        return;
    }
    $expected = trim(file_get_contents($hashPath));
    $actual = hash_file('sha256', $zipPath);
    if ($expected !== $actual) {
        rrmdir($tmp);
        Flight::json(['error' => 'checksum'], 500);
        return;
    }
    if ($verifyOnly) {
        rrmdir($tmp);
        Flight::json(['verified' => true]);
        return;
    }
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        rrmdir($tmp);
        Flight::json(['error' => 'zip_open'], 500);
        return;
    }
    $zip->extractTo($target);
    $zip->close();
    if ($restoreDb && $restoreCmd) {
        $dbFile = rtrim($target, '/') . '/db.sql';
        if (file_exists($dbFile)) {
            $cmd = str_replace('{in}', escapeshellarg($dbFile), $restoreCmd);
            $code = 0;
            system($cmd, $code);
            if ($code !== 0) {
                rrmdir($tmp);
                Flight::json(['error' => 'db_restore'], 500);
                return;
            }
        }
    }
    rrmdir($tmp);
    Flight::json(['restored' => true]);
});

Flight::route('POST /api/admin/rotate-token', function () {
    $config = Flight::get('config');
    $db = Flight::get('db');
    require_auth($config, $db);
    $new = bin2hex(random_bytes(16));
    $stmt = $db->prepare('INSERT INTO tokens(token, created_at) VALUES(?, ?)');
    $stmt->execute([$new, gmdate('c')]);
    $config['token'] = $new;
    save_config(CONFIG_DIR . '/config.php', $config);
    Flight::set('config', $config);
    Flight::json(['token' => $new]);
});
?>
