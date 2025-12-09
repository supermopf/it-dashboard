<?php
require('../config.php');

// WebSocket Protocol Functions
function hybi10Decode($data)
{
    $bytes = $data;
    $secondByte = sprintf('%08b', ord($bytes[1]));
    $masked = ($secondByte[0] == '1');
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
        
        $decodedData = '';
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
    $payloadLength = strlen($payload);

    switch ($type) {
        case 'text':
            $frameHead[0] = 129; // FIN, Text-Frame (10000001)
            break;
        case 'close':
            $frameHead[0] = 136; // FIN, Close Frame(10001000)
            break;
        case 'ping':
            $frameHead[0] = 137; // FIN, Ping frame (10001001)
            break;
        case 'pong':
            $frameHead[0] = 138; // FIN, Pong frame (10001010)
            break;
    }

    // Set mask and payload length (using 1, 3 or 9 bytes)
    if ($payloadLength > 65535) {
        $payloadLengthBin = str_split(sprintf('%064b', $payloadLength), 8);
        $frameHead[1] = ($masked === true) ? 255 : 127;
        for ($i = 0; $i < 8; $i++) {
            $frameHead[$i + 2] = bindec($payloadLengthBin[$i]);
        }
        if ($frameHead[2] > 127) {
            return false; // Frame too big
        }
    } elseif ($payloadLength > 125) {
        $payloadLengthBin = str_split(sprintf('%016b', $payloadLength), 8);
        $frameHead[1] = ($masked === true) ? 254 : 126;
        $frameHead[2] = bindec($payloadLengthBin[0]);
        $frameHead[3] = bindec($payloadLengthBin[1]);
    } else {
        $frameHead[1] = ($masked === true) ? $payloadLength + 128 : $payloadLength;
    }

    // Convert frame-head to string
    foreach (array_keys($frameHead) as $i) {
        $frameHead[$i] = chr($frameHead[$i]);
    }

    if ($masked === true) {
        $mask = array();
        for ($i = 0; $i < 4; $i++) {
            $mask[$i] = chr(rand(0, 255));
        }
        $frameHead = array_merge($frameHead, $mask);
    }
    
    $frame = implode('', $frameHead);
    
    // Append payload to frame
    for ($i = 0; $i < $payloadLength; $i++) {
        $frame .= ($masked === true) ? $payload[$i] ^ $mask[$i % 4] : $payload[$i];
    }

    return $frame;
}

function connectWebSocket($host, $port, $origin)
{
    $header = "GET / HTTP/1.1\r\n" .
              "Upgrade: WebSocket\r\n" .
              "Connection: Upgrade\r\n" .
              "Origin: $origin\r\n" .
              "Host: $host\r\n" .
              "Sec-WebSocket-Key: asdasdaas76da7sd6asd6as7d\r\n\r\n";
    
    $sock = @fsockopen($host, $port, $errno, $errstr, 2);
    if (!$sock) {
        throw new Exception("WebSocket connection failed: $errstr ($errno)");
    }
    
    fwrite($sock, $header);
    fread($sock, 2000); // Read handshake response
    
    return $sock;
}

function sendWebSocketMessage($host, $port, $origin, $message)
{
    try {
        error_log("[SongTitle] Attempting to send message to WebSocket...");
        $sock = connectWebSocket($host, $port, $origin);
        $encoded = hybi10Encode(json_encode($message));
        
        error_log("[SongTitle] Message JSON: " . json_encode($message));
        error_log("[SongTitle] Encoded message length: " . strlen($encoded));
        
        $written = fwrite($sock, $encoded);
        error_log("[SongTitle] Bytes written to socket: " . ($written ?: "0"));
        
        // Read response to confirm receipt
        $response = fread($sock, 2000);
        if ($response) {
            error_log("[SongTitle] WebSocket response received: " . strlen($response) . " bytes");
        }
        
        fclose($sock);
        error_log("[SongTitle] WebSocket message sent successfully");
    } catch (Exception $e) {
        error_log("[SongTitle] ERROR sending WebSocket message: " . $e->getMessage());
        throw $e;
    }
}

