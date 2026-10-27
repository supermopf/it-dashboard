<?php
// Testkonfiguration für den Integrationstest von websocket/server.php (keine echten Werte).
date_default_timezone_set('Europe/Berlin');
define('LDAP_DOMAIN', 'example.test');
define('LDAP_BASE_DN', 'DC=example,DC=test');
define('DASHBOARD_DOMAIN', '127.0.0.1');
define('DASHBOARD_BASE_URL', 'http://127.0.0.1:8090');
define('WEBSOCKET_URL', 'ws://127.0.0.1:9200/');
define('WEBSOCKET_PORT', (int)(getenv('TEST_SERVER_PORT') ?: 9200));
define('DEFAULT_RADIOSTATION', 'https://radio.example.test/stream.mp3');
define('DEFAULT_RADIOSTATION_ICON', '');
define('APIKEY_openweathermap', '');
define('DASHBOARD_SQL_INSTANCE', '');
$Default_Connection = array('Database' => '', 'CharacterSet' => 'UTF-8');
if (getenv('TEST_TRUSTED_NETWORKS')) {
    define('WEBSOCKET_TRUSTED_COMMAND_NETWORKS', explode(',', getenv('TEST_TRUSTED_NETWORKS')));
}
