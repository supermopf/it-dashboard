<?php
/**
 * Wetterdaten und Regenradar.
 *
 * Wetter: Yahoo Weather über RapidAPI (YAHOO_WEATHER_RAPIDAPI_KEY), sonst OpenWeatherMap (APIKEY_openweathermap).
 * Yahoo hat seine frühere öffentliche API (query.yahooapis.com) 2019 abgeschaltet; die aktuelle Yahoo-Wetter-API
 * gibt es nur über RapidAPI. Ergebnis wird 10 min zwischengespeichert (Freikontingent von RapidAPI).
 *
 * Konfiguration (config.php, alle optional):
 *   YAHOO_WEATHER_RAPIDAPI_KEY   RapidAPI-Key mit Abo für „Yahoo Weather“
 *   YAHOO_WEATHER_RAPIDAPI_HOST  Standard: yahoo-weather5.p.rapidapi.com
 *   WEATHER_LOCATION             Standard: Isernhagen
 *   WEATHER_LAT / WEATHER_LON    Standard: 52.48 / 9.86 (Mittelpunkt der Wetterkarte)
 */
require_once(__DIR__ . '/http.php');

function weather_location(): array
{
    return array(
        'name' => defined('WEATHER_LOCATION') ? WEATHER_LOCATION : 'Isernhagen',
        'lat' => defined('WEATHER_LAT') ? (float)WEATHER_LAT : 52.48,
        'lon' => defined('WEATHER_LON') ? (float)WEATHER_LON : 9.86,
    );
}

/**
 * Aktuelles Wetter, normalisiert:
 *   city, temp (°C), text (deutsch), icon (OpenWeatherMap-Iconname, z. B. "10d" → monitor/img/weather/10d.png),
 *   snow (bool), humidity (%|null), wind (km/h|null), high/low (°C|null), source ("Yahoo"|"OpenWeatherMap")
 * Null, wenn keine Quelle erreichbar ist und nichts zwischengespeichert wurde.
 */
function weather_current(): ?array
{
    return cached_json('weather', 600, function () {
        if (defined('YAHOO_WEATHER_RAPIDAPI_KEY') && YAHOO_WEATHER_RAPIDAPI_KEY !== '') {
            $w = weather_from_yahoo();
            if ($w !== null) {
                return $w;
            }
        }
        if (defined('APIKEY_openweathermap') && APIKEY_openweathermap !== '') {
            return weather_from_openweathermap();
        }
        return null;
    });
}

function weather_from_yahoo(): ?array
{
    $loc = weather_location();
    $host = defined('YAHOO_WEATHER_RAPIDAPI_HOST') ? YAHOO_WEATHER_RAPIDAPI_HOST : 'yahoo-weather5.p.rapidapi.com';
    $url = 'https://' . $host . '/weather?' . http_build_query(array('location' => $loc['name'], 'format' => 'json', 'u' => 'c'));
    $res = http_json('GET', $url, array('x-rapidapi-key: ' . YAHOO_WEATHER_RAPIDAPI_KEY, 'x-rapidapi-host: ' . $host), null, 8);
    $obs = $res['json']['current_observation'] ?? null;
    if ($res['status'] !== 200 || !is_array($obs) || !isset($obs['condition']['temperature'])) {
        error_log('[Weather] Yahoo: HTTP ' . $res['status']);
        return null;
    }
    $code = (int)($obs['condition']['code'] ?? 44);
    $lat = (float)($res['json']['location']['lat'] ?? $loc['lat']);
    $lon = (float)($res['json']['location']['long'] ?? $loc['lon']);
    $mapped = weather_yahoo_code($code, weather_is_day($lat, $lon));
    $today = $res['json']['forecasts'][0] ?? array();
    return array(
        'city' => (string)($res['json']['location']['city'] ?? $loc['name']),
        'temp' => (float)$obs['condition']['temperature'],
        'text' => $mapped[0],
        'icon' => $mapped[1],
        'snow' => $mapped[2],
        'humidity' => isset($obs['atmosphere']['humidity']) ? (int)$obs['atmosphere']['humidity'] : null,
        'wind' => isset($obs['wind']['speed']) ? (int)round((float)$obs['wind']['speed']) : null,
        'high' => isset($today['high']) ? (int)$today['high'] : null,
        'low' => isset($today['low']) ? (int)$today['low'] : null,
        'source' => 'Yahoo',
    );
}

function weather_from_openweathermap(): ?array
{
    $loc = weather_location();
    $url = 'https://api.openweathermap.org/data/2.5/weather?' . http_build_query(array(
            'q' => $loc['name'] . ',de', 'APPID' => APIKEY_openweathermap, 'lang' => 'de', 'units' => 'metric'));
    $res = http_json('GET', $url, array(), null, 8);
    $j = $res['json'];
    if ($res['status'] !== 200 || !isset($j['main']['temp'])) {
        error_log('[Weather] OpenWeatherMap: HTTP ' . $res['status']);
        return null;
    }
    $desc = (string)($j['weather'][0]['description'] ?? '');
    $icon = preg_replace('/[^a-z0-9]/i', '', (string)($j['weather'][0]['icon'] ?? '01d'));
    return array(
        'city' => (string)($j['name'] ?? $loc['name']),
        'temp' => (float)$j['main']['temp'],
        'text' => $desc !== '' ? mb_strtoupper(mb_substr($desc, 0, 1)) . mb_substr($desc, 1) : '',
        'icon' => $icon,
        'snow' => strpos($desc, 'chnee') !== false || strpos($icon, '13') === 0,
        'humidity' => isset($j['main']['humidity']) ? (int)$j['main']['humidity'] : null,
        'wind' => isset($j['wind']['speed']) ? (int)round((float)$j['wind']['speed'] * 3.6) : null,
        'high' => isset($j['main']['temp_max']) ? (int)round((float)$j['main']['temp_max']) : null,
        'low' => isset($j['main']['temp_min']) ? (int)round((float)$j['main']['temp_min']) : null,
        'source' => 'OpenWeatherMap',
    );
}

