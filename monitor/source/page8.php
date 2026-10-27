<?php
/**
 * Created by PhpStorm.
 * User: victor.lange
 * Date: 31.05.2017
 * Time: 08:07
 */

require("../../config.php");

$conn = sqlsrv_connect(SCCM_SQL_INSTANCE, array("Database" => SCCM_SQL_DATABASE, "CharacterSet" => "UTF-8"));

$Wellen_Conf = array(
    array(
        "ID" => "00100016",
        "Name" => "Server Welle 1",
        "Prop" => "SWelle1"
    ),
    array(
        //Pfusch am Stizzel
        "ID" => "00100180",
        "Name" => "Server Welle 2",
        "Prop" => "SWelle2"
    ),
    array(
        "ID" => "00100142",
        "Name" => "Server Welle 3",
        "Prop" => "SWelle3"
    )
);

$WellenData = array();
$ChartData = array();


if ($conn) {
    //Haben wir Kontakt?
    foreach ($Wellen_Conf as $ConfItem) {
        // Get LIST
        if ($result = sqlsrv_query($conn, "
        SELECT TOP 250 comp.Name0 As 'Name', count(comp.Name0) AS NumberOfMissingUpdates
        from v_R_System comp,v_Update_ComplianceStatus stat,v_UpdateInfo upinfo,v_FullCollectionMembership coll
        where 
            comp.ResourceID = stat.ResourceID AND
            comp.ResourceID = coll.ResourceID AND
            stat.CI_ID = upInfo.CI_ID AND
            /*
            https://technet.microsoft.com/en-us/library/bb932203.aspx
            TopicType	MessageID	Description
            500			0			Detection state unknown
            500			1			Update is not required
            500			2			Update is required
            500			3			Update is installed
            */
            stat.Status = 2 AND
            --upInfo.ArticleID != 'NULL' AND 
            --comp.Operating_System_Name_and0 LIKE '%Server%' AND
            /*
            00100016	Server Welle 1
            00100180	Server Welle 2 (for Deployment only)
            00100142	Server Welle 3
            */
            (coll.CollectionID = '" . $ConfItem["ID"] . "') AND
            upinfo.IsSuperseded = 0 AND
            upinfo.IsDeployed = 1 AND
            upinfo.IsExpired = 0 AND
            upinfo.Severity > 0
        GROUP BY comp.Name0
        ORDER BY NumberOfMissingUpdates DESC, Name
        ")) {
            $WellenData[$ConfItem["Prop"]] = array();
            while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
                array_push($WellenData[$ConfItem["Prop"]], $row);
            }
        } else {
            echo "<pre>";
            die(print_r(sqlsrv_errors(), true));
        }


        //Get ChartData
        if ($result = sqlsrv_query($conn, "
        SELECT [Total]=SUM(NumberTotal), [Unknown]=SUM(NumberUnknown),[Other]=SUM(NumberOther), [Success]=SUM(NumberSuccess),[InProgress]=SUM(NumberInProgress), [Errors]=SUM(NumberErrors)
        FROM 
            [CM_001].[dbo].[v_DeploymentSummary] coll
        WHERE 
        (
            (coll.CollectionID = '" . $ConfItem["ID"] . "') AND
            coll.DeploymentTime < GetDate()
        )")) {
            while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
                $ChartData[$ConfItem["Prop"]] = $row;
            }
        } else {
            echo "<pre>";
            die(print_r(sqlsrv_errors(), true));
        }
    }
} else {
    echo "<pre>";
    die(print_r(sqlsrv_errors(), true));
}

//Checkmk: Ubuntu Updates / Reboots
$UbuntuData = array();
$UbuntuSummary = array(
    "Total" => 0,
    "UpToDate" => 0,
    "NormalPending" => 0,
    "RebootPending" => 0,
    "SecurityPending" => 0,
    "Outdated" => 0
);

