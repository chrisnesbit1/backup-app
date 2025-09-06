<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../app/helpers.php';

class HelpersTest extends TestCase
{
    public function testLoadConfigReturnsEmptyArrayWhenFileMissing(): void
    {
        $path = sys_get_temp_dir() . '/nonexistent_config_' . uniqid() . '.php';
        if (file_exists($path)) {
            unlink($path);
        }
        $result = load_config($path);
        $this->assertSame([], $result);
    }

    public function testSaveAndLoadConfigRoundtrip(): void
    {
        $path = sys_get_temp_dir() . '/config_' . uniqid() . '.php';
        $config = ['foo' => 'bar', 'num' => 42];
        save_config($path, $config);
        $this->assertFileExists($path);
        $loaded = load_config($path);
        $this->assertSame($config, $loaded);
        $perms = fileperms($path) & 0777;
        $this->assertSame(0600, $perms);
        unlink($path);
    }

    public function testEnforcePermissionsSetsCorrectPermissions(): void
    {
        $dir = sys_get_temp_dir() . '/perm_' . uniqid();
        $file = $dir . '/config.php';
        mkdir($dir, 0755);
        file_put_contents($file, "<?php return [];\n");
        chmod($dir, 0755);
        chmod($file, 0644);

        $warnings = enforce_permissions($file);
        $this->assertSame([], $warnings);
        $this->assertSame(0700, fileperms($dir) & 0777);
        $this->assertSame(0600, fileperms($file) & 0777);

        unlink($file);
        rmdir($dir);
    }
}
