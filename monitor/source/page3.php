<?php
/**
 * Created by PhpStorm.
 * User: victor.lange
 * Date: 05.04.2016
 * Time: 16:12
 */
//
//Netzwerk
//
require("../../config.php");
$conn = sqlsrv_connect(DASHBOARD_SQL_INSTANCE, $Default_Connection);

if ($conn) {
    //Haben wir Kontakt?
    if ($result = sqlsrv_query($conn, "SELECT * FROM [IT-Dashboard].[dbo].[IT-Dashboard_Network] WHERE [Timestamp] > DATEADD(MINUTE ,-60,GETDATE()) ORDER BY [Timestamp] ASC")) {
        $array = array();
        while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
            array_push($array, $row);
        }
    } else {
        echo "<pre>";
        die(print_r(sqlsrv_errors(), true));
    }
    if ($result = sqlsrv_query($conn, "SELECT [Name]
	FROM [IT-Dashboard].[dbo].[IT-Dashboard_Network] 
	WHERE [Timestamp] > DATEADD(MINUTE, -60, GETDATE())
	AND [Alias] LIKE '%2000013629%'
	GROUP BY [Name]
	ORDER BY AVG((Inbound+Outbound)) DESC")) {
        $Usage = array();
        while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
            array_push($Usage, $row);
        }
    } else {
        echo "<pre>";
        die(print_r(sqlsrv_errors(), true));
    }

} else {
    echo "<pre>";
    die(print_r(sqlsrv_errors(), true));
}

//CE_KH_Prim
$CE_KH_Prim = array();
$CE_KH_Prim_Time = date_create('2009-10-11');
$CE_KH_Prim_Array = array();

//MPLS
$MPLS = array();
$MPLS_Time = date_create('2009-10-11');
$MPLSArray = array();

//Internet KH
$Internet_KH = array();
$Internet_KH_Time = date_create('2009-10-11');
$Internet_KH_Array = array();

//$Internet_ECOM
$Internet_ECOM = array();
$Internet_ECOM_Time = date_create('2009-10-11');
$Internet_ECOM_Array = array();

//Others
$Others = array();


