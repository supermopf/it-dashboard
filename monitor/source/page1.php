<?php
/**
 * Created by PhpStorm.
 * User: victor.lange
 * Date: 05.04.2016
 * Time: 14:13
 */
//
//Temperaturen
//
require("../../config.php");
$conn = sqlsrv_connect(DASHBOARD_SQL_INSTANCE, $Default_Connection);

if( $conn ) {
     //echo "Connection established.<br />";
}else{
     echo "Connection could not be established.<br /><pre>";
     die( print_r( sqlsrv_errors(), true));
}

$query = "
SELECT *
FROM [IT-Dashboard].[dbo].[IT-Dashboard_APC-Temperature]
WHERE [Timestamp] >= DATEADD(Minute, -120,GETDATE())
AND [Location] IN ('GerberEG', 'GerberOG', 'Street One', 'CECIL')
ORDER BY [Location] ASC, [Timestamp] ASC
";

if ($result = sqlsrv_query($conn, $query)) {
    $LocationData = [];
    while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
        if (!isset($LocationData[$row['Location']])) {
            $LocationData[$row['Location']] = [];
        }
        $LocationData[$row['Location']][] = $row;
    }
} else {
    echo "<pre>";
    die(print_r(sqlsrv_errors(), true));
}

$json = file_get_contents('https://api.openweathermap.org/data/2.5/weather?q=Isernhagen,de&APPID=' . APIKEY_openweathermap . '&lang=de');
$oIsernhagen = json_decode($json);
//$json = file_get_contents('https://api.openweathermap.org/data/2.5/weather?q=Celle,de&APPID=' . APIKEY_openweathermap . '&lang=de');
//$oCelle = json_decode($json);

$Locations = array(
    "CECIL"      => "CECIL",
    "GerberOG"   => "Gerberstraße OG",
    "Street One" => "Street One",
    "GerberEG"   => "Gerberstraße EG",
);

?>
<div class="row">
    <div class="col-lg-6">
        <div class="row">
            <div class="card yellow summary-inline">
                <div class="card-body">
                    <img height="128px" src="<?= "./img/weather/" . $oIsernhagen->weather[0]->icon . ".png" ?>"/>
                    <div class="content">
                        <div class="title"><?= $oIsernhagen->name ?>: <?= number_format(round(($oIsernhagen->main->temp - 273.15), 1), 1, ",", ".") ?> °C
                        </div>
                        <div class="title"><?= $oIsernhagen->weather[0]->description ?></div>
                    </div>
                    <div class="clear-both"></div>
                </div>
            </div>
        </div>
        <div class="row top-buffer">
            <div class="col-sm-12">
<?php

$i = 0;
foreach ($Locations as $name => $displayname) {
    $data = $LocationData[$name];
    if ($i % 2 == 0) {
?>
                <div class="row">
<?php
    }
?>
                <div class="col-sm-6 col-xs-12">
                    <div class="card state-<?= end($data)["StatusCode"] ?>">
                        <div class="card-header">
                            <div class="card-title">
                                <div class="title">
                                    <div class="col-sm-8"><?= $displayname ?></div>
                                    <div class="col-sm-2 blue"><?= end($data)["Humidity"] ?>%</div>
                                    <div class="col-sm-2 red"><?= end($data)["Temperature"] ?>°C</div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body no-padding">
                            <canvas id="location<?= $i ?>" class="chart" width="478" height="260"
                                    style="width: 478px; height: 260px;"></canvas>
                        </div>
                    </div>
                </div>
<?php
    if ($i % 2 == 1) {
?>
                </div>
<?php
    }
    $i++;
}
                ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <div class="title">Wetter</div>
                </div>
            </div>
            <div class="card-body no-padding">
                <img class="img-responsive" src="../radar.png?nocache=<?= uniqid() ?>" />
            </div>
            <div class="card-footer"><br></div>
        </div>
    </div>
</div>
<script>
    var minDate = new Date();
    minDate.setHours(minDate.getHours() - 2);

    var options = {
        scales: {
            xAxes: [{
                type: 'time',
                time: {
                    unit: 'minute',
                    min: minDate,
                    max: Date.now(),
                    displayFormats: {
                        minute: 'HH:mm'
                    },
                    unitStepSize: 30
                },
                ticks: {
                    fontSize: 15,
                    minRotation: 0,
                    maxRotation: 0,
                    autoSkip: true
                }
            }],
            yAxes: [{
                ticks: {
                    max: 100,
                    min: 0,
                    fontSize: 15
                }
            }]
        },
        borderCapStyle: 'butt',
        lineTension: 0.1,
        spanGaps: true
    };
</script>
<?php

$i = 0;
//Für jedes Diagramm
foreach ($Locations as $name => $displayname) {
    $data = $LocationData[$name];
    $timestamp_labels = array_map(function($row) {
        return $row['Timestamp']->format('c');
    }, $data);

    $temperature_data = array_column($data, 'Temperature');
    $humidity_data = array_column($data, 'Humidity');

    ?>
    <!-- Klima <?= $name ?> -->
    <script type="text/javascript">
        ctx = $('#location<?= $i ?>').get(0).getContext('2d');
        var data = {
        labels: <?= json_encode($timestamp_labels) ?>,
            datasets: [
                {
                    pointRadius: 1,
                    label: "Temperatur in °C",
                    backgroundColor: [
                        'rgba(199, 13, 59, 0.2)'
                    ],
                    borderColor: [
                        'rgba(199, 13, 58, 1)'
                    ],
                    borderWidth: 1,
                    data: <?= json_encode($temperature_data) ?>
                },{
                    pointRadius: 1,
                    label: "Luftfeuchtigkeit in %",
                        backgroundColor: [
                        'rgba(69, 150, 155, 0.2)'
                    ],
                    borderColor: [
                        'rgba(69, 150, 155, 1)'
                    ],
                    borderWidth: 1,
                    data: <?= json_encode($humidity_data) ?>
                }
            ]
        };
    var chartInstance<?= $i ?> = new Chart(ctx, {type: 'line', data: data, options: options});
</script>
<?php
    $i++;
}
?>


