<?php
/**
 * Created by PhpStorm.
 * User: victor.lange
 * Date: 31.05.2017
 * Time: 08:07
 */

require("../../config.php");
$conn = sqlsrv_connect(DASHBOARD_SQL_INSTANCE, $Default_Connection);

if ($conn) {
    //Haben wir Kontakt?
    if ($result = sqlsrv_query($conn, "SELECT t.[Servername],t.[HDDUsage],t.[SGCount],t.[TimeStamp] FROM [IT-Dashboard].[dbo].[IT-Dashboard_M3_Main] t INNER JOIN (SELECT [Servername],max([TimeStamp]) AS Zeit FROM [IT-Dashboard].[dbo].[IT-Dashboard_M3_Main] GROUP BY [Servername]) tm on t.Servername = tm.Servername AND t.[TimeStamp] = tm.Zeit WHERE t.Servername <> '' ORDER BY [Servername]")) {
        $server = array();
        while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
            array_push($server, $row);
        }

    } else {
        echo "<pre>";
        die(print_r(sqlsrv_errors(), true));
    }
    if ($result = sqlsrv_query($conn, "SELECT t.[Servername],t.[SGName],t.[Beschreibung],t.[Status],t.[Lat_Write],t.[Lat_Transfer],t.[Lat_Read],t.[TimeStamp]  FROM [IT-Dashboard].[dbo].[IT-Dashboard_M3_SG] t INNER JOIN (SELECT [ServerName],[SGName],max([TimeStamp]) AS Zeit FROM [IT-Dashboard].[dbo].[IT-Dashboard_M3_SG] GROUP BY [ServerName],[SGName]) tm on t.Servername = tm.Servername AND t.[SGName] = tm.[SGName] AND t.[TimeStamp] = tm.Zeit ORDER BY SGName")) {
        $SG = array();
        while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
            array_push($SG, $row);
        }
    } else {
        echo "<pre>";
        die(print_r(sqlsrv_errors(), true));
    }
} else {
    echo "<pre>";
    die(print_r(sqlsrv_errors(), true));
}
if(!isset($server[1])){
    echo "<pre>";
    echo "Power ðŸ¥š ist nicht erreichbar";
    die();
}
?>


<div class="row">
    <div class="col-lg-4">
        <?php
        if ($server[0]['TimeStamp']->format('U') < strtotime('-15 minutes')) {
            echo '<div class="panel panel-danger">';
        } else {
            echo '<div class="panel panel-default">';
        }
        ?>
        <div class="panel-heading">
            <?php echo $server[0]['Servername'] ?>
            <span class="pull-right">Daten vom: <?php echo $server[0]['TimeStamp']->format('d.m.Y H:i') ?></span>
        </div>
        <div class="panel-body">
            <div class="progress">
                <div class="progress-bar" role="progressbar" style="width:<?php echo $server[0]['HDDUsage'] ?>">
                    HDD <?php echo $server[0]['HDDUsage'] ?></div>
            </div>
        </div>
    </div>
</div>
<div class="col-lg-4">
    <?php
    if ($server[1]['TimeStamp']->format('U') < strtotime('-15 minutes')) {
        echo '<div class="panel panel-danger">';
    } else {
        echo '<div class="panel panel-default">';
    }
    ?>
    <div class="panel-heading">
        <?php echo $server[1]['Servername'] ?>
        <span class="pull-right">Daten vom: <?php echo $server[1]['TimeStamp']->format('d.m.Y H:i') ?></span>
    </div>
    <div class="panel-body">
        <div class="progress">
            <div class="progress-bar" role="progressbar" style="width:<?php echo $server[1]['HDDUsage'] ?>">
                HDD <?php echo $server[1]['HDDUsage'] ?></div>
        </div>
    </div>
</div>
</div>
<div class="col-lg-4">
    <?php
    if ($server[2]['TimeStamp']->format('U') < strtotime('-15 minutes')) {
        echo '<div class="panel panel-danger">';
    } else {
        echo '<div class="panel panel-default">';
    }
    ?>
    <div class="panel-heading">
        <?php echo $server[2]['Servername'] ?>
        <span class="pull-right">Daten vom: <?php echo $server[2]['TimeStamp']->format('d.m.Y H:i') ?></span>
    </div>
    <div class="panel-body">
        <div class="progress">
            <div class="progress-bar" role="progressbar" style="width:<?php echo $server[2]['HDDUsage'] ?>">
                HDD <?php echo $server[2]['HDDUsage'] ?></div>
        </div>
    </div>
