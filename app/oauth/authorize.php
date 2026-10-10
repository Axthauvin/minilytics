<?php

declare(strict_types=1);

use Minilytics\Auth\Auth;
use Minilytics\OAuth\OAuthException;
use Minilytics\OAuth\OAuthServer;

/**
 * OAuth consent screen: the signed-in user allows an AI assistant to read the
 * analytics, and the assistant receives an authorization code.
 */
require_once __DIR__ . '/../vendor/autoload.php';
Auth::startSession();
if (!Auth::hasDatabase()) {
    header('Location: /dashboard/onboarding.php');
    exit;
}

const OAUTH_PARAMS = ['response_type', 'client_id', 'redirect_uri', 'code_challenge', 'code_challenge_method', 'state', 'scope', 'resource'];

$redirect = static function (string $location): never {
    header('Location: ' . $location, true, 302);
    exit;
};
$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$params = array_intersect_key($isPost ? $_POST : $_GET, array_flip(OAUTH_PARAMS));
$error = null;
$request = null;

try {
    $request = OAuthServer::authorizationRequest($params);
} catch (OAuthException $e) {
    if ($e->redirectUri !== null) {
        $redirect(OAuthServer::redirectWith($e->redirectUri, ['error' => $e->error, 'error_description' => $e->getMessage(), 'state' => $e->state]));
    }
    $error = $e->getMessage();
}

$user = Auth::user();
if ($request !== null && $user === null) {
    $redirect('/dashboard/login.php?next=' . rawurlencode('/oauth/authorize.php?' . http_build_query($params)));
}

if ($request !== null && $isPost) {
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals((string) ($_SESSION['oauth_csrf'] ?? ''), $_POST['csrf'])) {
        $error = 'This page expired. Go back to your assistant and connect again.';
    } else {
        unset($_SESSION['oauth_csrf']);
        if (($_POST['decision'] ?? '') === 'allow') {
            $code = OAuthServer::createAuthorizationCode($request, (int) $user['id']);
            $redirect(OAuthServer::redirectWith($request['redirect_uri'], ['code' => $code, 'state' => $request['state']]));
        }
        $redirect(OAuthServer::redirectWith($request['redirect_uri'], ['error' => 'access_denied', 'error_description' => 'Access was denied.', 'state' => $request['state']]));
    }
}
$_SESSION['oauth_csrf'] ??= bin2hex(random_bytes(16));

$h = static fn(?string $value): string => htmlspecialchars((string) $value, ENT_QUOTES);
$clientName = $request['client']['client_name'] ?? '';
$redirectHost = $request === null ? '' : (string) (parse_url($request['redirect_uri'], PHP_URL_HOST) ?: parse_url($request['redirect_uri'], PHP_URL_SCHEME));
$isLocalApp = $request !== null && OAuthServer::redirectsToLocalApp($request['redirect_uri']);
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Connect an assistant | Minilytics</title><link rel="stylesheet" href="/dashboard/src/assets/css/auth.css"></head><body><main class="auth-card">
<img src="/dashboard/src/assets/logo.svg" width="44" height="44" alt="Minilytics">
<?php if ($error !== null): ?>
<h1>Connection failed</h1>
<div class="auth-error"><?= $h($error) ?></div>
<?php else: ?>
<span class="eyebrow">Connect an assistant</span>
<h1>Allow <?= $h($clientName) ?> to read your analytics?</h1>
<p>You are signed in as <strong><?= $h($user['email']) ?></strong>.</p>
<ul class="oauth-scopes">
    <li><strong>Read-only.</strong> It can read the statistics of every website in this workspace. It cannot change or delete anything.</li>
    <li>You can disconnect it at any time in <strong>Settings → AI assistants</strong>.</li>
</ul>
<?php if ($isLocalApp): ?>
<div class="auth-error">Access is handed to an application running on this computer. Only continue if you just started this connection from your assistant.</div>
<?php else: ?>
<p class="oauth-redirect">After you allow access, you will return to <strong><?= $h($redirectHost) ?></strong>.</p>
<?php endif; ?>
<form method="post" action="/oauth/authorize.php" class="oauth-actions">
<?php foreach ($params as $name => $value): ?>
    <input type="hidden" name="<?= $h((string) $name) ?>" value="<?= $h(is_string($value) ? $value : '') ?>">
<?php endforeach; ?>
    <input type="hidden" name="csrf" value="<?= $h($_SESSION['oauth_csrf']) ?>">
    <button type="submit" name="decision" value="deny" class="oauth-deny">Cancel</button>
    <button type="submit" name="decision" value="allow">Allow access</button>
</form>
<?php endif; ?>
</main></body></html>
