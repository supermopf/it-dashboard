<?php
/**
 * Created by PhpStorm.
 * User: victor.lange
 * Date: 07.03.2018
 * Time: 12:41
 */

$root = "https://it-dashboard.cbr.de/monitor/source/";
$cacheroot = dirname(__FILE__) . "/cache/";

$pages = array(
    "page1.php",
    "page2.php",
    "page3.php",
    "page4.php",
    "page5.php",
    "page6.php",
    "page7.php",
    "page8.php",
    "page9.php",
    "page10.php",
    "page11.php",
    "page12.php"
);


foreach ($pages as $page) {
    $content = file_get_contents($root . $page);

    echo "Writing " . $cacheroot . $page;
    file_put_contents($cacheroot . $page, $content);
}


