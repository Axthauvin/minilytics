<?php

declare(strict_types=1);
require_once __DIR__ . '/src/api/auth.php';
Auth::startSession();
if (Auth::hasDatabase()) {
    header('Location: ' . (Auth::user() ? '/dashboard/' : '/dashboard/login.php'));
    exit;
}
$error = '';
$step = $_GET['step'] ?? ($_SERVER['REQUEST_METHOD'] === 'POST' ? 'account' : 'welcome');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $email = trim(strtolower($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if (!Auth::validEmail($email)) $error = 'Enter a valid email address.';
    elseif ($passwordError = Auth::passwordError($password)) $error = $passwordError;
    else {
        $db = Auth::db();
        $stmt = $db->prepare('INSERT INTO users (email, password_hash, role) VALUES (:email, :password, "admin")');
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $stmt->bindValue(':password', password_hash($password, PASSWORD_DEFAULT), SQLITE3_TEXT);
        $stmt->execute();
        Auth::login(['id' => $db->lastInsertRowID()]);
        header('Location: /dashboard/#websites');
        exit;
    }
}
?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Set up Minilytics</title>
    <link rel="stylesheet" href="/dashboard/src/assets/css/auth.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="/dashboard/src/assets/js/icons.js"></script>
</head>

<body>
    <main class="auth-card">
        <img src="/dashboard/src/assets/logo.svg" width="44" height="44" alt="Minilytics">
        <?php if ($step !== 'account'): ?><span class="eyebrow">Step 1 of 2</span>
            <h1>Welcome to Minilytics</h1>
            <p>Private, lightweight web analytics for your websites. Let’s secure your workspace with an administrator account.</p>
            <form method="get">
                <input type="hidden" name="step" value="account"><button type="submit">Get started</button>
            </form>
        <?php else: ?>
            <span class="eyebrow">Step 2 of 2</span>
            <h1>Create your admin account</h1>
            <p>This account protects your analytics workspace and lets you invite other people.</p>
            <?php
            if ($error): ?><div class="auth-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="post">
                <label>Email address
                    <input required type="email" name="email" autocomplete="email">
                </label>

                <label>Password
                    <span class="password-input">
                        <input required minlength="10" type="password" name="password" autocomplete="new-password" data-password-strength>
                        <button type="button" class="password-toggle" aria-label="Show password" title="Show password"></button>
                    </span>
                </label>
                <div class="password-strength" aria-live="polite">
                    <div class="password-strength-track"><span></span></div>
                    <p>Enter a password</p>
                </div><button type="submit">Create workspace</button>
            </form>
        <?php endif; ?>
    </main>
    <script src="/dashboard/src/assets/js/password-strength.js"></script>
</body>

</html>