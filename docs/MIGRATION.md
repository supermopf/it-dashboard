# Migration

## Kurzfassung

- Die Yodeck-URL bleibt gleich: `https://<server>/monitor/?Name=<Dashboard>`.
- Ohne Build (`monitor/dist/` fehlt) liefert `monitor/index.php` automatisch die alte Oberfläche aus.
- Rollback jederzeit: `?legacy=1` pro Display oder `define("MONITOR_FORCE_LEGACY", true);` für alle.
- WebSocket-Protokoll, REST-API, Datenbanken, Cronjobs und Admin-Oberflächen bleiben kompatibel – mit den
  unten aufgeführten, bewusst eingeführten Abweichungen.

## Schritte

1. Branch deployen (PHP-Dateien).
2. Frontend bauen und `monitor/dist/` auf den Server kopieren (siehe [YODECK-DEPLOYMENT.md](YODECK-DEPLOYMENT.md)).
3. `websocket/server.php` neu starten (geänderter Server-Code).
4. Optional `config.php` um die neuen Einstellungen ergänzen ([CONFIGURATION.md](CONFIGURATION.md)).
5. Im Admin „Reload All“ auslösen – alle Displays laden die neue Oberfläche.
6. Ein Display mit `?debug=1` beobachten (Overlay unten rechts).

## Breaking Changes und Verhaltensänderungen

### Monitor (Signage)

| Änderung | Vorher | Jetzt | Grund / Ausweg |
|---|---|---|---|
| Skripte in `ToastBody` | wurden ausgeführt (`.html()`) | werden entfernt; nur `<script>location.reload()</script>` löst einen kontrollierten Reload aus | Sicherheit (AUDIT X2). Übergangsweise `MONITOR_OPTIONS['toastScripts'] = 'legacy'` |
| HTML in `ToastSubject`/`ToastBody` | beliebig | Allowlist: `b strong i em u s br p div span small mark code pre ul ol li h1–h6 blockquote hr img sub sup`, keine Attribute außer `img src/alt` | Sicherheit |
| `?nocache=<zufall>` an Toast-Bildern | angehängt | entfällt | Server-Cache-Dateien sind inhaltsadressiert (md5) |
| Neuer Toast während offenem Toast | alter Schließ-Timer schloss den neuen vorzeitig | Timer wird zurückgesetzt | Fehlerbehebung |
| Mehrere Videos (Fun Mode) | alle spielen | max. eines (konfigurierbar `maxActiveVideos`), weitere zeigen das erste Bild | Pi-Performance |
| Videos der Seiten | mit Ton, Autoplay | stumm, Start bei Sichtbarkeit | Autoplay-Policy, Performance |
| Toast-Video ohne Autoplay-Freigabe | Fehler | Fallback: stumm abspielen | Robustheit |
| SCOM-Slider | rotierten nie (Kopplung an Seite „6“) | rotieren alle 10 s | Fehlerbehebung (AUDIT P22) |
| Glücksrad-Würfel | 1…20 als 0-basierter Index (Index 20 blockierte das Rad) | 1-basiert interpretiert | Fehlerbehebung (AUDIT P28) |
| Glücksrad-Optik | Blur während der Drehung | ohne Blur | Pi-Performance |
| Schnee | jQuery-Snowfall, 150 DOM-Flocken | Canvas, 60 Flocken, 30 fps, aus bei reduzierter Bewegung | Pi-Performance |
| Statusanzeige Navigation | „warning“ auch bei Temperatur/helpLine/M365/Updates (immer) | Warnung nur für Alarmseiten 3,4,5,6,9 (konfigurierbar) | AUDIT §5.1 |
| Verbindungsabbruch | nach 20 Versuchen `alert()` | unbegrenzt mit Backoff, Offline-Banner, optional Recovery-Reload | Dauerbetrieb |
| Chart-Animationen | 1 s pro Diagramm | aus (konfigurierbar) | Pi-Performance |
| Schriften/Icons | Google Fonts, Font Awesome (CDN) | Systemschrift, Inline-SVG | keine externen Requests |
| Tooltips/Hover der Diagramme | aktiv | aus | Signage ohne Maus, weniger Listener |
| Uhrformat | `9:05:03` | `09:05:03` | Lesbarkeit |