// Fehlen die Checkmk-Konstanten in config.php, wird der Ubuntu-Teil übersprungen statt mit
// „Undefined constant“ abzubrechen (docs/AUDIT.md S15).
$checkmk_response = false;
$checkmk_status = 0;
if (defined('CHECKMK_BASE_URL') && CHECKMK_BASE_URL !== '') {
    $checkmk_url = rtrim(CHECKMK_BASE_URL, '/') . "/check_mk/view.py?" . http_build_query(array(
            "output_format" => "json_export",
            "view_name" => "ubuntu_updates_reboots"
        ));

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $checkmk_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, (defined('CHECKMK_USER') ? CHECKMK_USER : '') . ':' . (defined('CHECKMK_SECRET') ? CHECKMK_SECRET : ''));
    //PHP curl does not use the Windows cert store, so the internal root CA is supplied explicitly
    if (defined('CA_BUNDLE') && is_readable(CA_BUNDLE)) {
        curl_setopt($ch, CURLOPT_CAINFO, CA_BUNDLE);
    }
    $checkmk_response = curl_exec($ch);
    $checkmk_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
}

if ($checkmk_status === 200 && $checkmk_response !== false) {
    $checkmk_rows = json_decode($checkmk_response, true);
    if (is_array($checkmk_rows) && count($checkmk_rows) > 1) {
        //First row holds the column names
        $columns = array_flip(array_shift($checkmk_rows));
        foreach ($checkmk_rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $host = isset($columns["host"]) && isset($row[$columns["host"]]) ? $row[$columns["host"]] : "";
            $reboot = isset($columns["service_state.reboot_required"]) ? $row[$columns["service_state.reboot_required"]] : "OK";
            $updates = isset($columns["service_state.apt_updates"]) ? $row[$columns["service_state.apt_updates"]] : "OK";
            $version = isset($columns["inv_software_os_name"]) ? $row[$columns["inv_software_os_name"]] : "";

            //Ubuntu supports LTS releases for 5 years, so anything released before that is out of support
            $support_limit = ((int)date("y") - 5) * 100 + (int)date("m");
            $outdated = false;
            if (preg_match('/(\d+)\.(\d+)/', $version, $m)) {
                $outdated = ((int)$m[1] * 100 + (int)$m[2]) < $support_limit;
            }

            $Server = array(
                "Hostname" => str_replace(".cbr.de", "", strtolower($host)),
                "Version" => $version,
                "Kernel" => isset($columns["inv_software_os_kernel_version"]) ? $row[$columns["inv_software_os_kernel_version"]] : "",
                "RebootRequired" => ($reboot !== "OK"),
                "UpdatesRequired" => ($updates !== "OK"),
                "SecurityUpdates" => isset($columns["service_specific_metric.security_updates"]) ? (int)$row[$columns["service_specific_metric.security_updates"]] : 0,
                "NormalUpdates" => isset($columns["service_specific_metric.normal_updates"]) ? (int)$row[$columns["service_specific_metric.normal_updates"]] : 0,
                "Outdated" => $outdated
            );

            if ($outdated) {
                $Server["State"] = "Outdated";
            } elseif ($Server["SecurityUpdates"] > 0) {
                $Server["State"] = "SecurityPending";
            } elseif ($Server["RebootRequired"]) {
                $Server["State"] = "RebootPending";
            } elseif ($Server["UpdatesRequired"] || $Server["NormalUpdates"] > 0) {
                $Server["State"] = "NormalPending";
            } else {
                $Server["State"] = "UpToDate";
            }
            $UbuntuSummary[$Server["State"]]++;

            array_push($UbuntuData, $Server);
        }
        $UbuntuSummary["Total"] = count($UbuntuData);

        $StateOrder = array("Outdated" => 0, "SecurityPending" => 1, "RebootPending" => 2, "NormalPending" => 3, "UpToDate" => 4);
        usort($UbuntuData, function ($a, $b) use ($StateOrder) {
            if ($a["State"] !== $b["State"]) {
                return $StateOrder[$a["State"]] - $StateOrder[$b["State"]];
            }
            if ($a["SecurityUpdates"] !== $b["SecurityUpdates"]) {
                return $b["SecurityUpdates"] - $a["SecurityUpdates"];
            }
            if ($a["NormalUpdates"] !== $b["NormalUpdates"]) {
                return $b["NormalUpdates"] - $a["NormalUpdates"];
            }
            return strcmp($a["Hostname"], $b["Hostname"]);
        });
    }
} else {
    // Print error message, maybe more details?
    echo "<div class='alert alert-danger'>Failed to retrieve Ubuntu server data from Checkmk.</div>";
}