function weather_is_day(float $lat, float $lon, ?int $ts = null): bool
{
    $ts = $ts ?? time();
    $sun = date_sun_info($ts, $lat, $lon);
    if (!is_int($sun['sunrise'] ?? null) || !is_int($sun['sunset'] ?? null)) {
        $h = (int)date('G', $ts);
        return $h >= 7 && $h < 19;
    }
    return $ts >= $sun['sunrise'] && $ts < $sun['sunset'];
}

/** Yahoo-Wettercode (0–47) → [deutscher Text, Icon, Schnee]. */
function weather_yahoo_code(int $code, bool $day): array
{
    $dn = $day ? 'd' : 'n';
    $map = array(
        0 => array('Tornado', '11'), 1 => array('Tropensturm', '11'), 2 => array('Orkan', '11'),
        3 => array('Schwere Gewitter', '11'), 4 => array('Gewitter', '11'),
        5 => array('Schneeregen', '13', true), 6 => array('Regen und Graupel', '09'), 7 => array('Schnee und Graupel', '13', true),
        8 => array('Gefrierender Nieselregen', '09'), 9 => array('Nieselregen', '09'), 10 => array('Gefrierender Regen', '09'),
        11 => array('Schauer', '09'), 12 => array('Regen', '10'), 13 => array('Schneegestöber', '13', true),
        14 => array('Leichte Schneeschauer', '13', true), 15 => array('Schneetreiben', '13', true), 16 => array('Schnee', '13', true),
        17 => array('Hagel', '09'), 18 => array('Graupel', '13'), 19 => array('Staub', '50'), 20 => array('Nebel', '50'),
        21 => array('Dunst', '50'), 22 => array('Rauch', '50'), 23 => array('Stürmisch', '04'), 24 => array('Windig', '03'),
        25 => array('Kalt', '04'), 26 => array('Bewölkt', '04'), 27 => array('Überwiegend bewölkt', '04'),
        28 => array('Überwiegend bewölkt', '04'), 29 => array('Teilweise bewölkt', '02'), 30 => array('Teilweise bewölkt', '02'),
        31 => array('Klar', '01'), 32 => array('Sonnig', '01'), 33 => array('Heiter', '02'), 34 => array('Heiter', '02'),
        35 => array('Regen und Hagel', '09'), 36 => array('Heiß', '01'), 37 => array('Vereinzelte Gewitter', '11'),
        38 => array('Verstreute Gewitter', '11'), 39 => array('Verstreute Schauer', '09'), 40 => array('Starkregen', '10'),
        41 => array('Verstreute Schneeschauer', '13', true), 42 => array('Starker Schneefall', '13', true),
        43 => array('Schneesturm', '13', true), 45 => array('Verstreute Schauer', '09'),
        46 => array('Verstreute Schneeschauer', '13', true), 47 => array('Verstreute Gewitter', '11'),
    );
    $e = $map[$code] ?? array('Keine Angabe', '03');
    return array($e[0], $e[1] . $dn, !empty($e[2]));
}

/**
 * Regenradar (RainViewer, ohne Key). Gibt ['time' => unix_ts, 'url' => Kachel-Vorlage mit {z}/{x}/{y}] zurück.
 * Die Kacheln lädt der Browser direkt – kein serverseitig erzeugtes radar.png mehr.
 */
function weather_radar(): ?array
{
    return cached_json('radar', 300, function () {
        $res = http_json('GET', 'https://api.rainviewer.com/public/weather-maps.json', array(), null, 8);
        $j = $res['json'];
        $past = $j['radar']['past'] ?? array();
        $last = end($past);
        if ($res['status'] !== 200 || !isset($j['host'], $last['path'])) {
            return null;
        }
        $color = defined('WEATHER_RADAR_COLOR') ? (int)WEATHER_RADAR_COLOR : 2;
        return array(
            'time' => (int)$last['time'],
            'url' => $j['host'] . $last['path'] . '/256/{z}/{x}/{y}/' . $color . '/1_1.png',
        );
    });
}

/** Kachelkoordinaten (Web-Mercator) eines Punkts: [x, y] als Gleitkommazahl. */
function weather_tile_coords(float $lat, float $lon, int $zoom): array
{
    $n = 2 ** $zoom;
    $latRad = deg2rad($lat);
    return array(
        ($lon + 180) / 360 * $n,
        (1 - log(tan($latRad) + 1 / cos($latRad)) / M_PI) / 2 * $n,
    );
}