function getMp3StreamTitle($streamingUrl, $interval = 19200, $offset = 0, $headers = true, $maxRetries = 5)
{
    static $retryCount = 0;
    
    $needle = 'StreamTitle=';
    $ua = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/27.0.1453.110 Safari/537.36';
    
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => 'Icy-MetaData: 1',
            'user_agent' => $ua,
            'timeout' => 5
        ]
    ];

    // Try to get icy-metaint from headers
    if ($headers) {
        error_log("[SongTitle] Fetching headers from: $streamingUrl");
        $headerList = @get_headers($streamingUrl);
        
        if ($headerList) {
            foreach ($headerList as $h) {
                error_log("[SongTitle] Header: $h");
                if (stripos($h, 'icy-metaint') !== false) {
                    $parts = explode(':', $h);
                    if (isset($parts[1])) {
                        $interval = (int)trim($parts[1]);
                        error_log("[SongTitle] Found icy-metaint interval: $interval");
                        
                        // Check if metadata is disabled (icy-metaint: 0)
                        if ($interval === 0) {
                            error_log("[SongTitle] WARNING: Stream has icy-metaint=0, no metadata available in stream");
                            $retryCount = 0;
                            return "";
                        }
                        break;
                    }
                }
            }
        } else {
            error_log("[SongTitle] WARNING: Could not fetch headers from stream");
        }
    }

    $context = stream_context_create($opts);
    $stream = @fopen($streamingUrl, 'r', false, $context);
    
    if (!$stream) {
        $error = error_get_last();
        error_log("[SongTitle] ERROR: Unable to open stream [$streamingUrl] - " . ($error['message'] ?? 'Unknown error'));
        throw new Exception("Unable to open stream [$streamingUrl]");
    }

    error_log("[SongTitle] Reading $interval bytes from offset $offset");
    $buffer = stream_get_contents($stream, $interval, $offset);
    fclose($stream);

    if (strpos($buffer, $needle) !== false) {
        $title = explode($needle, $buffer)[1];
        $extracted = substr($title, 1, strpos($title, ';') - 2);
        error_log("[SongTitle] SUCCESS: Found song title: $extracted");
        $retryCount = 0; // Reset counter
        return $extracted;
    }
    
    // Prevent infinite recursion
    if ($retryCount >= $maxRetries) {
        error_log("[SongTitle] WARNING: No metadata found after $maxRetries attempts");
        $retryCount = 0;
        return "";
    }
    
    // Recursively try next chunk
    $retryCount++;
    error_log("[SongTitle] No metadata in chunk, trying next (attempt $retryCount/$maxRetries)");
    return getMp3StreamTitle($streamingUrl, $interval, $offset + $interval, false, $maxRetries);
}

function getRadioBoxMetadata($streamUrl)
{
    // Map known stream URLs/domains to RadioBox identifiers
    // Based on onlineradiobox.com station IDs
    $radioBoxMap = [
        // Your specific stations
        'radio-hannover' => 'de.radiohannover',
        'radiohannover' => 'de.radiohannover',
        'ndr1niedersachsen' => 'de.ndr1niedersachsen',
        'ndrinfo' => 'de.ndrinfo',
        'ndrplus' => 'de.ndrplus',
        'njoy' => 'de.njoy',
        '1live-diggi' => 'de.1livediggi',
        '1live/diggi' => 'de.1livediggi',
        '89.0rtl' => 'de.890rtl',
        'stream.89.0rtl' => 'de.890rtl',
        'bayern1' => 'de.bayern1',
        'br-br1' => 'de.bayern1',
        'mdr' => 'de.mdrsachsenanhalt',
        'peppermint' => 'de.peppermintfm',
        'planetradio' => 'de.planetradio',
        'radio21' => 'de.radio21',
        'radiobollerwagen' => 'de.radiobollerwagen',
        'radiofritz' => 'de.fritz',
        'rbb-fritz' => 'de.fritz',
        'radiotop40' => 'de.radiotop40',
        'schlagerparadies' => 'de.schlagerparadies',
        'sunshine-live' => 'de.sunshinelive',
        
        // Common German stations
        'antenne-bayern' => 'de.antennebayern',
        'antennebayern' => 'de.antennebayern',
        'swr3' => 'de.swr3',
        'ffh' => 'de.planetffh',
        'bigfm' => 'de.bigfm',
        '1live' => 'de.wdr1live',
        'bayern3' => 'de.bayern3',
        'ndr2' => 'de.ndr2',
        'mdr-jump' => 'de.mdrjump',
        'rock-antenne' => 'de.rockantenne',
    ];
    
    // Try to find RadioBox identifier from stream URL
    $radioBoxId = null;
    foreach ($radioBoxMap as $pattern => $id) {
        if (stripos($streamUrl, $pattern) !== false) {
            $radioBoxId = $id;
            error_log("[SongTitle] Matched RadioBox ID from map: $id");
            break;
        }
    }
    
    if (!$radioBoxId) {
        // Try to extract station name from URL for generic lookup
        if (preg_match('/\/\/([^\.]+)[\.-]/', $streamUrl, $matches)) {
            $stationName = $matches[1];
            // Remove common prefixes/suffixes and normalize
            $stationName = str_replace(['stream', 'radio', 'live', 'wdr', 'ndr', 'br'], '', strtolower($stationName));
            $stationName = trim($stationName, '-_');
            
            // Try common patterns: de.stationname
            if (!empty($stationName)) {
                $radioBoxId = "de.$stationName";
                error_log("[SongTitle] Attempting RadioBox with auto-detected ID: $radioBoxId");
            }
        }
    }
    
    if ($radioBoxId) {
        $scraperUrl = "https://scraper.onlineradiobox.com/$radioBoxId?l=0";
        error_log("[SongTitle] Trying OnlineRadioBox scraper: $scraperUrl");
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $scraperUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200 && $response) {
            $data = json_decode($response, true);
            
            if (isset($data['title']) && !empty($data['title'])) {
                error_log("[SongTitle] RadioBox API response: " . $data['title']);
                return $data['title'];
            } elseif (isset($data['iArtist']) && isset($data['iName'])) {
                $title = trim($data['iArtist']) . ' - ' . trim($data['iName']);
                error_log("[SongTitle] RadioBox API response (artist/name): $title");
                return $title;
            } else {
                error_log("[SongTitle] RadioBox returned data but no title field: " . substr($response, 0, 200));
            }
        } else {
            error_log("[SongTitle] RadioBox API returned HTTP $httpCode");
        }
    }
    
    return "";
}

