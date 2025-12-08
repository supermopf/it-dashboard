<?php
/***********DO NOT TOUCH***********/
/**                              **/
/**  THIS FILE WILL BE INCLUDED  **/
/**                              **/
/***********DO NOT TOUCH***********/

$beginn = microtime(true);
require("../../config.php");
$NO_SQL_ERROR = true;
$Graph_Count = count($config);
if (DebugMode) {
    error_reporting(E_ALL);
}
function Get_SQL_Data($serverName, $connectionInfo, $query)
{
    global $NO_SQL_ERROR;
    $conn = sqlsrv_connect($serverName, $connectionInfo);
    $array = array();
    if ($conn) {
        //Haben wir Kontakt?
        if ($result = sqlsrv_query($conn, $query)) {
            $NO_SQL_ERROR = true;
            while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
                $array[] = $row;
            }
        } else {
            $NO_SQL_ERROR = false;
            if (DebugMode) {
                //SQL Fehler
                echo "<pre>" . PHP_EOL;
                echo "<!-- Query: $query -->" . PHP_EOL;
                die(print_r(sqlsrv_errors(), true));
            } else {
                //PROD Meldung
                echo '<div class="alert alert-danger col-lg-12">' . PHP_EOL;
                echo "<i class='fa fa-exclamation-triangle' aria-hidden='true'></i> Es ist ein Fehler bei der Abfrage aufgetreten!";;
                echo '</div>' . PHP_EOL;
            }
        }
        sqlsrv_close($conn);
    } else {
        $NO_SQL_ERROR = false;
        if (DebugMode) {
            echo "<pre>" . PHP_EOL;
            echo "<!-- ServerName: $serverName -->" . PHP_EOL;
            echo "<!-- connectionInfo START -->" . PHP_EOL;
            print_r($connectionInfo);
            echo "<!-- connectionInfo END -->" . PHP_EOL;
            die(print_r(sqlsrv_errors(), true));
        } else {
            echo '<div class="alert alert-danger col-lg-12">' . PHP_EOL;
            echo "<i class='fa fa-exclamation-triangle' aria-hidden='true'></i> Es ist ein Fehler bei der Verbindung zur Datenbank aufgetreten!";
            echo '</div>' . PHP_EOL;
        }
    }
    return $array;
}

