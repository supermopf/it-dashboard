<?php
/**
 * Relevanz der Dashboard-Seiten: welche Seite in der Rotation gezeigt und welche übersprungen wird.
 *
 * Zwei Quellen:
 *  1. Die Seite selbst: monitor/source/pageN.php schreibt `<!-- dashboard:relevant=0|1 -->` in ihre Ausgabe.
 *     rendercache.php liest die Markierung und übernimmt sie in page_status.json (`hasData`). So stimmen
 *     „was angezeigt würde“ und „ob angezeigt wird“ immer überein (z. B. leere SCOM-Liste).
 *  2. Zeitfenster (PAGE_SCHEDULES): server.php prüft beim Seitenwechsel, ob die Seite gerade gezeigt werden
 *     darf (z. B. Julian-O-Meter nur Mo/Di/Fr 12–18 Uhr).
 */

/** Markierung für die Ausgabe einer Quellseite. */
function page_relevance_marker(bool $relevant): string
{
    return '<!-- dashboard:relevant=' . ($relevant ? '1' : '0') . ' -->';
}

/** Liest die Markierung aus gerendertem HTML; null = keine Markierung. */
function page_relevance_from_html(string $html): ?bool
{
    if (preg_match('/<!--\s*dashboard:relevant=([01])\s*-->/', $html, $m)) {
        return $m[1] === '1';
    }
    return null;
}

/**
 * Zeitfenster je Seite. Überschreibbar per PAGE_SCHEDULES in config.php:
 *   define("PAGE_SCHEDULES", array(10 => array('days' => array(1, 2, 5), 'from' => '12:00', 'to' => '18:00')));
 * days: ISO-Wochentage (1 = Montag … 7 = Sonntag), from/to: HH:MM (to exklusiv).
 */
function page_schedules(): array
{
    if (defined('PAGE_SCHEDULES') && is_array(PAGE_SCHEDULES)) {
        return PAGE_SCHEDULES;
    }
    return array(
        10 => array('days' => array(1, 2, 5), 'from' => '12:00', 'to' => '18:00'),
    );
}

/** true, wenn die Seite zum Zeitpunkt $ts laut Zeitfenster gezeigt werden darf (ohne Fenster: immer). */
function page_schedule_allows(int $page, ?int $ts = null, ?array $schedules = null): bool
{
    $schedules = $schedules ?? page_schedules();
    if (!isset($schedules[$page]) || !is_array($schedules[$page])) {
        return true;
    }
    $s = $schedules[$page];
    $ts = $ts ?? time();
    $days = isset($s['days']) ? array_map('intval', (array)$s['days']) : array(1, 2, 3, 4, 5, 6, 7);
    if (!in_array((int)date('N', $ts), $days, true)) {
        return false;
    }
    $now = date('H:i', $ts);
    $from = $s['from'] ?? '00:00';
    $to = $s['to'] ?? '24:00';
    return $now >= $from && $now < $to;
}
