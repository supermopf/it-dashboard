<?php
/**
 * JavaScript Configuration File
 * This file provides configuration constants to JavaScript files
 */
header('Content-Type: application/javascript');
require('config.php');
?>

// Dashboard Configuration
const DASHBOARD_CONFIG = {
    LDAP_DOMAIN: '<?php echo LDAP_DOMAIN; ?>',
    LDAP_BASE_DN: '<?php echo LDAP_BASE_DN; ?>',
    DASHBOARD_DOMAIN: '<?php echo DASHBOARD_DOMAIN; ?>',
    DASHBOARD_BASE_URL: '<?php echo DASHBOARD_BASE_URL; ?>',
    WEBSOCKET_URL: '<?php echo WEBSOCKET_URL; ?>'
};
