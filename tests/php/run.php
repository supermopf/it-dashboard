<?php
/**
 * Minimaler PHP-Testläufer ohne Abhängigkeiten (PHPUnit ist auf dem IIS-Server nicht vorhanden).
 *   php tests/php/run.php
 * Exit-Code 0 = alle Tests grün.
 */

require_once __DIR__ . '/../../lib/security.php';
require_once __DIR__ . '/../../websocket/lib/protocol.php';
require_once __DIR__ . '/../../websocket/lib/api_validation.php';

$passed = 0;
$failed = 0;

function check(string $name, bool $cond, string $detail = ''): void
{
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "  ✓ $name\n";
    } else {
        $failed++;
        echo "  ✗ $name" . ($detail !== '' ? " — $detail" : '') . "\n";
    }
}

/** Client → Server-Frame (maskiert), wie ein Browser es sendet. */
function client_frame(string $payload, int $opcode = 0x1): string
{
    $len = strlen($payload);
    $head = chr(0x80 | $opcode);
    if ($len <= 125) {
        $head .= chr(0x80 | $len);
    } elseif ($len < 65536) {
        $head .= chr(0x80 | 126) . pack('n', $len);
    } else {
        $head .= chr(0x80 | 127) . pack('NN', 0, $len);
    }
    $mask = random_bytes(4);
    $masked = '';
    for ($i = 0; $i < $len; $i++) {
        $masked .= $payload[$i] ^ $mask[$i % 4];
    }
    return $head . $mask . $masked;
}

echo "WebSocket-Protokoll\n";
foreach (array(5, 125, 126, 1024, 5000, 70000) as $n) {
    $p = str_repeat('x', $n);
    list($frames, $rest) = ws_decode_frames(client_frame($p));
    check("Roundtrip $n Bytes", count($frames) === 1 && $frames[0]['payload'] === $p && $rest === '');
}
$big = str_repeat('a', 70000);
$enc = ws_encode_frame($big);
check('Server-Frame ≥ 64 KiB: 64-Bit-Länge korrekt (AUDIT S4)', ord($enc[1]) === 127 && unpack('N2', substr($enc, 2, 8))[2] === 70000 && strlen($enc) === 70010);
check('Server-Frame 126…65535 Bytes', ord(ws_encode_frame(str_repeat('a', 300))[1]) === 126);

$two = client_frame('{"message":"!repeat"}') . client_frame('{"message":"!spin"}');
list($frames, $rest) = ws_decode_frames($two);
check('Mehrere Frames in einem Empfang', count($frames) === 2 && $frames[1]['payload'] === '{"message":"!spin"}');

$f = client_frame(str_repeat('y', 3000));
list($frames, $rest) = ws_decode_frames(substr($f, 0, 1024));
check('Unvollständiger Frame bleibt im Puffer (AUDIT S3)', count($frames) === 0 && strlen($rest) === 1024);
list($frames, $rest) = ws_decode_frames($rest . substr($f, 1024));
check('… und wird mit dem Rest vervollständigt', count($frames) === 1 && strlen($frames[0]['payload']) === 3000 && $rest === '');

list($frames) = ws_decode_frames(client_frame('', 0x8));
check('Close-Frame wird erkannt', $frames[0]['opcode'] === WS_OPCODE_CLOSE);
$threw = false;
try {
    ws_decode_frames(chr(0x81) . chr(0x80 | 127) . pack('NN', 0, WS_MAX_FRAME_BYTES + 1));
} catch (InvalidArgumentException $e) {
    $threw = true;
}
check('Übergroßer Frame wird abgelehnt', $threw);

echo "!var-Whitelist (AUDIT S2)\n";
check('Bool True', ws_parse_var('!var Cycle True') === array('Cycle', true));
check('Bool False', ws_parse_var('!var FUN False') === array('FUN', false));
check('Bool ungültig', ws_parse_var('!var FUN vielleicht') === null);
check('Lautstärke begrenzt', ws_parse_var('!var Radiovolume 7') === array('Radiovolume', 1.0));
check('Lautstärke nicht numerisch', ws_parse_var('!var YTvolume abc') === null);
check('SongTitle urldecode', ws_parse_var('!var SongTitle Artist+-+Titel%21') === array('SongTitle', 'Artist - Titel!'));
check('Radiostation nur http(s)', ws_parse_var('!var Radiostation javascript:alert(1)') === null);
check('Radiostation gültig', ws_parse_var('!var Radiostation https%3A%2F%2Fradio.test%2Fstream.mp3') === array('Radiostation', 'https://radio.test/stream.mp3'));
check('Unbekannte Variable abgelehnt ($clients)', ws_parse_var('!var clients 1') === null);
check('Unbekannte Variable abgelehnt ($socket)', ws_parse_var('!var socket x') === null);
check('CycleTime begrenzt', ws_parse_var('!var CycleTime 1') === array('CycleTime', 10));

