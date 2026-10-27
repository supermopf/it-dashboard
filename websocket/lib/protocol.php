<?php
/**
 * WebSocket-Protokoll- und Validierungsfunktionen für websocket/server.php.
 * Ausgelagert, damit sie ohne laufenden Server getestet werden können (tests/php/run.php).
 */

const WS_OPCODE_TEXT = 0x1;
const WS_OPCODE_CLOSE = 0x8;
const WS_OPCODE_PING = 0x9;
const WS_OPCODE_PONG = 0xA;

/** Maximale Größe einer eingehenden Nachricht (Toasts mit langem Body passen bequem hinein). */
const WS_MAX_FRAME_BYTES = 1048576;

/**
 * Server → Client-Frame (unmaskiert). Ersetzt mask(): korrekte 64-Bit-Länge für Nachrichten ≥ 64 KiB.
 */
function ws_encode_frame(string $payload, int $opcode = WS_OPCODE_TEXT): string
{
    $b1 = 0x80 | ($opcode & 0x0f);
    $len = strlen($payload);
    if ($len <= 125) {
        $header = pack('CC', $b1, $len);
    } elseif ($len < 65536) {
        $header = pack('CCn', $b1, 126, $len);
    } else {
        $header = pack('CCNN', $b1, 127, ($len >> 32) & 0xFFFFFFFF, $len & 0xFFFFFFFF);
    }
    return $header . $payload;
}

/**
 * Zerlegt einen Empfangspuffer in vollständige Frames.
 * Gibt [frames, rest] zurück; frames = Liste von ['opcode' => int, 'payload' => string].
 * Unvollständige Frames bleiben im Rest und werden mit dem nächsten Empfang ergänzt.
 * Wirft InvalidArgumentException bei zu großen Frames.
 */
function ws_decode_frames(string $buffer): array
{
    $frames = array();
    $offset = 0;
    $total = strlen($buffer);
    while ($total - $offset >= 2) {
        $b1 = ord($buffer[$offset]);
        $b2 = ord($buffer[$offset + 1]);
        $opcode = $b1 & 0x0f;
        $masked = ($b2 & 0x80) !== 0;
        $len = $b2 & 0x7f;
        $pos = $offset + 2;
        if ($len === 126) {
            if ($total - $pos < 2) {
                break;
            }
            $len = unpack('n', substr($buffer, $pos, 2))[1];
            $pos += 2;
        } elseif ($len === 127) {
            if ($total - $pos < 8) {
                break;
            }
            $parts = unpack('N2', substr($buffer, $pos, 8));
            $len = ($parts[1] << 32) | $parts[2];
            $pos += 8;
        }
        if ($len > WS_MAX_FRAME_BYTES) {
            throw new InvalidArgumentException('WebSocket-Frame zu groß: ' . $len . ' Bytes');
        }
        $maskLen = $masked ? 4 : 0;
        if ($total - $pos < $maskLen + $len) {
            break; // unvollständig
        }
        $mask = $masked ? substr($buffer, $pos, 4) : '';
        $pos += $maskLen;
        $data = substr($buffer, $pos, $len);
        if ($masked && $len > 0) {
            $data = $data ^ str_repeat($mask, intdiv($len, 4) + 1);
            $data = substr($data, 0, $len);
        }
        $frames[] = array('opcode' => $opcode, 'payload' => $data);
        $offset = $pos + $len;
    }
    return array($frames, (string)substr($buffer, $offset));
}

/**
 * Erlaubte Variablen für `!var <Name> <Wert>` mit Typ.
 * Legacy setzte per `${"$name"} = $value` beliebige globale Variablen (docs/AUDIT.md S2).
 */
function ws_var_whitelist(): array
{
    return array(
        'FUN' => 'bool',
        'Cycle' => 'bool',
        'Radio' => 'bool',
        'Wheel' => 'bool',
        'Snow' => 'bool',
        'Cat' => 'bool',
        'BusActive' => 'bool',
        'Radiovolume' => 'volume',
        'YTvolume' => 'volume',
        'Radiostation' => 'url',
        'RadioStationIcon' => 'text',
        'SongTitle' => 'text',
        'CycleTime' => 'cycle',
    );
}

/**
 * Parst `!var <Name> <Wert>`. Gibt [Name, Wert] zurück oder null, wenn Name/Wert unzulässig sind.
 * Werte wie Legacy: "True"/"False" → bool, sonst urldecode().
 */
