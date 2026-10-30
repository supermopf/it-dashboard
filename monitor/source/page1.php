<?php
/**
 * Created by PhpStorm.
 * User: victor.lange
 * Date: 05.04.2016
 * Time: 14:13
 */
//
// Temperaturen der Rechenzentren + Wetter + Regenradar
//
// Temperaturen: direkt aus OMD/Checkmk (OMD_TEMPERATURE_SENSORS, aktueller Status + Verlauf der letzten 2 h aus
// den RRDs). Ohne diese Konfiguration wie bisher aus der Tabelle IT-Dashboard_APC-Temperature.
// Wetter: lib/weather.php (Yahoo über RapidAPI, sonst OpenWeatherMap). Radar: Kacheln lädt der Browser direkt.
//
require("../../config.php");
require_once(__DIR__ . '/../../lib/security.php');
require_once(__DIR__ . '/../../lib/checkmk.php');
require_once(__DIR__ . '/../../lib/weather.php');

$RangeEnd = time();
$RangeStart = $RangeEnd - 2 * 3600;

/*
 * $Sensors: Liste von
 *   name, temp, humidity, state (0 OK, 1 WARN, 2 CRIT, 3 UNKNOWN), series (ts => [temp, humidity])
 */
$Sensors = array();

if (defined('OMD_TEMPERATURE_SENSORS') && is_array(OMD_TEMPERATURE_SENSORS) && checkmk_configured()) {
    // Status aller beteiligten Dienste mit einer Abfrage
    $exprs = array();
    foreach (OMD_TEMPERATURE_SENSORS as $cfg) {
        foreach (array('temperature', 'humidity') as $kind) {
            if (!empty($cfg[$kind])) {
                $exprs[] = array('op' => 'and', 'expr' => array(
                    checkmk_filter('host_name', '=', $cfg[$kind . '_host'] ?? $cfg['host']),
                    checkmk_filter('description', '=', $cfg[$kind]),
                ));
            }
        }
    }
    $states = array();
    foreach (checkmk_services(array('op' => 'or', 'expr' => $exprs), array('host_name', 'description', 'state')) ?? array() as $svc) {
        $states[$svc['host_name'] . '|' . $svc['description']] = (int)$svc['state'];
    }

    foreach (OMD_TEMPERATURE_SENSORS as $name => $cfg) {
        $series = array();
        $state = null;
        foreach (array('temperature' => 0, 'humidity' => 1) as $kind => $col) {
            if (empty($cfg[$kind])) {
                continue;
            }
            $host = $cfg[$kind . '_host'] ?? $cfg['host'];
            $metric = $cfg[$kind . '_metric'] ?? ($kind === 'temperature' ? 'temp' : 'humidity');
            foreach (checkmk_metric_history($host, $cfg[$kind], $metric, $RangeStart, $RangeEnd) ?? array() as $point) {
                if ($point[1] !== null) {
                    $series[$point[0]][$col] = $point[1];
                }
            }
            $key = $host . '|' . $cfg[$kind];
            if (isset($states[$key])) {
                $state = max($state ?? 0, $states[$key]);
            }
        }
        ksort($series);
        $Sensors[] = array('name' => (string)$name, 'state' => $state ?? 3, 'series' => $series);
    }
} else {
    $conn = sqlsrv_connect(DASHBOARD_SQL_INSTANCE, $Default_Connection);
    if (!$conn) {
        echo "Connection could not be established.<br /><pre>";
        die(print_r(sqlsrv_errors(), true));
    }
    $query = "
    SELECT *
    FROM [IT-Dashboard].[dbo].[IT-Dashboard_APC-Temperature]
    WHERE [Timestamp] >= DATEADD(Minute, -120,GETDATE())
    AND [Location] IN ('GerberEG', 'GerberOG', 'Street One', 'CECIL')
    ORDER BY [Location] ASC, [Timestamp] ASC
    ";
    if (!($result = sqlsrv_query($conn, $query))) {
        echo "<pre>";
        die(print_r(sqlsrv_errors(), true));
    }
    $LocationData = array();
    while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
        $LocationData[$row['Location']][] = $row;
    }
    $Locations = array(
        "CECIL" => "CECIL",
        "GerberOG" => "Gerberstraße OG",
        "Street One" => "Street One",
        "GerberEG" => "Gerberstraße EG",
    );
    foreach ($Locations as $key => $displayname) {
        $series = array();
        $state = 3;
        foreach ($LocationData[$key] ?? array() as $row) {
            $series[$row['Timestamp']->getTimestamp()] = array((float)$row['Temperature'], (float)$row['Humidity']);
            $state = (int)$row['StatusCode'];
        }
        $Sensors[] = array('name' => $displayname, 'state' => $state, 'series' => $series);
    }
}

