<?php
/**
 * Created by PhpStorm.
 * User: victor.lange
 * Date: 23.02.2017
 * Time: 15:38
 */
require('./config.php');

$serverName = HELPLINE_SQL_INSTANCE;
$connectionInfo = array("Database" => HELPLINE_SQL_DATABASE, "CharacterSet" => "UTF-8");
$conn = sqlsrv_connect($serverName, $connectionInfo);

if ($conn) {
    //Tickets heute gelöst
    if ($result = sqlsrv_query($conn, "
            SELECT 
                [assignedagent],
                COUNT(*) AS Anzahl_Tickets
            FROM [HL_Data].[dbo].[SBl_SSa_IncRecSerReq_kw]
            WHERE
                DATEADD(DAY, 0, DATEDIFF(DAY, 0, [closingtime])) = DATEADD(DAY, 0, DATEDIFF(DAY, 0, CURRENT_TIMESTAMP)) AND
                [assignedagent] != 'NULL' AND
                ([state] = 'Gelöst' OR [state] = 'Geschlossen')
            GROUP BY [assignedagent]
            ORDER BY Anzahl_Tickets DESC")
    ) {
        $tickets_finished = array();
        while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
            array_push($tickets_finished, $row);
        }
    } else {

        echo "<pre>";
        die(print_r(sqlsrv_errors(), true));
    }


    //Tickets letzte 7 Tage
    if ($result = sqlsrv_query($conn, "
            SELECT 
                [assignedagent],
                COUNT(*) AS Anzahl_Tickets
            FROM [HL_Data].[dbo].[SBl_SSa_IncRecSerReq_kw]
            WHERE
                DATEADD(DAY, 0, DATEDIFF(DAY, 0, [closingtime])) > DATEADD(DAY, 0, DATEDIFF(DAY, 7, CURRENT_TIMESTAMP)) AND
                [assignedagent] != 'NULL' AND
                ([state] = 'Gelöst' OR [state] = 'Geschlossen')
            GROUP BY [assignedagent]
            ORDER BY Anzahl_Tickets DESC")
    ) {
        $tickets_finished7 = array();
        while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
            array_push($tickets_finished7, $row);
        }
    } else {

        echo "<pre>";
        die(print_r(sqlsrv_errors(), true));
    }
    // Tickets aufm buckel
    // AND [keyword] NOT LIKE 'ERP%'
    // AND [keyword] NOT LIKE 'Programmierung%'
    // AND [keyword] NOT LIKE 'PDM%'
    // AND [keyword] NOT LIKE 'Facili%'
    // AND [keyword] NOT LIKE 'Mobi%'
    // AND [keyword] NOT LIKE 'OMS%'
    // AND [keyword] NOT LIKE 'ECM%'
    // AND [keyword] NOT LIKE 'BI%'
    // AND [keyword] NOT LIKE 'Database%'
    // AND [keyword] NOT LIKE 'DPL%'
    // AND [keyword] NOT LIKE 'EDI%'
    if ($result = sqlsrv_query($conn, "
            SELECT 
                [assignedagent],
                COUNT(*) AS Anzahl_Tickets
            FROM [HL_Data].[dbo].[SBl_SSa_IncRecSerReq_kw]
            WHERE
                [state] != 'Gelöst' AND
                [state] != 'Geschlossen' AND
                [assignedagent] != 'NULL'
            GROUP BY [assignedagent]
            ORDER BY [Anzahl_Tickets] DESC")
    ) {
        $tickets_stored = array();
        while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
            array_push($tickets_stored, $row);
        }
    } else {

        echo "<pre>";
        die(print_r(sqlsrv_errors(), true));
    }
    //--------
    if ($result = sqlsrv_query($conn, "
          SELECT TOP 20 T1.[assignedagent],MAX(T1.[closingtime]) AS 'Letztes_Ticket'
          FROM [HL_Data].[dbo].[SBl_SSa_IncRecSerReq_kw] T1
          WHERE
              DATEADD(DAY, 0, DATEDIFF(DAY, 0, [closingtime])) > DATEADD(DAY, 0, DATEDIFF(DAY, 14, CURRENT_TIMESTAMP)) AND
              T1.[assignedagent] != 'NULL' AND (T1.[state] = 'Gelöst' OR T1.[state] = 'Geschlossen')
          GROUP BY T1.[assignedagent]
          ORDER BY [Letztes_Ticket] ASC")
    ) {
        $last_ticket = array();
        while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
            array_push($last_ticket, $row);
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

<!DOCTYPE html>
<html>

<head>
    <title>IT Dashboard</title>
    <meta http-equiv="refresh" content="3600; URL=<?php echo DASHBOARD_BASE_URL; ?>/control">
    <meta name="HandheldFriendly" content="true"/>
    <meta name="apple-mobile-web-app-capable" content="yes"/>
    <meta name="apple-mobile-web-app-status-bar-style" content="black"/>
    <meta name="apple-mobile-web-app-title" content="Dashboard App">
    <meta name="viewport" content="initial-scale=1 maximum-scale=1 user-scalable=0 minimal-ui"/>
    <link rel="shortcut icon" href="../monitor/img/favicon.ico" type="image/x-ico; charset=binary"/>
    <link rel="icon" href="../monitor/img/favicon.ico" type="image/x-ico; charset=binary"/>
    <!-- Fonts -->
    <link href='http://fonts.googleapis.com/css?family=Roboto+Condensed:300,400' rel='stylesheet' type='text/css'>
    <link href='http://fonts.googleapis.com/css?family=Lato:300,400,700,900' rel='stylesheet' type='text/css'>
    <!-- CSS Libs -->
    <link rel="stylesheet" type="text/css" href="./monitor/lib/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="./monitor/lib/css/font-awesome.min.css">
    <link rel="stylesheet" type="text/css" href="./monitor/lib/css/animate.min.css">
    <link rel="stylesheet" type="text/css" href="./monitor/lib/css/bootstrap-switch.min.css">
    <link rel="stylesheet" type="text/css" href="./monitor/lib/css/checkbox3.min.css">
    <!-- CSS App -->
    <link rel="stylesheet" type="text/css" href="./monitor/css/style.css">
    <link rel="stylesheet" type="text/css" href="./monitor/css/themes/flat-blue.css">
    <style>
        .row {
            margin-top: 15px;
        }

        .hover {
            background-color: #F3F315;
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-3">
            <div class="panel panel-default">
                <div class="panel-heading text-center">Gelöste Tickets (Letzte 7 Tage)</div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table">
                            <tr>
                                <th>Platz</th>
                                <th>Name</th>
                                <th>Tickets</th>
                            </tr>
                            <?php
                            $tickets_finished_max = count($tickets_finished7);
                            for ($i = 0; $i < $tickets_finished_max; $i++) {
                                echo "<tr>";
                                echo "<td>" . ($i + 1) . "</td>";
                                echo "<td><span class='" . $tickets_finished7[$i]["assignedagent"] . "'>" . preg_replace('/(?<!\ )[A-Z]/', ' $0', $tickets_finished7[$i]["assignedagent"]) . "<span></td>";
                                echo "<td>" . $tickets_finished7[$i]["Anzahl_Tickets"] . "</td>";
                                echo "</tr>";
                            }
                            ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="panel panel-default">
                <div class="panel-heading text-center">Gelöste Tickets (Heute)</div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table">
                            <tr>
                                <th>Platz</th>
                                <th>Name</th>
                                <th>Tickets</th>
                            </tr>
                            <?php
                            $tickets_finished_max = count($tickets_finished);
                            for ($i = 0; $i < $tickets_finished_max; $i++) {
                                echo "<tr>";
                                echo "<td>" . ($i + 1) . "</td>";
                                echo "<td><span class='" . $tickets_finished[$i]["assignedagent"] . "'>" . preg_replace('/(?<!\ )[A-Z]/', ' $0', $tickets_finished[$i]["assignedagent"]) . "</span></td>";
                                echo "<td>" . $tickets_finished[$i]["Anzahl_Tickets"] . "</td>";
                                echo "</tr>";
                            }
                            ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="panel panel-default">
                <div class="panel-heading text-center">Kein Ticket gelöst seit (Letzte 14 Tage)</div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table">
                            <tr>
                                <th>Platz</th>
                                <th>Name</th>
                                <th>Tickets</th>
                            </tr>
                            <?php
                            $last_ticket_count = count($last_ticket);
                            $today = new DateTime();

                            for ($i = 0; $i < $last_ticket_count; $i++) {
                                echo "<tr>";
                                echo "<td>" . ($i + 1) . "</td>";
                                echo "<td><span class='" . $last_ticket[$i]["assignedagent"] . "'>" . preg_replace('/(?<!\ )[A-Z]/', ' $0', $last_ticket[$i]["assignedagent"]) . "</span></td>";
//                                echo "<td>".$last_ticket[$i]["Letztes_Ticket"]->format('d.m.Y h:i')."</td>";
                                echo "<td title='" . $last_ticket[$i]["Letztes_Ticket"]->format('d.m.Y h:i') . "'>" . $last_ticket[$i]["Letztes_Ticket"]->diff($today)->days . " Tagen</td>";
                                echo "</tr>";
                            }
                            ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="panel panel-default">
                <div class="panel-heading text-center">Tickets im Besitz</div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table">
                            <tr>
                                <th>Platz</th>
                                <th>Name</th>
                                <th>Tickets</th>
                            </tr>
                            <?php
                            $tickets_stored_max = count($tickets_stored);
                            for ($i = 0; $i < $tickets_stored_max; $i++) {
                                echo "<tr class=''>";
                                echo "<td>" . ($i + 1) . "</td>";
                                echo "<td><span class='" . $tickets_stored[$i]["assignedagent"] . "'>" . preg_replace('/(?<!\ )[A-Z]/', ' $0', $tickets_stored[$i]["assignedagent"]) . "</span></td>";
                                echo "<td>" . $tickets_stored[$i]["Anzahl_Tickets"] . "</td>";
                                echo "</tr>";
                            }
                            ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>


<script src="//ajax.googleapis.com/ajax/libs/jquery/2.0.0/jquery.min.js"></script>


<script>
    $('span').hover(function () {
        var myClass = $(this).attr("class");
        $("." + myClass).addClass('hover');
    }, function () {
        $("span").removeClass('hover');
    });
</script>