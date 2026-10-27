<?php
/**
 * Created by PhpStorm.
 * User: victor.lange
 * Date: 30.03.2017
 * Time: 09:51
 */

date_default_timezone_set('Europe/Berlin');

define("PRIMARY_STYLE", 'color: rgb(204,37,41);');
define("Pri_Color", 'rgba(211, 94, 96,0.6)');
define("Pri_Color_Border", 'rgba(204, 37, 41,1)');

define("SECONDARY_STYLE", 'color: rgb(62, 150, 81);');
define("Sec_Color", 'rgba(132, 186, 91,0.4)');
define("Sec_Color_Border", 'rgba(62, 150, 81,1)');

// Von monitor/source/page4.php (VMware) benötigt
define("TERTIARY_STYLE", 'color: rgb(32, 120, 200);');
define("Ter_Color", 'rgba(32, 120, 200, 0.3)');
define("Ter_Color_Border", 'rgba(32, 120, 200, 1)');

// Von monitor/source/page10.php (Julian-O-Meter) benötigt
define("JULIAN_KONTINGENT", '10');

// Von monitor/source/page8.php (Ubuntu-Updates via Checkmk) benötigt
define("CHECKMK_BASE_URL", '');
define("CHECKMK_USER", '');
define("CHECKMK_SECRET", '');
// define("CA_BUNDLE", 'C:/inetpub/dashboard/ca-bundle.pem'); // optional: internes Root-CA für curl


//For Active Direcory
define("LDAP_USERNAME", '');
define("LDAP_PASSWORD", '');
define("LDAP_BASE_DN", '');

define("DASHBOARD_SQL_INSTANCE", '');
define("DASHBOARD_SQL_DATABASE", '');

define("HELPLINE_SQL_INSTANCE", '');
define("HELPLINE_SQL_DATABASE", '');

define("DERDACK_SQL_INSTANCE", '');
define("DERDACK_SQL_DATABASE", '');

define("SCCM_SQL_INSTANCE", '');
define("SCCM_SQL_DATABASE", '');

define("APIKEY_openweathermap", '');

define("DEFAULT_RADIOSTATION", '');
define("DEFAULT_RADIOSTATION_ICON", '');

//Microsoft 365 Service Health API (Azure AD App Registration)
define("M365_TENANT_ID", ''); // Your Azure AD Tenant ID
define("M365_CLIENT_ID", ''); // Azure AD App Client ID
define("M365_CLIENT_SECRET", ''); // Azure AD App Client Secret
define("M365_SERVICES", 'Exchange,SharePoint,MicrosoftTeams,skypeforbusiness,OneDriveForBusiness,AzureActiveDirectory,DynamicsCRM,MicrosoftIntune,PowerBIcom,Planner,Yammer,Forms'); // Comma-separated list of services to monitor

//Domain Configuration
define("LDAP_DOMAIN", '');
define("DASHBOARD_DOMAIN", '');
define("DASHBOARD_BASE_URL", '');
define("WEBSOCKET_URL", '');
define("SUPPORT_EMAIL", '');

//Connection Info
$Default_Connection = array("Database" => DASHBOARD_SQL_INSTANCE, "CharacterSet" => "UTF-8");

/*
 * ---------------------------------------------------------------------------------------------------------
 * Optionale Einstellungen (alle auskommentiert = bisheriges Verhalten). Details: docs/CONFIGURATION.md
 * ---------------------------------------------------------------------------------------------------------
 */

// WebSocket-Server
// define("WEBSOCKET_PORT", 9000);
// define("WEBSOCKET_ALLOWED_ORIGINS", array('https://dashboard.example.local'));
// define("WEBSOCKET_TRUSTED_COMMAND_NETWORKS", array('10.0.0.0/8', '127.0.0.1'));

// Toast-Medien, die der Server herunterlädt (SSRF-Schutz)
// define("TOAST_MEDIA_ALLOWED_HOSTS", array('media.giphy.com', 'intranet.example.local'));
// define("TOAST_MEDIA_MAX_BYTES", 25 * 1024 * 1024);

// REST-API (websocket/api.php)
// define("TOAST_API_KEY", 'langer-zufaelliger-wert');   // externe Aufrufer senden Header X-Api-Key
// define("TOAST_API_RATE_LIMIT", 30);                   // Anfragen pro Minute und IP

// Zugriffsschutz Admin/Control/Workmode (zusätzlich IIS-Windows-Authentifizierung empfohlen)
// define("ADMIN_ALLOWED_USERS", array('DOMAIN\\admin1', 'admin2'));
// define("ADMIN_ALLOWED_NETWORKS", array('10.10.0.0/16'));
// define("TRUSTED_PROXIES", array('127.0.0.1', '::1'));

// Monitor (Signage-Frontend)
// define("MONITOR_FORCE_LEGACY", true);                 // Rollback auf die alte Oberfläche
// define("MONITOR_OPTIONS", array(
//     'alertPages' => array('3', '4', '5', '6', '9'),
//     'maxActiveVideos' => 1,
//     'snowFlakes' => 60,
//     'toastScripts' => 'reload-only',                    // 'legacy' = Skripte im ToastBody ausführen (unsicher)
//     'recovery' => array('enabled' => true, 'wsDownMs' => 600000, 'maxReloadsPerHour' => 3, 'heapLimitMb' => 0),
// ));






