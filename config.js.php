<?php
/**
 * JavaScript Configuration File
 * This file provides configuration constants to JavaScript files (Legacy-Oberflächen: control, Admin, monitor/legacy.php).
 * Werte werden per json_encode ausgegeben (kein Ausbruch aus dem String möglich).
 */
header('Content-Type: application/javascript; charset=utf-8');
header('X-Content-Type-Options: nosniff');
require(getenv('DASHBOARD_CONFIG') ?: __DIR__ . '/config.php');
$flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
?>

// Dashboard Configuration
const DASHBOARD_CONFIG = {
    LDAP_DOMAIN: <?php echo json_encode(LDAP_DOMAIN, $flags); ?>,
    LDAP_BASE_DN: <?php echo json_encode(LDAP_BASE_DN, $flags); ?>,
    DASHBOARD_DOMAIN: <?php echo json_encode(DASHBOARD_DOMAIN, $flags); ?>,
    DASHBOARD_BASE_URL: <?php echo json_encode(DASHBOARD_BASE_URL, $flags); ?>,
    WEBSOCKET_URL: <?php echo json_encode(WEBSOCKET_URL, $flags); ?>
};
