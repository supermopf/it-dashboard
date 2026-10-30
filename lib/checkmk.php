<?php
/**
 * Zugriff auf OMD/Checkmk über die REST-API (Checkmk ≥ 2.1).
 *
 * Konfiguration (config.php):
 *   CHECKMK_BASE_URL  z. B. https://monitoring.example.local/mysite   (wie für page8.php)
 *   CHECKMK_USER      Automationsbenutzer
 *   CHECKMK_SECRET    dessen Automation-Secret
 *   OMD_SITE          optional, Standard: letzter Pfadteil von CHECKMK_BASE_URL
 */
require_once(__DIR__ . '/http.php');

function checkmk_configured(): bool
{
    return defined('CHECKMK_BASE_URL') && CHECKMK_BASE_URL !== '' && defined('CHECKMK_USER') && CHECKMK_USER !== '';
}

function checkmk_site(): string
{
    if (defined('OMD_SITE') && OMD_SITE !== '') {
        return OMD_SITE;
    }
    $path = trim((string)parse_url(CHECKMK_BASE_URL, PHP_URL_PATH), '/');
    $parts = explode('/', $path);
    return (string)end($parts);
}

/**
 * Ein Aufruf der REST-API. $query: Name → Wert oder Liste von Werten (wird als name=a&name=b kodiert,
 * so erwartet Checkmk z. B. `columns`). Gibt das dekodierte JSON zurück oder null bei Fehlern.
 */
function checkmk_api(string $method, string $path, array $query = array(), $body = null, int $timeout = 20): ?array
{
    if (!checkmk_configured()) {
        return null;
    }
    $pairs = array();
    foreach ($query as $name => $value) {
        foreach ((array)$value as $v) {
            $pairs[] = rawurlencode($name) . '=' . rawurlencode(is_string($v) ? $v : json_encode($v));
        }
    }
    $url = rtrim(CHECKMK_BASE_URL, '/') . '/check_mk/api/1.0' . $path . ($pairs ? '?' . implode('&', $pairs) : '');
    $res = http_json($method, $url, array('Authorization: Bearer ' . CHECKMK_USER . ' ' . (defined('CHECKMK_SECRET') ? CHECKMK_SECRET : '')), $body, $timeout);
    if ($res['status'] < 200 || $res['status'] >= 300 || !is_array($res['json'])) {
        error_log('[Checkmk] ' . $method . ' ' . $path . ' → HTTP ' . $res['status']);
        return null;
    }
    return $res['json'];
}

/** Livestatus-Filter für die REST-API bauen: checkmk_filter('state', '!=', 0) */
function checkmk_filter(string $column, string $op, $value): array
{
    return array('op' => $op, 'left' => $column, 'right' => (string)$value);
}

/** Dienste nach Livestatus-Filter. Gibt Liste von Spalten-Arrays zurück (oder null bei Fehler). */
function checkmk_services(array $filter, array $columns): ?array
{
    $res = checkmk_api('GET', '/domain-types/service/collections/all', array('query' => array($filter), 'columns' => $columns));
    if ($res === null) {
        return null;
    }
    $out = array();
    foreach ($res['value'] ?? array() as $item) {
        if (isset($item['extensions']) && is_array($item['extensions'])) {
            $out[] = $item['extensions'];
        }
    }
    return $out;
}

/** Hosts nach Livestatus-Filter. */
function checkmk_hosts(array $filter, array $columns): ?array
{
    $res = checkmk_api('GET', '/domain-types/host/collections/all', array('query' => array($filter), 'columns' => $columns));
    if ($res === null) {
        return null;
    }
    $out = array();
    foreach ($res['value'] ?? array() as $item) {
        if (isset($item['extensions']) && is_array($item['extensions'])) {
            $out[] = $item['extensions'];
        }
    }
    return $out;
}

/**
 * Verlauf einer Metrik (RRD) eines Dienstes. Gibt [[unix_ts, wert|null], …] zurück oder null bei Fehler.
 */
function checkmk_metric_history(string $host, string $service, string $metric, int $start, int $end, string $reduce = 'average'): ?array
{
    $res = checkmk_api('POST', '/domain-types/metric/actions/get/invoke', array(), array(
        'time_range' => array('start' => date('c', $start), 'end' => date('c', $end)),
        'reduce' => $reduce,
        'site' => checkmk_site(),
        'host_name' => $host,
        'service_description' => $service,
        'type' => 'single_metric',
        'metric_id' => $metric,
    ));
    if ($res === null) {
        return null;
    }
    // Antwortformat je nach Version: metrics[].data_points (2.2+) bzw. curves[].rrddata (2.1)
    $curve = $res['metrics'][0] ?? $res['curves'][0] ?? null;
    $points = $curve['data_points'] ?? $curve['rrddata'] ?? null;
    if (!is_array($points)) {
        return null;
    }
    $step = (int)($res['step'] ?? 60);
    $from = isset($res['time_range']['start']) ? (is_numeric($res['time_range']['start']) ? (int)$res['time_range']['start'] : strtotime($res['time_range']['start'])) : $start;
    $out = array();
    foreach (array_values($points) as $i => $v) {
        $out[] = array($from + $i * $step, $v === null ? null : round((float)$v, 1));
    }
    return $out;
}

/** Aktuelle Probleme (Dienste + Hosts), nicht quittiert und nicht in Wartung. Null bei Fehler. */
function checkmk_problems(bool $includeWarnings = true): ?array
{
    $notAcked = checkmk_filter('acknowledged', '=', 0);
    $noDowntime = checkmk_filter('scheduled_downtime_depth', '=', 0);
    $services = checkmk_services(
        array('op' => 'and', 'expr' => array(
            $includeWarnings ? checkmk_filter('state', '!=', 0) : checkmk_filter('state', '=', 2),
            $notAcked,
            $noDowntime,
            checkmk_filter('host_scheduled_downtime_depth', '=', 0),
        )),
        array('host_name', 'description', 'state', 'plugin_output', 'last_state_change')
    );
    $hosts = checkmk_hosts(
        array('op' => 'and', 'expr' => array(checkmk_filter('state', '!=', 0), $notAcked, $noDowntime)),
        array('name', 'state', 'plugin_output', 'last_state_change')
    );
    if ($services === null && $hosts === null) {
        return null;
    }
    $out = array();
    foreach ($hosts ?? array() as $h) {
        $out[] = array(
            'host' => (string)($h['name'] ?? ''),
            'service' => 'Host nicht erreichbar',
            'state' => 2, // Host DOWN/UNREACH immer kritisch
            'output' => (string)($h['plugin_output'] ?? ''),
            'since' => (int)($h['last_state_change'] ?? 0),
        );
    }
    foreach ($services ?? array() as $s) {
        $out[] = array(
            'host' => (string)($s['host_name'] ?? ''),
            'service' => (string)($s['description'] ?? ''),
            'state' => (int)($s['state'] ?? 3),
            'output' => (string)($s['plugin_output'] ?? ''),
            'since' => (int)($s['last_state_change'] ?? 0),
        );
    }
    return $out;
}
