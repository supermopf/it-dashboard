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

echo '<div class="row">';
echo '<div class="col-lg-12">';

$now = new \DateTime();
$now = $now->modify('-15 minutes');


$count = Count($array) - 1;
for ($i = 0; $i <= $count; $i++) {

    $array[$i]["TimeStamp"]->setTimezone(new DateTimeZone('Europe/Berlin'));

    if ($now < $array[$i]["TimeStamp"]) {
        $color = "glow";
    } else {
        $color = "";
    }
    echo '<div class="col-sm-3 col-xs-12">';
    echo '<div class="' . $color . '">';
    echo '<div class="card">';
    echo '<div class="card-header bg-danger min-height-header">';
    echo '<div class="card-title">';
    echo '<div class="title text-center">';
    echo '<div class="col-sm-12">' . $array[$i]["Subject"] . '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '<div class="card-body">';
    echo '<div class="card-text">';
    echo "<div class='min-height-text'>";
    echo "<p>" . $array[$i]["AlertText"] . "</p>";
    echo "</div>";
    echo '<hr class="half-rule"/>';
    echo "<div>";
    echo "<div class='col-sm'>";
    echo "<span class='pull-left'>" . $array[$i]["TimeStamp"]->format('d.m.Y H:i') . "</span>";
    echo '</div>';
    echo "<div class='col-sm'>";
    echo "<span class='pull-right'>Quittiert von: " . $array[$i]["DisplayName"] . "</span>";
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    if (($i + 1) % 4 == 0 AND $i != 0) {
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

