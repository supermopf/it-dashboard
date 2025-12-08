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
$serverName = DASHBOARD_SQL_INSTANCE;
$connectionInfo = array("Database" => DASHBOARD_SQL_DATABASE, "CharacterSet" => "UTF-8");
$conn = sqlsrv_connect($serverName, $connectionInfo);

if ($conn) {
	if ($result = sqlsrv_query($conn, "SELECT *  FROM [IT-Dashboard].[dbo].[IT-Dashboard_Julianometer] WHERE Year(Timestamp) = Year(CURRENT_TIMESTAMP) AND Month(Timestamp) = Month(CURRENT_TIMESTAMP) ORDER BY [ID] DESC")) {
        $JulianStuff = array();
        while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
            array_push($JulianStuff, $row);
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
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <div class="title">Julian-O-Meter</div>
                </div>
            </div>
            <div class="card-body no-padding">
                <canvas id="JulianChart" height = "250" class="chart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
		<div class="card summary-inline">
			<h1 class="text-center">Verbleibendes Kontingent: <?php echo (JULIAN_KONTINGENT - Count($JulianStuff)); ?></h1>
		</div>
        <div class="card summary-inline">
            <div class="card-body">
                <!-- Table -->
                <table class="table table-responsive">
                    <thead>
                    <tr>
                        <th style="width: 10%;" class="h4">Datum</th>
                        <th style="width: 15%;" class="h4">Verbraucher</th>
                        <th class="h4">Beschreibung</th>
                    </tr>
                    </thead>
                    <tbody>
					<?php
						foreach ($JulianStuff as $feature) {
							$newDate = $feature["Timestamp"]->format('d.m.Y');
							echo "<tr>";
								echo "<td>".$newDate."</td>";
								echo "<td>".$feature["User"]."</td>";
								echo "<td>".$feature["Description"]."</td>";
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
$ChartData = array();
array_push($ChartData,Count($JulianStuff));

$ChartData = json_encode($ChartData);
?>

<script>
ctx = $('#JulianChart').get(0).getContext('2d');
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
                max: <?php echo JULIAN_KONTINGENT; ?>,
                fontSize: 15
            }
        }]
    },
    elements: {
        line: {
            tension: 0.3
        }
    },
	legend: {
		display: false
	},
    borderCapStyle: 'butt'
};
var bar_chart = new Chart(ctx, {
  type: 'bar',
  data: {
    labels: ["Verbrauchtes Julian-Kontingent"],
    datasets: [{
		data: <?php echo $ChartData ?>,
		label: "Julian-Kontingent",
		backgroundColor: [
            'rgba(255,99,132,1)',
        ],
		borderColor: [
            'rgba(255, 99, 132, 0.2)',
        ],
        borderWidth: 1
    }]
  },
  options: options
});

</script>
