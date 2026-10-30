<?php
/**
 * Created by PhpStorm.
 * User: victor.lange
 * Date: 07.03.2018
 * Time: 12:41
 */
require(getenv('DASHBOARD_CONFIG') ?: __DIR__ . '/../config.php');
require_once(__DIR__ . '/../lib/pages.php');
$conn = sqlsrv_connect(DASHBOARD_SQL_INSTANCE, $Default_Connection);

$root = DASHBOARD_BASE_URL . "/monitor/source/";
$cacheroot = dirname(__FILE__) . "/cache/";

$pages = array(
    "page1.php",
    "page2.php",
    "page3.php",
    "page4.php",
    "page5.php",
    "page6.php",
    "page7.php",
    "page8.php",
    "page9.php",
	"page10.php"
);

// Array to store page status information
$pageStatus = array();

// Function to check if a page has data
function checkPageHasData($pageNumber) {    
    global $conn;
    
    switch ($pageNumber) {
        case 1: // Temperature - Always show (informational data)
            return true;

        case 2: // helpLine - Check for open tickets
            // Could check for urgent/critical tickets if needed
            return true;
        
        case 3: // Performance - Check if any servers have issues
            if ($conn) {
                $result = sqlsrv_query($conn, "
                    SELECT COUNT(*) as count 
                    FROM [IT-Dashboard].[dbo].[IT-Dashboard_Performance]
                    WHERE [Timestamp] > DATEADD(MINUTE, -10, GETDATE())
                    AND ([CPU] > 90 OR [RAM] > 90)
                ");
                if ($result) {
                    $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
                    return $row['count'] > 0; // High CPU/RAM = Warning
                }
            }
            return false;
        
        case 4: // VMware - Check for high resource usage
            if ($conn) {
                $result = sqlsrv_query($conn, "
                    SELECT COUNT(*) as count 
                    FROM [IT-Dashboard].[dbo].[IT-Dashboard_VMWARE]
                    WHERE [Timestamp] > DATEADD(MINUTE, -10, GETDATE())
                    AND ([CPU] > 90 OR [RAM] > 90)
                ");
                if ($result) {
                    $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
                    return $row['count'] > 0; // High CPU/RAM = Warning
                }
            }
            return false;
        
        case 5: // SCOM Criticals
            if ($conn) {
                $result = sqlsrv_query($conn, "SELECT COUNT(*) as count FROM [IT-Dashboard].[dbo].[IT-Dashboard_SCOM_Criticals]");
                if ($result) {
                    $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
                    return $row['count'] > 0; // Alerts exist = Warning
                }
            }
            return false;
        
        case 6: // Backup – überspringen, wenn die Erfolgsquote 100 % beträgt (gleiche Rundung wie page6.php)
            if ($conn) {
                $result = sqlsrv_query($conn, "
                    SELECT TOP 1 [Successful], [Partially Successful] AS Partial, [Failed]
                    FROM [IT-Dashboard].[dbo].[IT-Dashboard_Backup_Status]
                    ORDER BY [Timestamp] DESC
                ");
                if ($result) {
                    $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
                    if (!$row) {
                        return true;
                    }
                    $total = (float)$row['Successful'] + (float)$row['Partial'] + (float)$row['Failed'];
                    return $total <= 0 || round((float)$row['Successful'] / $total * 100, 2) < 100;
                }
            }
            return true;
        
        case 7: // M365 Service Health - Check for active issues
            // This would require checking M365 API status
            // For now, always show the page
            return true; // Treat as informational
        
        case 8: // SCCM Updates - Check for missing updates
            // Always show (informational)
            return true;
        
        case 9: // Derdack - Check for recent alerts
            $derdack_conn = sqlsrv_connect(DERDACK_SQL_INSTANCE, array(
                "Database" => DERDACK_SQL_DATABASE,
                "CharacterSet" => "UTF-8"
            ));
            if ($derdack_conn) {
                $result = sqlsrv_query($derdack_conn, "
                    SELECT COUNT(*) as count 
                    FROM [dbo].[V_IT-Dashboard] 
                    WHERE [TimeStamp] > DATEADD(MINUTE, -60, GETDATE())
                ");
                if ($result) {
                    $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
                    sqlsrv_close($derdack_conn);
                    return $row['count'] > 0; // Recent alerts = Warning
                }
                sqlsrv_close($derdack_conn);
            }
            return false;
        
        case 10: // Julianometer – das Zeitfenster (Mo/Di/Fr 12–18 Uhr) prüft server.php, siehe lib/pages.php
            return true;
        
        default:
            // Unknown pages always show
            return true;
    }
}

/**
 * Erkennt Fehlerausgaben der Quellseiten (die(print_r(sqlsrv_errors())), PHP-Fatal-Errors).
 * Gleiche Regeln wie das Frontend (frontend/src/core/view-cache.ts, looksInvalid).
 */
function renderLooksInvalid($html)
{
    $t = trim((string)$html);
    if ($t === '') {
        return 'leer';
    }
    if (preg_match('/^<pre>\s*Array\s*\(/i', $t) || preg_match('/\[SQLSTATE\]\s*=>/i', $t)) {
        return 'Datenbankfehler';
    }
    if (preg_match('/<b>(Fatal error|Parse error)<\/b>:/i', $t) || preg_match('/^(PHP )?Fatal error:/im', $t)) {
        return 'PHP-Fehler';
    }
    return null;
}

/** Atomar schreiben: Clients lesen nie eine halb geschriebene Datei. */
function writeAtomic($file, $content)
{
    $tmp = $file . '.tmp';
    if (file_put_contents($tmp, $content, LOCK_EX) === false) {
        return false;
    }
    if (!@rename($tmp, $file)) {
        // Windows: rename über bestehende Datei kann bei geöffnetem Handle scheitern → Fallback.
        $ok = @copy($tmp, $file);
        @unlink($tmp);
        return $ok;
    }
    return true;
}

$renderCtx = stream_context_create(array('http' => array('timeout' => 60, 'ignore_errors' => true)));

foreach ($pages as $page) {
    $content = @file_get_contents($root . $page, false, $renderCtx);
    $httpStatus = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $httpStatus = (int)$m[1];
    }

    $invalid = $content === false ? 'nicht abrufbar' : ($httpStatus !== 200 ? 'HTTP ' . $httpStatus : renderLooksInvalid($content));
    if ($invalid !== null) {
        // Letzte gültige Version behalten (AUDIT S11/S13).
        echo "SKIP " . $cacheroot . $page . " ($invalid) – vorherige Version bleibt erhalten\n";
    } else {
        echo "Writing " . $cacheroot . $page . "\n";
        writeAtomic($cacheroot . $page, $content);
    }

    // Extract page number from filename (e.g., "page5.php" -> 5)
    preg_match('/page(\d+)\.php/', $page, $matches);
    if (isset($matches[1])) {
        $pageNumber = (int)$matches[1];
        // Vorrang hat die Markierung der Seite selbst (lib/pages.php) – sie kennt den tatsächlichen Inhalt.
        $marker = $invalid === null ? page_relevance_from_html($content) : null;
        $hasData = $marker !== null ? $marker : checkPageHasData($pageNumber);
        $pageStatus[$pageNumber] = array(
            'hasData' => $hasData,
            'lastCheck' => time()
        );
        echo "Page $pageNumber: " . ($hasData ? "ACTIVE (show page)" : "EMPTY (skip page)") . "\n";
    }
}

sqlsrv_close($conn);

// Write page status to JSON file
$statusFile = dirname(__FILE__) . "/cache/page_status.json";
writeAtomic($statusFile, json_encode($pageStatus, JSON_PRETTY_PRINT));
echo "\nPage status written to: $statusFile\n";
