<?php
/**
 * Created by PhpStorm.
 * User: victor.lange
 * Date: 05.04.2016
 * Time: 16:12
 */
require("../../config.php");
$conn = sqlsrv_connect(DASHBOARD_SQL_INSTANCE, $Default_Connection);

if ($conn) {
    //Haben wir Kontakt?
    if ($result = sqlsrv_query($conn, "SELECT  top(1) * FROM [IT-Dashboard].[dbo].[IT-Dashboard_der-postillion] ORDER BY newid()")) {
        $postillon = array();
        while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
            array_push($postillon, $row);
        }
    } else {
        echo "<pre>";
        die(print_r(sqlsrv_errors(), true));
    }
//    Coding love
    if ($result = sqlsrv_query($conn, "SELECT  top(2) * FROM [IT-Dashboard].[dbo].[IT-Dashboard_thecodinglove] order by newid()")) {
        $thecodinglove = array();
        while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
            array_push($thecodinglove, $row);
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
    <div class="col-lg-12">
        <div class="page-header text-center"><h1>+++<?php echo $postillon[0]['text'] ?>+++</h1></div>
    </div>
</div>
<div class="row">
    <div class="col-lg-6 col-sm-12">
        <div class="card equal">
            <div class="card-header">
                <div class="card-title">
                    <div class="title"><h2><strong><?php echo $thecodinglove[0]["title"]; ?></strong></h2></div>
                </div>
            </div>
            <div class="panel-body">
                <?php
                if(strpos($thecodinglove[0]["URL"],"webm") !== false){
                    echo '<video autoplay loop class="col-xs-12 img-responsive" src="'.$thecodinglove[0]["URL"].'"></video>';
                }else{
                    echo '<img src="'.$thecodinglove[0]["URL"].'" class="col-xs-12 img-responsive">';
                }
                ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6 col-sm-12">
        <div class="card equal">
            <div class="card-header">
                <div class="card-title">
                    <div class="title"><h2><strong><?php echo $thecodinglove[1]["title"]; ?></strong></h2></div>
                </div>
            </div>
            <div class="panel-body">
                <?php
                if(strpos($thecodinglove[1]["URL"],"webm") !== false){
                    echo '<video autoplay loop class="col-xs-12 img-responsive" src="'.$thecodinglove[1]["URL"].'"></video>';
                }else{
                    echo '<img src="'.$thecodinglove[1]["URL"].'" class="col-xs-12 img-responsive">';
                }
                ?>
            </div>
        </div>
    </div>
</div>
