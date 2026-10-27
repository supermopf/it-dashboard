<?php
function WriteWebSocketLog($logLine)
{
    $logPath = __DIR__ . '/logs/websocket.log';
    $maxSize = 10 * 1024 * 1024;
    $maxArchives = 5;

    clearstatcache(true, $logPath);
    if (is_file($logPath) && filesize($logPath) + strlen($logLine) > $maxSize) {
        for ($index = $maxArchives; $index >= 1; $index--) {
            $source = $index === 1 ? $logPath : $logPath . '.' . ($index - 1);
            $archive = $logPath . '.' . $index;

            if (is_file($archive)) {
                @unlink($archive);
            }
            if (is_file($source)) {
                @rename($source, $archive);
            }
        }
    }

    @file_put_contents($logPath, $logLine, FILE_APPEND | LOCK_EX);
}

// Global error and exception handlers for comprehensive logging
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return true;
    }

    $errorType = '';
    switch ($errno) {
        case E_ERROR: $errorType = 'E_ERROR'; break;
        case E_WARNING: $errorType = 'E_WARNING'; break;
        case E_PARSE: $errorType = 'E_PARSE'; break;
        case E_NOTICE: $errorType = 'E_NOTICE'; break;
        default: $errorType = "Error[$errno]"; break;
    }
    $logLine = date("Y-m-d H:i:s") . " [$errorType] $errstr in $errfile:$errline" . PHP_EOL;
    echo $logLine;
    WriteWebSocketLog($logLine);
    return false; // Let PHP handle it normally too
});

set_exception_handler(function($exception) {
    $logLine = date("Y-m-d H:i:s") . " [UNCAUGHT EXCEPTION] " . $exception->getMessage() . 
               " in " . $exception->getFile() . ":" . $exception->getLine() . PHP_EOL;
    $logLine .= "Stack trace:" . PHP_EOL . $exception->getTraceAsString() . PHP_EOL;
    echo $logLine;
    WriteWebSocketLog($logLine);
});

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $logLine = date("Y-m-d H:i:s") . " [FATAL ERROR] " . $error['message'] . 
                   " in " . $error['file'] . ":" . $error['line'] . PHP_EOL;
        echo $logLine;
        WriteWebSocketLog($logLine);
    }
});

include(getenv('DASHBOARD_CONFIG') ?: __DIR__ .'/../config.php');
require_once(__DIR__ . '/db_helper.php');
require_once(__DIR__ . '/lib/protocol.php');
require_once(__DIR__ . '/../lib/security.php');

$host = DASHBOARD_DOMAIN; //host
$port = defined('WEBSOCKET_PORT') ? (string)WEBSOCKET_PORT : '9000'; //port
$null = NULL; //null var

//Create TCP/IP sream socket
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
//reuseable port
socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

//bind socket on all network interfaces
socket_bind($socket, '0.0.0.0', (int)$port);

//listen to port
socket_listen($socket);

//create & add listning socket to the list
$clients = array($socket);

LogNormal("Server started on: " . $host . ":" . $port);

$Timestamp = time();
$Page = 1;
$FUN = False;
$Cycle = True;
$Cat = False;
$Wheel = False;
$Snow = False;
$FUN_active = False;
$Wheel_active = False;
$BusActive = False;
$lasttimestamp = strtotime("now");
$lastsongpull = strtotime("now");
$Radio = True;
$Radiostation = DEFAULT_RADIOSTATION;
$RadioStationIcon = DEFAULT_RADIOSTATION_ICON;
$Radiovolume = 0.01;
$YTvolume = 0.01;
$ForwardedIP = "";
$SongTitle = "";
$PageStatusCache = LoadPageStatus();
$LastPageStatusLoad = time();

define("Debug", True);
/*
 * 1 = Wichtig
 * 2 = Normal
 * 3 = ALLES
 */
define("DebugLogLevel", 3);


$CycleTime = 30;


$registered = array();
$ProxyMatch = array();
$dashboards = array(); // Dashboard registry: [resource_id => ['socket' => resource, 'name' => dashboardName]]
$connectionCountByIP = array(); // Track connections per IP: [IP => count]
$recvBuffers = array(); // Unvollständige Frames pro Client: [resource_id => string]
$clientIPs = array(); // Reale Client-IP pro Socket: [resource_id => IP]

define("MAX_CONNECTIONS_PER_IP", 10); // Maximum connections from single IP

// Optional: Netze, aus denen steuernde Befehle (!var, !fpage, !urgent, …) angenommen werden.
// Leer/undefiniert = bisheriges Verhalten (alle Clients dürfen alles). Siehe docs/SECURITY.md.
$TrustedCommandNetworks = defined('WEBSOCKET_TRUSTED_COMMAND_NETWORKS') ? array_filter((array)WEBSOCKET_TRUSTED_COMMAND_NETWORKS) : array();
// Optional: erlaubte Origin-Header beim Handshake (z. B. https://dashboard.example.local).
$AllowedOrigins = defined('WEBSOCKET_ALLOWED_ORIGINS') ? array_filter((array)WEBSOCKET_ALLOWED_ORIGINS) : array();
// Optional: Host-Whitelist für Toast-Medien, die der Server herunterlädt (SSRF-Schutz).
$ToastMediaHosts = defined('TOAST_MEDIA_ALLOWED_HOSTS') ? array_filter((array)TOAST_MEDIA_ALLOWED_HOSTS) : array();
$ToastMediaMaxBytes = defined('TOAST_MEDIA_MAX_BYTES') ? (int)TOAST_MEDIA_MAX_BYTES : 25 * 1024 * 1024;

