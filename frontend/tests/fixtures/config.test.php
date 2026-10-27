<?php
// Testkonfiguration für Playwright/Baseline (enthält keine echten Werte).
// Verwendung: DASHBOARD_CONFIG=frontend/tests/fixtures/config.test.php php -S 127.0.0.1:8090 -t .
date_default_timezone_set('Europe/Berlin');
define('LDAP_DOMAIN', 'example.test');
define('LDAP_BASE_DN', 'DC=example,DC=test');
define('DASHBOARD_DOMAIN', '127.0.0.1');
define('DASHBOARD_BASE_URL', 'http://127.0.0.1:8090');
define('WEBSOCKET_URL', getenv('TEST_WS_URL') ?: 'ws://127.0.0.1:9100/');
define('SUPPORT_EMAIL', 'support@example.test');
define('MONITOR_OPTIONS', array(
    'cachePath' => getenv('TEST_CACHE_PATH') ?: '../frontend/tests/fixtures/cache/',
    'fetchTimeoutMs' => (int)(getenv('TEST_FETCH_TIMEOUT') ?: 4000),
    'recovery' => array('enabled' => false),
));
