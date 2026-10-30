<?php
/**
 * Created by PhpStorm.
 * User: victor.lange
 * Date: 05.04.2016
 * Time: 16:12
 */
//
// SCOM-Meldungen, angereichert mit aktuellen Problemen aus OMD/Checkmk (nicht quittiert, nicht in Wartung).
// Ohne Meldungen markiert sich die Seite als nicht relevant und wird in der Rotation übersprungen.
//
// Optional in config.php:
//   OMD_ALERTS_ON_SCOM             false = keine OMD-Meldungen auf dieser Seite (Standard: true, wenn Checkmk konfiguriert)
//   OMD_ALERTS_INCLUDE_WARNINGS    false = nur kritische OMD-Meldungen (Standard: true)
//
require("../../config.php");
require_once(__DIR__ . '/../../lib/security.php');
require_once(__DIR__ . '/../../lib/pages.php');
require_once(__DIR__ . '/../../lib/checkmk.php');
$conn = sqlsrv_connect(DASHBOARD_SQL_INSTANCE, $Default_Connection);

if (!$conn) {
    echo "<pre>";
    die(print_r(sqlsrv_errors(), true));
}
if (!($result = sqlsrv_query($conn, "SELECT * FROM [IT-Dashboard].[dbo].[IT-Dashboard_SCOM_Criticals] ORDER BY Hostname,LastTimeModified"))) {
    echo "<pre>";
    die(print_r(sqlsrv_errors(), true));
}

/** Hostname für die Gruppierung: SCOM liefert teils FQDN, OMD teils Kurznamen. */
function alert_host_key(string $host): string
{
    $host = strtolower(trim($host));
    return preg_match('/^\d+\.\d+\.\d+\.\d+$/', $host) ? $host : explode('.', $host)[0];
}

// Einheitliche Liste: host, severity (1 Warnung, 2 Kritisch), source, text, time (Unix-Zeit|null)
$Alerts = array();
while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
    $modified = $row["LastTimeModified"];
    $Alerts[] = array(
        'host' => alert_host_key((string)$row["Hostname"]),
        'severity' => $row["HealthState"] == "Warning" ? 1 : 2,
        'source' => 'SCOM',
        'text' => trim($row["ObjectDisplayName"] . ' ' . $row["MonitorDisplayName"] . ' (' . $row["OperationalState"] . ')'),
        'time' => $modified instanceof DateTimeInterface ? $modified->getTimestamp() : (strtotime((string)$modified) ?: null),
    );
}

$OmdError = false;
$OmdEnabled = checkmk_configured() && (!defined('OMD_ALERTS_ON_SCOM') || OMD_ALERTS_ON_SCOM);
if ($OmdEnabled) {
    $problems = checkmk_problems(!defined('OMD_ALERTS_INCLUDE_WARNINGS') || OMD_ALERTS_INCLUDE_WARNINGS);
    if ($problems === null) {
        $OmdError = true;
    } else {
        foreach ($problems as $p) {
            $output = trim(preg_replace('/\s+/', ' ', $p['output']));
            $Alerts[] = array(
                'host' => alert_host_key($p['host']),
                // UNKNOWN (3) wie eine Warnung behandeln
                'severity' => $p['state'] === 2 ? 2 : 1,
                'source' => 'OMD',
                'text' => $p['service'] . ($output !== '' ? ' – ' . mb_strimwidth($output, 0, 140, '…') : ''),
                'time' => $p['since'] ?: null,
            );
        }
    }
}

usort($Alerts, function ($a, $b) {
    return array($a['host'], -$a['severity'], $a['time'] ?? 0) <=> array($b['host'], -$b['severity'], $b['time'] ?? 0);
});

echo page_relevance_marker(count($Alerts) > 0);
?>
<style>
    .alert-src {
        display: inline-block;
        min-width: 3.4em;
        margin-right: var(--space-2, 8px);
        padding: 0 var(--space-2, 8px);
        border-radius: var(--radius-pill, 999px);
        background: rgba(255, 255, 255, 0.08);
        color: var(--color-text-secondary, #a3acb9);
        font-size: var(--fs-xs, 13px);
        font-weight: var(--fw-semibold, 600);
        text-align: center;
        vertical-align: 0.1em;
    }
    .alert-src.omd { background: rgba(95, 179, 246, 0.16); color: var(--color-info, #5fb3f6); }
    .alert-sev-2 .icon, .alert-sev-2 .fa { color: var(--color-danger, #f0584f); }
    .alert-sev-1 .icon, .alert-sev-1 .fa { color: var(--color-warning, #e3a535); }
</style>
<div class="row">
    <div class="col-lg-12">
        <?php // Ohne Meldungen bleibt die Seite leer → das Frontend zeigt „Keine Einträge“ ?>
        <?php if ($Alerts): ?>Seite <span id="CurrentPage"></span> von <span id="PageCount"></span><?php endif; ?>
        <?php if ($OmdError): ?><span class="pull-right">OMD derzeit nicht erreichbar – nur SCOM-Meldungen</span><?php endif; ?>
    </div>
</div>
<div class="row">
    <div class="col-lg-12">
        <div id="wrapper">
            <div id="slider1" class="content" style="display: none;">
                <?php
                $allowed = 15;
                $objects = 0;
                $sliderid = 1;
                $lasthostname = null;
                foreach ($Alerts as $alert) {
                    if ($lasthostname !== $alert['host']) {
                        if ($lasthostname !== null) {
                            echo '</div>';
                            if ($objects >= $allowed) {
                                $objects = 0;
                                echo "</div>";
                                $sliderid++;
                                echo "<div class=\"content\" id=\"slider" . $sliderid . "\"  style=\"display: none;\">";
                            }
                        }
                        //New Server
                        $lasthostname = $alert['host'];
                        echo '<a href="#" class="list-group-item active">' . h($alert['host']) . '</a>'; //Title
                        echo '<div class="list-group">';
                        $objects++;
                    }

                    $icon = $alert['severity'] === 1
                        ? "<i class=\"fa fa-exclamation-triangle\" aria-hidden=\"true\"></i>"
                        : "<i class=\"fa fa-times\" aria-hidden=\"true\"></i>";
                    $src = '<span class="alert-src' . ($alert['source'] === 'OMD' ? ' omd' : '') . '">' . $alert['source'] . '</span>';
                    $when = $alert['time'] ? date('d.m.Y H:i', $alert['time']) : '';

                    echo '<a href="#" class="list-group-item alert-sev-' . $alert['severity'] . '">' . $icon . ' ' . $src . h($alert['text']) . '<span class="pull-right">' . h($when) . '</span></a>';
                    $objects++;
                }
                if ($lasthostname !== null) {
                    echo "</div>";
                }
                ?>
            </div>
        </div>
    </div>
</div>

<script>
    var i = 0;
    var PageCount = <?php echo $sliderid; ?>;
    $('#PageCount').html(PageCount);
    $('#CurrentPage').html(i);

    CyclePages();
</script>
