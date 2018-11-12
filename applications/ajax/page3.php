<?php
/**********CONFIG***********/
//Server, welche angezeigt werden sollen
$config = array(
    1 => "m3cbrmaklcm",
    2 => "m3cbrmec01",
    3 => "m3cbrmec02",
    4 => "m3cbrmos01",
    5 => "m3cbrmos02",
    6 => "m3cbrmos03",
    7 => "m3cbrsql",
    8 => "m3cbrsql02",
    9 => "m3cbries",
    10 => "m3cbripa",
    11 => "m3cbriso",
    12 => "m3cbriso02",
    13 => "m3cbrpmt",
    14 => "m3cbrpmt02"
);

//Zeigt Fehler an
define("DebugMode", false);

//SCOM SQL WHERE Statement
$SCOM_SQL = "WHERE (PrincipalName LIKE '%ERP%' OR PrincipalName LIKE '%M3%' OR Name LIKE '%ERP%' OR Name LIKE '%M3%' OR Description LIKE '%ERP%' OR Description LIKE '%M3%') AND [TimeRaised] > Dateadd(DAY ,-1,getdate()) ";

//DERDACK SQL WHERE Statement
$DERDACK_SQL = "WHERE  Subject LIKE '%ERP%' OR Subject LIKE '%M3%' OR AlertText LIKE '%ERP%' OR AlertText LIKE '%M3%'";

//HelpLine Keyword
$HELPLINE_SQL = "AND ([keyword] LIKE '%ERP%' OR [keyword] LIKE '%M3%')";


/*******DO NOT TOUCH********/
include('./source.php');