<?php
/**
 * Created by PhpStorm.
 * User: victor.lange
 * Date: 07.03.2018
 * Time: 12:41
 */
require('../config.php');

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
    $conn = sqlsrv_connect(DASHBOARD_SQL_INSTANCE, array(
        "Database" => "IT-Dashboard",
        "UID" => DASHBOARD_SQL_USERNAME,
        "PWD" => DASHBOARD_SQL_PASSWORD
    ));
    
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
                    sqlsrv_close($conn);
                    return $row['count'] > 0; // High CPU/RAM = Warning
                }
                sqlsrv_close($conn);
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
                    sqlsrv_close($conn);
                    return $row['count'] > 0; // High CPU/RAM = Warning
                }
                sqlsrv_close($conn);
            }
            return false;
        
        case 5: // SCOM Criticals
            if ($conn) {
                $result = sqlsrv_query($conn, "SELECT COUNT(*) as count FROM [IT-Dashboard].[dbo].[IT-Dashboard_SCOM_Criticals]");
                if ($result) {
                    $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
                    sqlsrv_close($conn);
                    return $row['count'] > 0; // Alerts exist = Warning
                }
                sqlsrv_close($conn);
            }
            return false;
        
        case 6: // Backup - Check for failed backups
            if ($conn) {
                $result = sqlsrv_query($conn, "
                    SELECT COUNT(*) as count 
                    FROM [IT-Dashboard].[dbo].[IT-Dashboard_Backup_Failed] 
                    WHERE [pullTime] > DATEADD(DAY, DATEDIFF(DAY, 0, GETDATE()), 0)
                ");
                if ($result) {
                    $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
                    sqlsrv_close($conn);
                    return $row['count'] > 0; // Failed backups = Warning
                }
                sqlsrv_close($conn);
            }
            return false;
        
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
        
        case 10: // Julianometer
            if ($conn) {
                $result = sqlsrv_query($conn, "
                    SELECT COUNT(*) as count 
                    FROM [IT-Dashboard].[dbo].[IT-Dashboard_Julianometer] 
                    WHERE Year(Timestamp) = Year(CURRENT_TIMESTAMP) 
                    AND Month(Timestamp) = Month(CURRENT_TIMESTAMP)
                ");
                if ($result) {
                    $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
                    sqlsrv_close($conn);
                    return $row['count'] > 0; // Has entries this month
                }
                sqlsrv_close($conn);
            }
            return false;
        
        default:
            // Unknown pages always show
            return true;
    }
}

foreach ($pages as $page) {
    $content = file_get_contents($root . $page);

    echo "Writing " . $cacheroot . $page . "\n";
    file_put_contents($cacheroot . $page, $content);
    
    // Extract page number from filename (e.g., "page5.php" -> 5)
    preg_match('/page(\d+)\.php/', $page, $matches);
    if (isset($matches[1])) {
        $pageNumber = (int)$matches[1];
        $hasData = checkPageHasData($pageNumber);
        $pageStatus[$pageNumber] = array(
            'hasData' => $hasData,
            'lastCheck' => time()
        );
        echo "Page $pageNumber: " . ($hasData ? "ACTIVE (show page)" : "EMPTY (skip page)") . "\n";
    }
}

// Write page status to JSON file
$statusFile = dirname(__FILE__) . "/cache/page_status.json";
file_put_contents($statusFile, json_encode($pageStatus, JSON_PRETTY_PRINT));
echo "\nPage status written to: $statusFile\n";
