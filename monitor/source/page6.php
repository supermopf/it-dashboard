<?php
/**
 * Created by PhpStorm.
 * User: victor.lange
 * Date: 05.04.2016
 * Time: 16:12
 */
//
// SCOM Meldungen
//
require("../../config.php");
$conn = sqlsrv_connect(DASHBOARD_SQL_INSTANCE, $Default_Connection);

if ($conn) {
    if ($result = sqlsrv_query($conn, "SELECT * FROM [IT-Dashboard].[dbo].[IT-Dashboard_SCOM_Criticals] ORDER BY Hostname,LastTimeModified")) {
        $criticals = array();
        while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
            array_push($criticals, $row);
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
    <!--  Criticals  -->
    <div class="col-lg-12">
        <div id="wrapper">
            <div id="slider1" class="content" style="display: none;">
                <?php
                $allowed = 20;
                $objects = 0;
                $sliderid = 1;
                $lasthostname = "";
                foreach ($criticals as $key => $Value) {
                    if ($lasthostname != $Value["Hostname"]) {
                        if ($lasthostname != "") {
                            echo '</div>';
                            if ($objects >= $allowed) {
                                $objects = 0;
                                echo "</div>";
                                $sliderid++;
                                echo "<div class=\"content\" id=\"slider" . $sliderid . "\"  style=\"display: none;\">";
                            }
                        }
                        //New Server
                        $lasthostname = $Value["Hostname"];

                        echo '<a href="#" class="list-group-item active">' . $Value["Hostname"] . '</a>'; //Title
                        echo '<div class="list-group">';

                        //Title is Object
                        $objects++;
                    }

                    if ($Value["HealthState"] == "Warning") {
                        $css_style = "warning";
                        $icon = "<i class=\"fa fa-exclamation-triangle\" aria-hidden=\"true\"></i>";
                    } else {
                        $css_style = "danger";
                        $icon = "<i class=\"fa fa-times\" aria-hidden=\"true\"></i>";
                    }

                    echo '<a href="#" class="list-group-item">' . $icon . ' ' . $Value["ObjectDisplayName"] . ' ' . $Value["MonitorDisplayName"] . " (" . $Value["OperationalState"] . ')<span class="pull-right">' . $Value["LastTimeModified"] . '</span></a>';

                    //Item is Object
                    $objects++;

                    $dateObj = $Value["LastTimeModified"];
                    if ($dateObj instanceof \DateTime) {
                        $diff = $time->diff($dateObj);
                    }
                }
                echo "</div>";
                ?>
            </div>
        </div>
    </div>
</div>

<script>
    var i = 0;
    var PageCount = <?php echo $sliderid; ?>;


    function CyclePages() {
        if (i == 0) {
            $('#slider1').show();
            i++;
        } else {
            if (PageCount > 1) {
                $('#slider' + i).hide();
                if (i >= PageCount) {
                    i = 1;
                }
                else {
                    i++;
                }
                $('#slider' + i).show();
                $('#slider' + i).animateCss('bounceInRight');
            }
        }
    }

    CyclePages();
</script>
