//Haupt-Kacheln
foreach ($array as $value) {
    if (isset($value["Inbound"]) && isset($value["Outbound"])) {
        //Leitung CE-KH pri
        if ($value["Name"] == "cel-acc-101.cbr.de") {
            $interval = date_diff($CE_KH_Prim_Time, $value["Timestamp"]);

            if (($interval->format('%i')) < 1 AND ($interval->format('%H')) == 0) {
                $CE_KH_Prim_Array["Inbound"] += $value["Inbound"];
                $CE_KH_Prim_Array["Outbound"] += $value["Outbound"];
                $CE_KH_Prim_Array["Value_Count"]++;
            } else {
                if ($CE_KH_Prim_Array != array()) {
                    array_push($CE_KH_Prim, $CE_KH_Prim_Array);
                }
                $CE_KH_Prim_Time = $value["Timestamp"];
                $CE_KH_Prim_Array["Timestamp"] = $value["Timestamp"];
                $CE_KH_Prim_Array["Inbound"] = $value["Inbound"];
                $CE_KH_Prim_Array["Outbound"] = $value["Outbound"];
                $CE_KH_Prim_Array["Value_Count"] = 1;
            }
        } //Leitung MPLS
        elseif ($value["Name"] == "de-cbr-isernh-ce-hsrp.mpls.cbr.de" OR $value["Name"] == "de-cbr-isernh-ce-02") {
            $interval = date_diff($MPLS_Time, $value["Timestamp"]);


            if (($interval->format('%i')) < 1 AND ($interval->format('%H')) == 0) {
                $MPLSArray["Inbound"] += $value["Inbound"];
                $MPLSArray["Outbound"] += $value["Outbound"];
                $MPLSArray["Value_Count"]++;
            } else {
                if ($MPLSArray != array()) {
                    array_push($MPLS, $MPLSArray);
                }
                $MPLS_Time = $value["Timestamp"];
                $MPLSArray["Timestamp"] = $value["Timestamp"];
                $MPLSArray["Inbound"] = $value["Inbound"];
                $MPLSArray["Outbound"] = $value["Outbound"];
                $MPLSArray["Value_Count"] = 1;
            }
        } //Internet KH
        elseif ($value["Name"] == "cbr-fw1") {
            $interval = date_diff($Internet_KH_Time, $value["Timestamp"]);
            if (($interval->format('%i')) < 1 AND ($interval->format('%H')) == 0) {
                $Internet_KH_Array["Inbound"] += $value["Inbound"];
                $Internet_KH_Array["Outbound"] += $value["Outbound"];
                $Internet_KH_Array["Value_Count"]++;
            } else {
                if ($Internet_KH_Array != array()) {
                    array_push($Internet_KH, $Internet_KH_Array);
                }
                $Internet_KH_Time = $value["Timestamp"];
                $Internet_KH_Array["Timestamp"] = $value["Timestamp"];
                $Internet_KH_Array["Inbound"] = $value["Inbound"];
                $Internet_KH_Array["Outbound"] = $value["Outbound"];
                $Internet_KH_Array["Value_Count"] = 1;
            }
        } //Internet ECOM
        elseif ($value["Name"] == "eco-acc-101.cbr.de") {
            $interval = date_diff($Internet_ECOM_Time, $value["Timestamp"]);
            if (($interval->format('%i')) < 1 AND ($interval->format('%H')) == 0) {
                $Internet_ECOM_Array["Inbound"] += $value["Inbound"];
                $Internet_ECOM_Array["Outbound"] += $value["Outbound"];
                $Internet_ECOM_Array["Value_Count"]++;
            } else {
                if ($Internet_ECOM_Array != array()) {
                    array_push($Internet_ECOM, $Internet_ECOM_Array);
                }
                $Internet_ECOM_Time = $value["Timestamp"];
                $Internet_ECOM_Array["Timestamp"] = $value["Timestamp"];
                $Internet_ECOM_Array["Inbound"] = $value["Inbound"];
                $Internet_ECOM_Array["Outbound"] = $value["Outbound"];
                $Internet_ECOM_Array["Value_Count"] = 1;
            }
        } else {
            //All Others
            array_push($Others, $value);
        }
    }
}


$graph_data = array();
array_push($graph_data, "Dummy");
array_push($graph_data, $CE_KH_Prim);
array_push($graph_data, $MPLS);
array_push($graph_data, $Internet_KH);
array_push($graph_data, $Internet_ECOM);

for ($i = 0; $i < count($Usage); $i++) {
    $temp_Other = array();
    foreach ($Others as $other) {
        if ($other["Name"] == $Usage[$i]["Name"]) {
            array_push($temp_Other, $other);
        }
    }
    if ($temp_Other != array()) {
        array_push($graph_data, $temp_Other);
    }
}


//echo "<pre>";
//print_r($graph_data);die();

function CheckState($array)
{
    if (array_key_exists(1, $array)) {

        if (end($array)["Outbound"] >= 90 OR end($array)["Inbound"] >= 90) {
            echo "state-2";
        } elseif (end($array)["Outbound"] >= 80 OR end($array)["Inbound"] >= 80) {
            echo "state-1";
        }
    }
}