/** Befehle, die ohne Vertrauensstellung erlaubt sind (Registrierung, Listen). */
function is_basic_command(string $message): bool
{
    foreach (array('!reg', '!dashboard', '!repeat', '!clientlist') as $prefix) {
        if (strncmp($message, $prefix, strlen($prefix)) === 0) {
            return true;
        }
    }
    return false;
}

/** gethostbyaddr() blockiert (DNS) – Ergebnisse begrenzt zwischenspeichern. */
function cached_hostname($ip)
{
    static $cache = array();
    if ($ip === null || $ip === '') {
        return '';
    }
    if (!isset($cache[$ip])) {
        if (count($cache) > 256) {
            $cache = array();
        }
        $cache[$ip] = @gethostbyaddr($ip) ?: $ip;
    }
    return $cache[$ip];
}

function resource_id($socket)
{
    return is_object($socket) ? spl_object_id($socket) : (int)$socket;
}

function LogDebug($message, $LogLevel = 3)
{
    if (Debug) {
        if (DebugLogLevel >= $LogLevel) {
            $logLine = date("Y-m-d H:i:s") . " [DEBUG] " . $message . PHP_EOL;
            echo $logLine;
            WriteWebSocketLog($logLine);
        }
    }
}

function LogNormal($message)
{
    // Handle Exception objects
    if ($message instanceof Exception) {
        $message = "Exception: " . $message->getMessage() . " in " . $message->getFile() . ":" . $message->getLine();
    } elseif ($message instanceof Throwable) {
        $message = "Error: " . $message->getMessage() . " in " . $message->getFile() . ":" . $message->getLine();
    }
    
    $logLine = date("Y-m-d H:i:s") . " " . $message . PHP_EOL;
    echo $logLine;
    WriteWebSocketLog($logLine);
}

function LoadPageStatus() {
    $statusFile = __DIR__ . '/../monitor/cache/page_status.json';
    if (file_exists($statusFile)) {
        $json = file_get_contents($statusFile);
        $status = json_decode($json, true);
        return $status ? $status : array();
    }
    return array();
}

function PageHasData($pageNumber, $pageStatusCache) {
    // Use cached status from rendercache.php
    // For monitoring pages: hasData = true means alerts exist (bad, don't skip)
    // hasData = false means no alerts (good, but we skip empty pages)
    if (isset($pageStatusCache[$pageNumber]) && isset($pageStatusCache[$pageNumber]['hasData'])) {
        // Return true if page has alerts (show it) or false if empty (skip it)
        return $pageStatusCache[$pageNumber]['hasData'];
    }
    // Default: assume page should be shown if not in cache
    return true;
}


