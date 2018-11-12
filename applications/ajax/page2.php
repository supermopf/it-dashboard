<?php
/**********CONFIG***********/
//Server, welche angezeigt werden sollen
$config = array(
    1 => "cbr-mobi44-rep",
    2 => "cbr-mobi44-01",
    3 => "cbr-mobi44-02",
    4 => "cbr-mobi44-03",
    5 => "cbr-mobi44-04",
    6 => "cbr-mobi44-05",
    7 => "cbr-mobi44-06"
);

//Zeigt Fehler an
define("DebugMode", false);

//SCOM SQL WHERE Statement
$SCOM_SQL = "WHERE (PrincipalName LIKE '%MOBI%' OR Name LIKE '%MOBI%' OR Description LIKE '%MOBI%') AND [TimeRaised] > Dateadd(DAY ,-1,getdate())";

//DERDACK SQL WHERE Statement
$DERDACK_SQL = "WHERE  Subject LIKE '%MOBI%'  OR Subject LIKE '%MOBI%' OR Subject LIKE '%MOBI%' OR AlertText LIKE '%MOBI%' OR AlertText LIKE '%MOBI%' OR AlertText LIKE '%MOBI%'";

//HelpLine Keyword
$HELPLINE_SQL = "AND [keyword] LIKE 'MOBI%'";


/*******DO NOT TOUCH********/
include('./source.php');