?>
    <div class="row">
        <div class="col-sm-3 col-xs-12">
            <div class="card <?php CheckState($graph_data[1]) ?>">
                <div class="card-header">
                    <div class="card-title">
                        <div class="title">
                            <div class="col-sm-8" id="network_title_1">CE-KH Prim.</div>
                            <div class="col-sm-2 green"><?php if (array_key_exists(1, $graph_data[1])) {
                                    echo end($graph_data[1])["Outbound"] . "%";
                                } ?></div>
                            <div class="col-sm-2 red"><?php if (array_key_exists(1, $graph_data[1])) {
                                    echo end($graph_data[1])["Inbound"] . "%";
                                } ?></div>
                        </div>
                    </div>
                </div>
                <div class="card-body no-padding">
                    <canvas id="network1" class="chart" width="478" height="260"
                            style="width: 478px; height: 260px;"></canvas>
                </div>
            </div>
        </div>
        <div class="col-sm-3 col-xs-12">
            <div class="card <?php CheckState($graph_data[2]) ?>">
                <div class="card-header">
                    <div class="card-title">
                        <div class="title">
                            <div class="col-sm-8" id="network_title_2">MPLS</div>
                            <div class="col-sm-2 green"><?php if (array_key_exists(1, $graph_data[2])) {
                                    echo end($graph_data[2])["Outbound"] . "%";
                                } ?></div>
                            <div class="col-sm-2 red"><?php if (array_key_exists(1, $graph_data[2])) {
                                    echo end($graph_data[2])["Inbound"] . "%";
                                } ?></div>
                        </div>
                    </div>
                </div>
                <div class="card-body no-padding">
                    <canvas id="network2" class="chart" width="478" height="260"
                            style="width: 478px; height: 260px;"></canvas>
                </div>
            </div>
        </div>
        <div class="col-sm-3 col-xs-12">
            <div class="card <?php CheckState($graph_data[3]) ?>">
                <div class="card-header">
                    <div class="card-title">
                        <div class="title">
                            <div class="col-sm-8" id="network_title_3">Internet KH</div>
                            <div class="col-sm-2 green"><?php if (array_key_exists(1, $graph_data[3])) {
                                    echo end($graph_data[3])["Outbound"] . "%";
                                } ?></div>
                            <div class="col-sm-2 red"><?php if (array_key_exists(1, $graph_data[3])) {
                                    echo end($graph_data[3])["Inbound"] . "%";
                                } ?></div>
                        </div>
                    </div>
                </div>
                <div class="card-body no-padding">
                    <canvas id="network3" class="chart" width="478" height="260"
                            style="width: 478px; height: 260px;"></canvas>
                </div>
            </div>
        </div>
        <div class="col-sm-3 col-xs-12">
            <div class="card <?php CheckState($graph_data[4]) ?>">
                <div class="card-header">
                    <div class="card-title">
                        <div class="title">
                            <div class="col-sm-8" id="network_title_4">Internet eCom</div>
                            <div class="col-sm-2 green"><?php if (array_key_exists(1, $graph_data[4])) {
                                    echo end($graph_data[4])["Outbound"] . "%";
                                } ?></div>
                            <div class="col-sm-2 red"><?php if (array_key_exists(1, $graph_data[4])) {
                                    echo end($graph_data[4])["Inbound"] . "%";
                                } ?></div>
                        </div>
                    </div>
                </div>
                <div class="card-body no-padding">
                    <canvas id="network4" class="chart" width="478" height="260"
                            style="width: 478px; height: 260px;"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-sm-3 col-xs-12">
            <div class="card <?php CheckState($graph_data[5]) ?>">
                <div class="card-header">
                    <div class="card-title">
                        <div class="title">
                            <div class="col-sm-8" id="network_title_5"></div>
                            <div class="col-sm-2 green"><?php if (array_key_exists(1, $graph_data[5])) {
                                    echo end($graph_data[5])["Outbound"] . "%";
                                } ?></div>
                            <div class="col-sm-2 red"><?php if (array_key_exists(1, $graph_data[5])) {
                                    echo end($graph_data[5])["Inbound"] . "%";
                                } ?></div>
                        </div>
                    </div>
                </div>
                <div class="card-body no-padding">
                    <canvas id="network5" class="chart" width="478" height="260"
                            style="width: 478px; height: 260px;"></canvas>
                </div>
            </div>
        </div>
        <div class="col-sm-3 col-xs-12">
            <div class="card <?php CheckState($graph_data[6]) ?>">
                <div class="card-header">
                    <div class="card-title">
                        <div class="title">
                            <div class="col-sm-8" id="network_title_6"></div>
                            <div class="col-sm-2 green"><?php if (array_key_exists(1, $graph_data[6])) {
                                    echo end($graph_data[6])["Outbound"] . "%";
                                } ?></div>
                            <div class="col-sm-2 red"><?php if (array_key_exists(1, $graph_data[6])) {
                                    echo end($graph_data[6])["Inbound"] . "%";
                                } ?></div>
                        </div>
                    </div>
                </div>
                <div class="card-body no-padding">
                    <canvas id="network6" class="chart" width="478" height="260"
                            style="width: 478px; height: 260px;"></canvas>
                </div>
            </div>
        </div>
        <div class="col-sm-3 col-xs-12">
            <div class="card <?php CheckState($graph_data[7]) ?>">
                <div class="card-header">
                    <div class="card-title">
                        <div class="title">
                            <div class="col-sm-8" id="network_title_7"></div>
                            <div class="col-sm-2 green"><?php if (array_key_exists(1, $graph_data[7])) {
                                    echo end($graph_data[7])["Outbound"] . "%";
                                } ?></div>
                            <div class="col-sm-2 red"><?php if (array_key_exists(1, $graph_data[7])) {
                                    echo end($graph_data[7])["Inbound"] . "%";
                                } ?></div>
                        </div>
                    </div>
                </div>
                <div class="card-body no-padding">
                    <canvas id="network7" class="chart" width="478" height="260"
                            style="width: 478px; height: 260px;"></canvas>
                </div>
            </div>
        </div>
        <div class="col-sm-3 col-xs-12">
            <div class="card <?php CheckState($graph_data[8]) ?>">
                <div class="card-header">
                    <div class="card-title">
                        <div class="title">
                            <div class="col-sm-8" id="network_title_8"></div>
                            <div class="col-sm-2 green"><?php if (array_key_exists(1, $graph_data[8])) {
                                    echo end($graph_data[8])["Outbound"] . "%";
                                } ?></div>
                            <div class="col-sm-2 red"><?php if (array_key_exists(1, $graph_data[8])) {
                                    echo end($graph_data[8])["Inbound"] . "%";
                                } ?></div>
                        </div>
                    </div>
                </div>
                <div class="card-body no-padding">
                    <canvas id="network8" class="chart" width="478" height="260"
                            style="width: 478px; height: 260px;"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-sm-3 col-xs-12">
            <div class="card <?php CheckState($graph_data[9]) ?>">
                <div class="card-header">
                    <div class="card-title">
                        <div class="title">
                            <div class="col-sm-8" id="network_title_9"></div>
                            <div class="col-sm-2 green"><?php if (array_key_exists(1, $graph_data[9])) {
                                    echo end($graph_data[9])["Outbound"] . "%";
                                } ?></div>
                            <div class="col-sm-2 red"><?php if (array_key_exists(1, $graph_data[9])) {
                                    echo end($graph_data[9])["Inbound"] . "%";
                                } ?></div>
                        </div>
                    </div>
                </div>
                <div class="card-body no-padding">
                    <canvas id="network9" class="chart" width="478" height="260"
                            style="width: 478px; height: 260px;"></canvas>
                </div>
            </div>
        </div>
        <div class="col-sm-3 col-xs-12">
            <div class="card <?php CheckState($graph_data[10]) ?>">
                <div class="card-header">
                    <div class="card-title">
                        <div class="title">
                            <div class="col-sm-8" id="network_title_10"></div>
                            <div class="col-sm-2 green"><?php if (array_key_exists(1, $graph_data[10])) {
                                    echo end($graph_data[10])["Outbound"] . "%";
                                } ?></div>
                            <div class="col-sm-2 red"><?php if (array_key_exists(1, $graph_data[10])) {
                                    echo end($graph_data[10])["Inbound"] . "%";
                                } ?></div>
                        </div>
                    </div>
                </div>
                <div class="card-body no-padding">
                    <canvas id="network10" class="chart" width="478" height="260"
                            style="width: 478px; height: 260px;"></canvas>
                </div>
            </div>
        </div>
        <div class="col-sm-3 col-xs-12">
            <div class="card <?php CheckState($graph_data[11]) ?>">
                <div class="card-header">
                    <div class="card-title">
                        <div class="title">
                            <div class="col-sm-8" id="network_title_11"></div>
                            <div class="col-sm-2 green"><?php if (array_key_exists(1, $graph_data[11])) {
                                    echo end($graph_data[11])["Outbound"] . "%";
                                } ?></div>
                            <div class="col-sm-2 red"><?php if (array_key_exists(1, $graph_data[11])) {
                                    echo end($graph_data[11])["Inbound"] . "%";
                                } ?></div>
                        </div>
                    </div>
                </div>
                <div class="card-body no-padding">
                    <canvas id="network11" class="chart" width="478" height="260"
                            style="width: 478px; height: 260px;"></canvas>
                </div>
            </div>
        </div>
        <div class="col-sm-3 col-xs-12">
            <div class="card <?php CheckState($graph_data[12]) ?>">
                <div class="card-header">
                    <div class="card-title">
                        <div class="title">
                            <div class="col-sm-8" id="network_title_12"></div>
                            <div class="col-sm-2 green"><?php if (array_key_exists(1, $graph_data[12])) {
                                    echo end($graph_data[12])["Outbound"] . "%";
                                } ?></div>
                            <div class="col-sm-2 red"><?php if (array_key_exists(1, $graph_data[12])) {
                                    echo end($graph_data[12])["Inbound"] . "%";
                                } ?></div>
                        </div>
                    </div>
                </div>
                <div class="card-body no-padding">
                    <canvas id="network12" class="chart" width="478" height="260"
                            style="width: 478px; height: 260px;"></canvas>
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
for ($i = 1; $i <= 12; $i++) {
    echo "<!-- Network $i --> ";
    echo '<script type="text/javascript">';
    if ($i > 4) {
        if (array_key_exists(1, $graph_data[$i])) {
            echo '$("#network_title_' . $i . '").text("' . $graph_data[$i][0]["Name"] . '")';
        }
    }
    echo "
            ctx = $('#network$i').get(0).getContext('2d');
            var data = {
            labels: [";
    $data = "";
    foreach ($graph_data[$i] as $value) {
        $data .= "'" . $value["Timestamp"]->format("H:i") . "',";
    }
    $data = substr($data, 0, -1);
    echo $data;
    echo "],
                datasets: [
                    {
                        label: \"Outbound in %\",
                        backgroundColor: [
                            'rgba(146,200,66,0.2)'
                        ],
                        borderColor: [
                            'rgba(146,200,66,1)'
                        ],
                        borderWidth: 1,
                        data: [";
    $data = "";
    foreach ($graph_data[$i] as $value) {
        $data .= $value["Outbound"] . ",";
    }
    $data = substr($data, 0, -1);
    echo $data;
    echo "]
                    },{
                        label: \"Inbound in %\",
                        backgroundColor: [
                            'rgba(242, 38, 19,0.2)'
                        ],
                        borderColor: [
                            'rgba(242, 38, 19,1)'
                        ],
                        borderWidth: 1,
                        data: [";
    $data = "";
    foreach ($graph_data[$i] as $value) {
        $data .= $value["Inbound"] . ",";
    }
    $data = substr($data, 0, -1);
    echo $data;

    echo "]}]};";
    echo "var chartInstance" . $i . " = new Chart(ctx, {type: 'line',data: data,options: options})";
    //echo "var LineChart$i = new Chart(ctx).Line(data,options);";
    echo '</script>';
}
?>