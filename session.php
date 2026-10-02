<?php


$salt = 'axel';

function generateSessionId(string $websiteId): string
{
    global $salt;

    $ip = $_SERVER['HTTP_CF_CONNECTING_IP']
        ?? $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '127.0.0.1';

    $ip = trim(explode(',', $ip)[0]);

    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $day = date('Y-m-d');

    return substr(hash('sha256', $websiteId . $ip . $ua . $day . $salt), 0, 32);
}

function generateVisitorId(string $websiteId): string
{
    return generateSessionId($websiteId);
}