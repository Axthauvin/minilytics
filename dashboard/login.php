<?php
declare(strict_types=1);

use Minilytics\Auth\Auth;
require_once __DIR__ . '/../vendor/autoload.php';
Auth::startSession();
if (!Auth::hasDatabase()) { header('Location: /dashboard/onboarding.php'); exit; }
if (Auth::user()) { header('Location: /dashboard/'); exit; }
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $email = trim(strtolower($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $db = Auth::db(); $stmt = $db->prepare('SELECT * FROM users WHERE email = :email'); $stmt->bindValue(':email', $email, SQLITE3_TEXT);
    $user = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if ($user && password_verify($password, $user['password_hash'])) { Auth::login($user); header('Location: /dashboard/'); exit; }
    $error = 'Incorrect email address or password.';
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Sign in | Minilytics</title><link rel="stylesheet" href="/dashboard/src/assets/css/auth.css"></head><body><main class="auth-card"><img src="/dashboard/src/assets/logo.svg" width="44" height="44" alt="Minilytics"><h1>Sign in</h1><p>Access your Minilytics dashboard.</p><?php if ($error): ?><div class="auth-error"><?= htmlspecialchars($error) ?></div><?php endif; ?><form method="post"><label>Email address<input required type="email" name="email" autocomplete="email"></label><label>Password<input required type="password" name="password" autocomplete="current-password"></label><button type="submit">Sign in</button></form></main></body></html>
