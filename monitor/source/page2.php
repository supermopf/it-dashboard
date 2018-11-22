<?php
/**
 * Created by PhpStorm.
 * User: victor.lange
 * Date: 05.04.2016
 * Time: 16:12
 */
//
//Helpline
//
require("../../config.php");
$serverName = HELPLINE_SQL_INSTANCE;
$connectionInfo = array("Database" => HELPLINE_SQL_DATABASE, "CharacterSet" => "UTF-8");
$conn = sqlsrv_connect($serverName, $connectionInfo);

if ($conn) {
    if ($result = sqlsrv_query($conn,"
        SELECT COUNT (referencenumber) AS NewTickets
        FROM [HL_Data].[dbo].[SBl_SSa_IncRecSerReq_kw]
        WHERE referencenumber LIKE FORMAT(GETDATE(), 'yyyyMMdd')+'%' 
        AND [keyword] NOT LIKE 'zz_%'  
        AND [keyword] NOT LIKE 'ERP%'
        AND [keyword] NOT LIKE 'Programmierung%'
        AND [keyword] NOT LIKE 'PDM%'
        AND [keyword] NOT LIKE 'Facili%'
        AND [keyword] NOT LIKE 'Mobi%'
        AND [keyword] NOT LIKE 'OMS%'
        AND [keyword] NOT LIKE 'ECM%'
        AND [keyword] NOT LIKE 'BI%'
        AND [keyword] NOT LIKE 'DPL%'
        AND [keyword] NOT LIKE 'EDI%'
        AND [keyword] NOT LIKE 'CST%'"
    )) {
        $NewTickets = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
    } else {
        echo "<pre>";
        die(print_r(sqlsrv_errors(), true));
    }



    if ($result = sqlsrv_query($conn,"
        SELECT Count([referencenumber]) as ClosedTickets
        FROM [HL_Data].[dbo].[SBl_SSa_IncRecSerReq_kw]
        WHERE  CAST([closingtime] AS DATE) = CAST (GETDATE() as DATE)
        AND [keyword] NOT LIKE 'zz_%'  
        AND [keyword] NOT LIKE 'ERP%'
        AND [keyword] NOT LIKE 'Programmierung%'
        AND [keyword] NOT LIKE 'PDM%'
        AND [keyword] NOT LIKE 'Facili%'
        AND [keyword] NOT LIKE 'Mobi%'
        AND [keyword] NOT LIKE 'OMS%'
        AND [keyword] NOT LIKE 'ECM%'
        AND [keyword] NOT LIKE 'BI%'
        AND [keyword] NOT LIKE 'DPL%'
        AND [keyword] NOT LIKE 'EDI%'
        AND [keyword] NOT LIKE 'CST%'"
    )) {
        $ClosedTickets = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
    } else {
        echo "<pre>";
        die(print_r(sqlsrv_errors(), true));
    }



    if ($result = sqlsrv_query($conn, "SELECT TOP 10 * FROM [HL_Data].[dbo].[SBl_SSa_IncRecSerReq_kw]
      WHERE [state] IN ('Offen', 'Zu prüfen') AND ((
             reservedby IN ('CHRISTIAN FORNER',
                                  'STEFAN AMTMANN',
                                  'ARIAN AMIR',
                                  'ADAM ATAK',
                                  'HEIKO LABENS',
                                  'ABOUDOU ROSENBERG',
                                  'THOMAS KORTE',
                                  'VICTOR LANGE',
                                  'STEVEN SACHERT',
                                  'JULIAN SIEBERT',
                                  'GERRIT GROTE',
                                  'SEBASTIAN NITZ',
                                  'FLORIAN SIBUS',
                                  'MICHAEL PTASCHINSKI',
                                  'STEFAN LÜKE',
                                  'FABIAN FISCHER',
                                  'PATRICK PUSCH',
                                  'MICHAEL RADEMACHER')
             ) OR (
             assignedagent IN ('CHRISTIANFORNER',
                                        'STEFANAMTMANN',
                                        'ARIANAMIR',
                                        'ADAMATAK',
                                        'HEIKOLABENS',
                                        'ABOUDOUROSENBERG',
                                        'THOMASKORTE',
                                        'VICTORLANGE',
                                        'STEVENSACHERT',
                                        'JULIANSIEBERT',
                                        'GERRITGROTE',
                                        'SEBASTIANNITZ',
                                        'FLORIANSIBUS',
                                        'MICHAELPTASCHINSKI',
                                        'STEFANLUEKE',
                                        'FABIANFISCHER',
                                        'PATRICKPUSCH',
                                        'MICHAELRADEMACHER')
             ) OR (
                    [keyword] NOT LIKE 'zz_%'  
                    AND [keyword] NOT LIKE 'ERP%'
            AND [keyword] NOT LIKE 'Programmierung%'
            AND [keyword] NOT LIKE 'PDM%'
            AND [keyword] NOT LIKE 'Facili%'
            AND [keyword] NOT LIKE 'Mobi%'
            AND [keyword] NOT LIKE 'OMS%'
            AND [keyword] NOT LIKE 'ECM%'
            AND [keyword] NOT LIKE 'BI%'
            AND [keyword] NOT LIKE 'DPL%'
            AND [keyword] NOT LIKE 'EDI%'
                    AND [priority] IN ('TOP', 'Hoch')
             ) )
             AND [promisedsolutiontime] IS NOT NULL
     ORDER BY case when [priority] = 'TOP' then 1
                                  when [priority] = 'Hoch' then 2
                                  when [priority] = 'Mittel' then 3
                                  when [priority] = 'Niedrig' then 4
                                  else 5
                           end ASC, [promisedsolutiontime] ASC
")) {
        $array = array();
        while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
            array_push($array, $row);
        }
    } else {
        echo "<pre>";
        die(print_r(sqlsrv_errors(), true));
    }
} else {
    echo "<pre>";
    die(print_r(sqlsrv_errors(), true));
}
?>
<div class="row">
    <div class="col-lg-6">
        <div class="card dark summary-inline">
            <div class="card-body">
                <div class="content">
                    <div class="title">Heute geöffnete Tickets</div>
                    <div class="title"><?php echo $NewTickets["NewTickets"]; ?></div>
                </div>
                <div class="clear-both"></div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card dark summary-inline">
            <div class="card-body">
                <div class="content">
                    <div class="title">Heute geschlossene Tickets</div>
                    <div class="title"><?php echo $ClosedTickets["ClosedTickets"]; ?></div>
                </div>
                <div class="clear-both"></div>
            </div>
        </div>
    </div>