// Aktuelle Werte = letzter vorhandener Messpunkt; Achsengrenzen aus den Daten (Seite wird vorgerendert)
$AxisMin = null;
$AxisMax = null;
foreach ($Sensors as &$sensor) {
    $sensor['temp'] = null;
    $sensor['humidity'] = null;
    foreach ($sensor['series'] as $ts => $values) {
        if (isset($values[0])) {
            $sensor['temp'] = $values[0];
        }
        if (isset($values[1])) {
            $sensor['humidity'] = $values[1];
        }
        $AxisMin = $AxisMin === null ? $ts : min($AxisMin, $ts);
        $AxisMax = $AxisMax === null ? $ts : max($AxisMax, $ts);
    }
}
unset($sensor);
$AxisMin = ($AxisMin ?? $RangeStart) * 1000;
$AxisMax = ($AxisMax ?? $RangeEnd) * 1000;

function fmt_num($value, int $decimals = 1): string
{
    return $value === null ? '–' : number_format((float)$value, $decimals, ',', '.');
}

$Weather = weather_current();
$Radar = weather_radar();
$Loc = weather_location();

// Karte: 5 × 5 Kacheln, der Standort liegt in der Mitte des sichtbaren Ausschnitts
$Zoom = defined('WEATHER_RADAR_ZOOM') ? (int)WEATHER_RADAR_ZOOM : 7;
list($TileX, $TileY) = weather_tile_coords($Loc['lat'], $Loc['lon'], $Zoom);
$Tx0 = (int)floor($TileX) - 2;
$Ty0 = (int)floor($TileY) - 2;
$OffX = round($TileX - $Tx0, 4);
$OffY = round($TileY - $Ty0, 4);
$BaseUrl = 'https://services.arcgisonline.com/arcgis/rest/services/Canvas/World_Dark_Gray_Base/MapServer/tile/{z}/{y}/{x}';
$LabelUrl = 'https://services.arcgisonline.com/arcgis/rest/services/Canvas/World_Dark_Gray_Reference/MapServer/tile/{z}/{y}/{x}';

