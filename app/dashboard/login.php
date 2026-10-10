<?php
declare(strict_types=1);

use Delight\Auth\AuthException;
use Delight\Auth\TooManyRequestsException;
use Minilytics\Auth\Auth;
use Minilytics\Auth\Csrf;

require_once __DIR__ . '/../vendor/autoload.php';
Auth::startSession();
if (!Auth::hasDatabase()) {
    header('Location: /dashboard/onboarding.php');
    exit;
}
// Where to go after signing in, such as the OAuth consent screen. Only local paths are accepted.
$next = (string) ($_GET['next'] ?? '');
if (!preg_match('#^/(?![/\\\\])[^\x00-\x20\\\\]*$#', $next)) {
    $next = '/dashboard/';
}
if (Auth::user()) {
    header('Location: ' . $next);
    exit;
}
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !Csrf::isValid()) {
    $error = 'This page expired. Please try again.';
} elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $email = trim(strtolower($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    try {
        Auth::engine()->login($email, $password);
        header('Location: ' . $next);
        exit;
    } catch (TooManyRequestsException) {
        http_response_code(429);
        $error = 'Too many sign-in attempts. Please try again later.';
    } catch (AuthException) {
        $error = 'Incorrect email address or password.';
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Sign in | Minilytics</title><link rel="stylesheet" href="/dashboard/src/assets/css/auth.css"></head><body><main class="auth-card"><img src="/dashboard/src/assets/logo.svg" width="44" height="44" alt="Minilytics"><h1>Sign in</h1><p>Access your Minilytics dashboard.</p><?php if ($error): ?><div class="auth-error"><?= htmlspecialchars($error) ?></div><?php endif; ?><form method="post"><?= Csrf::field() ?><label>Email address<input required type="email" name="email" autocomplete="email"></label><label>Password<input required type="password" name="password" autocomplete="current-password"></label><button type="submit">Sign in</button></form></main></body></html>
