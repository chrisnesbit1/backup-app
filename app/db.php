<?php
function get_db(string $path): PDO
{
    $init = !file_exists($path);
    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if ($init) {
        $pdo->exec('CREATE TABLE IF NOT EXISTS backups (id INTEGER PRIMARY KEY AUTOINCREMENT, filename TEXT, created_at TEXT)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS tokens (token TEXT PRIMARY KEY, created_at TEXT)');
    }
    return $pdo;
}