echo "URL-/Host-Prüfung\n";
check('http(s) erlaubt', validate_http_url('https://example.test/a.png') !== null);
check('relativ abgelehnt', validate_http_url('/img/a.png') === null);
check('file: abgelehnt', validate_http_url('file:///etc/passwd') === null);
check('Leerzeichen abgelehnt', validate_http_url("https://a.test/x y") === null);
check('Host-Whitelist leer = alles', url_host_allowed('https://a.test/x', array()));
check('Host-Whitelist Subdomain', url_host_allowed('https://cdn.media.test/x', array('media.test')));
check('Host-Whitelist fremd', !url_host_allowed('https://evilmedia.test/x', array('media.test')));

echo "Medien-Cache (SSRF/Typprüfung)\n";
$tmp = sys_get_temp_dir() . '/itdash-test-' . getmypid();
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
$name = fetch_media_to_cache('https://media.test/x.png', $tmp, 'picture', 1000, 1, function () use ($png) {
    return $png;
});
check('PNG wird gecacht', $name !== null && substr($name, -4) === '.png' && is_file("$tmp/$name"));
check('HTML als Bild abgelehnt', fetch_media_to_cache('https://media.test/x.png', $tmp, 'picture', 1000, 1, function () {
    return '<html><script>alert(1)</script></html>';
}) === null);
check('Größenlimit', fetch_media_to_cache('https://media.test/x.png', $tmp, 'picture', 10, 1, function () use ($png) {
    return $png;
}) === null);
check('Bild ist kein Sound', fetch_media_to_cache('https://media.test/x.png', $tmp, 'sound', 1000, 1, function () use ($png) {
    return $png;
}) === null);
array_map('unlink', glob("$tmp/*") ?: array());
@rmdir($tmp);

echo "REST-API-Validierung\n";
$r = validate_toast(array('ToastSubject' => 'Hallo', 'ToastTime' => '5000', 'ToastVolume' => '0.3', 'ToastColor' => '#FA2A00', 'Evil' => 'x', 'ToastHistory' => 'False'));
check('Gültiger Toast', !$r['errors'] && !isset($r['data']['Evil']) && $r['data']['ToastHistory'] === 'false');
check('ToastTime < 1000 abgelehnt', isset(validate_toast(array('ToastTime' => '5'))['errors']['ToastTime']));
check('ToastTime nicht numerisch', isset(validate_toast(array('ToastTime' => 'abc'))['errors']['ToastTime']));
check('ToastVolume > 1 abgelehnt', isset(validate_toast(array('ToastVolume' => '2'))['errors']['ToastVolume']));
check('ToastColor CSS-Injection abgelehnt', isset(validate_toast(array('ToastColor' => 'red;background:url(x)'))['errors']['ToastColor']));
check('ToastPicture relativ abgelehnt', isset(validate_toast(array('ToastPicture' => '/img/a.png'))['errors']['ToastPicture']));
check('ToastPicture javascript: abgelehnt', isset(validate_toast(array('ToastPicture' => 'javascript:alert(1)'))['errors']['ToastPicture']));
check('Leere Felder bleiben erlaubt', !validate_toast(array('ToastPicture' => '', 'ToastBody' => 'x'))['errors']);
check('Reload-Toast bleibt gültig', !validate_toast(array('ToastBody' => '<script>location.reload()</script>', 'ToastHistory' => 'false'))['errors']);
check('Sehr langer Body abgelehnt', isset(validate_toast(array('ToastBody' => str_repeat('x', 20001)))['errors']['ToastBody']));
check('Target mit Umlauten', !validate_toast(array('ToastBody' => 'x', 'Target' => 'Büro 1'))['errors']);
check('Target mit HTML abgelehnt', isset(validate_toast(array('ToastBody' => 'x', 'Target' => '<b>x</b>'))['errors']['Target']));
check('Array-Werte abgelehnt', isset(validate_toast(array('ToastBody' => array('x')))['errors']['ToastBody']));
check('is_toast_request wie bisher', is_toast_request(array('ToastBody' => '')) && !is_toast_request(array('ToastColor' => '#fff')));
check('YouTube gültig', validate_youtube_url('https://www.youtube.com/watch?v=dQw4w9WgXcQ') !== null && validate_youtube_url('https://youtu.be/dQw4w9WgXcQ') !== null);
check('YouTube fremder Host', validate_youtube_url('https://youtube.com.evil.test/watch?v=x') === null);
check('Same-Origin', is_same_origin_request(array('HTTP_HOST' => 'dash.test', 'HTTP_ORIGIN' => 'https://dash.test')));
check('Cross-Origin', !is_same_origin_request(array('HTTP_HOST' => 'dash.test', 'HTTP_ORIGIN' => 'https://evil.test')));
check('Ohne Origin/Referer kein Same-Origin', !is_same_origin_request(array('HTTP_HOST' => 'dash.test')));

