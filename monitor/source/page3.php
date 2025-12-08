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

$query = "
WITH Ranked AS (
    SELECT
        Highest.[Name],
        Highest.Rank,
        Perf.[CPU],
        Perf.[RAM],
        Perf.[Timestamp],
        LAG(Perf.[CPU]) OVER (PARTITION BY Highest.[Name] ORDER BY Perf.[Timestamp]) AS PrevCPU,
        LAG(Perf.[RAM]) OVER (PARTITION BY Highest.[Name] ORDER BY Perf.[Timestamp]) AS PrevRAM,
        LEAD(Perf.[CPU]) OVER (PARTITION BY Highest.[Name] ORDER BY Perf.[Timestamp]) AS NextCPU,
        LEAD(Perf.[RAM]) OVER (PARTITION BY Highest.[Name] ORDER BY Perf.[Timestamp]) AS NextRAM,
        ROW_NUMBER() OVER (PARTITION BY Highest.[Name] ORDER BY Perf.[Timestamp]) AS RowAsc,
        ROW_NUMBER() OVER (PARTITION BY Highest.[Name] ORDER BY Perf.[Timestamp] DESC) AS RowDesc
    FROM (
        SELECT TOP 12 
            [Name], 
            ROW_NUMBER() OVER (ORDER BY (SELECT NULL)) AS Rank
        FROM [IT-Dashboard].[dbo].[IT-Dashboard_Performance]
        WHERE [Timestamp] > DATEADD(MINUTE, -120, GETDATE())
        GROUP BY [Name]
        ORDER BY AVG([CPU]) DESC
    ) Highest
    JOIN [IT-Dashboard].[dbo].[IT-Dashboard_Performance] AS Perf 
        ON Perf.[Name] = Highest.[Name]
    WHERE Perf.[Timestamp] > DATEADD(MINUTE, -120, GETDATE())
)
SELECT DISTINCT
	Rank,
    [Name],
    [CPU],
    [RAM],
    [Timestamp]
FROM Ranked
WHERE RowAsc = 1                       -- first row
   OR RowDesc = 1                      -- latest row
   OR PrevCPU <> CPU OR PrevRAM <> RAM -- row where change happens
   OR NextCPU <> CPU OR NextRAM <> RAM -- row before change
ORDER BY Rank, [Timestamp];
";

if ($result = sqlsrv_query($conn, $query)) {
    $servers = [];
    while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
        if (!isset($servers[$row['Name']])) {
            $servers[$row['Name']] = [];
        }
        $servers[$row['Name']][] = $row;
    }
} else {
    echo "<pre>";
    die(print_r(sqlsrv_errors(), true));
}

function CheckState($name, $servers)
{
    if (array_key_exists($name, $servers)) {
        if (end($servers[$name])["CPU"] >= 90) {
            return "state-2";
        } elseif (end($servers[$name])["CPU"] >= 80) {
            return "state-1";
        }
    }
}
?>
<div class="row">
<div class="col-lg-12">
<?php
$i = 1;
$total = count($servers);

foreach ($servers as $name => $data) {
    $latest   = end($data); // last row for CPU/RAM
    ?>
    <div class="col-sm-3 col-xs-12">
        <div class="card <?= CheckState($name, $servers) ?>">
            <div class="card-header">
                <div class="card-title">
                    <div class="title">
                        <div class="col-sm-8" id="<?= $name ?>"><?= $name ?></div>
                        <div class="col-sm-2" style="<?= PRIMARY_STYLE ?>"><?= $latest['CPU'] ?>%</div>
                        <div class="col-sm-2" style="<?= SECONDARY_STYLE ?>"><?= $latest['RAM'] ?>%</div>
                    </div>
                </div>
            </div>
            <div class="card-body no-padding">
                <canvas id="perf-<?= $name ?>" class="chart"></canvas>
            </div>
        </div>
    </div>
    <?php

    if ($i % 4 === 0) {
        echo '</div></div>';
        if ($i < $total) {
            echo '<div class="row"><div class="col-lg-12">';
        }
    }

    $i++;
}
?>

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
        lineTension: 0,
        spanGaps: true
    };
</script>
<?php
$i = 1;

foreach ($servers as $name => $data) {
    // Prepare data arrays
    $timestamp_labels = array_map(function($row) {
        return $row['Timestamp']->format('c');
    }, $data);

    $cpu_data = array_column($data, 'CPU');
    $ram_data = array_column($data, 'RAM');
    ?>
    <!-- Perf <?= $name ?> -->
    <script type="text/javascript">
        const ctx<?= $i ?> = $("#perf-<?= $name ?>").get(0).getContext("2d");
        const data<?= $i ?> = {
            labels: <?= json_encode($timestamp_labels) ?>,
            datasets: [
                {
                    label: "CPU Auslastung in %",
                    backgroundColor: "<?= Pri_Color ?>",
                    borderColor: "<?= Pri_Color_Border ?>",
                    cubicInterpolationMode: "monotone",
                    pointRadius: 0,
                    borderWidth: 1,
                    data: <?= json_encode($cpu_data) ?>
                },
                {
                    label: "RAM Auslastung in %",
                    backgroundColor: "<?= Sec_Color ?>",
                    borderColor: "<?= Sec_Color_Border ?>",
                    cubicInterpolationMode: "monotone",
                    pointRadius: 0,
                    borderWidth: 1,
                    data: <?= json_encode($ram_data) ?>
                }
            ]
        };
        const chartInstance<?= $i ?> = new Chart(ctx<?= $i ?>, {
            type: 'line',
            data: data<?= $i ?>,
            options: options
        });
    </script>
    <?php
    $i++;
}
?>
</div>
</div>