function getRadioHostMetadata($streamUrl)
{
    // Extract radiohost.de stream info from URL
    if (preg_match('/radiohost\.de\/([^\/\?]+)/', $streamUrl, $matches)) {
        $streamName = $matches[1];
        
        // Try to get metadata from radiohost.de API
        $apiUrl = "https://radiohannover.stream08.radiohost.de/status-json.xsl";
        error_log("[SongTitle] Trying radiohost.de API: $apiUrl");
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200 && $response) {
            $data = json_decode($response, true);
            error_log("[SongTitle] API Response: " . substr($response, 0, 200) . "...");
            
            // Try different possible fields
            if (isset($data['icestats']['source'])) {
                if (is_array($data['icestats']['source'])) {
                    foreach ($data['icestats']['source'] as $source) {
                        if (isset($source['title']) && !empty($source['title'])) {
                            error_log("[SongTitle] Found title in API: " . $source['title']);
                            return $source['title'];
                        }
                    }
                } elseif (isset($data['icestats']['source']['title'])) {
                    return $data['icestats']['source']['title'];
                }
            }
        }
    }
    
    return "";
}

function getDiviconMetadata($streamUrl)
{
    // Try to get metadata from divicon redirect chain
    if (preg_match('/divicon-stream\.net/', $streamUrl)) {
        error_log("[SongTitle] Detected divicon stream, trying to extract metadata URL");
        
        // First, try the current playing info endpoint
        if (preg_match('/https?:\/\/([^\/]+)\/([^\/]+)/', $streamUrl, $matches)) {
            $baseDomain = $matches[1];
            $streamName = $matches[2];
            
            // Try currentplaying endpoint (common for divicon streams)
            $currentPlayingUrl = "https://$baseDomain/currentsong?sid=1";
            error_log("[SongTitle] Trying currentsong API: $currentPlayingUrl");
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $currentPlayingUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode == 200 && !empty($response)) {
                $response = trim($response);
                error_log("[SongTitle] Currentsong response: $response");
                if (!empty($response) && $response !== 'radiohannover-live') {
                    return $response;
                }
            }
        }
        
        // Extract the base domain/path from original URL
        if (preg_match('/https?:\/\/([^\/]+)/', $streamUrl, $matches)) {
            $baseDomain = $matches[1];
            $apiUrl = "https://$baseDomain/status-json.xsl";
            
            error_log("[SongTitle] Trying divicon API: $apiUrl");
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode == 200 && $response) {
                $data = json_decode($response, true);
                error_log("[SongTitle] API Response (first 1000 chars): " . substr($response, 0, 1000));
                
                // Try different possible fields for Icecast/SHOUTcast
                if (isset($data['icestats']['source'])) {
                    $sources = $data['icestats']['source'];
                    
                    // Handle both single source and array of sources
                    if (!isset($sources[0])) {
                        $sources = [$sources];
                    }
                    
                    error_log("[SongTitle] Found " . count($sources) . " source(s) in API response");
                    
                    foreach ($sources as $index => $source) {
                        error_log("[SongTitle] Source $index keys: " . implode(', ', array_keys($source)));
                        
                        // Try various title fields (in order of preference)
                        if (isset($source['title']) && !empty($source['title']) && $source['title'] !== 'Unknown') {
                            error_log("[SongTitle] Found title in API: " . $source['title']);
                            return $source['title'];
                        }
                        if (isset($source['artist']) && isset($source['song'])) {
                            $title = trim($source['artist']) . ' - ' . trim($source['song']);
                            error_log("[SongTitle] Found artist/song in API: " . $title);
                            return $title;
                        }
                        // Check for yp_currently_playing (used by some Icecast servers)
                        if (isset($source['yp_currently_playing']) && !empty($source['yp_currently_playing'])) {
                            error_log("[SongTitle] Found yp_currently_playing: " . $source['yp_currently_playing']);
                            return $source['yp_currently_playing'];
                        }
                    }
                    error_log("[SongTitle] No title field found in any source (only server_name available)");
                } else {
                    error_log("[SongTitle] No 'source' field in icestats");
                }
            } else {
                error_log("[SongTitle] API returned HTTP $httpCode");
            }
        }
    }
    
    return "";
}

