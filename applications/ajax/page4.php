<?php
/**********CONFIG***********/
//Server, welche angezeigt werden sollen
$config = array(
    1 => "kh-bi-app01",
    2 => "kh-bi-app02",
    3 => "kh-bi-web01",
    4 => "kh-bi-web02",
    5 => "dev-bi-web11",
    6 => "dev-bi-app11",
    7 => "test-bi-web11",
    8 => "test-bi-app11"
);

//Zeigt Fehler an
define("DebugMode", false);

//SCOM SQL WHERE Statement
$SCOM_SQL = "WHERE (PrincipalName LIKE '%bi%' OR PrincipalName LIKE '%bi%' OR Name LIKE '%bi%' OR Name LIKE '%bi%' OR Description LIKE '%bi%' OR Description LIKE '%bi%') AND [TimeRaised] > Dateadd(DAY ,-1,getdate()) ";

//DERDACK SQL WHERE Statement
$DERDACK_SQL = "WHERE  Subject LIKE '%bi%' OR Subject LIKE '%bi%' OR AlertText LIKE '%bi%' OR AlertText LIKE '%bi%'";

//HelpLine Keyword
$HELPLINE_SQL = "AND ([keyword] LIKE '%bi%' OR [keyword] LIKE '%bi%')";


/*******DO NOT TOUCH********/
include('./source.php');