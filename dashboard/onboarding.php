<?php

declare(strict_types=1);

use Minilytics\Auth\Auth;
use Minilytics\Database\Database;

require_once __DIR__ . '/../vendor/autoload.php';
Auth::startSession();
$step = $_GET['step'] ?? ($_SERVER['REQUEST_METHOD'] === 'POST' ? 'account' : 'welcome');
if (Auth::hasDatabase() && $step !== 'database') {
    header('Location: ' . (Auth::user() ? '/dashboard/' : '/dashboard/login.php'));
    exit;
}
$error = '';
if ($step === 'database') {
    $user = Auth::user();
    if (!$user || ($user['role'] ?? '') !== 'admin') {
        header('Location: /dashboard/login.php');
        exit;
    }
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $step === 'account') {
    $email = trim(strtolower($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if (!Auth::validEmail($email)) {
        $error = 'Enter a valid email address.';
    } elseif ($passwordError = Auth::passwordError($password)) {
        $error = $passwordError;
    } else {
        $db = Auth::db();
        $stmt = $db->prepare('INSERT INTO users (email, password_hash, role) VALUES (:email, :password, "admin")');
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $stmt->bindValue(':password', password_hash($password, PASSWORD_DEFAULT), SQLITE3_TEXT);
        $stmt->execute();
        Auth::login(['id' => $db->lastInsertRowID()]);
        header('Location: /dashboard/onboarding.php?step=database');
        exit;
    }
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $step === 'database') {
    try {
        Database::saveDatabaseConfig([
            'driver' => $_POST['driver'] ?? 'sqlite',
            'host' => $_POST['host'] ?? '',
            'port' => $_POST['port'] ?? 3306,
            'database' => $_POST['database'] ?? '',
            'username' => $_POST['username'] ?? '',
            'password' => $_POST['password'] ?? '',
            'create_database' => !empty($_POST['create_database']),
        ]);
        header('Location: /dashboard/#websites');
        exit;
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}
$databaseDriver = in_array($_POST['driver'] ?? '', ['mysql', 'mariadb'], true) ? $_POST['driver'] : 'sqlite';
$databaseStage = $error !== '' && $databaseDriver !== 'sqlite' ? 'config' : 'choice';
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
    <link rel="alternate icon" type="image/svg+xml" href="/favicon.svg">
</head>

<body>
    <main class="auth-card<?= $step === 'database' ? ' auth-card--database' : '' ?>">
        <img src="/dashboard/src/assets/logo.svg" width="44" height="44" alt="Minilytics">
        <?php if ($step === 'welcome'): ?><span class="eyebrow">Step 1 of 3</span>
            <h1>Welcome to Minilytics !</h1>
            <p>Private, lightweight web analytics for your websites. Let’s secure your workspace with an administrator account.</p>
            <form method="get">
                <input type="hidden" name="step" value="account"><button class="btn-primary" type="submit">Get started</button>
            </form>
        <?php elseif ($step === 'account'): ?>
            <span class="eyebrow">Step 2 of 3</span>
            <h1>Create your admin account</h1>
            <p>This account protects your analytics workspace and lets you invite other people.</p>
            <p class="account-reassurance">Your email is used only to identify your account and let you sign in. We will never use it to send spam or marketing emails.</p>
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
        <?php else: ?>
            <span class="eyebrow">Step 3 of 3</span>
            <?php if ($error): ?><div class="auth-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <form method="post" class="database-onboarding-form" id="databaseOnboardingForm" data-initial-stage="<?= $databaseStage ?>">
                <input type="hidden" name="driver" value="<?= htmlspecialchars($databaseDriver) ?>" id="databaseDriver">
                <section id="storageChoiceStage" <?= $databaseStage === 'config' ? ' hidden' : '' ?>>
                    <h1>Choose your analytics storage</h1>
                    <p>Minilytics needs a database to store your visitors, pageviews, and events. Start instantly with a local database or connect an external server.</p>
                    <div class="storage-options" role="radiogroup" aria-label="Analytics storage">
                        <label class="storage-option<?= $databaseDriver === 'sqlite' ? ' is-selected' : '' ?>" data-storage-option="sqlite">
                            <input type="radio" name="storage" value="sqlite" <?= $databaseDriver === 'sqlite' ? ' checked' : '' ?>>
                            <span class="storage-logo storage-logo--sqlite"><img src="https://cdn.simpleicons.org/sqlite/003B57" width="38" height="38" alt="SQLite"></span>
                            <span><strong>SQLite</strong><small>Recommended for small and medium-sized projects. No setup required.</small></span>
                        </label>
                        <label class="storage-option<?= $databaseDriver !== 'sqlite' ? ' is-selected' : '' ?>" data-storage-option="remote">
                            <input type="radio" name="storage" value="remote" <?= $databaseDriver !== 'sqlite' ? ' checked' : '' ?>>
                            <span class="storage-logo storage-logo--servers" aria-label="MySQL and MariaDB">
                                <img src="https://cdn.simpleicons.org/mysql/4479A1" width="38" height="38" alt="MySQL">
                                <img src="https://cdn.simpleicons.org/mariadb/003545" width="38" height="38" alt="MariaDB">
                            </span>
                            <span><strong>MySQL or MariaDB</strong><small>Best for high traffic and many simultaneous users.</small></span>
                        </label>
                    </div>
                    <p class="storage-guidance" id="storageGuidance"><strong>Not sure?</strong> Choose SQLite for now. Choose MySQL or MariaDB if you expect many analytics events at the same time, need several application servers, or already use a managed database.</p>
                    <button type="button" id="storageChoiceContinue">Continue with SQLite</button>
                </section>
                <section id="storageConfigStage" <?= $databaseStage === 'choice' ? ' hidden' : '' ?>>
                    <h1>Configure your database server</h1>
                    <p>Enter the connection information provided by your hosting provider. Minilytics will test it before continuing.</p>
                    <div class="remote-storage-fields" id="remoteStorageFields">
                        <label>Database engine
                            <select id="remoteDriver">
                                <option value="mysql">MySQL</option>
                                <option value="mariadb">MariaDB</option>
                            </select>
                        </label>
                        <label>Host<input required name="host" autocomplete="off" placeholder="localhost" value="<?= htmlspecialchars($_POST['host'] ?? '') ?>"></label>
                        <label>Port<input name="port" type="number" min="1" max="65535" value="<?= htmlspecialchars($_POST['port'] ?? '3306') ?>"></label>
                        <label>Database name<input required name="database" autocomplete="off" placeholder="minilytics" value="<?= htmlspecialchars($_POST['database'] ?? '') ?>"></label>
                        <label>Username<input required name="username" autocomplete="username" placeholder="db_user" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"></label>
                        <label>Password<input name="password" type="password" autocomplete="new-password"></label>
                        <label class="storage-checkbox"><input name="create_database" type="checkbox" <?= !isset($_POST['create_database']) || !empty($_POST['create_database']) ? ' checked' : '' ?>><span><strong>Create the database if it is missing</strong><small>Requires the database user to have the CREATE permission.</small></span></label>
                        <p class="storage-guidance"><strong>Before continuing:</strong> Minilytics tests this connection before saving it. The database user needs CREATE, ALTER, INDEX, SELECT, INSERT, UPDATE and DELETE permissions.</p>
                    </div>
                    <button type="submit">Test connection and continue</button>
                    <button type="button" class="back-button" id="backToStorageChoice">Choose a different storage option</button>
                    <p class="storage-footer">Changing this connector later does not copy existing analytics automatically.</p>
                </section>
            </form>
        <?php endif; ?>
    </main>
    <script src="/dashboard/src/assets/js/password-strength.js"></script>
    <?php if ($step === 'database'): ?><script>
            (() => {
                const form = document.getElementById('databaseOnboardingForm');
                const choiceStage = document.getElementById('storageChoiceStage');
                const configStage = document.getElementById('storageConfigStage');
                const button = document.getElementById('storageChoiceContinue');
                const storage = form.querySelectorAll('input[name="storage"]');
                const remoteDriver = document.getElementById('remoteDriver');
                const databaseDriver = document.getElementById('databaseDriver');
                const setChoice = () => {
                    const remote = [...storage].some((radio) => radio.checked && radio.value === 'remote');
                    databaseDriver.value = remote ? remoteDriver.value : 'sqlite';
                    button.textContent = remote ? 'Continue to server setup' : 'Continue with SQLite';
                    document.querySelectorAll('[data-storage-option]').forEach((option) => option.classList.toggle('is-selected', option.dataset.storageOption === (remote ? 'remote' : 'sqlite')));
                };
                const showStage = (stage) => {
                    choiceStage.hidden = stage !== 'choice';
                    configStage.hidden = stage !== 'config';
                    configStage.querySelectorAll('input, select').forEach((field) => field.disabled = stage !== 'config');
                };
                storage.forEach((radio) => radio.addEventListener('change', setChoice));
                remoteDriver.addEventListener('change', () => databaseDriver.value = remoteDriver.value);
                button.addEventListener('click', () => {
                    if (databaseDriver.value === 'sqlite') form.requestSubmit();
                    else showStage('config');
                });
                document.getElementById('backToStorageChoice').addEventListener('click', () => showStage('choice'));
                remoteDriver.value = databaseDriver.value === 'mariadb' ? 'mariadb' : 'mysql';
                setChoice();
                showStage(form.dataset.initialStage);
            })();
        </script><?php endif; ?>
</body>

</html>