// Main execution
$host = '127.0.0.1';
$port = 9000;
$origin = DASHBOARD_BASE_URL . "/monitor/";
$songTitle = "";

try {
    // Connect and wait for radio station info
    error_log("[SongTitle] Connecting to WebSocket server $host:$port");
    $sock = connectWebSocket($host, $port, $origin);
    
    $radiostationUrl = null;
    $timeout = time() + 10; // 10 second timeout
    
    while (!$radiostationUrl && time() < $timeout) {
        $wsdata = fread($sock, 2000);
        $msg = json_decode(hybi10Decode($wsdata));
        
        if (isset($msg->type) && $msg->type == "update" && isset($msg->Radiostation)) {
            $radiostationUrl = $msg->Radiostation;
            error_log("[SongTitle] Received radio station URL: $radiostationUrl");
        }
    }
    fclose($sock);

    if (!$radiostationUrl) {
        error_log("[SongTitle] WARNING: No radio station URL received from WebSocket");
    }

    // Get current song title from stream
    if ($radiostationUrl) {
        try {
            error_log("[SongTitle] Attempting to extract song title from stream");
            
            // Try standard icy-metadata first
            $result = getMp3StreamTitle($radiostationUrl);
            
            // If that fails, try OnlineRadioBox scraper (works for many stations)
            if (empty($result)) {
                error_log("[SongTitle] Standard metadata failed, trying OnlineRadioBox scraper");
                $result = getRadioBoxMetadata($radiostationUrl);
            }
            
            // If that fails too, try divicon/icecast APIs
            if (empty($result)) {
                error_log("[SongTitle] RadioBox failed, trying divicon/icecast APIs");
                $result = getDiviconMetadata($radiostationUrl);
            }
            
            // Validate format: "Artist - Title"
            if ($result && preg_match("/^.+?\s*-\s*.+$/", $result)) {
                $songTitle = $result;
                error_log("[SongTitle] Valid song title found: $songTitle");
            } else if ($result && $result !== 'radiohannover-live' && !preg_match('/^radio.*-live$/i', $result)) {
                // Accept any non-empty result that's not just the station name
                $songTitle = $result;
                error_log("[SongTitle] Song title found (non-standard format): $songTitle");
            } else {
                error_log("[SongTitle] INFO: Station does not provide song metadata - sending empty update");
                $songTitle = "";
            }
        } catch (Exception $e) {
            error_log("[SongTitle] ERROR: Failed to get song title - " . $e->getMessage());
        }
    }

    // Send song title back to WebSocket server
    error_log("[SongTitle] Preparing to send song title to WebSocket: " . ($songTitle ?: "(empty)"));
    
    $messageData = [
        "type" => "command",
        "message" => "!var SongTitle " . urlencode($songTitle)
    ];
    
    error_log("[SongTitle] Message to send: " . json_encode($messageData));
    
    sendWebSocketMessage($host, $port, $origin, $messageData);
    
    error_log("[SongTitle] Script completed successfully");

} catch (Exception $e) {
    error_log("[SongTitle] FATAL ERROR: " . $e->getMessage());
    exit(1);
}