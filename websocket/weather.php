<?php
$json = file_get_contents('https://tilecache.rainviewer.com/api/maps.json');
$Timestamp = max(json_decode($json));

$overlayPath = Array(
    'https://tilecache.rainviewer.com/v2/radar/'.$Timestamp.'/256/7/66/40.png?color=6',
    'https://tilecache.rainviewer.com/v2/radar/'.$Timestamp.'/256/7/67/40.png?color=6',
    'https://tilecache.rainviewer.com/v2/radar/'.$Timestamp.'/256/7/68/40.png?color=6',
    'https://tilecache.rainviewer.com/v2/radar/'.$Timestamp.'/256/7/69/40.png?color=6',
    'https://tilecache.rainviewer.com/v2/radar/'.$Timestamp.'/256/7/66/41.png?color=6',
    'https://tilecache.rainviewer.com/v2/radar/'.$Timestamp.'/256/7/67/41.png?color=6',
    'https://tilecache.rainviewer.com/v2/radar/'.$Timestamp.'/256/7/68/41.png?color=6',
    'https://tilecache.rainviewer.com/v2/radar/'.$Timestamp.'/256/7/69/41.png?color=6',
    'https://tilecache.rainviewer.com/v2/radar/'.$Timestamp.'/256/7/66/42.png?color=6',
    'https://tilecache.rainviewer.com/v2/radar/'.$Timestamp.'/256/7/67/42.png?color=6',
    'https://tilecache.rainviewer.com/v2/radar/'.$Timestamp.'/256/7/68/42.png?color=6',
    'https://tilecache.rainviewer.com/v2/radar/'.$Timestamp.'/256/7/69/42.png?color=6',
    'https://tilecache.rainviewer.com/v2/radar/'.$Timestamp.'/256/7/66/43.png?color=6',
    'https://tilecache.rainviewer.com/v2/radar/'.$Timestamp.'/256/7/67/43.png?color=6',
    'https://tilecache.rainviewer.com/v2/radar/'.$Timestamp.'/256/7/68/43.png?color=6',
    'https://tilecache.rainviewer.com/v2/radar/'.$Timestamp.'/256/7/69/43.png?color=6'
);
$srcImagePaths = Array(
    'https://services.arcgisonline.com/arcgis/rest/services/World_Street_Map/MapServer/tile/7/40/66',
    'https://services.arcgisonline.com/arcgis/rest/services/World_Street_Map/MapServer/tile/7/40/67',
    'https://services.arcgisonline.com/arcgis/rest/services/World_Street_Map/MapServer/tile/7/40/68',
    'https://services.arcgisonline.com/arcgis/rest/services/World_Street_Map/MapServer/tile/7/40/69',
    'https://services.arcgisonline.com/arcgis/rest/services/World_Street_Map/MapServer/tile/7/41/66',
    'https://services.arcgisonline.com/arcgis/rest/services/World_Street_Map/MapServer/tile/7/41/67',
    'https://services.arcgisonline.com/arcgis/rest/services/World_Street_Map/MapServer/tile/7/41/68',
    'https://services.arcgisonline.com/arcgis/rest/services/World_Street_Map/MapServer/tile/7/41/69',
    'https://services.arcgisonline.com/arcgis/rest/services/World_Street_Map/MapServer/tile/7/42/66',
    'https://services.arcgisonline.com/arcgis/rest/services/World_Street_Map/MapServer/tile/7/42/67',
    'https://services.arcgisonline.com/arcgis/rest/services/World_Street_Map/MapServer/tile/7/42/68',
    'https://services.arcgisonline.com/arcgis/rest/services/World_Street_Map/MapServer/tile/7/42/69',
    'https://services.arcgisonline.com/arcgis/rest/services/World_Street_Map/MapServer/tile/7/43/66',
    'https://services.arcgisonline.com/arcgis/rest/services/World_Street_Map/MapServer/tile/7/43/67',
    'https://services.arcgisonline.com/arcgis/rest/services/World_Street_Map/MapServer/tile/7/43/68',
    'https://services.arcgisonline.com/arcgis/rest/services/World_Street_Map/MapServer/tile/7/43/69'
);

$tileWidth = $tileHeight = 256;
$numberOfTiles = 3;
$pxBetweenTiles = 0;
$leftOffSet = $topOffSet = 0;


$mapWidth = $mapHeight = ($tileWidth + $pxBetweenTiles) * $numberOfTiles;

$overlay = imagecreatetruecolor($mapWidth, $mapHeight);
$mapImage = imagecreatetruecolor($mapWidth, $mapHeight);

imagealphablending($overlay, false);
imagesavealpha($overlay,true);

function indexToCoords($index)
{
    global $tileWidth, $pxBetweenTiles, $leftOffSet, $topOffSet, $numberOfTiles;

    $x = ($index % 4) * ($tileWidth + $pxBetweenTiles) + $leftOffSet;
    $y = floor($index / 4) * ($tileWidth + $pxBetweenTiles) + $topOffSet;
    return Array($x, $y);
}

foreach ($overlayPath as $index => $srcImagePath)
{
    list ($x, $y) = indexToCoords($index);

    $img=imagecreatefrompng($srcImagePath);

    imagecopy($overlay, $img, $x, $y, 0, 0, $tileWidth, $tileHeight);
    imagedestroy($img);
}
foreach ($srcImagePaths as $index => $srcImagePath)
{
    list ($x, $y) = indexToCoords($index);
    $tileImg = imagecreatefromjpeg($srcImagePath);

    imagecopy($mapImage, $tileImg, $x, $y, 0, 0, $tileWidth, $tileHeight);
    imagedestroy($tileImg);
}
imagefilter($overlay, IMG_FILTER_COLORIZE, 0,0,0,127);
imagecolortransparent($overlay,imagecolorat($overlay,0,0));

imagecopymerge($mapImage, $overlay, 0, 0, 0, 0, $mapWidth, $mapHeight,50);

header ("Content-type: image/png");
imagepng($mapImage,'C:\inetpub\dashboard\radar.png');

?>