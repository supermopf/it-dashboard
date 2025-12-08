<?php
/**
 * Created by PhpStorm.
 * User: victor.lange
 * Date: 09.10.2018
 * Time: 15:16
 */

require ('../config.php');

$conn = sqlsrv_connect(DASHBOARD_SQL_INSTANCE, $Default_Connection);

if ($conn) {
    //Haben wir Kontakt?
    if ($result = sqlsrv_query($conn, "SELECT * FROM [IT-Dashboard].[dbo].[IT-Dashboard_Radiostations]")) {
        $array = array();
        while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
            array_push($array, $row);
        }
    } else {
        echo "<pre>";
        die(print_r(sqlsrv_errors(), true));
    }
}

echo json_encode($array);