echo "<div class='row'>";
foreach ($Wellen_Conf as $ConfItem) {
    echo "<div class='col-lg-3'>";
    //Chart
    echo "<div class='card'>";
    echo "<div class='card-header'>";
    echo "<div class='card-title'>";
    echo "<div class='title text-center'>";
    //Heading
    echo $ConfItem["Name"];
    echo "</div>";
    echo "</div>";
    echo "<div class='card-body no-padding'>";
    //Data
    echo "<div class='col-lg-12'>";
    echo "<canvas id='Chart" . $ConfItem["Prop"] . "' class='chart'></canvas>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
}
//Ubuntu (Checkmk)
echo "<div class='col-lg-3'>";
echo "<div class='card'>";
echo "<div class='card-header'>";
echo "<div class='card-title'>";
echo "<div class='title text-center'>Ubuntu Server</div>";
echo "</div>";
echo "<div class='card-body no-padding'>";
echo "<div class='col-lg-12'>";
echo "<canvas id='ChartUbuntu' class='chart'></canvas>";
echo "</div>";
echo "</div>";
echo "</div>";
echo "</div>";
echo "</div>";
echo "</div>";
echo "<div class='row'>";
foreach ($WellenData as $key => $Welle) {
    echo "<div class='col-lg-3'>";
    //List
    echo "<ul class='list-group' style='background: #313335;'>";
    echo "<li class='list-group-item active'>Servername<span class='pull-right'>Fehlende Updates</span></li>";
    foreach ($Welle as $Item) {
        echo "<li class='list-group-item small text-lowercase' style='width: 100%;display: inline-flex;padding-top: 0;padding-bottom: 0;justify-content: space-between;flex-direction: row;flex-wrap: nowrap;'><span>" . $Item["Name"] . "</span><span>" . $Item["NumberOfMissingUpdates"] . "</span></li>";
    }
    echo "</ul>";
    echo "</div>";
}
echo "<div class='col-lg-3'>";
echo "<ul class='list-group' style='background: #313335;'>";
echo "<li class='list-group-item active'>Servername<span class='pull-right'>Updates (Security / Normal)</span></li>";
foreach ($UbuntuData as $Server) {
    $Icon = $Server["RebootRequired"] ? " <i class='fa fa-refresh' style='" . PRIMARY_STYLE . "'></i>" : "";
    $NameStyle = $Server["Outdated"] ? " color: #FF2D2D; font-weight: bold;" : "";
    $Title = $Server["Outdated"] ? " title='" . htmlspecialchars($Server["Version"]) . " - nicht mehr im Support'" : " title='" . htmlspecialchars($Server["Version"]) . "'";
    echo "<li class='list-group-item small text-lowercase'" . $Title . " style='width: 100%;display: inline-flex;padding-top: 0; padding-bottom: 0;justify-content: space-between;flex-direction: row;flex-wrap: nowrap;" . $NameStyle . "'><span>" . htmlspecialchars($Server["Hostname"]) . $Icon . "</span>" .
        "<span>" . $Server["SecurityUpdates"] . " / " . $Server["NormalUpdates"] . "</span></li>";
}
echo "</ul>";
echo "</div>";
echo "</div>";

