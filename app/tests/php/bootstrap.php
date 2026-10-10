<?php

declare(strict_types=1);

/*
 * Runs the tests against a throwaway data directory, so they never touch a
 * real install. It holds a single website, "test_site".
 */

require __DIR__ . '/../../vendor/autoload.php';

$dataDir = sys_get_temp_dir() . '/minilytics-phpunit-' . bin2hex(random_bytes(4));
mkdir($dataDir, 0770, true);
file_put_contents($dataDir . '/sites.json', json_encode([[
    'id' => 'test_site',
    'name' => 'Test site',
    'domain' => 'example.com',
    'allowed_domains' => ['example.com'],
    'internal_ips' => [],
    'retention_days' => 395,
]]));
putenv('MINILYTICS_DATA_DIR=' . $dataDir);

register_shutdown_function(static function () use ($dataDir): void {
    foreach (glob($dataDir . '/*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($dataDir);
});
