<?php
/**
 * Created by PhpStorm.
 * User: victor.lange
 * Date: 05.04.2016
 * Time: 16:12
 */
//
//DERDACK
//

//Weil SQL Kacka ist
require("../../config.php");
require_once(__DIR__ . '/../../lib/security.php');
date_default_timezone_set('UTC');
$serverName = DERDACK_SQL_INSTANCE;
$connectionInfo = array("Database" => DERDACK_SQL_DATABASE, "CharacterSet" => "UTF-8");
$conn = sqlsrv_connect($serverName, $connectionInfo);

if ($conn) {
    //Haben wir Kontakt?
    if ($result = sqlsrv_query($conn, "SELECT * FROM [dbo].[V_IT-Dashboard] ORDER BY TIMESTAMP DESC")) {
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

/** Text für eine Kachel kürzen (zusätzlich zur CSS-Begrenzung, damit lange Meldungen das DOM nicht aufblähen). */
function tile_text($value, int $max): string
{
    $text = trim(preg_replace('/[ \t]+/', ' ', str_replace("\r", '', (string)$value)));
    $text = preg_replace("/\n{2,}/", "\n", $text);
    return mb_strimwidth($text, 0, $max, '…', 'UTF-8');
}

$now = new \DateTime();
$now = $now->modify('-15 minutes');
?>
<style>
    /* Alle Kacheln gleich groß: festes Raster, Inhalte werden gekürzt (…) statt die Kachel zu strecken */
    .dd-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        grid-auto-rows: 21rem;
        gap: var(--space-4, 16px);
    }
    .dd-grid > .dd-tile { min-width: 0; min-height: 0; }
    .dd-grid .card {
        display: flex;
        flex-direction: column;
        height: 100%;
        margin: 0;
    }
    .dd-grid .card-header {
        flex: none;
        height: 5rem;
        display: flex;
        align-items: center;
    }
    .dd-grid .dd-subject {
        width: 100%;
        font-size: var(--fs-md, 17px);
        font-weight: var(--fw-semibold, 600);
        line-height: var(--lh-tight, 1.15);
        text-align: center;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        overflow-wrap: anywhere;
    }
    .dd-grid .glow .dd-subject { padding: 0 2.5rem; }
    .dd-grid .card-body {
        flex: 1;
        min-height: 0;
        display: flex;
        flex-direction: column;
    }
    .dd-grid .dd-text {
        flex: 1;
        min-height: 0;
        margin: 0;
        line-height: var(--lh-normal, 1.4);
        white-space: pre-line;
        overflow-wrap: anywhere;
        display: -webkit-box;
        -webkit-line-clamp: 6;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .dd-grid .dd-footer {
        flex: none;
        display: flex;
        justify-content: space-between;
        gap: var(--space-3, 12px);
        padding-top: var(--space-3, 12px);
        margin-top: var(--space-3, 12px);
        border-top: 1px solid var(--color-border, rgba(255,255,255,0.08));
        color: var(--color-text-secondary, #a3acb9);
        font-size: var(--fs-sm, 15px);
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    .dd-grid .dd-footer span:last-child { overflow: hidden; text-overflow: ellipsis; }
</style>
<?php
echo '<div class="dd-grid">';
foreach ($array as $item) {
    $item["TimeStamp"]->setTimezone(new DateTimeZone('Europe/Berlin'));
    $color = $now < $item["TimeStamp"] ? "glow" : "";
    $ack = trim((string)$item["DisplayName"]);

    echo '<div class="dd-tile ' . $color . '">';
    echo '<div class="card">';
    echo '<div class="card-header bg-danger">';
    echo '<div class="dd-subject" title="' . h($item["Subject"]) . '">' . h(tile_text($item["Subject"], 120)) . '</div>';
    echo '</div>';
    echo '<div class="card-body">';
    echo '<p class="dd-text">' . h(tile_text($item["AlertText"], 360)) . '</p>';
    echo '<div class="dd-footer">';
    echo '<span>' . $item["TimeStamp"]->format('d.m.Y H:i') . '</span>';
    echo '<span>' . ($ack !== '' ? 'Quittiert: ' . h($ack) : 'Nicht quittiert') . '</span>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
}
echo '</div>';
?>
