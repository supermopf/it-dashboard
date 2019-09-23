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

if ($conn) {
    //Haben wir Kontakt?
    if ($result = sqlsrv_query($conn, "SELECT *  FROM [IT-Dashboard].[dbo].[IT-Dashboard_APC-Temperature] WHERE [Timestamp] > DATEADD(hour,-1,GETDATE()) ORDER BY [Timestamp] ASC")) {
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

$json = file_get_contents('https://api.openweathermap.org/data/2.5/weather?q=Isernhagen,de&APPID='.APIKEY_openweathermap.'&lang=de');
$oIsernhagen = json_decode($json);
$json = file_get_contents('https://api.openweathermap.org/data/2.5/weather?q=Celle,de&APPID='.APIKEY_openweathermap.'&lang=de');
$oCelle = json_decode($json);




$numbers = array();
foreach ($array as $value) {
    $numbers[] = $value["Temperature"];
    $numbers[] = $value["Humidity"];
}
$max = @max($numbers);
$maxnumber = ceil($max / 20);

//IDs mit
$config = array(
    1 => "CECIL MEN",
    2 => "Street One",
    3 => "CECIL"
);

$DisplayNameConfig = array(
    1 => "CECIL MEN",
    2 => "Street One",
    3 => "CECIL"
);


$Values_Now = [""];
foreach ($config as $value) {
    $temp_array = [];
    for ($i = 0; $i < count($array); $i++) {
        if ($array[$i]["Location"] == $value) {
            array_push($temp_array, $array[$i]);
        }
    }
    array_push($Values_Now, end($temp_array));
}
?>
<div class="row">
    <div class="col-lg-6">
        <div class="row">
            <div class="card yellow summary-inline">
                <div class="card-body">
                    <img height="128px" src="<?php echo "./img/weather/".$oIsernhagen->weather[0]->icon.".png" ?>"/>
                    <div class="content">
                        <div class="title"><?php echo $oIsernhagen->name ?>
                            : <?php echo round(($oIsernhagen->main->temp - 273.15),1) ?> °C
                        </div>
                        <div class="title"><?php echo $oIsernhagen->weather[0]->description ?></div>
                    </div>
                    <div class="clear-both"></div>
                </div>
            </div>
        </div>
        <div class="row top-buffer">
            <div class="col-sm-12">
                <div class="row">
                    <div class="col-sm-6 col-xs-12">
                        <div class="card state-<?php echo $Values_Now[1]["StatusCode"] ?>">
                            <div class="card-header">
                                <div class="card-title">
                                    <div class="title">
                                        <div class="col-sm-8"><?php echo $DisplayNameConfig[1] ?></div>
                                        <div class="col-sm-2 blue"><?php echo $Values_Now[1]["Humidity"]; ?>%</div>
                                        <div class="col-sm-2 red"><?php echo $Values_Now[1]["Temperature"]; ?>°C</div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body no-padding">
                                <canvas id="wetter1" class="chart" width="478" height="260"
                                        style="width: 478px; height: 260px;"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xs-12">
                        <div class="card state-<?php echo $Values_Now[2]["StatusCode"] ?>">
                            <div class="card-header">
                                <div class="card-title">
                                    <div class="title">
                                        <div class="col-sm-8"><?php echo $DisplayNameConfig[2] ?></div>
                                        <div class="col-sm-2 blue"><?php echo $Values_Now[2]["Humidity"]; ?>%</div>
                                        <div class="col-sm-2 red"><?php echo $Values_Now[2]["Temperature"]; ?>°C</div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body no-padding">
                                <canvas id="wetter2" class="chart" width="478" height="260"
                                        style="width: 478px; height: 260px;"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-sm-8 col-lg-offset-2">
                        <div class="card state-<?php echo $Values_Now[3]["StatusCode"] ?>">
                            <div class="card-header">
                                <div class="card-title">
                                    <div class="title">
                                        <div class="col-sm-8"><?php echo $DisplayNameConfig[3] ?></div>
                                        <div class="col-sm-2 blue"><?php echo $Values_Now[3]["Humidity"]; ?>%</div>
                                        <div class="col-sm-2 red"><?php echo $Values_Now[3]["Temperature"]; ?>°C</div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body no-padding">
                                <canvas id="wetter3" class="chart" width="478" height="260"
                                        style="width: 478px; height: 260px;"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
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
                <img class="img-responsive" src="../radar.png?nocache=<?php echo uniqid(); ?>" />
            </div>
        </div>
    </div>
</div>
<script>
    var options = {
        scales: {
            xAxes: [{
                ticks: {
                    fontSize: 15
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

//Für jedes Diagramm
for ($i = 1; $i <= 4; $i++) {
    echo PHP_EOL . "<!-- Wetter $i --> ";
    echo '<script type="text/javascript">';
    echo "
            ctx = $('#wetter$i').get(0).getContext('2d');
            var data = {
            labels: [";
    $labels = "";
    $counter = 0;
    foreach ($array as $key => $value) {
        if ($array[$key]["Location"] == $config[$i]) {
            $dateObj = $array[$key]["Timestamp"];
            //Haben wir es geschafft Rick?
            if ($dateObj instanceof \DateTime) {
                //Nur alle 5 Minuten anzeigen
                if ($dateObj->format('i') % 10 == 0) {
                    $labels .= "'" . $dateObj->format('H:i') . "',";
                }
            }
        }
    }
    echo substr($labels, 0, -1);
    echo "],
                datasets: [
                    {
                        label: \"Temperatur in °C\",
                        backgroundColor: [
                            'rgba(242, 38, 19, 0.2)'
                        ],
                        borderColor: [
                            'rgba(242, 38, 19,1)'
                        ],
                        borderWidth: 1,
                        data: [";
    $data = "";
    foreach ($array as $key => $value) {

        if ($array[$key]["Location"] == $config[$i]) {
            $data .= $array[$key]["Temperature"] . ",";
        }
    }
    echo substr($data, 0, -1);
    echo "]
                    },{
                        label: \"Luftfeuchtigkeit in %\",
                         backgroundColor: [
                            'rgba(34, 167, 240,0.2)'
                        ],
                        borderColor: [
                            'rgba(34, 167, 240,1)'
                        ],
                        borderWidth: 1,
                        data: [";
    $data = "";
    foreach ($array as $key => $value) {

        if ($array[$key]["Location"] == $config[$i]) {
            $data .= $array[$key]["Humidity"] . ",";
        }
    }
    echo substr($data, 0, -1);

    echo "]}]};" . PHP_EOL;

    echo "var chartInstance" . $i . " = new Chart(ctx, {type: 'line',data: data,options: options})";
    //echo "var LineChart$i = new Chart(ctx).Line(data,options);";
    echo '</script>';
}
?>