//start endless loop, so that our script doesn't stop
while (true) {
    try {
        //manage multipal connections
        $changed = $clients;
        
        // Filter out invalid sockets before socket_select
        $changed = array_filter($changed, function($client) {
            return $client instanceof \Socket || (is_resource($client) && get_resource_type($client) === 'Socket');
        });
        
        if (empty($changed)) {
            usleep(10000); // 10ms sleep if no valid clients
            continue;
        }
        
        //returns the socket resources in $changed array
        // 100 ms statt 10 µs: kein Busy-Loop mehr (AUDIT S1). select() kehrt bei Aktivität sofort zurück;
        // der 1-s-Update-Takt und die Rotation bleiben auf < 0,1 s genau.
        socket_select($changed, $null, $null, 0, 100000);


    //check for new socket
    if (in_array($socket, $changed)) {
        $socket_new = socket_accept($socket); //accpet new socket

        $header = socket_read($socket_new, 4096); //read data sent by the socket
        $ForwardedIP = '';
        if (!perform_handshaking($header, $socket_new, $host, $port, $ForwardedIP, $AllowedOrigins)) { //perform websocket handshake
            @socket_close($socket_new);
            $found_socket = array_search($socket, $changed);
            unset($changed[$found_socket]);
            continue;
        }

        // Ohne X-Forwarded-For (direkter Zugriff) die Peer-Adresse verwenden statt eines Restwerts.
        if ($ForwardedIP === '') {
            socket_getpeername($socket_new, $peerIp, $peerPort);
            $ForwardedIP = $peerIp . ':' . $peerPort;
        }
        $Connection = explode(":",$ForwardedIP);
        $ip = $Connection[0];
        $clientPort = $Connection[1] ?? '';
        
        // Check connection limit per IP
        if (isset($connectionCountByIP[$ip]) && $connectionCountByIP[$ip] >= MAX_CONNECTIONS_PER_IP) {
            LogNormal("Connection limit exceeded for IP: $ip (current: " . $connectionCountByIP[$ip] . ", max: " . MAX_CONNECTIONS_PER_IP . ")");
            socket_close($socket_new);
            
            //make room for new socket
            $found_socket = array_search($socket, $changed);
            unset($changed[$found_socket]);
            continue; // Skip to next iteration
        }
        
        $clients[] = $socket_new; //add socket to client array
        
        // Increment connection counter for this IP
        if (!isset($connectionCountByIP[$ip])) {
            $connectionCountByIP[$ip] = 0;
        }
        $connectionCountByIP[$ip]++;

        socket_getpeername($socket_new, $proxyip, $proxyport);

        $ProxyMatch[$proxyip . ":" . $proxyport] = array('IP' => $ip,'Port' => $clientPort);
        $clientIPs[resource_id($socket_new)] = $ip;

        $response = mask(json_encode(array('type' => 'console', 'message' => $ip . ':' . $clientPort . ' (' . cached_hostname($ip) . ') connected (' . (count($clients) - 1) . ' Clients, IP has ' . $connectionCountByIP[$ip] . ' connections)'))); //prepare json data
        LogDebug($ip . " verbunden (" . count($clients) . " Clients, IP total: " . $connectionCountByIP[$ip] . ")", 2);
        send_message($response); //notify all users about new connection

        //make room for new socket
        $found_socket = array_search($socket, $changed);
        unset($changed[$found_socket]);
    }

    //Cycle thoose pages
    if ($Timestamp <= strtotime('-' . $CycleTime . ' Seconds') AND $Cycle == True) {
        // Reload page status every 60 seconds
        if ($LastPageStatusLoad <= strtotime('-60 Seconds')) {
            $PageStatusCache = LoadPageStatus();
            $LastPageStatusLoad = time();
            LogDebug("Page status cache reloaded", 2);
        }
        
        $random = rand(1, 99);
        if ($random == 88) {
            $now = new DateTime();
            $commmand = mask(json_encode(array('type' => 'console', 'message' => "Miau @ " . $now->format('Y-m-d H:i:s')))); //prepare json data
            send_message($commmand);
            $Cat = True;
        } else {
            $Cat = False;
        }
        $Timestamp = time();
        if ($FUN) {
			$commmand = mask(json_encode(array('type' => 'command', 'message' => "!page FUN"))); //prepare json data
			send_message($commmand);
            $FUN_active = True;
        } else {
			if ($Page == 10) {
				$Page = 1;
				//Check for Snow
				try {
					// Timeout, damit ein hängender Wetterdienst nicht alle Clients blockiert (AUDIT S7).
					$weatherCtx = stream_context_create(array('http' => array('timeout' => 3)));
					$json = @file_get_contents('https://api.openweathermap.org/data/2.5/weather?q=Isernhagen,de&APPID='.APIKEY_openweathermap.'&lang=de', false, $weatherCtx);
					if ($json !== false) {
						$oIsernhagen = json_decode($json);
						if ($oIsernhagen && isset($oIsernhagen->weather[0]->description)) {
							if (strpos($oIsernhagen->weather[0]->description, "chnee") !== false) {
								$Snow = True;
							} else {
								$Snow = False;
							}
						}
					}
				} catch (Exception $e) {
					LogDebug("Weather API error: " . $e->getMessage(), 1);
				}
			} else {
				$Page++;
			}
			
			// Skip pages without data
			$maxAttempts = 10; // Prevent infinite loop
			$attempts = 0;
			while (!PageHasData($Page, $PageStatusCache) && $attempts < $maxAttempts) {
				LogNormal("Skipping empty page $Page");
				$Page++;
				if ($Page > 10) {
					$Page = 1;
				}
				$attempts++;
			}
			
			$commmand = mask(json_encode(array('type' => 'command', 'message' => "!page $Page"))); //prepare json data
			send_message($commmand); //notify all users about new connection
			$FUN_active = False;
        }
    }

    //Send update
    if ($lasttimestamp <= strtotime("-1 second")) {
        $lasttimestamp = strtotime("now");
        
        // Prepare page status for client
        $pageStatusForClient = array();
        foreach ($PageStatusCache as $pageNum => $status) {
            // hasData = true means alerts exist (warning), false means no alerts (ok)
            $pageStatusForClient[$pageNum] = $status['hasData'] ? 'warning' : 'ok';
        }
        
        $var_array = array(
            "type" => "update",
            "Page" => $Page,
            "FUN" => $FUN ? 'true' : 'false',
            "Wheel" => $Wheel ? 'true' : 'false',
            "Radio" => $Radio ? 'true' : 'false',
            "Radiostation" => $Radiostation,
            "Radiovolume" => $Radiovolume,
            "YTvolume" => $YTvolume,
            "Cat" => $Cat ? 'true' : 'false',
            "Snow" => $Snow ? 'true' : 'false',
            "BusActive" => $BusActive ? 'true' : 'false',
            "CD" => $Timestamp - strtotime('-' . ($CycleTime - 1) . ' Seconds'),
            "Cycle" => $Cycle ? 'true' : 'false',
            "SongTitle" => $SongTitle,
            "RadioStationIcon" => $RadioStationIcon,
            "PageStatus" => $pageStatusForClient
        );
        $update = mask(json_encode($var_array));
        send_message($update);
    }

    if ($FUN_active == True AND $FUN == False) {
        $FUN_active = False;
        $commmand = mask(json_encode(array('type' => 'command', 'message' => "!page $Page")));
        send_message($commmand);
        $Timestamp = time();
    }

    if ($FUN_active == False AND $FUN == True) {
        $FUN_active = True;
        $commmand = mask(json_encode(array('type' => 'command', 'message' => "!page FUN")));
        send_message($commmand);
        $Timestamp = time();
    }
	
	if ($Wheel AND $Wheel_active == False) {
		$Wheel_active = True;
        $Cycle = False;
        $commmand = mask(json_encode(array('type' => 'command', 'message' => "!page WHEEL")));
        send_message($commmand);
        $Timestamp = time();
    }
	
	if ($Wheel == False AND $Wheel_active == True) {
		$Wheel_active = False;
        $Cycle = True;
        $commmand = mask(json_encode(array('type' => 'command', 'message' => "!page $Page")));
        send_message($commmand);
        $Timestamp = time();
    }

    //loop through all connected sockets
    foreach ($changed as $changed_socket) {
        $forceDisconnect = false;

        //check for any incomming data
        while (@socket_recv($changed_socket, $buf, 65536, 0) >= 1) {
            // Frames puffern und vollständig dekodieren (Legacy: genau ein Frame ≤ 1024 Byte, AUDIT S3).
            $rid = resource_id($changed_socket);
            $recvBuffers[$rid] = ($recvBuffers[$rid] ?? '') . $buf;
            try {
                list($frames, $recvBuffers[$rid]) = ws_decode_frames($recvBuffers[$rid]);
            } catch (InvalidArgumentException $e) {
                LogNormal("Frame verworfen: " . $e->getMessage());
                $frames = array();
                $recvBuffers[$rid] = '';
            }
            if (strlen($recvBuffers[$rid]) > WS_MAX_FRAME_BYTES) {
                $recvBuffers[$rid] = '';
            }
            $senderIp = $clientIPs[$rid] ?? '';

            foreach ($frames as $frame) {
            if ($frame['opcode'] === WS_OPCODE_PING) {
                $pong = ws_encode_frame($frame['payload'], WS_OPCODE_PONG);
                @socket_write($changed_socket, $pong, strlen($pong));
                continue;
            }
            if ($frame['opcode'] === WS_OPCODE_CLOSE) {
                // Close-Handshake beantworten und Verbindung sofort freigeben. Legacy antwortete nicht;
                // Clients hielten die TCP-Verbindung dann bis zu ihrem Timeout offen (Verbindungslimit pro IP).
                $closeFrame = ws_encode_frame(substr($frame['payload'], 0, 2), WS_OPCODE_CLOSE);
                @socket_write($changed_socket, $closeFrame, strlen($closeFrame));
                $forceDisconnect = true;
                break;
            }
            if ($frame['opcode'] !== WS_OPCODE_TEXT) {
                continue; // Pong/Binär: kein Befehl
            }
            $received_text = $frame['payload'];
            $tst_msg = json_decode($received_text); //json decode

            //prepare data to be sent to client
            if (is_object($tst_msg) && !empty($tst_msg->message) && is_string($tst_msg->message)) {
                $user_message = $tst_msg->message; //message text
                LogDebug("USERMESSAGE: " . substr($user_message, 0, 500), 3);
                if ($TrustedCommandNetworks && !is_basic_command($user_message) && !ip_in_cidrs($senderIp, $TrustedCommandNetworks)) {
                    LogNormal("Befehl von nicht vertrauenswürdiger IP $senderIp verworfen: " . substr($user_message, 0, 40));
                    continue;
                }
                //!var Varname Wert – nur freigegebene Variablen mit Typprüfung (AUDIT S2)
                if (substr($user_message, 0, 4) == "!var") {
                    $parsed = ws_parse_var($user_message);
                    if ($parsed === null) {
                        LogNormal("!var abgelehnt: " . substr($user_message, 0, 80));
                    } else {
                        list($varName, $var) = $parsed;
                        LogNormal($varName . " = " . (is_bool($var) ? ($var ? 'True' : 'False') : $var));
                        $$varName = $var;
                    }
                } elseif (substr($user_message, 0, 4) == "!reg") {
                    $name = "";
                    if (socket_getpeername($changed_socket, $ip, $peerPort)) {
                        $split = explode(" ", $user_message, 2);
                        $name = isset($split[1]) ? trim($split[1]) : "";

                        $RealIP = ($ProxyMatch[$ip . ":" . $peerPort]['IP'] ?? $ip) . ":" . ($ProxyMatch[$ip . ":" . $peerPort]['Port'] ?? $peerPort);

                        $registered[$RealIP] = $name;
                    }
                } elseif ($user_message == "!dashboardlist") {
                    // Return list of registered dashboards (must be before !dashboard check!)
                    LogNormal("!dashboardlist command received");
                    $dashboardList = array();
                    foreach ($dashboards as $entry) {
                        $name = $entry['name'];
                        if (!in_array($name, $dashboardList)) {
                            $dashboardList[] = $name;
                        }
                    }
                    
                    LogNormal("Sending dashboard list with " . count($dashboardList) . " dashboards: " . implode(", ", $dashboardList));
                    
                    $var_array = array(
                        "type" => "dashboardlist",
                        "dashboards" => $dashboardList
                    );
                    $command = mask(json_encode($var_array));
                    @socket_write($changed_socket, $command, strlen($command));
                    LogDebug("Sent dashboard list: " . implode(", ", $dashboardList), 2);
                } elseif (substr($user_message, 0, 10) == "!dashboard") {
                    // Dashboard registration: !dashboard <name>
                    $split = explode(" ", $user_message, 2);
                    if (isset($split[1]) && !empty(trim($split[1]))) {
                        $dashboardName = trim($split[1]);
                        $resourceId = is_object($changed_socket) ? spl_object_id($changed_socket) : (int)$changed_socket;
                        $dashboards[$resourceId] = array('socket' => $changed_socket, 'name' => $dashboardName);
                        
                        if (socket_getpeername($changed_socket, $ip, $peerPort)) {
                            $RealIP = ($ProxyMatch[$ip . ":" . $peerPort]['IP'] ?? $ip) . ":" . ($ProxyMatch[$ip . ":" . $peerPort]['Port'] ?? $peerPort);
                            LogNormal("Dashboard '$dashboardName' registered from $RealIP");
                        }
                    }
                } elseif ($user_message == "!clientlist") {
                    foreach ($clients as $clt) {
                        if ($clt != $clients[0]) {
                            socket_getpeername($clt, $ip, $peerPort);
                            $RealIP = ($ProxyMatch[$ip . ":" . $peerPort]['IP'] ?? $ip) . ":" . ($ProxyMatch[$ip . ":" . $peerPort]['Port'] ?? $peerPort);

                            if (isset($registered[$RealIP])) {
                                $var_array = array(
                                    "type" => "clientlist",
                                    "message" => "<tr><td>" . h($RealIP) . "</td><td>" . h(cached_hostname($ProxyMatch[$ip . ":" . $peerPort]['IP'] ?? $ip)) . "</td><td>" . h($registered[$RealIP]) . "</td></tr>"
                                );
                                $command = mask(json_encode($var_array));
                                send_message($command);
                            } else {
                                $var_array = array(
                                    "type" => "clientlist",
                                    "message" => "<tr><td>" . h($RealIP) . "</td><td>(" . h(cached_hostname($ProxyMatch[$ip . ":" . $peerPort]['IP'] ?? $ip)) . ")</td><td>?</td></tr>"
                                );
                                $command = mask(json_encode($var_array));
                                send_message($command);

                                $command = mask(json_encode(array('type' => 'auth', 'message' => "AUTH_REQUEST"))); //prepare json data
                                send_message($command);
                            }
                        }
                    }
                } elseif (substr($user_message, 0, 6) == "!fpage") {
                    $split = explode(" ", $user_message);

                    $Page = $split[1];
                    LogNormal("[FORCE]Switching to: " . $Page);
                    $command = mask(json_encode(array('type' => 'command', 'message' => "!page $Page"))); //prepare json data
                    send_message($command);
                    //Normal Time + Extension Time
                    $Timestamp = time();
                } elseif ($user_message == "!repeat") {
                    LogNormal("Repeating Page $Page...");
                    $command = mask(json_encode(array('type' => 'command', 'message' => "!page $Page"))); //prepare json data
                    send_message($command);
                } elseif ($user_message == "!spin") {
                    LogNormal("Rolling...");
					$roll1 = rand(1,20);
					$roll2 = rand(1,20);
					$roll3 = rand(1,20);
                    $command = mask(json_encode(array('type' => 'command', 'message' => "!roll $roll1 $roll2 $roll3"))); //prepare json data
                    send_message($command);
                } elseif (substr($user_message, 0, 6) == "!video") {
                    // Format: !video <url> [target]
                    $parts = explode("!video ", $user_message, 2);
                    $params = trim($parts[1]);
                    
                    // Check if target is specified (format: URL|Target or just URL)
                    $target = "Alle"; // Default to all dashboards
                    $url = $params;
                    if (strpos($params, '|') !== false) {
                        $urlParts = explode('|', $params, 2);
                        $url = trim($urlParts[0]);
                        $targetParam = trim($urlParts[1]);
                        if (!empty($targetParam)) {
                            $target = $targetParam;
                        }
                    }
                    
                    // Save to database
                    try {
                        $db = new ToastDB();
                        $db->addYouTubeHistory($url);
                    } catch (Exception $e) {
                        LogDebug("Failed to save YouTube URL to database: " . $e->getMessage(), 1);
                    }

                    $response_text = mask(json_encode(array('type' => 'command', 'message' => "!video " . $url)));
                    send_message($response_text, $target);
                    
                    if ($target) {
                        LogNormal("YouTube video sent to dashboard: $target");
                    }
                } elseif (substr($user_message, 0, 7) == "!urgent") {

                    $split = explode("!urgent ", $user_message, 2);
                    $data = $split[1] ?? '';
                    $obj = json_decode($data);
                    if (!is_object($obj)) {
                        LogNormal("!urgent mit ungültigem JSON verworfen");
                        continue;
                    }
                    // Medien server-seitig cachen: ein Abruf, Timeout, Größen- und Typlimit,
                    // optional Host-Whitelist (AUDIT S8/X9). Bereits gecachte eigene URLs nicht erneut laden.
                    $ownCache = rtrim(DASHBOARD_BASE_URL, '/') . '/websocket/';
                    foreach (array('ToastSound' => array('cache', 'sound'), 'ToastPicture' => array('imgcache', 'picture')) as $field => $cfg) {
                        if (!isset($obj->$field) || $obj->$field === '' || !is_string($obj->$field)) {
                            continue;
                        }
                        $mediaUrl = trim($obj->$field);
                        if (strpos($mediaUrl, $ownCache . $cfg[0] . '/') === 0) {
                            continue;
                        }
                        if (!url_host_allowed($mediaUrl, $ToastMediaHosts)) {
                            LogNormal("$field: Host nicht erlaubt – entfernt");
                            unset($obj->$field);
                            continue;
                        }
                        $cachedName = fetch_media_to_cache($mediaUrl, __DIR__ . '/' . $cfg[0], $cfg[1], $ToastMediaMaxBytes);
                        if ($cachedName === null) {
                            LogNormal("$field nicht abrufbar oder Typ nicht erlaubt – entfernt");
                            unset($obj->$field);
                        } else {
                            $obj->$field = $ownCache . $cfg[0] . '/' . $cachedName;
                        }
                    }
                    $json = json_encode($obj);
                    
                    // Extract target if specified
                    $target = "Alle"; // Default to all dashboards
                    if (isset($obj->Target) && !empty($obj->Target)) {
                        $target = $obj->Target;
                    }
                    // Remove Target from message payload (not needed in client message)
                    if (isset($obj->Target)) {
                        unset($obj->Target);
                        $json = json_encode($obj);
                    }
                    
                    if (isset($obj->ToastHistory) && $obj->ToastHistory == "false") {
                        //Do not save
                    } else {
                        // Save to database (modern approach)
                        try {
                            $db = new ToastDB();
                            $db->addHistory((array)$obj);
                        } catch (Exception $e) {
                            LogDebug("Failed to save toast to database: " . $e->getMessage(), 1);
                        }
                    }
                    $response_text = mask(json_encode(array('type' => 'command', 'message' => '!urgent ' . $json)));
                    send_message($response_text, $target); //send data
                    
                    if ($target) {
                        LogNormal("Toast sent to dashboard: $target");
                    }
                } else {
//				    echo $user_message;
                    $response_text = mask(json_encode(array('type' => 'command', 'message' => $user_message)));
                    send_message($response_text); //send data
                }
            }
            } // foreach $frames
            if ($forceDisconnect) {
                break; // weiter zur Disconnect-Behandlung unten
            }
            break 2; //exist this loop
        }

        // Hier ankommen heißt: select() meldete den Socket lesbar, recv() lieferte aber keine Daten → EOF/Fehler.
        $buf = $forceDisconnect ? false : @socket_read($changed_socket, 1024, PHP_NORMAL_READ);
        if ($buf === false || $buf === '') { // check disconnected client
            // remove client for $clients array
            $found_socket = array_search($changed_socket, $clients);
            // Eigene Variable für den Peer-Port: $port ist der Server-Port (AUDIT S9).
            socket_getpeername($changed_socket, $ip, $peerPort);
            $proxyKey = $ip . ":" . $peerPort;
            $match = $ProxyMatch[$proxyKey] ?? array('IP' => $ip, 'Port' => $peerPort);

            // Decrement connection counter for this IP
            $realClientIP = $match['IP'];
            if (isset($connectionCountByIP[$realClientIP]) && $connectionCountByIP[$realClientIP] > 0) {
                $connectionCountByIP[$realClientIP]--;
                LogDebug("Connection count for $realClientIP decreased to " . $connectionCountByIP[$realClientIP], 3);
            }
            
            // Remove from dashboard registry if registered
            $resourceId = is_object($changed_socket) ? spl_object_id($changed_socket) : (int)$changed_socket;
            if (isset($dashboards[$resourceId])) {
                $dashboardName = $dashboards[$resourceId]['name'];
                unset($dashboards[$resourceId]);
                LogNormal("Dashboard '$dashboardName' unregistered");
            }
            
            unset($clients[$found_socket]);
            $clients = array_values($clients); // Re-index array to prevent holes
            socket_close($changed_socket);

            $RealIP = $match['IP'] . ":" . $match['Port'];

            // Zuordnungen entfernen – wuchsen im Dauerbetrieb unbegrenzt (AUDIT S5).
            unset($ProxyMatch[$proxyKey], $registered[$RealIP], $recvBuffers[$resourceId], $clientIPs[$resourceId]);

            //notify all users about disconnected connection
            $response = mask(json_encode(array('type' => 'console', 'message' => $RealIP . ' (' . cached_hostname($match['IP']) . ') disconnected')));
            LogDebug($ip . " getrennt", 2);
            send_message($response);
        }
    }
    } catch (Exception $e) {
        LogNormal($e);
        LogNormal("Error in main loop - continuing...");
        // Log full stack trace for debugging
        LogDebug("Exception trace: " . $e->getTraceAsString(), 1);
    } catch (Throwable $e) {
        LogNormal($e);
        LogNormal("Fatal error in main loop - continuing...");
        // Log full stack trace for debugging
        LogDebug("Throwable trace: " . $e->getTraceAsString(), 1);
    }
}
// close the listening socket
socket_close($socket);

