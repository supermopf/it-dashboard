<?php
/**
 * Created by PhpStorm.
 * User: victor.lange
 * Date: 18.07.2018
 * Time: 13:30
 */
require(getenv('DASHBOARD_CONFIG') ?: __DIR__ . '/../config.php');
require_once __DIR__ . '/../lib/security.php';
require_once __DIR__ . '/lib/api_validation.php';
set_time_limit(30);
send_security_headers("default-src 'self'; style-src 'self'; frame-ancestors 'self'");

/** Antwort mit Statuscode; Body bleibt für Erfolg „OK“ (kompatibel zu bestehenden Aufrufern). */
function api_respond(int $status, string $text): void
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $text;
    exit;
}

/** Nachricht an den lokalen WebSocket-Server übergeben. */
function api_send_ws(string $message): void
{
    $host = '127.0.0.1';
    $port = defined('WEBSOCKET_PORT') ? (int)WEBSOCKET_PORT : 9000;
    $local = DASHBOARD_BASE_URL . "/monitor/";
    $data = json_encode(array("type" => "command", "message" => $message));
    $head = "GET / HTTP/1.1\r\n" .
        "Upgrade: WebSocket\r\n" .
        "Connection: Upgrade\r\n" .
        "Origin: $local\r\n" .
        "Host: $host\r\n" .
        "Sec-WebSocket-Key: " . base64_encode(random_bytes(16)) . "\r\n" .
        "Content-Length: " . strlen($data) . "\r\n\r\n";
    $sock = @fsockopen($host, $port, $errno, $errstr, 2);
    if ($sock === false) {
        api_respond(503, 'WebSocket-Server nicht erreichbar');
    }
    stream_set_timeout($sock, 3);
    fwrite($sock, $head);
    fread($sock, 2000);
    fwrite($sock, hybi10Encode($data));
    fclose($sock);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Optionaler API-Schlüssel für externe Aufrufer; Admin-Seiten (Same-Origin) brauchen ihn nicht.
    $apiKey = defined('TOAST_API_KEY') ? (string)TOAST_API_KEY : '';
    if ($apiKey !== '' && !is_same_origin_request($_SERVER)) {
        $given = (string)($_SERVER['HTTP_X_API_KEY'] ?? ($_POST['ApiKey'] ?? ''));
        if (!hash_equals($apiKey, $given)) {
            api_respond(401, 'API-Schlüssel fehlt oder ist falsch');
        }
    }
    unset($_POST['ApiKey']);
    $limit = defined('TOAST_API_RATE_LIMIT') ? (int)TOAST_API_RATE_LIMIT : 30;
    if (!rate_limit_allow('toastapi', client_ip(), $limit, 60)) {
        header('Retry-After: 60');
        api_respond(429, 'Zu viele Anfragen');
    }
}

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


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        isset($_POST["ToastSubject"]) ||
        isset($_POST["ToastBody"]) ||
        isset($_POST["ToastPicture"]) ||
        isset($_POST["ToastSound"]) ||
        isset($_POST["ToastTime"]) ||
        isset($_POST["ToastVolume"])
    ) {


        $result = validate_toast($_POST);
        if ($result['errors']) {
            $msg = array();
            foreach ($result['errors'] as $field => $error) {
                $msg[] = "$field: $error";
            }
            api_respond(400, "Ungültige Parameter:\n" . implode("\n", $msg));
        }
        $payload = json_encode($result['data'], JSON_UNESCAPED_UNICODE);
        api_send_ws("!urgent " . $payload);
        echo "OK";
    }elseif(isset($_POST["YouTubeURL"])){
	
	    // YouTube video with optional Target parameter
	    $url = validate_youtube_url($_POST["YouTubeURL"]);
	    if ($url === null) {
	        api_respond(400, 'YouTubeURL: keine gültige YouTube-URL');
	    }
	    $target = isset($_POST["Target"]) ? trim((string)$_POST["Target"]) : '';
	    if ($target !== '' && !preg_match('/^[\p{L}\p{N} ._-]{1,64}$/u', $target)) {
	        api_respond(400, 'Target: ungültig');
	    }

	    // Build message with target if specified
	    $videoMessage = $url;
	    if ($target !== '') {
	        $videoMessage .= "|" . $target;
	    }
        api_send_ws("!video " . $videoMessage);
        echo "OK";
	} else {
        api_respond(400, "Wrong Request! RTFM " . DASHBOARD_BASE_URL . "/websocket/api.php");
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
            <tr>
                <td>ToastVideoNoRepeat</td>
                <td>POST</td>
                <td>Nein</td>
                <td>Boolean</td>
                <td>false</td>
                <td>Gibt an, ob der Toast nach dem Ende des Videos gestoppt werden soll.</td>
            </tr>
			<tr>
                <td>YouTubeURL</td>
                <td>POST</td>
                <td>Nein</td>
                <td>String</td>
                <td>false</td>
                <td>Startet ein YouTube Video.</td>
            </tr>
        </tbody>
    </table>
</body>




";
}

