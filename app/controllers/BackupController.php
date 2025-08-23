<?php
// Backup-related API endpoints

Flight::route('POST /api/backup', function () {
    $config = Flight::get('config');
    $db = Flight::get('db');
    require_auth($config, $db);
    $payload = json_decode(file_get_contents('php://input'), true);
    $paths = $payload['paths'] ?? [];
    $tmp = sys_get_temp_dir() . '/backup_' . bin2hex(random_bytes(4));
    mkdir($tmp, 0700, true);
    $zipPath = "$tmp/archive.zip";
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
        rrmdir($tmp);
        Flight::json(['error' => 'zip_open'], 500);
        return;
    }
    foreach ($paths as $p) {
        if (is_dir($p)) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($p, FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                $local = substr($file, strlen($p) + 1);
                $zip->addFile($file, basename($p) . '/' . $local);
            }
        } elseif (is_file($p)) {
            $zip->addFile($p, basename($p));
        }
    }
    if (!empty($payload['db_dump_cmd'])) {
        $dump = "$tmp/db.sql";
        $cmd = str_replace('{out}', escapeshellarg($dump), $payload['db_dump_cmd']);
        $code = 0;
        system($cmd, $code);
        if ($code === 0 && file_exists($dump)) {
            $zip->addFile($dump, 'db.sql');
        } else {
            rrmdir($tmp);
            Flight::json(['error' => 'db_dump'], 500);
            return;
        }
    }
    $zip->close();
    $hash = hash_file('sha256', $zipPath);
    $manifest = ['created_at' => gmdate('c'), 'paths' => $paths, 'sha256' => $hash];
    $manifestPath = "$tmp/manifest.json";
    file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT));
    $hashPath = "$tmp/archive.sha256";
    file_put_contents($hashPath, $hash);
    $filename = 'backup-' . date('YmdHis');
    $files = [
        $filename . '.zip' => $zipPath,
        $filename . '.manifest.json' => $manifestPath,
        $filename . '.sha256' => $hashPath,
    ];
    foreach ($files as $key => $path) {
        $url = s3_presign_put($config['bucket'], $key, $config);
        if (!s3_put($url, $path)) {
            rrmdir($tmp);
            Flight::json(['error' => 's3_upload'], 500);
            return;
        }
    }
    if (($config['storage'] ?? 'S3_ONLY') === 'LOCAL_AND_S3') {
        $dataDir = __DIR__ . '/../../data/';
        foreach ($files as $key => $path) {
            rename($path, $dataDir . $key);
        }
    } else {
        rrmdir($tmp);
    }
    $stmt = $db->prepare('INSERT INTO backups(filename, created_at) VALUES(?, ?)');
    $stmt->execute([$filename, gmdate('c')]);
    $id = $db->lastInsertId();
    Flight::json(['id' => (int)$id, 'filename' => $filename, 'sha256' => $hash]);
});

Flight::route('GET /api/backups', function () {
    $config = Flight::get('config');
    $db = Flight::get('db');
    require_auth($config, $db);
    $rows = $db->query('SELECT id, filename, created_at FROM backups ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
    Flight::json($rows);
});

Flight::route('GET /api/backup/@id', function ($id) {
    $config = Flight::get('config');
    $db = Flight::get('db');
    require_auth($config, $db);
    $stmt = $db->prepare('SELECT id, filename, created_at FROM backups WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        Flight::json(['error' => 'not_found'], 404);
        return;
    }
    Flight::json($row);
});

Flight::route('GET /api/backup/@id/download', function ($id) {
    $config = Flight::get('config');
    $db = Flight::get('db');
    require_auth($config, $db);
    $stmt = $db->prepare('SELECT filename FROM backups WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        Flight::json(['error' => 'not_found'], 404);
        return;
    }
    $fn = $row['filename'];
    Flight::json([
        'zip' => s3_presign_get($config['bucket'], $fn . '.zip', $config),
        'manifest' => s3_presign_get($config['bucket'], $fn . '.manifest.json', $config),
        'sha256' => s3_presign_get($config['bucket'], $fn . '.sha256', $config),
    ]);
});
?>
