<!-- ESX Nodes START -->
<!-- VMware -->
<?php
require("../../config.php");
$conn = sqlsrv_connect(DASHBOARD_SQL_INSTANCE, $Default_Connection);

$query = "";
$query .= "SELECT ";
$query .= "	Highest.[Hostname], ";
$query .= "  [CPU], ";
$query .= "  [RAM], ";
$query .= "  [Description], ";
$query .= "  [Guests], ";
$query .= "  [Timestamp] ";
$query .= "FROM ";
$query .= "( ";
$query .= "	SELECT TOP 12 [Hostname],ROW_NUMBER() OVER (ORDER BY (SELECT NULL)) AS Rank ";
$query .= "	FROM [IT-Dashboard].[dbo].[IT-Dashboard_VMWARE] ";
$query .= "	WHERE[Timestamp] > DATEADD(MINUTE, -30, GETDATE()) ";
$query .= "	GROUP BY [Hostname] ";
$query .= "	ORDER BY AVG([CPU]) DESC ";
$query .= ") Highest ";
$query .= "JOIN [IT-Dashboard].[dbo].[IT-Dashboard_VMWARE] AS Perf ON Perf.[Hostname] = Highest.[Hostname] ";
$query .= "WHERE[Timestamp] > DATEADD(MINUTE, -30, GETDATE()) ";
$query .= "ORDER BY Highest.Rank,[Timestamp]";


if ($result = sqlsrv_query($conn, $query)) {
    $LastHostname = "";
    $temp_array = array();
    $vmware = array();
    while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
        if ($LastHostname != $row["Hostname"]) {
            $vmware[] = $temp_array;
            $temp_array = array();
            $temp_array[] = $row;
            $LastHostname = $row["Hostname"];
        } else {
            $temp_array[] = $row;
        }
    }
    $vmware[] = $temp_array;
} else {
    echo "<pre>";
    die(print_r(sqlsrv_errors(), true));
}


//        echo "<pre>";
//        print_R($vmware);

$count = count($vmware) - 1;


function CheckState($array)
{
    if (array_key_exists(1, $array)) {
        if (end($array)["CPU"] >= 90) {
            return "state-2";
        } elseif (end($array)["CPU"] >= 80) {
            return "state-1";
        }
    }
}

echo '<div class="row">';
echo '<div class="col-lg-12">';

for ($i = 1; $i <= $count; $i++) {
    echo '<div class="col-sm-3 col-xs-12">';
    echo '<div class="card ' . CheckState($vmware[$i]) . '">';
    echo '<div class="card-header">';
    echo '<div class="card-title">';
    echo '<div class="title">';
    echo '<div class="col-sm-8" id="' . $vmware[$i][0]["Hostname"] . '">' . $vmware[$i][0]["Hostname"] . '</div>';
    echo '<div class="col-sm-2" style="' . PRIMARY_STYLE . '">';
    if (array_key_exists(1, $vmware[$i])) {
        echo end($vmware[$i])["CPU"] . "%";
    }
    echo '</div>';
    echo '<div class="col-sm-2" style="' . SECONDARY_STYLE . '">';
    if (array_key_exists(1, $vmware[$i])) {
        echo end($vmware[$i])["RAM"] . "%";
    }
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '<div class="card-body no-padding">';
    echo '<canvas id="vmware' . $i . '" class="chart"></canvas>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    if ($i % 4 == 0) {
        echo '<!-- DIV -->';
        echo '</div>';
        echo '<!-- ROW END -->';
        echo '</div>';
        if ($count != $i) {
            echo '<div class="row">';
            echo '<div class="col-lg-12">';
        }
    }
}
?>


<script>
    var options = {
        scales: {
            xAxes: [{
                ticks: {
                    fontSize: 15,
                    maxTicksLimit: 20
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
for ($i = 1; $i <= $count; $i++) {
    echo PHP_EOL . "<!-- Vmware $i --> ";
    echo '<script type="text/javascript">';
    echo '
                            ctx = $("#vmware' . $i . '").get(0).getContext("2d");
                            var data = {
                            labels: [';
    $labels = "";
    foreach ($vmware[$i] as $key => $value) {
        if ($vmware[$i][$key]["Hostname"] === $vmware[$i][0]["Hostname"]) {
            $dateObj = $vmware[$i][$key]["Timestamp"];
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
                                backgroundColor: "' . Pri_Color . '",
                                borderColor: "' . Pri_Color_Border . '",
                                pointRadius: 1,
                                borderWidth: 1,
                                data: [';
    $data = "";
    foreach ($vmware[$i] as $key => $value) {
        if ($vmware[$i][$key]['Hostname'] === $vmware[$i][0]['Hostname']) {
            $data .= $vmware[$i][$key]['CPU'] . ',';
        }
    }
    echo substr($data, 0, -1);
    echo ']
                            },{
                                label: "RAM Auslastung in %",
                                backgroundColor: "' . Sec_Color . '",
                                borderColor: "' . Sec_Color_Border . '",
                                borderWidth: 1,
                                pointRadius: 1,
                                data: [';
    $data = '';
    foreach ($vmware[$i] as $key => $value) {

        if ($vmware[$i][$key]['Hostname'] === $vmware[$i][0]['Hostname']) {
            $data .= $vmware[$i][$key]['RAM'] . ',';
        }
    }
    echo substr($data, 0, -1);

    echo ']}]};' . PHP_EOL;

    echo "var chartInstance" . $i . ' = new Chart(ctx, {type: \'line\',data: data, options: options})';
    echo '</script>';
}
?>
</div>
</div>
<!-- ESX Nodes END -->