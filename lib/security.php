<?php
/**
 * Gemeinsame Sicherheits-Helfer (HTML-Escaping, Header, Client-IP, Rate Limiting, Admin-Zugriff).
 * Alle Funktionen sind optional konfigurierbar über Konstanten in config.php (siehe docs/CONFIGURATION.md).
 */

if (!function_exists('h')) {
    /** HTML-Escaping für Text- und Attributkontext. */
    function h($value): string
    {
        if ($value instanceof DateTimeInterface) {
            $value = $value->format('d.m.Y H:i');
        }
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}

/** JSON für die Einbettung in HTML/<script> (kein </script>-Ausbruch möglich). */
function json_for_html($value): string
{
    return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/** Standard-Sicherheitsheader. `$csp` = null → keine CSP setzen. */
function send_security_headers(?string $csp = null): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header_remove('X-Powered-By');
    if ($csp !== null && $csp !== '') {
        header('Content-Security-Policy: ' . $csp);
    }
}

/**
 * Client-IP. X-Forwarded-For wird nur ausgewertet, wenn der direkte Peer in TRUSTED_PROXIES steht
 * (sonst kann jeder Client die IP fälschen).
 */
function client_ip(): string
{
    $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $trusted = defined('TRUSTED_PROXIES') ? (array)TRUSTED_PROXIES : array('127.0.0.1', '::1');
    if (in_array($remote, $trusted, true) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
        $candidate = $parts[0];
        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            return $candidate;
        }
    }
    return $remote;
}

/** Prüft, ob $ip in einer der CIDR-Angaben liegt (IPv4 und IPv6). */
function ip_in_cidrs(string $ip, array $cidrs): bool
{
    $bin = @inet_pton($ip);
    if ($bin === false) {
        return false;
    }
    foreach ($cidrs as $cidr) {
        $cidr = trim((string)$cidr);
        if ($cidr === '') {
            continue;
        }
        if (strpos($cidr, '/') === false) {
            $cidr .= strlen($bin) === 4 ? '/32' : '/128';
        }
        list($net, $bits) = explode('/', $cidr, 2);
        $netBin = @inet_pton($net);
        if ($netBin === false || strlen($netBin) !== strlen($bin)) {
            continue;
        }
        $bits = (int)$bits;
        $bytes = intdiv($bits, 8);
        $rest = $bits % 8;
        if ($bytes > 0 && substr($bin, 0, $bytes) !== substr($netBin, 0, $bytes)) {
            continue;
        }
        if ($rest === 0) {
            return true;
        }
        $mask = chr((0xFF << (8 - $rest)) & 0xFF);
        if ((ord($bin[$bytes]) & ord($mask)) === (ord($netBin[$bytes]) & ord($mask))) {
            return true;
        }
    }
    return false;
}

/**
 * Einfaches Rate Limiting (Fixed Window) mit Dateien im Temp-Verzeichnis.
 * Gibt true zurück, wenn die Anfrage erlaubt ist.
 */
function rate_limit_allow(string $bucket, string $key, int $limit, int $windowSeconds, ?string $dir = null): bool
{
    if ($limit <= 0) {
        return true;
    }
    $dir = $dir ?? (sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'itdashboard-ratelimit');
    if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
        return true; // Ohne Schreibrecht nicht blockieren (fail open), aber protokollieren.
    }
    $window = intdiv(time(), max(1, $windowSeconds));
    $file = $dir . DIRECTORY_SEPARATOR . preg_replace('/[^a-z0-9_.-]/i', '_', $bucket . '_' . $key) . '.json';
    $fh = @fopen($file, 'c+');
    if ($fh === false) {
        return true;
    }
    try {
        flock($fh, LOCK_EX);
        $raw = stream_get_contents($fh);
        $data = json_decode($raw ?: '', true);
        if (!is_array($data) || ($data['w'] ?? null) !== $window) {
            $data = array('w' => $window, 'n' => 0);
        }
        $data['n']++;
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode($data));
        return $data['n'] <= $limit;
    } finally {
        flock($fh, LOCK_UN);
        fclose($fh);
    }
}

/**
 * Zugriffsschutz für Admin- und Steuerseiten.
 *
 * Aktiv nur, wenn mindestens eine der Konstanten gesetzt ist:
 *   ADMIN_ALLOWED_USERS    – Liste erlaubter Windows-/IIS-Benutzer (REMOTE_USER / AUTH_USER, case-insensitiv)
 *   ADMIN_ALLOWED_NETWORKS – Liste erlaubter Netze (CIDR)
 * Ohne Konfiguration bleibt das bisherige Verhalten (offen) erhalten – siehe docs/SECURITY.md.
 */
function require_admin_access(): void
{
    $users = defined('ADMIN_ALLOWED_USERS') ? array_filter((array)ADMIN_ALLOWED_USERS) : array();
    $nets = defined('ADMIN_ALLOWED_NETWORKS') ? array_filter((array)ADMIN_ALLOWED_NETWORKS) : array();
    if (!$users && !$nets) {
        return;
    }
    $ok = false;
    if ($users) {
        $user = (string)($_SERVER['REMOTE_USER'] ?? $_SERVER['AUTH_USER'] ?? '');
        $short = strtolower(preg_replace('/^.*\\\\/', '', $user));
        foreach ($users as $allowed) {
            $allowed = strtolower((string)$allowed);
            if ($allowed !== '' && ($allowed === strtolower($user) || $allowed === $short)) {
                $ok = true;
                break;
            }
        }
    }
    if (!$ok && $nets) {
        $ok = ip_in_cidrs(client_ip(), $nets);
    }
    if (!$ok) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Zugriff verweigert.';
        exit;
    }
}