function map_tiles(string $template, int $z, int $x0, int $y0, string $class): string
{
    $html = '<div class="p1-layer ' . $class . '">';
    for ($y = $y0; $y < $y0 + 5; $y++) {
        for ($x = $x0; $x < $x0 + 5; $x++) {
            $src = str_replace(array('{z}', '{x}', '{y}'), array($z, $x, $y), $template);
            $html .= '<img src="' . h($src) . '" alt="" loading="eager">';
        }
    }
    return $html . '</div>';
}
?>
<style>
    .p1 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: var(--space-4, 16px);
        height: 100%;
        min-height: 0;
    }
    .p1 .card { margin: 0; }
    .p1-left {
        display: flex;
        flex-direction: column;
        gap: var(--space-4, 16px);
        min-height: 0;
    }
    .p1-weather .card-body {
        display: flex;
        align-items: center;
        gap: var(--space-5, 24px);
    }
    .p1-weather img { height: 6rem; width: auto; flex: none; }
    .p1-weather-main { flex: 1; min-width: 0; }
    .p1-weather-temp {
        font-size: var(--fs-kpi, 60px);
        font-weight: var(--fw-semibold, 600);
        line-height: 1;
        letter-spacing: -0.02em;
        font-variant-numeric: tabular-nums;
    }
    .p1-weather-text {
        margin-top: var(--space-2, 8px);
        font-size: var(--fs-lg, 22px);
        color: var(--color-text-secondary, #a3acb9);
    }
    .p1-weather-meta {
        display: grid;
        grid-template-columns: auto auto;
        gap: var(--space-1, 4px) var(--space-4, 16px);
        font-size: var(--fs-md, 17px);
        color: var(--color-text-secondary, #a3acb9);
        font-variant-numeric: tabular-nums;
    }
    .p1-weather-meta b { color: var(--color-text, #e9edf2); font-weight: var(--fw-medium, 500); text-align: right; }
    .p1-sensors {
        flex: 1;
        min-height: 0;
        display: grid;
        grid-template-columns: 1fr 1fr;
        grid-auto-rows: 1fr;
        gap: var(--space-4, 16px);
    }
    .p1-sensors .card { display: flex; flex-direction: column; min-height: 0; }
    .p1-sensors .card-body { flex: 1; min-height: 0; position: relative; }
    .p1-chart { position: absolute; inset: var(--space-2, 8px); }
    .p1-map { display: flex; flex-direction: column; min-height: 0; }
    .p1-map .card-title .title { display: flex; justify-content: space-between; gap: var(--space-4, 16px); }
    .p1-map .card-title .when { color: var(--color-text-secondary, #a3acb9); font-weight: var(--fw-regular, 400); }
    .p1-map-body {
        --p1-tile: 16rem;
        position: relative;
        flex: 1;
        min-height: 0;
        overflow: hidden;
        background: #1b1f24;
    }
    .p1-tiles {
        position: absolute;
        width: calc(5 * var(--p1-tile));
        height: calc(5 * var(--p1-tile));
        left: calc(50% - <?= $OffX ?> * var(--p1-tile));
        top: calc(50% - <?= $OffY ?> * var(--p1-tile));
    }
    .p1-layer {
        position: absolute;
        inset: 0;
        display: grid;
        grid-template-columns: repeat(5, var(--p1-tile));
        grid-template-rows: repeat(5, var(--p1-tile));
    }
    .p1-layer img { width: 100%; height: 100%; display: block; }
    .p1-radar { opacity: 0.8; }
    .p1-pin {
        position: absolute;
        left: 50%;
        top: 50%;
        transform: translate(-50%, -50%);
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: var(--space-1, 4px);
        pointer-events: none;
    }
    .p1-pin::before {
        content: '';
        width: 0.875rem;
        height: 0.875rem;
        border-radius: 50%;
        background: var(--color-accent, #5b9bff);
        box-shadow: 0 0 0 3px rgba(11, 14, 18, 0.85);
    }
    .p1-pin span {
        padding: 0 var(--space-2, 8px);
        border-radius: var(--radius-pill, 999px);
        background: rgba(11, 14, 18, 0.75);
        font-size: var(--fs-sm, 15px);
        font-weight: var(--fw-semibold, 600);
    }
    .p1-map-note {
        position: absolute;
        left: var(--space-3, 12px);
        bottom: var(--space-3, 12px);
        padding: var(--space-1, 4px) var(--space-3, 12px);
        border-radius: var(--radius-sm, 8px);
        background: rgba(11, 14, 18, 0.75);
        color: var(--color-text-secondary, #a3acb9);
        font-size: var(--fs-xs, 13px);
    }
    /* Die Kacheln laden extern – fehlt eine, bleibt die dunkle Fläche (Platz im Raster bleibt erhalten) */
    .p1-layer .img-missing { visibility: hidden; min-height: 0; }
</style>
<div class="p1">
    <div class="p1-left">
        <div class="card p1-weather">
            <div class="card-body">
                <?php if ($Weather): ?>
                    <img src="<?= h('./img/weather/' . preg_replace('/[^a-z0-9]/i', '', (string)$Weather['icon']) . '.png') ?>" alt=""/>
                    <div class="p1-weather-main">
                        <div class="p1-weather-temp"><?= fmt_num($Weather['temp']) ?> °C</div>
                        <div class="p1-weather-text"><?= h($Weather['city']) ?> · <?= h($Weather['text']) ?></div>
                    </div>
                    <div class="p1-weather-meta">
                        <?php if ($Weather['high'] !== null): ?><span>Hoch / Tief</span><b><?= (int)$Weather['high'] ?>° / <?= (int)$Weather['low'] ?>°</b><?php endif; ?>
                        <?php if ($Weather['humidity'] !== null): ?><span>Luftfeuchte</span><b><?= (int)$Weather['humidity'] ?> %</b><?php endif; ?>
                        <?php if ($Weather['wind'] !== null): ?><span>Wind</span><b><?= (int)$Weather['wind'] ?> km/h</b><?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="p1-weather-main">
                        <div class="p1-weather-text">Wetterdaten derzeit nicht verfügbar</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="p1-sensors">
            <?php foreach ($Sensors as $i => $sensor): ?>
                <?php // Checkmk: 0 OK, 1 WARN, 2 CRIT, 3 UNKNOWN (→ Warnung) ?>
                <div class="card state-<?= (int)$sensor['state'] === 3 ? 1 : (int)$sensor['state'] ?>">
                    <div class="card-header">
                        <div class="card-title">
                            <div class="title">
                                <div class="col-sm-8"><?= h($sensor['name']) ?></div>
                                <div class="col-sm-2 blue"><?= fmt_num($sensor['humidity'], 0) ?> %</div>
                                <div class="col-sm-2 red"><?= fmt_num($sensor['temp']) ?> °C</div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body no-padding">
                        <div class="p1-chart"><canvas id="location<?= $i ?>" class="chart"></canvas></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="card p1-map">
        <div class="card-header">
            <div class="card-title">
                <div class="title">
                    <span>Regenradar</span>
                    <span class="when"><?= $Radar ? 'Stand ' . date('H:i', (int)$Radar['time']) : '' ?></span>
                </div>
            </div>
        </div>
        <div class="p1-map-body">
            <div class="p1-tiles">
                <?= map_tiles($BaseUrl, $Zoom, $Tx0, $Ty0, 'p1-base') ?>
                <?php if ($Radar): ?><?= map_tiles($Radar['url'], $Zoom, $Tx0, $Ty0, 'p1-radar') ?><?php endif; ?>
                <?= map_tiles($LabelUrl, $Zoom, $Tx0, $Ty0, 'p1-labels') ?>
            </div>
            <div class="p1-pin"><span><?= h($Loc['name']) ?></span></div>
            <div class="p1-map-note">
                <?= $Radar ? 'Radar: RainViewer' : 'Radardaten derzeit nicht verfügbar' ?>
                <?= $Weather ? ' · Wetter: ' . h($Weather['source']) : '' ?>
            </div>
        </div>
    </div>
</div>
<script>
    var minDate = new Date(<?= (int)$AxisMin ?>);
    var maxDate = new Date(<?= (int)$AxisMax ?>);

    var options = {
        responsive: true,
        maintainAspectRatio: false,
        legend: {
            display: false
        },
        scales: {
            xAxes: [{
                type: 'time',
                time: {
                    unit: 'minute',
                    min: minDate,
                    max: maxDate,
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
        layout: {
            padding: { left: 0, right: 12 }
        },
        borderCapStyle: 'butt',
        lineTension: 0.1,
        spanGaps: true
    };
</script>
<?php
//Für jedes Diagramm
foreach ($Sensors as $i => $sensor) {
    $labels = array();
    $temperature_data = array();
    $humidity_data = array();
    foreach ($sensor['series'] as $ts => $values) {
        $labels[] = date('c', $ts);
        $temperature_data[] = $values[0] ?? null;
        $humidity_data[] = $values[1] ?? null;
    }
    ?>
    <!-- Klima <?= h($sensor['name']) ?> -->
    <script type="text/javascript">
        ctx = $('#location<?= $i ?>').get(0).getContext('2d');
        var data = {
            labels: <?= json_for_html($labels) ?>,
            datasets: [
                {
                    pointRadius: 0,
                    label: "Temperatur in °C",
                    backgroundColor: 'rgba(199, 13, 59, 0.2)',
                    borderColor: 'rgba(199, 13, 58, 1)',
                    borderWidth: 1.5,
                    data: <?= json_for_html($temperature_data) ?>
                }, {
                    pointRadius: 0,
                    label: "Luftfeuchtigkeit in %",
                    backgroundColor: 'rgba(69, 150, 155, 0.2)',
                    borderColor: 'rgba(69, 150, 155, 1)',
                    borderWidth: 1.5,
                    data: <?= json_for_html($humidity_data) ?>
                }
            ]
        };
        var chartInstance<?= $i ?> = new Chart(ctx, {type: 'line', data: data, options: options});
    </script>
    <?php
}
?>
