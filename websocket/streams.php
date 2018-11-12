<?php
/**
 * Created by PhpStorm.
 * User: victor.lange
 * Date: 09.10.2018
 * Time: 15:16
 */

require ('../config.php');

$string = file_get_contents(RADIOLIST);


preg_match_all('/(?P<tag>#EXTINF:-1)|(?:(?P<prop_key>[-a-z]+)=\"(?P<prop_val>[^"]+)")|(?<something>,[^\r\n]+)|(?<url>http[^\s]+)/', $string, $match);

$count = count($match[0]);

$list = [];
$result = [];
$index = -1;

for ($i = 0; $i < $count; $i++) {
    $item = $match[0][$i];

    if (!empty($match['tag'][$i])) {
        //is a tag increment the result index
        ++$index;
    } elseif (!empty($match['prop_key'][$i])) {
        //is a prop - split item
        $list[$index][$match['prop_key'][$i]] = $match['prop_val'][$i];
    } elseif (!empty($match['something'][$i])) {
        //is a prop - split item
        $list[$index]['something'] = $item;
    } elseif (!empty($match['url'][$i])) {
        $list[$index]['url'] = $item;
    }
}

foreach ($list as $item) {
    if (isset($item["group-title"]) && $item["group-title"] == "Radio") {
        $result[] = $item;
    }
}
//print_r($result);
echo json_encode($result);
