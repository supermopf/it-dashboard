<?php
/**
 * Validierung der REST-API (websocket/api.php). Ohne Seiteneffekte, damit testbar (tests/php/run.php).
 */

require_once __DIR__ . '/protocol.php';

/** Bekannte Toast-Felder. Unbekannte Felder werden verworfen (Legacy: komplettes $_POST weitergereicht). */
const TOAST_FIELDS = array(
    'ToastSubject', 'ToastBody', 'ToastPicture', 'ToastSound', 'ToastTime', 'ToastVolume',
    'ToastColor', 'ToastTextColor', 'ToastHistory', 'ToastVideoNoRepeat', 'Target',
);

/** Felder, von denen mindestens eines vorhanden sein muss (wie bisher, api.php:124-131). */
const TOAST_TRIGGER_FIELDS = array('ToastSubject', 'ToastBody', 'ToastPicture', 'ToastSound', 'ToastTime', 'ToastVolume');

function is_toast_request(array $post): bool
{
    foreach (TOAST_TRIGGER_FIELDS as $f) {
        if (isset($post[$f])) {
            return true;
        }
    }
    return false;
}

/**
 * Prüft und normalisiert einen Toast. Gibt ['data' => array, 'errors' => array] zurück.
 * Leere Strings bleiben leer (der Monitor verwendet dann Standardwerte).
 */
function validate_toast(array $post): array
{
    $data = array();
    $errors = array();
    foreach (TOAST_FIELDS as $f) {
        if (!array_key_exists($f, $post)) {
            continue;
        }
        $v = $post[$f];
        if (!is_scalar($v)) {
            $errors[$f] = 'muss ein einfacher Wert sein';
            continue;
        }
        $v = trim((string)$v);
        if ($v === '') {
            $data[$f] = '';
            continue;
        }
        switch ($f) {
            case 'ToastSubject':
                if (mb_strlen($v) > 500) {
                    $errors[$f] = 'max. 500 Zeichen';
                }
                break;
            case 'ToastBody':
                if (mb_strlen($v) > 20000) {
                    $errors[$f] = 'max. 20000 Zeichen';
                }
                break;
            case 'ToastPicture':
            case 'ToastSound':
                if (validate_http_url($v) === null) {
                    $errors[$f] = 'muss eine absolute http(s)-URL sein';
                }
                break;
            case 'ToastTime':
                if (!ctype_digit($v) || (int)$v < 1000 || (int)$v > 3600000) {
                    $errors[$f] = 'Millisekunden zwischen 1000 und 3600000';
                }
                break;
            case 'ToastVolume':
                if (!is_numeric($v) || (float)$v < 0 || (float)$v > 1) {
                    $errors[$f] = 'Zahl zwischen 0 und 1';
                }
                break;
            case 'ToastColor':
            case 'ToastTextColor':
                if (!preg_match('/^(#[0-9a-f]{3,8}|rgba?\([\d\s.,%]+\)|[a-z]{3,20})$/i', $v)) {
                    $errors[$f] = 'ungültige Farbe';
                }
                break;
            case 'ToastHistory':
            case 'ToastVideoNoRepeat':
                $l = strtolower($v);
                if ($l !== 'true' && $l !== 'false') {
                    $errors[$f] = '"true" oder "false"';
                }
                $v = $l;
                break;
            case 'Target':
                if (!preg_match('/^[\p{L}\p{N} ._-]{1,64}$/u', $v)) {
                    $errors[$f] = 'max. 64 Zeichen (Buchstaben, Ziffern, Leerzeichen, . _ -)';
                }
                break;
        }
        $data[$f] = $v;
    }
    return array('data' => $data, 'errors' => $errors);
}

/** YouTube-URL prüfen (youtube.com, youtu.be, youtube-nocookie.com). */
function validate_youtube_url($url): ?string
{
    $url = is_string($url) ? trim($url) : '';
    if (validate_http_url($url) === null) {
        return null;
    }
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    $host = preg_replace('/^(www|m)\./', '', $host);
    return in_array($host, array('youtube.com', 'youtu.be', 'youtube-nocookie.com'), true) ? $url : null;
}

/** Same-Origin-Anfrage (Admin-Seiten auf demselben Host)? */
function is_same_origin_request(array $server): bool
{
    $host = strtolower((string)($server['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return false;
    }
    foreach (array('HTTP_ORIGIN', 'HTTP_REFERER') as $h) {
        if (!empty($server[$h])) {
            $p = parse_url((string)$server[$h]);
            $originHost = strtolower(($p['host'] ?? '') . (isset($p['port']) ? ':' . $p['port'] : ''));
            return $originHost === $host;
        }
    }
    return false;
}
