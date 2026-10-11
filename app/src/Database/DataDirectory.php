<?php

declare(strict_types=1);

namespace Minilytics\Database;

use RuntimeException;

/** The folder where Minilytics keeps its data, outside the web root. */
final class DataDirectory
{
    /**
     * Uses MINILYTICS_DATA_DIR when set, otherwise a "minilytics-data" folder
     * next to the web root.
     */
    public static function path(): string
    {
        static $resolved = null;
        if ($resolved !== null) {
            return $resolved;
        }

        $configured = getenv('MINILYTICS_DATA_DIR');
        $dir = is_string($configured) && trim($configured) !== ''
            ? rtrim(trim($configured), '/\\')
            : dirname(__DIR__, 3) . '/minilytics-data';

        if ((is_dir($dir) || @mkdir($dir, 0770, true) || is_dir($dir)) && is_writable($dir)) {
            return $resolved = $dir;
        }
        throw new RuntimeException('No writable data directory. Set MINILYTICS_DATA_DIR to a writable path outside the web root.');
    }
}