?>
    <!-- Monitoring START-->
    <div class="bg-info">
        <!-- SCOM -->
        <?php
        #region <SCOM-Code>
        $query = "";
        $query .= "SELECT TOP 1000 [PrincipalName] ";
        $query .= "  ,[Name] ";
        $query .= "  ,[Description] ";
        $query .= "  ,[TimeRaised] ";
        $query .= "  ,[Priority] ";
        $query .= "  ,[Severity] ";
        $query .= "  ,[CustomField1] ";
        $query .= "  ,[RepeatCount] ";
        $query .= "  ,[Timestamp] ";
        $query .= "FROM [IT-Dashboard].[dbo].[IT-Dashboard_SCOM] ";
        $query .= $SCOM_SQL;
        $query .= " ORDER BY [TimeRaised] DESC";


        $array = Get_SQL_Data(DASHBOARD_SQL_INSTANCE, $Default_Connection, $query);

        if ($NO_SQL_ERROR) : ?>
            <div class="row bouncer">
                <div class="col-lg-12">
                    <div class="panel panel-default">
                        <div class="panel-heading">SCOM</div>
                        <div class="panel-body">
                            <div class="col-lg-12 table-responsive">
                                <!-- Table -->
                                <table class="table table-striped">
                                    <thead>
                                    <tr>
                                        <th width="10%">Server</th>
                                        <th width="5%">Meldungsart</th>
                                        <th width="70%">Meldung</th>
                                        <th width="5%">Priorität</th>
                                        <th width="10%">Erstellungszeitpunkt</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                    $prio = array(
                                        "TOP" => "<strong>TOP</strong>",
                                        "High" => "<strong>Hoch</strong>",
                                        "Normal" => "Mittel",
                                        "Low" => "Niedrig"
                                    );
                                    $time = new DateTime();
                                    foreach ($array as $key => $Value) {
                                        $dateObj = $array[$key]["TimeRaised"];
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
                                            if ($diff->format("%M") != 1) {
                                                $datestr = "%M Monaten";
                                            } else {
                                                $datestr = "%m Monat";
                                            }
                                        } elseif ($diff->format("%d") >= 1) {
                                            if ($diff->format("%D") != 1) {
                                                $datestr = "%D Tagen";
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

                                        switch ($array[$key]["Priority"]) {
                                            case "High":
                                                $color = "warning";
                                                break;
                                            case "TOP":
                                                $color = "danger";
                                                break;
                                            default:
                                                $color = "default";
                                                break;
                                        }


                                        echo "<tr>";
                                        echo "<td class='$color h5'>" . $array[$key]["PrincipalName"] . "</td>";
                                        echo "<td class='$color h5'>" . $array[$key]["Severity"] . "</td>";
                                        echo "<td class='$color h5'>" . $array[$key]["Description"] . "</td>";
                                        echo "<td class='$color h5'>" . $prio[$array[$key]["Priority"]] . "</td>";
                                        echo "<td class='$color h5'>" . $diff->format($prefix . $datestr) . "</td>";
                                        echo "</tr>";
                                    }
                                    ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif;
        #endregion <SCOM-Code>
        ?>
        <!-- DERDACK -->
        <?php
        #region <DERDACK-Code>
        $serverName = DERDACK_SQL_INSTANCE;
        $connectionInfo = array("Database" => DERDACK_SQL_DATABASE, "CharacterSet" => "UTF-8");
        $query = "";
        $query .= "SELECT ";
        $query .= "    * ";
        $query .= "FROM ";
        $query .= "    [dbo].[V_IT-Dashboard] ";
        $query .= $DERDACK_SQL;

        $array = Get_SQL_Data($serverName, $connectionInfo, $query);
        ?>
        <?php if ($NO_SQL_ERROR) : ?>
            <div class="row bouncer">
                <div class="col-lg-12">
                    <div class="panel panel-default">
                        <div class="panel-heading">DERDACK</div>
                        <div class="panel-body">
                            <div class="col-lg-12 table-responsive">
                                <!-- Table -->
                                <table class="table table-striped">
                                    <thead>
                                    <tr>
                                        <th width="25%">Betreff</th>
                                        <th width="55%">Meldung</th>
                                        <th width="10%">Erstellungszeitpunkt</th>
                                        <th width="10%">Empfänger</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                    //
                                    // SCOM Meldungen
                                    //
                                    $serverName = DERDACK_SQL_INSTANCE;
                                    $connectionInfo = array("Database" => DERDACK_SQL_DATABASE, "CharacterSet" => "UTF-8");
                                    $query = "SELECT * FROM [dbo].[V_IT-Dashboard] WHERE Subject LIKE '%PDM%' OR Subject LIKE '%SRM%' OR Subject LIKE '%PLM%' OR AlertText LIKE '%PDM%' OR AlertText LIKE '%SRM%' OR AlertText LIKE '%PLM%'";
                                    $array = Get_SQL_Data($serverName, $connectionInfo, $query);


                                    $time = new DateTime();
                                    foreach ($array as $key => $Value) {
                                        $dateObj = $array[$key]["TimeStamp"];
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
                                            if ($diff->format("%M") != 1) {
                                                $datestr = "%M Monaten";
                                            } else {
                                                $datestr = "%m Monat";
                                            }
                                        } elseif ($diff->format("%d") >= 1) {
                                            if ($diff->format("%D") != 1) {
                                                $datestr = "%D Tagen";
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

                                        echo "<tr>";
                                        echo "<td class='$color h5'>" . $array[$key]["Subject"] . "</td>";
                                        echo "<td class='$color h5'>" . $array[$key]["AlertText"] . "</td>";
                                        echo "<td class='$color h5'>" . $diff->format($prefix . $datestr) . "</td>";
                                        echo "<td class='$color h5'>" . $array[$key]["DisplayName"] . "</td>";
                                        echo "</tr>";
                                    }
                                    ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php
        endif;
        #endregion <DERDACK-Code>
        ?>
        <!-- HelpLine -->
        <?php
        #region <HelpLine-Code>
        $serverName = HELPLINE_SQL_INSTANCE;
        $connectionInfo = array("Database" => HELPLINE_SQL_DATABASE, "CharacterSet" => "UTF-8");
        $query = "";
        $query .= "SELECT";
        $query .= "  * ";
        $query .= "FROM [HL_Data].[dbo].[SBl_SSa_IncRecSerReq_kw] ";
        $query .= "WHERE [state] IN ('Offen', 'Zu prüfen', 'Warte auf') ";
        $query .= "AND [keyword] NOT LIKE '%zz%' ";
        $query .= $HELPLINE_SQL;
        $query .= "ORDER BY [promisedsolutiontime] DESC";
        $array = Get_SQL_Data($serverName, $connectionInfo, $query);
        ?>
        <?php if ($NO_SQL_ERROR) : ?>
            <div class="row bouncer">
                <div class="col-lg-12">
                    <div class="panel panel-default">
                        <div class="panel-heading">HelpLine</div>
                        <div class="panel-body">
                            <div class="row">
                                <div class="col-lg-12 table-responsive">
                                    <!-- Table -->
                                    <table class="table table-striped">
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
                                            "Niedrig" => "Niedrig",
                                            "nach Vereinbarung" => "nach Vereinbarung"
                                        );
                                        foreach ($array as $key => $Value) {
                                            $dateObj = $array[$key]["promisedsolutiontime"];
                                            $color = "";
                                            $prefix = "";
                                            $time_string = "";
                                            if ($dateObj instanceof \DateTime) {
                                                $diff = $time->diff($dateObj);
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
                                                    if ($diff->format("%M") != 1) {
                                                        $datestr = "%M Monaten";
                                                    } else {
                                                        $datestr = "%m Monat";
                                                    }
                                                } elseif ($diff->format("%d") >= 1) {
                                                    if ($diff->format("%D") != 1) {
                                                        $datestr = "%D Tagen";
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

                                                $time_string = $diff->format($prefix . $datestr);
                                            }

                                            $assignedagent = preg_replace('/(?<!\ )[A-Z]/', ' $0', $array[$key]["assignedagent"]);


                                            echo "<tr>";
                                            echo "<td class='$color h5'>" . $array[$key]["referencenumber"] . "</td>";
                                            echo "<td class='$color h5'>" . $array[$key]["subject"] . "</td>";
                                            echo "<td class='$color h5'>" . $array[$key]["requestername"] . "</td>";
                                            echo "<td class='$color h5'>" . $array[$key]["keyword"] . "</td>";
                                            echo "<td class='$color h5'>" . $prio[$array[$key]["priority"]] . "</td>";
                                            echo "<td class='$color h5'>" . $assignedagent . "</td>";
                                            echo "<td class='$color h5'>" . $array[$key]["reservedby"] . "</td>";
                                            echo "<td class='$color h5'>" . $time_string . "</td>";
                                            echo "</tr>";
                                        }
                                        ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php
        endif;
        #endregion <HelpLine-Code>
        ?>
    </div>
    <!-- Monitoring END -->

    <!-- Server START-->
    <div class="bg-warning">
        <!-- CPU/RAM -->
        <?php
        #region <CPU+RAM-Code>

        $Perf_SQL = "";
        foreach ($config as $server) {
            $Perf_SQL .= "NAME = '" . $server . "." . LDAP_DOMAIN . "' OR ";
        }
        $Perf_SQL = substr($Perf_SQL, 0, -3);


        $query = "";
        $query .= "SELECT ";
        $query .= "  [Name], ";
        $query .= "  [CPU], ";
        $query .= "  [RAM], ";
        $query .= "  [Timestamp] ";
        $query .= "FROM [IT-Dashboard].[dbo].[IT-Dashboard_Performance] ";
        $query .= "WHERE [Timestamp] > DATEADD(MINUTE, -180, GETDATE()) ";
        $query .= "AND ($Perf_SQL) ";
        $query .= "ORDER BY Timestamp ASC";
        $array = Get_SQL_Data(DASHBOARD_SQL_INSTANCE, $Default_Connection, $query);

        //        echo "<!--".$query."-->"

        ?>
        <?php if ($NO_SQL_ERROR) : ?>
            <div class="row bouncer">
                <?php
                for ($i = 1; $i <= $Graph_Count; $i++) {
                    if ($i == $Graph_Count AND $Graph_Count % 2 != 0) {
                        echo '<div class="col-lg-6 col-lg-offset-3">';
                    } else {
                        echo '<div class="col-lg-6">';
                    }
                    echo '<div class="panel panel-default">';
                    echo '<div class="panel-heading">' . $config[$i] . '</div>';
                    echo '<div class="panel-body">';
                    echo '<div class="col-lg-12 col-md-12 col-sm-12">';
                    echo '<canvas id="server' . $i . '" class="chart"></canvas>';
                    echo '</div>';
                    echo '</div>';
                    echo '</div>';
                    echo '</div>';
                }
                ?>
                <?php
                //Für jedes Diagramm
                for ($i = 1; $i <= $Graph_Count; $i++) {
                    echo PHP_EOL . "<!-- Perf $i --> ";
                    echo '<script type="text/javascript">';
                    echo '
                            ctx = $("#server' . $i . '").get(0).getContext("2d");
                            var data = {
                            labels: [';
                    $labels = "";
                    foreach ($array as $key => $value) {
                        if ($array[$key]["Name"] === $config[$i] . "." . LDAP_DOMAIN) {
                            $dateObj = $array[$key]["Timestamp"];
                            //Haben wir es geschafft Rick?
                            if ($dateObj instanceof \DateTime) {
                                $labels .= "'" . $dateObj->format('H:i') . "',";
                            }
                        }
                    }
                    echo substr($labels, 0, -1);
                    echo '],
                        datasets: [
                            {
                                label: "CPU Auslastung in %",
                                backgroundColor: "rgba(34, 167, 240,0.4)",
                                borderColor: "rgba(34, 167, 240,1)",
                                borderWidth: 1,
                                data: [';
                    $data = "";
                    foreach ($array as $key => $value) {

                        if ($array[$key]["Name"] === $config[$i] . "." . LDAP_DOMAIN) {
                            $data .= $array[$key]["CPU"] . ",";
                        }
                    }
                    echo substr($data, 0, -1);
                    echo ']
                            },{
                                label: "RAM Auslastung in %",
                                backgroundColor: "rgba(242, 38, 19,0.2)",
                                borderColor: "rgba(242, 38, 19,1)",
                                borderWidth: 1,
                                data: [';
                    $data = "";
                    foreach ($array as $key => $value) {

                        if ($array[$key]["Name"] === $config[$i] . "." . LDAP_DOMAIN) {
                            $data .= $array[$key]["RAM"] . ",";
                        }
                    }
                    echo substr($data, 0, -1);

                    echo ']}]};' . PHP_EOL;

                    echo 'var chartInstance' . $i . ' = new Chart(ctx, {type: "line",data: data,options: options_line})';
                    //echo "var LineChart$i = new Chart(ctx).Line(data,options);";
                    echo '</script>';
                }
                ?>
            </div>
        <?php
        endif;
        #endregion <CPU+RAM-Code>
        ?>
    </div>
    <!-- Server END -->

    <!-- ESX Nodes START -->
    <div class="bg-primary ">
        <!-- VMware -->
        <div class="row bouncer">
            <?php
            $conn = sqlsrv_connect(DASHBOARD_SQL_INSTANCE, $Default_Connection);

            $array = array(array());

            $HostsOnNode = array();

            function in_array_r($item, $array)
            {
                return preg_match('/"' . $item . '"/i', json_encode($array));
            }

            function Get_ESXHost_Data($Hostname)
            {
                global $conn, $array, $HostsOnNode;
                $vmware = array();
                $query = "";
                $query .= "SELECT ";
                $query .= "  [Hostname], ";
                $query .= "  [CPU], ";
                $query .= "  [RAM], ";
                $query .= "  [Description], ";
                $query .= "  [Guests], ";
                $query .= "  [Timestamp] ";
                $query .= "FROM [IT-Dashboard].[dbo].[IT-Dashboard_VMWARE] ";
                $query .= "WHERE [Timestamp] > DATEADD(MINUTE, -180, GETDATE()) ";
                $query .= "AND Hostname = (SELECT ";
                $query .= "  [ESX] ";
                $query .= "FROM [IT-Dashboard].[dbo].[IT-Dashboard_ESX_Host] ";
                $query .= "WHERE Host = '" . $Hostname . "') ";
                $query .= "ORDER BY Timestamp ASC";
                if ($result = sqlsrv_query($conn, $query)) {
                    while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
                        $vmware[] = $row;
                    }
                    if (array_key_exists(0, $vmware)) {
                        if (array_key_exists($vmware[0]["Hostname"], $HostsOnNode)) {
                            $HostsOnNode[$vmware[0]["Hostname"]][] = $Hostname;
                        } else {
                            $HostsOnNode[$vmware[0]["Hostname"]] = array($Hostname);
                        }
                        if (!in_array_r($vmware[0]["Hostname"], $array)) {
                            $array[] = $vmware;
                        }
                    }
                } else {
                    echo "<pre>";
                    die(print_r(sqlsrv_errors(), true));
                }
            }

            if ($conn) {
                for ($i = 1; $i <= $Graph_Count; $i++) {
                    Get_ESXHost_Data($config[$i]);
                }
            }
            sqlsrv_close($conn);

            $count = count($array) - 1;

            for ($i = 1; $i <= $count; $i++) {
                if ($i === $count AND $count % 2 != 0) {
                    echo '<div class="col-lg-6 col-lg-offset-3">';
                } else {
                    echo '<div class="col-lg-6">';
                }
                echo '<div class="panel panel-default">';
                echo '<div class="panel-heading" id="' . $array[$i][0]["Hostname"] . '"></div>';
                echo '<div class="panel-body">';
                echo '<div class="col-lg-12 col-md-12 col-sm-12">';
                echo '<canvas id="vmware' . $i . '" class="chart"></canvas>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
            }
            ?>
            <script>
                <?php
                foreach ($HostsOnNode as $key => $value) {
                    $string = rtrim(implode(', ', $value), ',');
                    echo '$("#' . $key . '").html("' . $key . ' (' . $string . ')")' . PHP_EOL;
                }
                ?>
            </script>

            <?php
            for ($i = 1; $i <= $count; $i++) {
                echo PHP_EOL . "<!-- Vmware $i --> ";
                echo '<script type="text/javascript">';
                echo '
                            ctx = $("#vmware' . $i . '").get(0).getContext("2d");
                            var data = {
                            labels: [';
                $labels = "";
                foreach ($array[$i] as $key => $value) {
                    if ($array[$i][$key]["Hostname"] === $array[$i][0]["Hostname"]) {
                        $dateObj = $array[$i][$key]["Timestamp"];
                        //Haben wir es geschafft Rick?
                        if ($dateObj instanceof \DateTime) {
                            $labels .= "'" . $dateObj->format('H:i') . "',";
                        }
                    }
                }
                echo substr($labels, 0, -1);
                echo '],
                        datasets: [
                            {
                                label: "CPU Auslastung in %",
                                backgroundColor: "rgba(34, 167, 240,0.4)",
                                pointHoverBackgroundColor: "rgba(34, 167, 240,0.8)",
                                borderColor: "rgba(34, 167, 240,1)",
                                borderWidth: 1,
                                data: [';
                $data = "";
                foreach ($array[$i] as $key => $value) {
                    if ($array[$i][$key]['Hostname'] === $array[$i][0]['Hostname']) {
                        $data .= $array[$i][$key]['CPU'] . ',';
                    }
                }
                echo substr($data, 0, -1);
                echo ']
                            },{
                                label: "RAM Auslastung in %",
                                backgroundColor: "rgba(242, 38, 19,0.2)",
                                borderColor: "rgba(242, 38, 19,1)",
                                borderWidth: 1,
                                data: [';
                $data = '';
                foreach ($array[$i] as $key => $value) {

                    if ($array[$i][$key]['Hostname'] === $array[$i][0]['Hostname']) {
                        $data .= $array[$i][$key]['RAM'] . ',';
                    }
                }
                echo substr($data, 0, -1);

                echo ']}]};' . PHP_EOL;

                echo "var chartInstance" . $i . ' = new Chart(ctx, {type: \'line\',data: data,options: options_line})';
                echo '</script>';
            }
            ?>
        </div>
    </div>
    <!-- ESX Nodes END -->

    <!-- Filer START -->
    <div class="bg-success">
        <!-- NetApp -->
        <div class="row bouncer">
            <div class="col-lg-12">
                <div class="panel panel-default">
                    <div class="panel-heading">NetApp</div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-lg-6">
                                <img class="img-responsive" src="../../monitor/grafana/37.png"/>
                            </div>
                            <div class="col-lg-6">
                                <img class="img-responsive" src="../../monitor/grafana/36.png"/>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Filer END -->
<?php
$dauer = microtime(true) - $beginn;
echo "<div class='bouncer'>Scriptlaufzeit: $dauer Sekunden</div>";
?>