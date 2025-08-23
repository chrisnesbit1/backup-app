<?php
function load_config(string $path): array
{
    if (file_exists($path)) {
        return include $path;
    }
    return [];
}

function save_config(string $path, array $config): void
{
    if (!is_dir(dirname($path))) {
        mkdir(dirname($path), 0700, true);
    }
    $export = var_export($config, true);
    file_put_contents($path, "<?php\nreturn $export;\n");
    chmod($path, 0600);
}

function enforce_permissions(string $path, bool $allow0640 = false): array
{
    $dir = dirname($path);
    @chmod($dir, 0700);
    @chmod($path, 0600);
    $warnings = [];
    if ((fileperms($dir) & 0777) !== 0700) {
        $warnings[] = 'Directory permissions are not 0700';
    }
    $filePerm = fileperms($path) & 0777;
    if ($filePerm !== 0600) {
        if (!($allow0640 && $filePerm === 0640)) {
            $warnings[] = 'Config permissions are not 0600';
        }
    }
    return $warnings;
}

function require_auth(array $config, PDO $db): void
{
    $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/Bearer\s+(.*)$/i', $hdr, $m)) {
        http_response_code(401);
        echo 'Unauthorized';
        exit;
    }
    $token = $m[1];
    $stmt = $db->prepare('SELECT token, created_at FROM tokens WHERE token = ?');
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $valid = false;
    if ($row) {
        if ($token === ($config['token'] ?? '')) {
            $valid = true;
        } elseif (time() - strtotime($row['created_at']) <= 1800) {
            $valid = true;
        }
    }
    $db->exec("DELETE FROM tokens WHERE token != '" . ($config['token'] ?? '') . "' AND created_at <= datetime('now','-30 minutes')");
    if (!$valid) {
        http_response_code(401);
        echo 'Unauthorized';
        exit;
    }
}

function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function verify_csrf(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $token = $_POST['csrf'] ?? '';
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(400);
        echo 'CSRF validation failed';
        exit;
    }
}

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = "$dir/$item";
        if (is_dir($path)) {
            rrmdir($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

function head_request(string $url): bool
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return $code >= 200 && $code < 400;
}

function render(string $view, array $vars = []): void
{
    extract($vars);
    include __DIR__ . '/views/layout_top.php';
    include __DIR__ . "/views/$view.php";
    include __DIR__ . '/views/layout_bottom.php';
}

function s3_put(string $url, string $file): bool
{
    $ch = curl_init($url);
    $fh = fopen($file, 'rb');
    curl_setopt_array($ch, [
        CURLOPT_PUT => true,
        CURLOPT_INFILE => $fh,
        CURLOPT_INFILESIZE => filesize($file),
        CURLOPT_RETURNTRANSFER => true,
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    fclose($fh);
    return $code >= 200 && $code < 300;
}

function s3_get(string $url, string $dest): bool
{
    $ch = curl_init($url);
    $fh = fopen($dest, 'wb');
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fh,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_RETURNTRANSFER => true,
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    fclose($fh);
    return $code >= 200 && $code < 300;
}