?>
<script>
    Chart.pluginService.register({
        beforeDraw: function (chart) {
            if (chart.config.options.elements.center) {
//Get ctx from string
                var ctx = chart.chart.ctx;

//Get options from the center object in options
                var centerConfig = chart.config.options.elements.center;
                var fontStyle = centerConfig.fontStyle || 'Arial';
                var txt = centerConfig.text;
                var color = centerConfig.color || '#000';
                var sidePadding = centerConfig.sidePadding || 20;
                var sidePaddingCalculated = (sidePadding / 100) * (chart.innerRadius * 2);
//Start with a base font of 30px
                ctx.font = "30px " + fontStyle;

//Get the width of the string and also the width of the element minus 10 to give it 5px side padding
                var stringWidth = ctx.measureText(txt).width;
                var elementWidth = (chart.innerRadius * 2) - sidePaddingCalculated;

// Find out how much the font can grow in width.
                var widthRatio = elementWidth / stringWidth;
                var newFontSize = Math.floor(30 * widthRatio);
                var elementHeight = (chart.innerRadius * 2);

// Pick a new font size so it will not be larger than the height of label.
                var fontSizeToUse = Math.min(newFontSize, elementHeight);

//Set font settings to draw it correctly.
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                var centerX = ((chart.chartArea.left + chart.chartArea.right) / 2);
                var centerY = ((chart.chartArea.top + chart.chartArea.bottom) / 2);
                ctx.font = fontSizeToUse + "px " + fontStyle;
                ctx.fillStyle = color;

//Draw text in center
                ctx.fillText(txt, centerX, centerY);
            }
        }
    });
    <?php
    $column_count = -1;
    foreach ($ChartData as $Report) {
        $column_count++;
        echo "var ctx" . $Wellen_Conf[$column_count]["Prop"] . " = $('#Chart" . $Wellen_Conf[$column_count]["Prop"] . "').get(0).getContext('2d');";
        echo "ctx" . $Wellen_Conf[$column_count]["Prop"] . ".canvas.width = 430;";
        echo "ctx" . $Wellen_Conf[$column_count]["Prop"] . ".canvas.height = 310;";
        echo 'var data' . $Wellen_Conf[$column_count]["Prop"] . ' = {
        labels: [
            "Success",
            "In Progress",
            "Errors",
            "Other",
            "Unknown"
        ],
                datasets: [
                    {
                        data: [' . str_replace(',', '.', $Report["Success"]) . ',' .
            str_replace(',', '.', $Report["InProgress"]) . ',' .
            str_replace(',', '.', $Report["Errors"]) . ',' .
            str_replace(',', '.', $Report["Other"]) . ',' .
            str_replace(',', '.', $Report["Unknown"]) . '],
        backgroundColor: [
        "#12AF12",
        "#FFA500",
        "#CC3300",
        "#808080",
        "#CACACA"
        ]
        }]
        };
    ';
        echo "
        var Backup = new Chart(ctx" . $Wellen_Conf[$column_count]["Prop"] . ", {
            type: 'doughnut',
            data: data" . $Wellen_Conf[$column_count]["Prop"] . ",
            options: {
                segmentShowStroke: false,
                responsive: true,
                maintainAspectRatio: true,
                animation:{
                animateScale:true
            },
            elements: {
                center: {
                    text: '" . round((str_replace(',', '.', $Report["Success"]) / (str_replace(',', '.', $Report["Total"])) * 100), 2) . "%" . "',
                    color: '#12AF12',
                    fontStyle: 'Roboto Condensed',
                    sidePadding: 20 //Default 20 (as a percentage)
                }
            }
            }
        });
    ";
    }

    //Ubuntu (Checkmk)
    $UbuntuPercent = $UbuntuSummary["Total"] > 0
        ? round($UbuntuSummary["UpToDate"] / $UbuntuSummary["Total"] * 100, 2)
        : 0;
    echo "
        var ctxUbuntu = $('#ChartUbuntu').get(0).getContext('2d');
        ctxUbuntu.canvas.width = 430;
        ctxUbuntu.canvas.height = 310;
        var dataUbuntu = {
            labels: ['Aktuell', 'Updates offen', 'Reboot nötig', 'Security Updates', 'Nicht unterstützt'],
            datasets: [{
                data: [" . $UbuntuSummary["UpToDate"] . "," . $UbuntuSummary["NormalPending"] . "," . $UbuntuSummary["RebootPending"] . "," . $UbuntuSummary["SecurityPending"] . "," . $UbuntuSummary["Outdated"] . "],
                backgroundColor: ['#12AF12', '#E5D800', '#FFA500', '#E56A5A', '#CC0000']
            }]
        };
        var Ubuntu = new Chart(ctxUbuntu, {
            type: 'doughnut',
            data: dataUbuntu,
            options: {
                segmentShowStroke: false,
                responsive: true,
                maintainAspectRatio: true,
                animation: {
                    animateScale: true
                },
                elements: {
                    center: {
                        text: '" . $UbuntuPercent . "%',
                        color: '#12AF12',
                        fontStyle: 'Roboto Condensed',
                        sidePadding: 20
                    }
                }
            }
        });
    ";

    ?>
</script>
