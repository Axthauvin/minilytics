<?php

declare(strict_types=1);

/**
 * Site root: send visitors to the private dashboard unless a landing page replaces it.
 * The target is built from this script's location, not from the requested path, so a
 * server that falls back to this file for unknown paths does not redirect in a loop.
 */
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
header('Location: ' . $base . '/dashboard/', true, 302);
