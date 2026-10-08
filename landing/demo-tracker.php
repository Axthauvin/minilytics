<?php

declare(strict_types=1);

use Minilytics\Database\Database;

/**
 * Loads the Minilytics tracker for the public demo website, so the landing
 * page is measured by Minilytics itself. Only public sites are exposed here;
 * their write key is the same value a regular tracking snippet publishes.
 */
header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

require_once dirname(__DIR__) . '/vendor/autoload.php';

try {
    $site = Database::getPublicSite();
} catch (Throwable $e) {
    $site = null;
}
if (!$site) {
    echo "/* Minilytics live demo is not configured. */\n";
    exit;
}

$attributes = json_encode([
    'data-site-id' => $site['id'],
    'data-site-key' => $site['write_key'],
    'data-privacy-mode' => 'strict',
], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
?>
(function () {
  var script = document.createElement("script");
  var attributes = <?php echo $attributes; ?>;
  script.defer = true;
  script.src = "/minilytics.js";
  Object.keys(attributes).forEach(function (name) {
    script.setAttribute(name, attributes[name]);
  });
  document.head.appendChild(script);
})();