</div>
</div>
</div>

<div class="row">
    <div class="col-lg-4">
        <!-- PRD -->
        <table class="table table-responsive table-striped">
            <thead>
            <tr>
                <th width="15%">Spiegelgruppe</th>
                <th width="35%">Beschreibung</th>
                <th width="15%">Status</th>
                <th width="15%">Lat. Write</th>
                <th width="20%">Lat. Transfer</th>
            </tr>
            </thead>
            <tbody>
            <?php
            foreach ($SG as $key => $Value) {
                if ($SG[$key]["Servername"] == $server[0]['Servername']) {
                    if ($SG[$key]["Status"] == "OK") {
                        echo "<tr>";
                    } elseif ($SG[$key]["Status"] == "WARNING") {
                        echo "<tr class='warning'>";
                    } else {
                        echo "<tr class='danger'>";
                    }
                    echo "<td class='h5'>" . $SG[$key]["SGName"] . "</td>";
                    echo "<td class='h5'>" . $SG[$key]["Beschreibung"] . "</td>";
                    echo "<td class='h5'>" . $SG[$key]["Status"] . "</td>";
                    echo "<td class='h5'>" . date('H:i:s', mktime(0, 0, $SG[$key]["Lat_Write"])) . "</td>";
                    echo "<td class='h5'>" . date('H:i:s', mktime(0, 0, $SG[$key]["Lat_Transfer"])) . "</td>";
                    echo "</tr>";
                }
            }
            ?>
            </tbody>
        </table>
    </div>
    <div class="col-lg-4">
        <!-- HAPRD -->
        <table class="table table-responsive table-striped">
            <thead>
            <tr>
                <th width="20%">Spiegelgruppe</th>
                <th width="35%">Beschreibung</th>
                <th width="20%">Status</th>
                <th width="25%">Latenz Read</th>
            </tr>
            </thead>
            <tbody>
            <?php
            foreach ($SG as $key => $Value) {
                if ($SG[$key]["Servername"] == $server[1]['Servername']) {
                    if ($SG[$key]["Status"] == "OK") {
                        echo "<tr>";
                    } elseif ($SG[$key]["Status"] == "WARNING") {
                        echo "<tr class='warning'>";
                    } else {
                        echo "<tr class='danger'>";
                    }
                    echo "<td class='h5'>" . $SG[$key]["SGName"] . "</td>";
                    echo "<td class='h5'>" . $SG[$key]["Beschreibung"] . "</td>";
                    echo "<td class='h5'>" . $SG[$key]["Status"] . "</td>";
                    echo "<td class='h5'>" . date('H:i:s', mktime(0, 0, $SG[$key]["Lat_Read"])) . "</td>";
                    echo "</tr>";
                }
            }
            ?>
            </tbody>
        </table>
    </div>
    <div class="col-lg-4">
        <!-- DEV -->
        <table class="table table-responsive table-striped">
            <thead>
            <tr>
                <th width="20">Spiegelgruppe</th>
                <th width="35%">Beschreibung</th>
                <th width="20%">Status</th>
                <th width="25%">Latenz Read</th>
            </tr>
            </thead>
            <tbody>
            <?php
            foreach ($SG as $key => $Value) {
                if ($SG[$key]["Servername"] == $server[2]['Servername']) {
                    if ($SG[$key]["Status"] == "OK") {
                        echo "<tr>";
                    } elseif ($SG[$key]["Status"] == "WARNING") {
                        echo "<tr class='warning'>";
                    } else {
                        echo "<tr class='danger'>";
                    }
                    echo "<td class='h5'>" . $SG[$key]["SGName"] . "</td>";
                    echo "<td class='h5'>" . $SG[$key]["Beschreibung"] . "</td>";
                    echo "<td class='h5'>" . $SG[$key]["Status"] . "</td>";
                    echo "<td class='h5'>" . date('H:i:s', mktime(0, 0, $SG[$key]["Lat_Read"])) . "</td>";
                    echo "</tr>";
                }
            }
            ?>
            </tbody>
        </table>
    </div>
</div>