echo "Sicherheits-Helfer\n";
check('h() escaped', h('<a href="x">\'') === '&lt;a href=&quot;x&quot;&gt;&apos;');
check('h(null)', h(null) === '');
check('h(DateTime)', h(new DateTime('2026-10-27 08:05')) === '27.10.2026 08:05');
check('json_for_html bricht nicht aus <script> aus', strpos(json_for_html(array('x' => '</script><script>')), '</script>') === false);
check('CIDR IPv4', ip_in_cidrs('10.1.2.3', array('10.0.0.0/8')) && !ip_in_cidrs('11.1.2.3', array('10.0.0.0/8')));
check('CIDR /32 ohne Suffix', ip_in_cidrs('192.168.1.5', array('192.168.1.5')));
check('CIDR /20', ip_in_cidrs('172.16.15.1', array('172.16.0.0/20')) && !ip_in_cidrs('172.16.16.1', array('172.16.0.0/20')));
check('CIDR IPv6', ip_in_cidrs('fd00::1', array('fd00::/8')));
check('CIDR ungültig', !ip_in_cidrs('kein-ip', array('10.0.0.0/8')));
$rlDir = sys_get_temp_dir() . '/itdash-rl-' . getmypid();
$allowed = 0;
for ($i = 0; $i < 7; $i++) {
    if (rate_limit_allow('test', '1.2.3.4', 5, 60, $rlDir)) {
        $allowed++;
    }
}
check('Rate Limit: 5 von 7 erlaubt', $allowed === 5);
check('Rate Limit pro Schlüssel', rate_limit_allow('test', '5.6.7.8', 5, 60, $rlDir));
array_map('unlink', glob("$rlDir/*") ?: array());
@rmdir($rlDir);

echo "\nSeiten-Relevanz und Zeitfenster\n";
require_once __DIR__ . '/../../lib/pages.php';
check('Markierung relevant=0', page_relevance_from_html('<div></div>' . page_relevance_marker(false)) === false);
check('Markierung relevant=1', page_relevance_from_html(page_relevance_marker(true) . '<p>x</p>') === true);
check('Ohne Markierung → null', page_relevance_from_html('<p>x</p>') === null);
$julian = array(10 => array('days' => array(1, 2, 5), 'from' => '12:00', 'to' => '18:00'));
check('Julian: Montag 12:00 sichtbar', page_schedule_allows(10, strtotime('2026-10-26 12:00'), $julian));
check('Julian: Dienstag 17:59 sichtbar', page_schedule_allows(10, strtotime('2026-10-27 17:59'), $julian));
check('Julian: Freitag 18:00 übersprungen', !page_schedule_allows(10, strtotime('2026-10-30 18:00'), $julian));
check('Julian: Montag 11:59 übersprungen', !page_schedule_allows(10, strtotime('2026-10-26 11:59'), $julian));
check('Julian: Mittwoch übersprungen', !page_schedule_allows(10, strtotime('2026-10-28 14:00'), $julian));
check('Seite ohne Zeitfenster immer sichtbar', page_schedule_allows(5, strtotime('2026-10-28 03:00'), $julian));

echo "\nGruselmodus-Variable\n";
check('!var Spooky on', ws_parse_var('!var Spooky on') === array('Spooky', 'on'));
check('!var Spooky AUTO', ws_parse_var('!var Spooky AUTO') === array('Spooky', 'auto'));
check('!var Spooky ungültig', ws_parse_var('!var Spooky maybe') === null);

echo "\n$passed bestanden, $failed fehlgeschlagen\n";
exit($failed === 0 ? 0 : 1);