function mime2ext($mime)
{
    $all_mimes = '{"png":["image\/png","image\/x-png"],"bmp":["image\/bmp","image\/x-bmp","image\/x-bitmap","image\/x-xbitmap","image\/x-win-bitmap","image\/x-windows-bmp","image\/ms-bmp","image\/x-ms-bmp","application\/bmp","application\/x-bmp","application\/x-win-bitmap"],"gif":["image\/gif"],"jpg":["image\/jpeg","image\/pjpeg"],"xspf":["application\/xspf+xml"],"vlc":["application\/videolan"],"wmv":["video\/x-ms-wmv","video\/x-ms-asf"],"au":["audio\/x-au"],"ac3":["audio\/ac3"],"flac":["audio\/x-flac"],"ogg":["audio\/ogg","video\/ogg","application\/ogg"],"kmz":["application\/vnd.google-earth.kmz"],"kml":["application\/vnd.google-earth.kml+xml"],"rtx":["text\/richtext"],"rtf":["text\/rtf"],"jar":["application\/java-archive","application\/x-java-application","application\/x-jar"],"zip":["application\/x-zip","application\/zip","application\/x-zip-compressed","application\/s-compressed","multipart\/x-zip"],"7zip":["application\/x-compressed"],"xml":["application\/xml","text\/xml"],"svg":["image\/svg+xml"],"3g2":["video\/3gpp2"],"3gp":["video\/3gp","video\/3gpp"],"mp4":["video\/mp4"],"m4a":["audio\/x-m4a","audio\/mp4"],"f4v":["video\/x-f4v"],"flv":["video\/x-flv"],"webm":["video\/webm"],"aac":["audio\/x-acc"],"m4u":["application\/vnd.mpegurl"],"pdf":["application\/pdf","application\/octet-stream"],"pptx":["application\/vnd.openxmlformats-officedocument.presentationml.presentation"],"ppt":["application\/powerpoint","application\/vnd.ms-powerpoint","application\/vnd.ms-office","application\/msword"],"docx":["application\/vnd.openxmlformats-officedocument.wordprocessingml.document"],"xlsx":["application\/vnd.openxmlformats-officedocument.spreadsheetml.sheet","application\/vnd.ms-excel"],"xl":["application\/excel"],"xls":["application\/msexcel","application\/x-msexcel","application\/x-ms-excel","application\/x-excel","application\/x-dos_ms_excel","application\/xls","application\/x-xls"],"xsl":["text\/xsl"],"mpeg":["video\/mpeg"],"mov":["video\/quicktime"],"avi":["video\/x-msvideo","video\/msvideo","video\/avi","application\/x-troff-msvideo"],"movie":["video\/x-sgi-movie"],"log":["text\/x-log"],"txt":["text\/plain"],"css":["text\/css"],"html":["text\/html"],"wav":["audio\/x-wav","audio\/wave","audio\/wav"],"xhtml":["application\/xhtml+xml"],"tar":["application\/x-tar"],"tgz":["application\/x-gzip-compressed"],"psd":["application\/x-photoshop","image\/vnd.adobe.photoshop"],"exe":["application\/x-msdownload"],"js":["application\/x-javascript"],"mp3":["audio\/mpeg","audio\/mpg","audio\/mpeg3","audio\/mp3"],"rar":["application\/x-rar","application\/rar","application\/x-rar-compressed"],"gzip":["application\/x-gzip"],"hqx":["application\/mac-binhex40","application\/mac-binhex","application\/x-binhex40","application\/x-mac-binhex40"],"cpt":["application\/mac-compactpro"],"bin":["application\/macbinary","application\/mac-binary","application\/x-binary","application\/x-macbinary"],"oda":["application\/oda"],"ai":["application\/postscript"],"smil":["application\/smil"],"mif":["application\/vnd.mif"],"wbxml":["application\/wbxml"],"wmlc":["application\/wmlc"],"dcr":["application\/x-director"],"dvi":["application\/x-dvi"],"gtar":["application\/x-gtar"],"php":["application\/x-httpd-php","application\/php","application\/x-php","text\/php","text\/x-php","application\/x-httpd-php-source"],"swf":["application\/x-shockwave-flash"],"sit":["application\/x-stuffit"],"z":["application\/x-compress"],"mid":["audio\/midi"],"aif":["audio\/x-aiff","audio\/aiff"],"ram":["audio\/x-pn-realaudio"],"rpm":["audio\/x-pn-realaudio-plugin"],"ra":["audio\/x-realaudio"],"rv":["video\/vnd.rn-realvideo"],"jp2":["image\/jp2","video\/mj2","image\/jpx","image\/jpm"],"tiff":["image\/tiff"],"eml":["message\/rfc822"],"pem":["application\/x-x509-user-cert","application\/x-pem-file"],"p10":["application\/x-pkcs10","application\/pkcs10"],"p12":["application\/x-pkcs12"],"p7a":["application\/x-pkcs7-signature"],"p7c":["application\/pkcs7-mime","application\/x-pkcs7-mime"],"p7r":["application\/x-pkcs7-certreqresp"],"p7s":["application\/pkcs7-signature"],"crt":["application\/x-x509-ca-cert","application\/pkix-cert"],"crl":["application\/pkix-crl","application\/pkcs-crl"],"pgp":["application\/pgp"],"gpg":["application\/gpg-keys"],"rsa":["application\/x-pkcs7"],"ics":["text\/calendar"],"zsh":["text\/x-scriptzsh"],"cdr":["application\/cdr","application\/coreldraw","application\/x-cdr","application\/x-coreldraw","image\/cdr","image\/x-cdr","zz-application\/zz-winassoc-cdr"],"wma":["audio\/x-ms-wma"],"vcf":["text\/x-vcard"],"srt":["text\/srt"],"vtt":["text\/vtt"],"ico":["image\/x-icon","image\/x-ico","image\/vnd.microsoft.icon"],"csv":["text\/x-comma-separated-values","text\/comma-separated-values","application\/vnd.msexcel"],"json":["application\/json","text\/json"]}';
    $all_mimes = json_decode($all_mimes, true);
    foreach ($all_mimes as $key => $value) {
        if (array_search($mime, $value) !== false) return $key;
    }
    return false;
}

