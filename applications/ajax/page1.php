<?php
/**********CONFIG***********/
//Server, welche angezeigt werden sollen
$config = array(
    1 => "cbr-pdm-app01",
    2 => "cbr-pdm-svc701",
    3 => "cbr-plmon701",
    4 => "cbr-plmon702",
    5 => "cbr-srmon701",
    6 => "cbr-srmon702",
    7 => "cbr-pdm-svc702"
);

//Zeigt Fehler an
define("DebugMode", false);

//SCOM SQL WHERE Statement
$SCOM_SQL = "WHERE (PrincipalName LIKE '%PDM%' OR PrincipalName LIKE '%SRM%' OR PrincipalName LIKE '%YPLM%' OR Name LIKE '%PDM%' OR Name LIKE '%SRM%' OR Name LIKE '%YPLM%' OR Description LIKE '%PDM%' OR Description LIKE '%SRM%' OR Description LIKE '%YPLM%') AND [TimeRaised] > Dateadd(DAY ,-1,getdate()) ";

//DERDACK SQL WHERE Statement
$DERDACK_SQL = "WHERE  Subject LIKE '%PDM%'  OR Subject LIKE '%SRM%' OR Subject LIKE '%PLM%' OR AlertText LIKE '%PDM%' OR AlertText LIKE '%SRM%' OR AlertText LIKE '%PLM%' ";

//HelpLine Keyword
$HELPLINE_SQL = "AND [keyword] LIKE '%PDM%' ";


/*******DO NOT TOUCH********/
include('./source.php');
