<?php
include(__DIR__ .'/../config.php');
require_once(__DIR__ . '/db_helper.php');

$host = DASHBOARD_DOMAIN; //host
$port = '9000'; //port
$null = NULL; //null var

//Create TCP/IP sream socket
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
//reuseable port
socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

//bind socket to specified host
socket_bind($socket, 0, $port);

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
$RadioStationIcon = "";
$Radiovolume = 0.05;
$YTvolume = 0.5;
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
define("DebugLogLevel", 1);


$CycleTime = 30;


$registered = array();
$ProxyMatch = array();

function get_http_response_code($url) {
    $headers = get_headers($url);
    return substr($headers[0], 9, 3);
}

function LogDebug($message, $LogLevel = 3)
{
    if (Debug) {
        if (DebugLogLevel >= $LogLevel) {
            echo date("Y-m-d H:i") . " [DEBUG] " . $message . PHP_EOL;
        }
    }
}

function LogNormal($message)
{
    echo date("Y-m-d H:i") . " " . $message . PHP_EOL;
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
    //manage multipal connections
    $changed = $clients;
    //returns the socket resources in $changed array
    socket_select($changed, $null, $null, 0, 10);


    //check for new socket
    if (in_array($socket, $changed)) {
        $socket_new = socket_accept($socket); //accpet new socket
        $clients[] = $socket_new; //add socket to client array

        $header = socket_read($socket_new, 1024); //read data sent by the socket
        perform_handshaking($header, $socket_new, $host, $port, $ForwardedIP); //perform websocket handshake

        $Connection = explode(":",$ForwardedIP);
        $ip = $Connection[0];
        $port = $Connection[1];

        socket_getpeername($socket_new, $proxyip, $proxyport);

        $ProxyMatch[$proxyip . ":" . $proxyport] = array('IP' => $ip,'Port' => $port);

        $response = mask(json_encode(array('type' => 'console', 'message' => $ip . ':' . $port . ' (' . gethostbyaddr($ip) . ') connected (' . (count($clients) - 1) . ' Clients)'))); //prepare json data
        LogDebug($ip . " verbunden (" . count($clients) . " Clients)", 2);
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
				$json = file_get_contents('https://api.openweathermap.org/data/2.5/weather?q=Isernhagen,de&APPID='.APIKEY_openweathermap.'&lang=de');
				$oIsernhagen = json_decode($json);
				if (strpos($oIsernhagen->weather[0]->description, "chnee") !== false) {
					$Snow = True;
				} else {
					$Snow = False;
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

        //check for any incomming data
        while (@socket_recv($changed_socket, $buf, 1024, 0) >= 1) {
            $received_text = unmask($buf); //unmask data
            $tst_msg = json_decode($received_text); //json decode

            //prepare data to be sent to client
            if (!empty($tst_msg->message)) {
                $user_message = $tst_msg->message; //message text
                LogDebug("USERMESSAGE: " . $user_message, 3);
                //!var Varname Wert
                if (substr($user_message, 0, 4) == "!var") {
                    $split = explode(" ", $user_message);

                    LogNormal($split[1] . " = " . $split[2]);

                    if ($split[2] == "True") {
                        $var = True;
                    } elseif ($split[2] == "False") {
                        $var = False;
                    } else {
                        $var = urldecode($split[2]);
                    }
                    ${"$split[1]"} = $var;
                } elseif (substr($user_message, 0, 4) == "!reg") {
                    $name = "";
                    if (socket_getpeername($changed_socket, $ip, $port)) {
                        $split = explode(" ", $user_message);
                        $name = $split[1];

                        $RealIP = $ProxyMatch[$ip . ":" . $port]['IP'] . ":" . $ProxyMatch[$ip . ":" . $port]['Port'];

                        $registered[$RealIP] = $name;
                    }
                } elseif ($user_message == "!clientlist") {
                    foreach ($clients as $clt) {
                        if ($clt != $clients[0]) {
                            socket_getpeername($clt, $ip, $port);
                            $RealIP = $ProxyMatch[$ip . ":" . $port]['IP'] . ":" . $ProxyMatch[$ip . ":" . $port]['Port'];

                            if (isset($registered[$RealIP])) {
                                $var_array = array(
                                    "type" => "clientlist",
                                    "message" => "<tr><td>" . $RealIP . "</td><td>" . gethostbyaddr($ProxyMatch[$ip . ":" . $port]['IP']) . "</td><td>" . $registered[$RealIP] . "</td></tr>"
                                );
                                $command = mask(json_encode($var_array));
                                send_message($command);
                            } else {
                                $var_array = array(
                                    "type" => "clientlist",
                                    "message" => "<tr><td>" . $RealIP . "</td><td>(" . gethostbyaddr($ProxyMatch[$ip . ":" . $port]['IP']) . ")</td><td>?</td></tr>"
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
                    $split = explode("!video ", $user_message);
                    $url = trim($split[1]);
                    
                    // Save to database
                    try {
                        $db = new ToastDB();
                        $db->addYouTubeHistory($url);
                    } catch (Exception $e) {
                        LogDebug("Failed to save YouTube URL to database: " . $e->getMessage(), 1);
                    }

                    $response_text = mask(json_encode(array('type' => 'command', 'message' => $user_message)));
                    send_message($response_text);
                } elseif (substr($user_message, 0, 7) == "!urgent") {

                    $split = explode("!urgent ", $user_message);
                    $data = $split[1];
                    $obj = json_decode($data);
                    //CacheSound
                    if (isset($obj->ToastSound ) && $obj->ToastSound != "") {
                        $parts = parse_url($obj->ToastSound);
                        preg_match('/[^\/]+$/', $parts["path"], $matches);
                        $last_word = $matches[0];

                        if(get_http_response_code($obj->ToastSound) != 200){
                            unset($obj->ToastSound);
                        }else{
                            //URL is valid and available
                            $Media = file_get_contents($obj->ToastSound);
                            $hash = md5($Media);
                            $file_info = new finfo(FILEINFO_MIME_TYPE);
                            $filetype = mime2ext($file_info->buffer($Media));

                            if (!file_exists('C:/inetpub/dashboard/websocket/cache/' . $last_word)) {
                                file_put_contents('C:/inetpub/dashboard/websocket/cache/' . $hash . "." . $filetype, fopen($obj->ToastSound, 'r'));
                            }
                            $obj->ToastSound = DASHBOARD_BASE_URL . '/websocket/cache/' . $hash . "." . $filetype;
                        }
                    }
                    //CachePicture
                    if (isset($obj->ToastPicture ) && $obj->ToastPicture != "") {
                        $parts = parse_url($obj->ToastPicture);
                        preg_match('/[^\/]+$/', $parts["path"], $matches);
                        $last_word = $matches[0];
                        if(get_http_response_code($obj->ToastPicture) != 200){
                            unset($obj->ToastPicture);
                        }else{
                            $Media = file_get_contents($obj->ToastPicture);
                            $hash = md5($Media);
                            $file_info = new finfo(FILEINFO_MIME_TYPE);
                            $filetype = mime2ext($file_info->buffer($Media));

                            if (!file_exists('C:/inetpub/dashboard/websocket/imgcache/' . $last_word)) {
                                file_put_contents('C:/inetpub/dashboard/websocket/imgcache/' . $hash . "." . $filetype, fopen($obj->ToastPicture, 'r'));
                            }
                            $obj->ToastPicture = DASHBOARD_BASE_URL . '/websocket/imgcache/' . $hash . "." . $filetype;
                        }
                    }
                    $json = json_encode($obj);
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
                    send_message($response_text); //send data
                } else {
//				    echo $user_message;
                    $response_text = mask(json_encode(array('type' => 'command', 'message' => $user_message)));
                    send_message($response_text); //send data
                }
            }
            break 2; //exist this loop
        }

        $buf = @socket_read($changed_socket, 1024, PHP_NORMAL_READ);
        if ($buf === false) { // check disconnected client
            // remove client for $clients array
            $found_socket = array_search($changed_socket, $clients);
            socket_getpeername($changed_socket, $ip, $port);
            unset($clients[$found_socket]);
            socket_close($changed_socket);

            $RealIP = $ProxyMatch[$ip . ":" . $port]['IP'] . ":" . $ProxyMatch[$ip . ":" . $port]['Port'];

            //notify all users about disconnected connection
            $response = mask(json_encode(array('type' => 'console', 'message' => $RealIP . ' (' . gethostbyaddr($ProxyMatch[$ip . ":" . $port]['IP']) . ') disconnected')));
            LogDebug($ip . " getrennt", 2);
            send_message($response);
        }
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

function send_message($msg)
{
    global $clients;
    foreach ($clients as $changed_socket) {
        @socket_write($changed_socket, $msg, strlen($msg));
    }
    return true;
}


//Unmask incoming framed message
function unmask($text)
{
    $length = ord($text[1]) & 127;
    if ($length == 126) {
        $masks = substr($text, 4, 4);
        $data = substr($text, 8);
    } elseif ($length == 127) {
        $masks = substr($text, 10, 4);
        $data = substr($text, 14);
    } else {
        $masks = substr($text, 2, 4);
        $data = substr($text, 6);
    }
    $text = "";
    for ($i = 0; $i < strlen($data); ++$i) {
        $text .= $data[$i] ^ $masks[$i % 4];
    }
    return $text;
}

//Encode message for transfer to client.
function mask($text)
{
    $b1 = 0x80 | (0x1 & 0x0f);
    $length = strlen($text);

    if ($length <= 125)
        $header = pack('CC', $b1, $length);
    elseif ($length > 125 && $length < 65536)
        $header = pack('CCn', $b1, 126, $length);
    elseif ($length >= 65536)
        $header = pack('CCNN', $b1, 127, $length);
    return $header . $text;
}

//handshake new client.
function perform_handshaking($receved_header, $client_conn, $host, $port, &$ForwardedIP)
{
    $headers = array();
    $lines = preg_split("/\r\n/", $receved_header);
    foreach ($lines as $line) {
        $line = chop($line);
        if (preg_match('/\A(\S+): (.*)\z/', $line, $matches)) {
            $headers[$matches[1]] = $matches[2];
        }
    }
    if (isset($headers['X-Forwarded-For'])) {
        $ForwardedIP = $headers['X-Forwarded-For'];
    }
    if (isset($headers['Sec-WebSocket-Key'])) {
        $secKey = $headers['Sec-WebSocket-Key'];
        $secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
        //hand shaking header
        $upgrade = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
            "Upgrade: websocket\r\n" .
            "Connection: Upgrade\r\n" .
            "WebSocket-Origin: $host\r\n" .
            "WebSocket-Location: ws://$host:$port/websocket/server.php\r\n" .
            "Sec-WebSocket-Accept:$secAccept\r\n\r\n";
        socket_write($client_conn, $upgrade, strlen($upgrade));
    }
}
