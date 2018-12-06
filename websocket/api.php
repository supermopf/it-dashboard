<?php
/**
 * Created by PhpStorm.
 * User: victor.lange
 * Date: 18.07.2018
 * Time: 13:30
 */


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        isset($_POST["ToastSubject"]) ||
        isset($_POST["ToastBody"]) ||
        isset($_POST["ToastPicture"]) ||
        isset($_POST["ToastSound"]) ||
        isset($_POST["ToastTime"]) ||
        isset($_POST["ToastVolume"])
    ) {
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


        $payload = json_encode($_POST);


        $host = '127.0.0.1';
        $port = 9000;
        $local = "http://it-dashboard.cbr.de/monitor/";

        $json = array(
            "type" => "command",
            "message" => "!urgent " . $payload
        );
        $data = json_encode($json);

        $head = "GET / HTTP/1.1" . "\r\n" .
            "Upgrade: WebSocket" . "\r\n" .
            "Connection: Upgrade" . "\r\n" .
            "Origin: $local" . "\r\n" .
            "Host: $host" . "\r\n" .
            "Sec-WebSocket-Key: asdasdaas76da7sd6asd6as7d" . "\r\n" .
            "Content-Length: " . strlen($data) . "\r\n" . "\r\n";
        //WebSocket handshake
        $sock = fsockopen($host, $port, $errno, $errstr, 2);
        fwrite($sock, $head) or die('error:' . $errno . ':' . $errstr);
        $headers = fread($sock, 2000);
        fwrite($sock, hybi10Encode($data)) or die('error:' . $errno . ':' . $errstr);
        $wsdata = fread($sock, 2000);
        hybi10Decode($wsdata);
        fclose($sock);
        echo "OK";
    } else {
        echo "Wrong Request! RTFM http://it-dashboard.cbr.de/websocket/api.php";
    }
} else {
    echo "
<head>
    <link rel=\"stylesheet\" type=\"text/css\" href=\"../monitor/lib/css/bootstrap.min.css\">
</head>
<body>
    <h1>Dashboard API</h1>
    
    <table class=\"table\">
        <thead>
            <tr>
                <th>Parameter</th>
                <th>Methode</th>
                <th>Required</th>
                <th>String</th>
                <th>Default</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>ToastSubject</td>
                <td>POST</td>
                <td>Nein</td>
                <td>String</td>
                <td></td>
                <td>Titel des Toasts</td>
            </tr>
            <tr>
                <td>ToastBody</td>
                <td>POST</td>
                <td>Nein</td>
                <td>String</td>
                <td></td>
                <td>Text des Toasts</td>
            </tr>
            <tr>
                <td>ToastPicture</td>
                <td>POST</td>
                <td>Nein</td>
                <td>String</td>
                <td></td>
                <td>Bild/Video des Toasts</td>
            </tr>
            <tr>
                <td>ToastColor</td>
                <td>POST</td>
                <td>Nein</td>
                <td>String</td>
                <td>#FA2A00</td>
                <td>Farbe des Toasts</td>
            </tr>
            <tr>
                <td>ToastTextColor</td>
                <td>POST</td>
                <td>Nein</td>
                <td>String</td>
                <td>#FFFFFF</td>
                <td>Farbe der Überschrift</td>
            </tr>
            <tr>
                <td>ToastSound</td>
                <td>POST</td>
                <td>Nein</td>
                <td>String</td>
                <td>Win XP Error</td>
                <td>URL des Sounds</td>
            </tr>
            <tr>
                <td>ToastTime</td>
                <td>POST</td>
                <td>Nein</td>
                <td>Integer</td>
                <td>30000</td>
                <td>Anzeigezeit des Toasts in ms</td>
            </tr>
            <tr>
                <td>ToastVolume</td>
                <td>POST</td>
                <td>Nein</td>
                <td>Float</td>
                <td>0.5</td>
                <td>Lautstärke von 0 bis 1</td>
            </tr>
            <tr>
                <td>ToastHistory</td>
                <td>POST</td>
                <td>Nein</td>
                <td>Boolean</td>
                <td>true</td>
                <td>Gibt an, ob der Toast in der History gespeichert werden soll</td>
            </tr>
        </tbody>
    </table>
</body>




";
}

