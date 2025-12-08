<?php
/**
 * Created by PhpStorm.
 * User: victor.lange
 * Date: 28.09.2018
 * Time: 11:10
 */

require('../config.php');

$name = $_GET["name"];


$ldap_connection = ldap_connect(LDAP_DOMAIN);
if (FALSE === $ldap_connection) {
    die("<p>Failed to connect to the LDAP server: " . LDAP_DOMAIN . "</p>");
}

ldap_set_option($ldap_connection, LDAP_OPT_PROTOCOL_VERSION, 3) or die('Unable to set LDAP protocol version');
ldap_set_option($ldap_connection, LDAP_OPT_REFERRALS, 0); // We need this for doing an LDAP search.

if (TRUE !== ldap_bind($ldap_connection, LDAP_USERNAME, LDAP_PASSWORD)) {
    die('<p>Failed to bind to LDAP server.</p>');
}


$ldap_base_dn = 'DC=cbr,DC=de';
$search_filter = "(&(objectCategory=person)(objectClass=user)(sAMAccountName=" . $name . "))";
$result = ldap_search($ldap_connection, $ldap_base_dn, $search_filter);
if (FALSE !== $result) {
    $entries = ldap_get_entries($ldap_connection, $result);
    if ($entries['count'] == 1 && isset($entries[0]["thumbnailphoto"])) {
        header('Content-Type: image/jpeg');
        echo $entries[0]["thumbnailphoto"][0];
    } else {
        $file = file_get_contents(DASHBOARD_BASE_URL . '/websocket/nopicture.png');
        header('Content-type: image/png');
        echo $file;
    }
}
ldap_unbind($ldap_connection); // Clean up after ourselves.
?>