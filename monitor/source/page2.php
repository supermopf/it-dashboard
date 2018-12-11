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

    if ($result = sqlsrv_query($conn,"
        SELECT Count([referencenumber]) As NumberOfTickets, DATEPART(HOUR, [registrationtime]) AS Hour
        FROM [HL_Data].[dbo].[SBl_SSa_IncRecSerReq_kw]
        WHERE registrationtime >= CAST(GETDATE() AS DATE) 
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
        AND [keyword] NOT LIKE 'CST%'
        
        GROUP BY DATEPART(HOUR, [registrationtime])"
    )) {
        $TicketChart = array();
        while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
            $TicketChart[$row['Hour']] = $row['NumberOfTickets'];
        }
    } else {
        echo "<pre>";
        die(print_r(sqlsrv_errors(), true));
    }



    if ($result = sqlsrv_query($conn, "
        SELECT *
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
        AND [keyword] NOT LIKE 'CST%'
        ORDER BY [registrationtime]
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
</div>
<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <div class="title">Heutiges Ticketaufkommen</div>
                </div>
            </div>
            <div class="card-body no-padding">
                <canvas id="TicketChart" class="chart" width="478" height="260" style="width: 478px; height: 260px;"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card summary-inline">
            <div class="card-body">
                <!-- Table -->
                <table class="table table-responsive table-striped">
                    <thead>
                    <tr>
                        <th style="width: 10%;" class="h4">Ticketnummer</th>
                        <th class="h4">Betreff</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    $time = new DateTime();
                    foreach ($array as $key => $Value) {
                        $dateObj = $array[$key]["promisedsolutiontime"];
                        $color = "";
                        $assignedagent = preg_replace('/(?<!\ )[A-Z]/', ' $0', $array[$key]["assignedagent"]);


                        echo "<tr class='helplinetable'>";
                        echo "<td class='$color h4 text'><span>" . $array[$key]["referencenumber"] . "</span></td>";
                        echo "<td class='$color h4 text'><span>" . $array[$key]["subject"] . "</span></td>";
                        echo "</tr>";
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$ChartLabels = array();
$ChartData = array();
for($i = 0; $i <= 23; $i++){
    $time = strtotime($i . ':00:00');
    if(isset($TicketChart[$i])){
        array_push($ChartLabels,date("G:i",$time));
        array_push($ChartData, $TicketChart[$i]);
    }else{
        array_push($ChartLabels, date("G:i",$time));
        array_push($ChartData, 0);
    }
}
$ChartLabels = json_encode($ChartLabels);
$ChartData = json_encode($ChartData);
?>

<script>
    const verticalLinePlugin = {
        getLinePosition: function (chart, pointIndex) {
            const meta = chart.getDatasetMeta(0); // first dataset is used to discover X coordinate of a point
            const data = meta.data;
            return data[pointIndex]._model.x;
        },
        renderVerticalLine: function (chartInstance, pointIndex) {
            const lineLeftOffset = this.getLinePosition(chartInstance, pointIndex);
            const scale = chartInstance.scales['y-axis-0'];
            const context = chartInstance.chart.ctx;

            // render vertical line
            context.beginPath();
            context.strokeStyle = 'rgba(255,99,132,1)';
            context.moveTo(lineLeftOffset, scale.top);
            context.lineTo(lineLeftOffset, scale.bottom);
            context.stroke();

        },

        afterDatasetsDraw: function (chart, easing) {
            if (chart.config.lineAtIndex) {
                chart.config.lineAtIndex.forEach(pointIndex => this.renderVerticalLine(chart, pointIndex));
            }
        }
    };

    Chart.plugins.register(verticalLinePlugin);



var options = {
    scales: {
        xAxes: [{
            ticks: {
                fontSize: 15
            }
        }],
        yAxes: [{
            ticks: {
                min: 0,
                max: 10,
                fontSize: 15
            }
        }]
    },
    elements: {
        line: {
            tension: 0.3
        }
    },
    borderCapStyle: 'butt'
};
var data = {
        labels: <?php echo $ChartLabels ?>,
        datasets: [{
        label: 'Anzahl Tickets',
        data: <?php echo $ChartData ?>,
        backgroundColor: [
            'rgba(255, 99, 132, 0.2)',
        ],
        borderColor: [
            'rgba(255,99,132,1)',
        ],
        borderWidth: 1
    }]
};
ctx = $('#TicketChart').get(0).getContext('2d');

var d = new Date();
var n = d.getHours();
var TicketChart = new Chart(ctx, {
    type: 'line',
    data: data,
    options: options,
    lineAtIndex: [n]
});
</script>
