<?php
/**
 * Monitor (Signage) – App-Shell.
 *
 * Lädt das mit Vite gebaute Frontend aus monitor/dist/. Fehlt der Build oder wird ?legacy=1 übergeben,
 * wird die bisherige Oberfläche (legacy.php) ausgeliefert – das ist zugleich der Rollback-Pfad.
 * Die URL für Yodeck bleibt unverändert: /monitor/?Name=<Dashboard>.
 */
require_once getenv('DASHBOARD_CONFIG') ?: __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/security.php';

$manifestFile = __DIR__ . '/dist/.vite/manifest.json';
$forceLegacy = isset($_GET['legacy']) || (defined('MONITOR_FORCE_LEGACY') && MONITOR_FORCE_LEGACY);
$entry = null;
if (!$forceLegacy && is_file($manifestFile)) {
    $manifest = json_decode((string)file_get_contents($manifestFile), true);
    $entry = is_array($manifest) ? ($manifest['src/main.ts'] ?? null) : null;
}
if (!$entry || empty($entry['file']) || !is_file(__DIR__ . '/dist/' . $entry['file'])) {
    require __DIR__ . '/legacy.php';
    return;
}

$wsUrl = defined('WEBSOCKET_URL') ? WEBSOCKET_URL : '';
$wsOrigin = '';
if ($wsUrl !== '' && ($parts = parse_url($wsUrl)) && isset($parts['scheme'], $parts['host'])) {
    $wsOrigin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
}

// Nur nicht-geheime Werte an den Browser geben.
$config = array(
    'wsUrl' => $wsUrl,
    'assetPath' => './',
    'cachePath' => './cache/',
);
if (defined('MONITOR_OPTIONS') && is_array(MONITOR_OPTIONS)) {
    $allowed = array('cachePath', 'alertPages', 'cycleSeconds', 'fetchTimeoutMs', 'prefetchLeadSeconds', 'maxActiveVideos',
        'chartAnimation', 'snowFlakes', 'spooky', 'toastScripts', 'recovery', 'wsWatchdogMs', 'uiScale', 'pages');
    foreach (MONITOR_OPTIONS as $k => $v) {
        if (in_array($k, $allowed, true)) {
            if ($k === 'cachePath' && !preg_match('#^[A-Za-z0-9_./-]{1,200}/$#', (string)$v)) {
                continue;
            }
            $config[$k] = $v;
        }
    }
}

$csp = implode('; ', array(
    "default-src 'self'",
    // 'unsafe-eval': Inline-Skripte der Legacy-Ansichten werden per new Function() in eigenem Scope ausgeführt.
    "script-src 'self' 'unsafe-eval' https://www.youtube.com https://s.ytimg.com",
    "style-src 'self' 'unsafe-inline'",
    "img-src * data: blob:",
    "media-src * blob:",
    "connect-src 'self'" . ($wsOrigin !== '' ? ' ' . $wsOrigin : ' ws: wss:'),
    "frame-src https://www.youtube.com https://www.youtube-nocookie.com",
    "font-src 'self' data:",
    "object-src 'none'",
    "base-uri 'self'",
));
if (defined('MONITOR_CSP')) {
    $csp = (string)MONITOR_CSP;
}
send_security_headers($csp);
header('Cache-Control: no-cache');
header('Content-Type: text/html; charset=utf-8');

$css = isset($entry['css']) && is_array($entry['css']) ? $entry['css'] : array();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <title>IT-Dashboard</title>
    <link rel="icon" href="./img/favicon.ico">
<?php foreach ($css as $file): ?>
    <link rel="stylesheet" href="./dist/<?= h($file) ?>">
<?php endforeach; ?>
    <link rel="modulepreload" href="./dist/<?= h($entry['file']) ?>">
    <script id="dashboard-config" type="application/json"><?= json_for_html($config) ?></script>
</head>
<body>
<div id="app"></div>
<noscript>JavaScript wird benötigt.</noscript>
<script type="module" src="./dist/<?= h($entry['file']) ?>"></script>
</body>
</html>