### WebSocket-Server

| Änderung | Auswirkung |
|---|---|
| `!var` nur für `FUN, Cycle, Radio, Wheel, Snow, Cat, BusActive, Radiovolume, YTvolume, Radiostation, RadioStationIcon, SongTitle, CycleTime` | Freitext-Befehle im Admin, die andere Variablen setzen, werden abgelehnt und geloggt. Booleans akzeptieren `True/False/true/false/1/0`; Lautstärken werden auf 0…1, `CycleTime` auf 10…3600 begrenzt; `Radiostation` muss http(s) sein. |
| Lautstärken als Zahl statt String im `update` | z. B. `0.5` statt `"0.5"` – JSON-Clients (control, Admin, Monitor) lesen beides |
| Close-Frames werden beantwortet, Verbindung sofort freigegeben | vorher blieben Verbindungen bis zum Client-Timeout belegt (Verbindungslimit pro IP) |
| Toast-Medien: nur Bild (png/jpg/gif/webp), Video (mp4/webm) bzw. Audio (mp3/wav/ogg/m4a), max. 25 MiB, 5 s Timeout | andere Typen werden entfernt (vorher: beliebige Dateien in `websocket/cache`) |
| Cache-Pfade relativ zu `websocket/` statt fest `C:/inetpub/dashboard/websocket/` | identisch, solange das Projekt dort liegt |
| `!urgent` mit ungültigem JSON | wird verworfen (vorher PHP-Warnungen) |

### REST-API (`websocket/api.php`)

| Änderung | Auswirkung |
|---|---|
| Validierung | ungültige Werte → **HTTP 400** mit Text statt stiller Weitergabe. `ToastTime` 1000–3600000, `ToastVolume` 0–1, Farben als CSS-Farbe, `ToastPicture`/`ToastSound` absolute http(s)-URL, `ToastHistory`/`ToastVideoNoRepeat` `true`/`false`, `Target` ≤ 64 Zeichen. Leere Felder bleiben erlaubt. |
| Unbekannte Felder | werden verworfen (vorher an alle Displays weitergereicht) |
| `YouTubeURL` | muss eine youtube.com/youtu.be-URL sein |
| Rate Limit | > 30 Anfragen/min/IP → HTTP 429 |
| Server nicht erreichbar | HTTP 503 statt PHP-Warnung |
| Erfolg | unverändert `OK` (HTTP 200) |

### Sonstiges

| Datei | Änderung |
|---|---|
| `monitor/index.php` | ist jetzt die neue Shell; die alte Seite heißt `monitor/legacy.php` |
| `monitor/rendercache.php` | überschreibt den Cache nicht mehr mit leeren Seiten oder Fehlerdumps |
| `websocket/logs.php` | „Logs löschen“ per POST-Button statt Link |
| `websocket/features.php` | ungültige `action`/`ID` → HTTP 400 |
| `control/app.js` | Reconnect mit Backoff statt Reload-Schleife; Wheel-Schalter zeigt den Wheel-Zustand. Alle Funktionen gibt es zusätzlich im Adminpanel (`websocket/index.php`, inkl. Radio je Dashboard) |
| `applications/` | **entfernt** (Desktop-Ansicht wurde nicht mehr genutzt) |
| `websocket/weather.php` | **entfernt** – das Regenradar lädt der Browser direkt (RainViewer-Kacheln), Cronjob „Radarbild“ und `radar.png` entfallen |
| `config.template.php` | fehlende Konstanten ergänzt, optionale Einstellungen dokumentiert |

## Unverändert

- WebSocket-Nachrichtenformat (`update`, `command`, `console`, `auth`, `clientlist`, `dashboardlist`)
- alle Befehle (`!page`, `!fpage`, `!repeat`, `!reg`, `!dashboard`, `!urgent`, `!video`, `!spin`/`!roll`,
  `!bus`, `!songtitle`, `!clientlist`, `!dashboardlist`)
- Seitenrotation (30 s, Überspringen leerer Seiten) im Server
- Datenbankzugriffe der Seiten, `page_status.json`, SQLite-Historie
- Control-, Admin-, Workmode- und Applications-Oberflächen (außer den genannten Korrekturen)
