<?php
function hybi10Decode($data)
{
    $bytes = $data;
    $dataLength = '';
    $mask = '';
    $coded_data = '';
    $decodedData = '';
    $secondByte = sprintf('%08b', ord($bytes[1]));
    $masked = ($secondByte[0] == '1') ? true : false;
    $dataLength = ($masked === true) ? ord($bytes[1]) & 127 : ord($bytes[1]);

    if ($masked === true) {
        if ($dataLength === 126) {
            $mask = substr($bytes, 4, 4);
            $coded_data = substr($bytes, 8);
        } elseif ($dataLength === 127) {
            $mask = substr($bytes, 10, 4);
            $coded_data = substr($bytes, 14);
        } else {
            $mask = substr($bytes, 2, 4);
            $coded_data = substr($bytes, 6);
        }
        for ($i = 0; $i < strlen($coded_data); $i++) {
            $decodedData .= $coded_data[$i] ^ $mask[$i % 4];
        }
    } else {
        if ($dataLength === 126) {
            $decodedData = substr($bytes, 4);
        } elseif ($dataLength === 127) {
            $decodedData = substr($bytes, 10);
        } else {
            $decodedData = substr($bytes, 2);
        }
    }

    return $decodedData;
}
function hybi10Encode($payload, $type = 'text', $masked = true)
{
    $frameHead = array();
    $frame = '';
    $payloadLength = strlen($payload);

    switch ($type) {
        case 'text':
            // first byte indicates FIN, Text-Frame (10000001):
            $frameHead[0] = 129;
            break;

        case 'close':
            // first byte indicates FIN, Close Frame(10001000):
            $frameHead[0] = 136;
            break;

        case 'ping':
            // first byte indicates FIN, Ping frame (10001001):
            $frameHead[0] = 137;
            break;

        case 'pong':
            // first byte indicates FIN, Pong frame (10001010):
            $frameHead[0] = 138;
            break;
    }

    // set mask and payload length (using 1, 3 or 9 bytes)
    if ($payloadLength > 65535) {
        $payloadLengthBin = str_split(sprintf('%064b', $payloadLength), 8);
        $frameHead[1] = ($masked === true) ? 255 : 127;
        for ($i = 0; $i < 8; $i++) {
            $frameHead[$i + 2] = bindec($payloadLengthBin[$i]);
        }

        // most significant bit MUST be 0 (close connection if frame too big)
        if ($frameHead[2] > 127) {
            $this->close(1004);
            return false;
        }
    } elseif ($payloadLength > 125) {
        $payloadLengthBin = str_split(sprintf('%016b', $payloadLength), 8);
        $frameHead[1] = ($masked === true) ? 254 : 126;
        $frameHead[2] = bindec($payloadLengthBin[0]);
        $frameHead[3] = bindec($payloadLengthBin[1]);
    } else {
        $frameHead[1] = ($masked === true) ? $payloadLength + 128 : $payloadLength;
    }

    // convert frame-head to string:
    foreach (array_keys($frameHead) as $i) {
        $frameHead[$i] = chr($frameHead[$i]);
    }

    if ($masked === true) {
        // generate a random mask:
        $mask = array();
        for ($i = 0; $i < 4; $i++) {
            $mask[$i] = chr(rand(0, 255));
        }

        $frameHead = array_merge($frameHead, $mask);
    }
    $frame = implode('', $frameHead);
    // append payload to frame:
    for ($i = 0; $i < $payloadLength; $i++) {
        $frame .= ($masked === true) ? $payload[$i] ^ $mask[$i % 4] : $payload[$i];
    }

    return $frame;
}
function getMp3StreamTitle($streamingUrl, $interval, $offset = 0, $headers = true)
{
    $needle = 'StreamTitle=';
    $ua = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/27.0.1453.110 Safari/537.36';
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => 'Icy-MetaData: 1',
            'user_agent' => $ua
        ]
    ];
    if (($headers = get_headers($streamingUrl))) {
        foreach ($headers as $h) {
            if (strpos(strtolower($h), 'icy-metaint') !== false && ($interval = explode(':', $h)[1])) {
                break;
            }
        }
    }
    $context = stream_context_create($opts);
    if ($stream = fopen($streamingUrl, 'r', false, $context)) {
        $buffer = stream_get_contents($stream, $interval, $offset);
        fclose($stream);
        if (strpos($buffer, $needle) !== false) {
            $title = explode($needle, $buffer)[1];
            return substr($title, 1, strpos($title, ';') - 2);
        } else {
            return getMp3StreamTitle($streamingUrl, $interval, $offset + $interval, false);
        }
    } else {
        throw new Exception("Unable to open stream [{$streamingUrl}]");
    }
}

$host = '127.0.0.1';
$port = 9000;
$local = "http://it-dashboard.cbr.de/monitor/";
$StationFound = false;
$SongTitle = "";

$head = "GET / HTTP/1.1" . "\r\n" .
    "Upgrade: WebSocket" . "\r\n" .
    "Connection: Upgrade" . "\r\n" .
    "Origin: $local" . "\r\n" .
    "Host: $host" . "\r\n" .
    "Sec-WebSocket-Key: asdasdaas76da7sd6asd6as7d" . "\r\n";
try{
    $sock = fsockopen($host, $port, $errno, $errstr, 2);
}catch(Exception $e){
    echo $e->getMessage(), "\n";
    die();
}


fwrite($sock, $head) or die('error:' . $errno . ':' . $errstr);
$headers = fread($sock, 2000);


while (!$StationFound) {
    $wsdata = fread($sock, 2000);
    $msg = hybi10Decode($wsdata);
    $msg = json_decode($msg);
    if(isset($msg)&& isset($msg->type) && $msg->type == "update"){
        $StationFound = true;
        fclose($sock);
    }
}
try {
    $result = getMp3StreamTitle($msg->Radiostation, 19200);
    if($result != "" && preg_match("/^.+?\s*-\s*.+$/", $result)){
        $SongTitle = $result;
    }
} catch (Exception $e) {

}



$json = array(
    "type" => "command",
    "message" => "!var SongTitle ".urlencode($SongTitle)
);
$data = json_encode($json);
$head = "GET / HTTP/1.1" . "\r\n" .
    "Upgrade: WebSocket" . "\r\n" .
    "Connection: Upgrade" . "\r\n" .
    "Origin: $local" . "\r\n" .
    "Host: $host" . "\r\n" .
    "Sec-WebSocket-Key: asdasdaas76da7sd6asd6as7d" . "\r\n" .
    "Content-Length: " . strlen($data) . "\r\n" . "\r\n";

$sock = fsockopen($host, $port, $errno, $errstr, 2);
fwrite($sock, $head) or die('error:' . $errno . ':' . $errstr);
$headers = fread($sock, 2000);
fwrite($sock, hybi10Encode($data)) or die('error:' . $errno . ':' . $errstr);
$wsdata = fread($sock, 2000);
hybi10Decode($wsdata);
fclose($sock);