function send_message($msg, $target = "Alle")
{
    global $clients, $dashboards;
    
    // Default to "Alle" if empty or null
    if ($target === null || $target === "") {
        $target = "Alle";
    }
    
    // If target is "Alle", send only to registered dashboards
    if (strtolower($target) === "alle") {
        $sentCount = 0;
        foreach ($dashboards as $entry) {
            $socket = $entry['socket'];
            if ($socket instanceof \Socket || is_resource($socket)) {
                @socket_write($socket, $msg, strlen($msg));
                $sentCount++;
            }
        }
        
        // Also send to non-dashboard clients (for backward compatibility with control, admin, etc.)
        $dashboardSockets = array();
        foreach ($dashboards as $entry) {
            $dashboardSockets[] = is_object($entry['socket']) ? spl_object_id($entry['socket']) : (int)$entry['socket'];
        }
        
        foreach ($clients as $changed_socket) {
            if ($changed_socket === $clients[0]) continue; // Skip listening socket
            
            // Skip if already sent (is a registered dashboard)
            $resourceId = is_object($changed_socket) ? spl_object_id($changed_socket) : (int)$changed_socket;
            if (in_array($resourceId, $dashboardSockets)) continue;
            
            if ($changed_socket instanceof \Socket || is_resource($changed_socket)) {
                @socket_write($changed_socket, $msg, strlen($msg));
                $sentCount++;
            }
        }
        
        LogDebug("Sent message to $sentCount clients (Alle)", 2);
        return true;
    }
    
    // Send only to specific dashboard(s)
    $sentCount = 0;
    foreach ($dashboards as $entry) {
        if ($entry['name'] === $target) {
            $socket = $entry['socket'];
            if ($socket instanceof \Socket || is_resource($socket)) {
                @socket_write($socket, $msg, strlen($msg));
                $sentCount++;
            }
        }
    }
    
    LogDebug("Sent message to $sentCount dashboard(s) with target '$target'", 2);
    return $sentCount > 0;
}


