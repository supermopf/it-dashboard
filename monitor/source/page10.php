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
        "ID" => "00100017' OR coll.CollectionID = '00100092' OR coll.CollectionID = '0010006F",
        "Name" => "Server Welle 2",
        "Prop" => "SWelle2"
    ),
    array(
        "ID" => "00100018",
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
        if ($result = sqlsrv_query($conn, "SELECT TOP 10 comp.Name0 As 'Name', count(comp.Name0) AS NumberOfMissingUpdates
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
            upInfo.ArticleID != 'NULL' AND 
            comp.Operating_System_Name_and0 LIKE '%Server%' AND
            /*
            00100016	Server Welle 1
            00100017	Server Welle 2
            00100018	Server Welle 3
            */
            (coll.CollectionID = '" . $ConfItem["ID"] . "' )AND
            upinfo.IsSuperseded = 0 AND
            upinfo.IsDeployed = 1 AND
            upinfo.IsExpired = 0 AND
            upinfo.Title LIKE '%Security%'
        GROUP BY comp.Name0
        ORDER BY NumberOfMissingUpdates DESC")) {
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

echo "<div class='row'>";
foreach ($Wellen_Conf as $ConfItem) {
    echo "<div class='col-lg-4'>";
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
echo "</div>";
echo "<div class='row'>";
foreach ($WellenData as $key => $Welle) {
    echo "<div class='col-lg-4'>";
    //List
    echo "<ul class='list-group'>";
    echo "<li class='list-group-item active'>Servername<span class='pull-right'>Fehlende Updates</span></li>";
    foreach ($Welle as $Item) {
        echo "<li class='list-group-item'>" . $Item["Name"] . "<span class='pull-right'>" . $Item["NumberOfMissingUpdates"] . "</span></li>";
    }
    echo "</ul>";
    echo "</div>";
}
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

    ?>
</script>