<div class="row">
    <div class="col-lg-12">
        <!-- Table -->
        <table class="table table-responsive table-striped">
            <thead>
            <tr>
                <th width="10%" class="h4">Ticketnummer</th>
                <th width="20%" class="h4">Betreff</th>
                <th width="15%" class="h4">Anfrager</th>
                <th width="15%" class="h4">Keyword</th>
                <th width="5%" class="h4">Priorität</th>
                <th width="10%" class="h4">Zuständiger Agent</th>
                <th width="10%" class="h4">Reserviert von</th>
                <th width="10%" class="h4">Versprochende Lösungszeit</th>
            </tr>
            </thead>
            <tbody>
            <?php
            $time = new DateTime();
            $prio = array(
                "TOP" => "<strong>TOP</strong>",
                "Hoch" => "<strong>Hoch</strong>",
                "Mittel" => "Mittel",
                "Niedrig" => "Niedrig"
            );
            foreach ($array as $key => $Value) {
                $dateObj = $array[$key]["promisedsolutiontime"];
                if ($dateObj instanceof \DateTime) {
                    $diff = $time->diff($dateObj);
                }
                $color = "";
                $prefix = "";
                if ($diff->format("%r%a") <= 0) {
                    $color = "danger";
                    $prefix = "Vor ";
                } else {
                    $prefix = "In ";
                }
                if ($diff->format("%r%a") <= 0) {
                    $color = "danger";
                    $prefix = "Vor ";
                }
                $datestr = "";
                if ($diff->format("%a") >= 365) {
                    if ($diff->format("%Y") != 1) {
                        $datestr = "%Y Jahren";
                    } else {
                        $datestr = "%y Jahr";
                    }
                } elseif ($diff->format("%a") >= 31) {
                    if ($diff->format("%m") > 1) {
                        $datestr = "%m Monaten";
                    } else {
                        $datestr = "%m Monat";
                    }
                } elseif ($diff->format("%d") >= 1) {
                    if ($diff->format("%d") != 1) {
                        $datestr = "%d Tagen";
                    } else {
                        $datestr = "%d Tag";
                    }
                } elseif ($diff->format("%h") <= 24) {
                    if ($diff->format("%h") != 1) {
                        $datestr = "%H Stunden";
                    } else {
                        $datestr = "%h Stunde";
                    }
                } else {
                    if ($diff->format("%i") != 1) {
                        $datestr = "%I Minuten";
                    } else {
                        $datestr = "%i Minute";
                    }
                }

                $assignedagent = preg_replace('/(?<!\ )[A-Z]/', ' $0', $array[$key]["assignedagent"]);


                echo "<tr class='helplinetable'>";
                echo "<td class='$color h4 text'><span>" . $array[$key]["referencenumber"] . "</span></td>";
                echo "<td class='$color h4 text'><span>" . $array[$key]["subject"] . "</span></td>";
                echo "<td class='$color h4 text'><span>" . $array[$key]["requestername"] . "</span></td>";
                echo "<td class='$color h4 text'><span>" . $array[$key]["keyword"] . "</span></td>";
                echo "<td class='$color h4 text'><span>" . $prio[$array[$key]["priority"]] . "</span></td>";
                echo "<td class='$color h4 text'><span>" . $assignedagent . "</span></td>";
                echo "<td class='$color h4 text'><span>" . $array[$key]["reservedby"] . "</span></td>";
                echo "<td class='$color h4 text'><span>" . $diff->format($prefix . $datestr) . "</span></td>";
                echo "</tr>";
            }
            ?>
            </tbody>
        </table>
    </div>
</div>

