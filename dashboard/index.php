<?php

use Minilytics\Auth\Auth;
use Minilytics\Database\Database;
require_once __DIR__ . '/../vendor/autoload.php';
Auth::startSession();
if (!Auth::hasDatabase()) { header('Location: /dashboard/onboarding.php'); exit; }
$authUser = Auth::user();
$isGuest = false;
$publicSite = null;
if (!$authUser) {
    // Live demo: anonymous visitors may browse a public website read-only
    // (?demo=1 picks the first public site, ?site=<id> a specific one).
    $requestedSite = $_GET['site'] ?? $_GET['site_id'] ?? null;
    if ($requestedSite !== null || !empty($_GET['demo'])) {
        $publicSite = Database::getPublicSite($requestedSite !== null ? (string)$requestedSite : null);
    }
    if (!$publicSite) { header('Location: /dashboard/login.php'); exit; }
    $isGuest = true;
    if ($requestedSite === null) {
        // Pin the URL to the demo site so the front-end opens it directly.
        $query = $_GET; unset($query['demo']); $query['site'] = $publicSite['id'];
        header('Location: /dashboard/?' . http_build_query($query) . '#overview');
        exit;
    }
}
$isEmbed = !empty($_GET['embed']);
// Ensure trailing slash when accessed as /dashboard to prevent relative path resolution issues
$reqUri = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($reqUri, PHP_URL_PATH);
if ($path === '/dashboard') {
    $query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
    header('Location: /dashboard/' . $query, true, 301);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="/dashboard/">
    <title>Minilytics | Privacy-Friendly Web Analytics</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/lipis/flag-icons@7.2.3/css/flag-icons.min.css">
    <!-- Core Stylesheets -->
    <link rel="stylesheet" href="/dashboard/src/assets/css/dashboard.css">
    <link rel="stylesheet" href="/dashboard/src/assets/css/overview.css">
    <link rel="stylesheet" href="/dashboard/src/assets/css/acquisition.css">
    <link rel="stylesheet" href="/dashboard/src/assets/css/import.css">
    <link rel="stylesheet" href="/dashboard/src/assets/css/settings.css">
    <link rel="stylesheet" href="/dashboard/src/assets/css/funnels.css">
    <link rel="icon" type="image/svg+xml" href="/dashboard/src/assets/logo.svg">
    <link rel="alternate icon" type="image/svg+xml" href="/favicon.svg">
    <script>
        window.MINILYTICS_GUEST = <?php echo $isGuest ? 'true' : 'false'; ?>;
        window.MINILYTICS_EMBED = <?php echo $isEmbed ? 'true' : 'false'; ?>;
        window.MINILYTICS_PUBLIC_SITE = <?php echo $isGuest ? json_encode($publicSite['id']) : 'null'; ?>;
    </script>
    <script>
        (function() {
            try {
                if (localStorage.getItem('minilytics_sidebar_collapsed') === 'true') {
                    document.documentElement.classList.add('sidebar-preload-collapsed');
                }
            } catch (e) {}
        })();
    </script>
</head>
<body class="<?php echo trim(($isGuest ? 'guest-mode ' : '') . ($isEmbed ? 'embed-mode' : '')); ?>">
    <!-- Top global loading bar -->
    <div id="appLoadingBar" class="app-loading-bar" aria-hidden="true"></div>

    <div class="app-container">
        <!-- Left Sidebar Navigation -->
        <?php include __DIR__ . '/src/components/sidebar.php'; ?>
        <button type="button" class="mobile-nav-backdrop" id="mobileNavBackdrop" aria-label="Close navigation" tabindex="-1"></button>

        <!-- Main Content Area -->
        <div class="main-wrapper">
            <?php if ($isGuest): ?>
            <!-- Live demo banner (read-only guest access) -->
            <div class="demo-banner" role="note">
                <?php if ($isEmbed): ?>
                <nav class="demo-embed-tabs" aria-label="Demo sections">
                    <?php foreach (['overview' => 'Overview', 'acquisition' => 'Acquisition', 'sessions' => 'Sessions', 'events' => 'Events', 'funnels' => 'Funnels'] as $page => $label): ?>
                    <a href="#<?php echo $page; ?>" class="nav-item" data-page="<?php echo $page; ?>"><?php echo $label; ?></a>
                    <?php endforeach; ?>
                </nav>
                <a href="/dashboard/?site=<?php echo urlencode($publicSite['id']); ?>#overview" target="_blank" rel="noopener">Open full demo ↗</a>
                <?php else: ?>
                <span><strong>Live demo</strong> · read-only mock data.</span>
                <a href="/landing/install.html">Get Minilytics</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <!-- Top Header with Live Visitors & Controls -->
            <?php include __DIR__ . '/src/components/header.php'; ?>

            <!-- Page Views Container -->
            <main class="content-container">
                <!-- Page 0: Websites Management (Home) -->
                <?php include __DIR__ . '/src/pages/websites.php'; ?>

                <!-- Page 1: Overview & Views -->
                <?php include __DIR__ . '/src/pages/overview.php'; ?>
                <?php include __DIR__ . '/src/pages/acquisition.php'; ?>

                <!-- Page 2: Filterable Events Stream -->
                <?php include __DIR__ . '/src/pages/events.php'; ?>

                <!-- Page 3: User Sessions -->
                <?php include __DIR__ . '/src/pages/sessions.php'; ?>
                <?php include __DIR__ . '/src/pages/funnels.php'; ?>
                <?php include __DIR__ . '/src/pages/settings.php'; ?>
            </main>
            <p style="margin: 0 32px 20px; font-size: 11px; color: var(--text-muted);"><a href="https://db-ip.com" target="_blank" rel="noopener noreferrer" style="color: inherit;">IP Geolocation by DB-IP</a></p>
        </div>
    </div>

    <!-- Inspector Modals & Drawers -->
    <?php include __DIR__ . '/src/components/modal.php'; ?>

    <!-- Lucide Icons & Icon Helper -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="/dashboard/src/assets/js/icons.js"></script>

    <!-- Modular Application Scripts -->
    <script src="/dashboard/src/assets/js/api.js"></script>
    <script src="/dashboard/src/assets/js/filters.js"></script>
    <script src="/dashboard/src/assets/js/calendar-picker.js"></script>
    <script src="/dashboard/src/assets/js/chart.js"></script>
    <script src="/dashboard/src/assets/js/websites.js"></script>
    <script src="/dashboard/src/assets/js/import.js"></script>
    <script src="/dashboard/src/assets/js/overview.js"></script>
    <script src="/dashboard/src/assets/js/acquisition.js"></script>
    <script src="/dashboard/src/assets/js/events.js"></script>
    <script src="/dashboard/src/assets/js/sessions.js"></script>
    <script src="/dashboard/src/assets/js/funnels.js"></script>
    <script src="/dashboard/src/assets/js/settings.js"></script>
    <script src="/dashboard/src/assets/js/app.js"></script>
</body>
</html>
