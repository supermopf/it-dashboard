<?php
/**
 * Kleiner HTTP-Client (curl) für JSON-APIs: Timeout, optionales internes Root-CA (CA_BUNDLE), keine Ausgabe
 * bei Fehlern. Gibt ['status' => int, 'json' => mixed|null, 'body' => string|false] zurück.
 */
function http_json(string $method, string $url, array $headers = array(), $body = null, int $timeout = 15): array
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(5, $timeout));
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    $headers[] = 'Accept: application/json';
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : json_encode($body));
        $headers[] = 'Content-Type: application/json';
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    // PHP-curl nutzt den Windows-Zertifikatsspeicher nicht – internes Root-CA explizit angeben
    if (defined('CA_BUNDLE') && is_readable(CA_BUNDLE)) {
        curl_setopt($ch, CURLOPT_CAINFO, CA_BUNDLE);
    }
    $response = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $json = is_string($response) ? json_decode($response, true) : null;
    return array('status' => $status, 'json' => $json, 'body' => $response);
}

/** Ergebnis einer Funktion für $ttl Sekunden im Temp-Verzeichnis zwischenspeichern (nur bei Erfolg != null). */
function cached_json(string $key, int $ttl, callable $producer)
{
    $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'itdashboard-' . preg_replace('/[^a-z0-9_.-]/i', '_', $key) . '.json';
    if (is_file($file) && filemtime($file) > time() - $ttl) {
        $data = json_decode((string)@file_get_contents($file), true);
        if ($data !== null) {
            return $data;
        }
    }
    $data = $producer();
    if ($data !== null) {
        @file_put_contents($file, json_encode($data), LOCK_EX);
        return $data;
    }
    // Quelle nicht erreichbar → letzten bekannten Stand verwenden (auch wenn abgelaufen)
    if (is_file($file)) {
        return json_decode((string)@file_get_contents($file), true);
    }
    return null;
}