function ws_parse_var(string $message): ?array
{
    $split = explode(' ', $message);
    $name = $split[1] ?? '';
    $raw = $split[2] ?? '';
    $types = ws_var_whitelist();
    if (!isset($types[$name])) {
        return null;
    }
    switch ($types[$name]) {
        case 'bool':
            $l = strtolower($raw);
            if ($l === 'true' || $l === '1') {
                return array($name, true);
            }
            if ($l === 'false' || $l === '0') {
                return array($name, false);
            }
            return null;
        case 'volume':
            if (!is_numeric($raw)) {
                return null;
            }
            return array($name, max(0.0, min(1.0, (float)$raw)));
        case 'cycle':
            if (!ctype_digit($raw)) {
                return null;
            }
            return array($name, max(10, min(3600, (int)$raw)));
        case 'url':
            $v = urldecode($raw);
            return validate_http_url($v) !== null || $v === '' ? array($name, $v) : null;
        case 'text':
        default:
            $v = urldecode($raw);
            if (strlen($v) > 1024) {
                $v = substr($v, 0, 1024);
            }
            return array($name, $v);
    }
}

/** Nur absolute http(s)-URLs ohne Steuerzeichen. */
function validate_http_url($url): ?string
{
    if (!is_string($url) || $url === '' || strlen($url) > 2048 || preg_match('/[\x00-\x1f\s]/', $url)) {
        return null;
    }
    $parts = parse_url($url);
    if (!$parts || !isset($parts['scheme'], $parts['host'])) {
        return null;
    }
    $scheme = strtolower($parts['scheme']);
    return ($scheme === 'http' || $scheme === 'https') ? $url : null;
}

/** Prüft optional eine Host-Whitelist (leere Liste = alle Hosts erlaubt). */
function url_host_allowed(string $url, array $allowedHosts): bool
{
    if (!$allowedHosts) {
        return true;
    }
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    foreach ($allowedHosts as $allowed) {
        $allowed = strtolower(trim((string)$allowed));
        if ($allowed !== '' && ($host === $allowed || substr($host, -strlen('.' . $allowed)) === '.' . $allowed)) {
            return true;
        }
    }
    return false;
}

/** Zulässige Medientypen für den Toast-Medien-Cache: MIME → Dateiendung. */
function toast_media_types(string $kind): array
{
    if ($kind === 'sound') {
        return array('audio/mpeg' => 'mp3', 'audio/mp3' => 'mp3', 'audio/wav' => 'wav', 'audio/x-wav' => 'wav', 'audio/wave' => 'wav', 'audio/ogg' => 'ogg', 'audio/mp4' => 'm4a', 'audio/x-m4a' => 'm4a');
    }
    return array('image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp', 'video/mp4' => 'mp4', 'video/webm' => 'webm');
}

/**
 * Lädt eine Medien-URL (einmal, mit Timeout und Größenlimit) in das Cache-Verzeichnis.
 * Gibt den Dateinamen (<md5>.<ext>) zurück oder null bei Fehler / unzulässigem Typ.
 * Ersetzt den dreifachen Abruf (get_headers + file_get_contents + fopen) aus dem Legacy-Code.
 */
function fetch_media_to_cache(string $url, string $dir, string $kind, int $maxBytes, int $timeoutSeconds = 5, ?callable $fetcher = null): ?string
{
    if (validate_http_url($url) === null) {
        return null;
    }
    if ($fetcher) {
        $data = $fetcher($url, $maxBytes);
    } else {
        $ctx = stream_context_create(array(
            'http' => array('timeout' => $timeoutSeconds, 'follow_location' => 1, 'max_redirects' => 3, 'ignore_errors' => false),
            'https' => array('timeout' => $timeoutSeconds),
        ));
        $fh = @fopen($url, 'rb', false, $ctx);
        if ($fh === false) {
            return null;
        }
        stream_set_timeout($fh, $timeoutSeconds);
        $data = @stream_get_contents($fh, $maxBytes + 1);
        fclose($fh);
    }
    if (!is_string($data) || $data === '' || strlen($data) > $maxBytes) {
        return null;
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->buffer($data);
    $types = toast_media_types($kind);
    if (!isset($types[$mime])) {
        return null;
    }
    $name = md5($data) . '.' . $types[$mime];
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $target = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $name;
    if (!is_file($target)) {
        $tmp = $target . '.tmp' . getmypid();
        if (@file_put_contents($tmp, $data) === false || !@rename($tmp, $target)) {
            @unlink($tmp);
            return null;
        }
    }
    return $name;
}