//Encode message for transfer to client.
function mask($text)
{
    return ws_encode_frame($text);
}

//handshake new client.
function perform_handshaking($receved_header, $client_conn, $host, $port, &$ForwardedIP, array $allowedOrigins = array())
{
    $headers = array();
    $lines = preg_split("/\r\n/", (string)$receved_header);
    foreach ($lines as $line) {
        $line = chop($line);
        if (preg_match('/\A(\S+): (.*)\z/', $line, $matches)) {
            $headers[strtolower($matches[1])] = $matches[2];
        }
    }
    if (isset($headers['x-forwarded-for'])) {
        $ForwardedIP = trim(explode(',', $headers['x-forwarded-for'])[0]);
    }
    // Optionale Origin-Prüfung (WEBSOCKET_ALLOWED_ORIGINS). Clients ohne Origin (PHP-Skripte) bleiben erlaubt.
    if ($allowedOrigins && isset($headers['origin'])) {
        $origin = rtrim($headers['origin'], '/');
        $ok = false;
        foreach ($allowedOrigins as $allowed) {
            if (strcasecmp(rtrim((string)$allowed, '/'), $origin) === 0) {
                $ok = true;
                break;
            }
        }
        if (!$ok) {
            LogNormal("Handshake abgelehnt: Origin " . substr($origin, 0, 100));
            return false;
        }
    }
    if (!isset($headers['sec-websocket-key'])) {
        return false;
    }
    $secKey = $headers['sec-websocket-key'];
    $secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
    //hand shaking header
    $upgrade = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
        "Upgrade: websocket\r\n" .
        "Connection: Upgrade\r\n" .
        "WebSocket-Origin: $host\r\n" .
        "WebSocket-Location: ws://$host:$port/websocket/server.php\r\n" .
        "Sec-WebSocket-Accept:$secAccept\r\n\r\n";
    socket_write($client_conn, $upgrade, strlen($upgrade));
    return true;
}
