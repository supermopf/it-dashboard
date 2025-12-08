<?php
/**
 * Created by PhpStorm.
 * User: victor.lange
 * Date: 05.04.2016
 * Time: 16:12
 */
//
// Normal Performance
//
require("../../config.php");
$conn = sqlsrv_connect(DASHBOARD_SQL_INSTANCE, $Default_Connection);

if ($conn) {
    //Haben wir Kontakt?
    if ($result = sqlsrv_query($conn, "SELECT TOP 1 * FROM [IT-Dashboard].[dbo].[IT-Dashboard_Backup_Status] ORDER BY [Timestamp] DESC")) {
        $status = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
    } else {
        echo "<pre>";
        die(print_r(sqlsrv_errors(), true));
    }
    if ($result = sqlsrv_query($conn, "SELECT DISTINCT [name],[objectType],[location],[lastSnapshot],[pullTime],[slaDomainName] FROM [IT-Dashboard].[dbo].[IT-Dashboard_Backup_Failed] WHERE [pullTime] > DATEADD(DAY,DATEDIFF(DAY,0,GETDATE()),0)")) {
        $Failed = array();
        while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
            array_push($Failed, $row);
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
    <div class="col-sm-8 col-xs-12">
        <table class="table table-responsive table-striped">
            <thead>
            <tr>
                <th width="10%" class="h4">Typ</th>
                <th width="20%" class="h4">Name</th>
                <th width="30%" class="h4">Location</th>
                <th width="15%" class="h4">Latest Snapshot</th>
                <th width="15%" class="h4">Last Pull</th>
				<th width="10%" class="h4">SLA</th>
            </tr>
            </thead>
            <tbody>
            <?php
            foreach ($Failed as $item) {
                echo "<tr>";
                echo "<td class='h4'>" . $item["objectType"] . "</td>";
                echo "<td class='h4'>" . $item["name"] . "</td>";
                echo "<td class='h4'>" . $item["location"] . "</td>";
                echo "<td class='h4'>" . date_format($item["lastSnapshot"], 'd.m.Y H:i') . "</td>";
                echo "<td class='h4'>" . date_format($item["pullTime"], 'd.m.Y H:i') . "</td>";
				echo "<td class='h4'>" . $item["slaDomainName"] . "</td>";
                echo "</tr>";
            }
            ?>
            </tbody>
        </table>
    </div>
    <div class="col-sm-4 col-xs-12">
        <div>
            <canvas id="Backup" class="chart"></canvas>
        </div>
    </div>
</div>


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

    var ctx = $('#Backup').get(0).getContext('2d');
    var data = {
        labels: [
            "Successful",
            "Partially Successful",
            "Failed"
        ],
        datasets: [
            {
                data: [<?php echo $status["Successful"] . "," . $status["Partially Successful"] . "," . $status["Failed"];?>],
                backgroundColor: [
                    "#12AF12",
                    "#FFD700",
                    "#CC3300"
                ]
            }]
    };
    var Backup = new Chart(ctx, {
        type: 'doughnut',
        data: data,
        options: {
            responsive: true,
            maintainAspectRatio: true,
            animation: {
                animateScale: true
            },
            elements: {
                center: {
                    text: '<?php echo round(@($status["Successful"] / ($status["Successful"] + $status["Partially Successful"] + $status["Failed"]) * 100), 2) . "%"; ?>',
                    color: '#12AF12',
                    fontStyle: 'Roboto Condensed',
                    sidePadding: 20 //Default 20 (as a percentage)
                }
            }
        }
    });
</script>