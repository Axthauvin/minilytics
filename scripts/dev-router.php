<?php

declare(strict_types=1);

/**
 * Development router for PHP's built-in server. It overlays landing/ on top of
 * app/, exactly like deploying the landing archive next to the application:
 * a file that exists in landing/ is served from there, anything else falls
 * through to the app document root.
 *
 *   php -S localhost:8080 -t app scripts/dev-router.php
 */
$landing = realpath(dirname(__DIR__) . '/landing');
$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$file = $landing === false ? false : realpath($landing . ($path === '/' ? '/index.html' : $path));

if ($file === false || !is_file($file) || !str_starts_with($file, $landing . DIRECTORY_SEPARATOR)) {
    return false;
}

header('Content-Type: ' . match (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
    'html' => 'text/html; charset=utf-8',
    'css' => 'text/css; charset=utf-8',
    'js' => 'application/javascript; charset=utf-8',
    'svg' => 'image/svg+xml',
    'png' => 'image/png',
    'jpg', 'jpeg' => 'image/jpeg',
    'webp' => 'image/webp',
    'ico' => 'image/x-icon',
    'woff2' => 'font/woff2',
    default => 'application/octet-stream',
});
readfile($file);
