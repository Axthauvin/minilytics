<?php
declare(strict_types=1);

use Delight\Auth\UserAlreadyExistsException;
use Minilytics\Auth\Auth;
use Minilytics\Auth\Csrf;

require_once __DIR__ . '/../vendor/autoload.php';
Auth::startSession();
$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$error = '';
$invite = null;
if (Auth::hasDatabase() && preg_match('/^[a-f0-9]{64}$/', $token)) {
    $db = Auth::db();
    $s = $db->prepare('SELECT * FROM invitations WHERE token_hash = :hash AND accepted_at IS NULL AND expires_at > CURRENT_TIMESTAMP');
    $s->bindValue(':hash', hash('sha256', $token), SQLITE3_TEXT);
    $invite = $s->execute()->fetchArray(SQLITE3_ASSOC) ?: null;
}
if (!$invite) {
    $error = 'This invitation link is invalid or has expired.';
}
if (!$error && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $password = (string) ($_POST['password'] ?? '');
    if (!Csrf::isValid()) {
        $error = 'This page expired. Please try again.';
    } elseif ($passwordError = Auth::passwordError($password)) {
        $error = $passwordError;
    } else {
        try {
            $id = Auth::createUser($invite['email'], $password, 'member');
            $s = $db->prepare('UPDATE invitations SET accepted_at = CURRENT_TIMESTAMP WHERE id = :id');
            $s->bindValue(':id', $invite['id'], SQLITE3_INTEGER);
            $s->execute();
            Auth::loginById($id);
            header('Location: /dashboard/#websites');
            exit;
        } catch (UserAlreadyExistsException) {
            $error = 'An account already exists for this email address.';
        }
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Join Minilytics</title><link rel="stylesheet" href="/dashboard/src/assets/css/auth.css"></head><body><main class="auth-card"><img src="/dashboard/src/assets/logo.svg" width="44" height="44" alt="Minilytics"><h1>Create your access</h1><?php if ($error): ?><div class="auth-error"><?= htmlspecialchars($error) ?></div><?php else: ?><p>You are joining Minilytics as <strong><?= htmlspecialchars($invite['email']) ?></strong>.</p><form method="post"><?= Csrf::field() ?><input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>"><label>Choose a password<span class="password-input"><input required minlength="10" type="password" name="password" autocomplete="new-password" data-password-strength><button type="button" class="password-toggle" aria-label="Show password">Show</button></span></label><div class="password-strength" aria-live="polite"><div class="password-strength-track"><span></span></div><p>Enter a password</p></div><button type="submit">Create my account</button></form><?php endif; ?></main><script src="/dashboard/src/assets/js/password-strength.js"></script></body></html>
