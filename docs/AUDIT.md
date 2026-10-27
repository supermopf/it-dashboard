# Audit – IT-Dashboard (Ist-Zustand)

Stand: Commit `ccc32bb` („PHP 8.5 Compatibility“, `master`), analysiert am 27.10.2026.
Alle Zeilenangaben beziehen sich auf diesen Commit. `config.php` (enthält Zugangsdaten, gitignored) wurde
nur auf die *Namen* der definierten Konstanten geprüft, Werte wurden nicht gelesen.

Legende Schweregrad: **kritisch** · **hoch** · **mittel** · **niedrig**

---

## 1. Architekturübersicht

```
                 ┌───────────────────────── Windows-Server / IIS (C:\inetpub\dashboard) ─────────────────────────┐
                 │                                                                                                 │
 Cron/Task ──▶  monitor/rendercache.php ──HTTP──▶ monitor/source/pageN.php ──▶ MSSQL (Dashboard, HelpLine,       │
 (Intervall      │   schreibt monitor/cache/pageN.php (statisches HTML+Inline-JS)      Derdack, SCCM), M365 Graph, │
  unbekannt)     │   schreibt monitor/cache/page_status.json                           Checkmk, OpenWeatherMap     │
                 │                                                                                                 │
 Dauerprozess ─▶ websocket/server.php  (PHP-Sockets, Port 9000, ein Prozess, Endlosschleife)                       │
                 │   • hält globalen Zustand (Page, FUN, Radio, Wheel, Snow, Cycle …)                              │
                 │   • taktet die Seitenrotation (30 s) und sendet jede Sekunde ein `update` an alle Clients       │
                 │   • leitet Befehle (!var, !fpage, !urgent, !video, !spin, !bus …) weiter                        │
                 │                                                                                                 │
 Cron/Task ──▶  websocket/songtitle.php (verbindet sich als WS-Client, liest Radiostation, sendet !var SongTitle) │
 Cron/Task ──▶  websocket/weather.php  (erzeugt radar.png aus Kacheln)                                             │
                 │                                                                                                 │
 HTTP-POST ──▶  websocket/api.php  (REST: Toast/YouTube → öffnet WS-Verbindung zu 127.0.0.1:9000, sendet Befehl)   │
                 └─────────────────────────────────────────────────────────────────────────────────────────────────┘
                         ▲ WebSocket (WEBSOCKET_URL, vermutlich via IIS/ARR-Proxy, X-Forwarded-For wird ausgewertet)
        ┌────────────────┼──────────────────────┬───────────────────────────┬────────────────────────┐
  monitor/index.php  control/index.php   websocket/index.php (Admin)  websocket/newtoast.php,   applications/
  (Signage, Yodeck/  (Tablet-Fernbedienung) + history/best/features/    youtube.php            (Desktop-Ansicht,
   Raspberry Pi 4,                          logs/julianometer           (Toast/YouTube senden)  kein WebSocket)
   ?Name=<Dashboard>)
```

### Komponenten

| Bereich | Dateien | Zweck |
|---|---|---|
| Startseite | `index.html` | Linkseite zu applications/control/monitor (Font Awesome per CDN) |
| Konfiguration | `config.template.php`, `config.php` (gitignored), `config.js.php` | PHP-Konstanten; `config.js.php` gibt LDAP_DOMAIN, LDAP_BASE_DN, DASHBOARD_DOMAIN, DASHBOARD_BASE_URL, WEBSOCKET_URL als JS-Objekt aus |
| Signage | `monitor/index.php`, `monitor/js/app.js`, `monitor/css/*`, `monitor/lib/*` | jQuery 2.2.2 + Bootstrap 3 + Chart.js 2.3.0 (Bundle, unminifiziert 700 KB) + animate.css + snowfall |
| Seiteninhalte | `monitor/source/page1..10.php`, `m365_helper.php` | Serverseitig gerendertes HTML + Inline-`<script>` mit Chart.js |
| Cache | `monitor/rendercache.php`, `monitor/cache/` | Rendert source → cache (HTTP-Abruf), schreibt `page_status.json` |
| Spezialseiten | `monitor/cache/pageFUN.php`, `pageYT.php`, `pageWHEEL.php` (versioniert, **nicht** von rendercache erzeugt) | Fun Mode, YouTube, Glücksrad |
| WebSocket | `websocket/server.php` | Eigener WebSocket-Server (RFC 6455 nur teilweise) |
| REST | `websocket/api.php` | `POST` Toast-Felder bzw. `YouTubeURL`/`Target`; `GET` = Doku-Tabelle |
| Admin | `websocket/index.php`, `navbar.php`, `newtoast.php`, `youtube.php`, `history.php`, `best.php`, `features.php`, `logs.php`, `julianometer.php` | Steuerung, Toast-Historie (SQLite `toasts.db` via `db_helper.php`), Feature-Requests, Logs |
| Hilfsdienste | `websocket/adphoto.php`, `weather.php`, `streams.php`, `songtitle.php` | AD-Foto, Radarbild, Radiosender-Liste, Songtitel |
| Fernbedienung | `control/index.php`, `control/app.js` | Tablet-Remote (Seiten, FUN, Radio, Rad, Bus) |
| Workmode | `workmode.php` | HelpLine-Agenten-Übersicht (kein WS) |
| Applications | `applications/*` | Desktop-Ansicht pro Anwendung (PDM, Mobimedia, ERP, BI) |

Laufzeitumgebung: Die Pfade `C:/inetpub/dashboard/...` (`websocket/server.php:537,538,556,557`, `weather.php:88`) und `web.config`
belegen einen **Windows-Server mit IIS**. Der Raspberry Pi 4 (Yodeck) ist ausschließlich **Anzeige-Client** (Chromium).

## 2. Datenfluss

### 2.1 Seitenrotation (Signage)
1. `server.php:245-306`: alle `$CycleTime = 30` s erhöht der Server `$Page` (1…10, Wrap), überspringt Seiten mit
   `hasData=false` laut `page_status.json` (`PageHasData`, `server.php:168-178`, max. 10 Versuche) und sendet
   `{"type":"command","message":"!page N"}` an **alle** Clients.
2. `monitor/js/app.js:288-301`: Client bricht laufendes AJAX ab und ruft `LoadPage(N)` auf.
3. `app.js:51-83`: `$.ajax GET ./cache/pageN.php?src=undefined&_=<ts>` (`$.ajaxSetup({cache:false})`, `app.js:146` → nie HTTP-Cache).
4. `app.js:77`: `$("#ajax").html(result)` – kompletter DOM-Austausch; jQuery führt alle Inline-Skripte global aus
   (`new Chart(...)`).
5. `app.js:78`: `animateCss("bounceInUp")` auf dem gesamten Inhaltsbereich.
6. Während YouTube läuft (`YTRunning`), werden `!page`-Befehle ignoriert (`app.js:59`).

### 2.2 Sekündliches Status-Update
`server.php:309-339` sendet jede Sekunde an alle Clients:
`Page, FUN, Wheel, Radio, Radiostation, Radiovolume, YTvolume, Cat, Snow, BusActive, CD, Cycle, SongTitle, RadioStationIcon, PageStatus`
(Booleans als Strings `"true"/"false"`, `CD` = Restsekunden bis Seitenwechsel, `PageStatus` = `{N: "warning"|"ok"}`).
Der Monitor verarbeitet es in `app.js:378-529` vollständig bei jeder Nachricht (Fortschrittsbalken, Katze, Radio,
Schnee, Navigationsstatus …) – ohne Deduplizierung.

### 2.3 Befehle (Client → Server)
| Befehl | Quelle | Wirkung (server.php) |
|---|---|---|
| `!reg [Name]` | alle Clients | `$registered[IP:Port] = Name` (397-406) |
| `!dashboard <Name>` | Monitor (`?Name=`) | Registrierung für Targeting (427-439) |
| `!repeat` | Monitor beim Verbinden | sendet `!page $Page` an alle (475-478) |
| `!var <Name> <Wert>` | control, Admin, songtitle, Monitor (BusActive) | **setzt beliebige globale PHP-Variable** (384-396) |
| `!fpage N` | control, Monitor-Navigation | `$Page=N`, Broadcast `!page N`, Timer-Reset (466-474) |
| `!spin` | control, Admin | `!roll r1 r2 r3` mit `rand(1,20)` (479-485) |
| `!video url[\|Target]` | youtube.php, api.php | YouTube-Historie (SQLite) + Senden an Target (486-516) |
| `!urgent {json}` | api.php | Medien server-seitig cachen, Historie, Senden an Target (517-591) |
| `!clientlist`, `!dashboardlist` | Admin, newtoast, youtube | Listen |
| alles andere | z. B. `!bus start/end/middle`, `!songtitle now`, `!page N` (Admin) | unverändert an alle weitergeleitet (592-596) |

### 2.4 Toast (Urgent-Modal)
`api.php` (POST) → `json_encode($_POST)` → WS `!urgent` → `server.php` lädt `ToastSound`/`ToastPicture` per
`get_headers`+`file_get_contents`+`fopen` herunter und ersetzt sie durch lokale Cache-URLs → Monitor
(`app.js:305-373`, nur mit `?Name=`) setzt Farbe, Titel, **Body per `.html()`**, Bild/Video per String-Konkatenation,
spielt Sound, zeigt Bootstrap-Modal, schließt nach `ToastTime` (Default 30 000 ms).
**„Alle Dashboards neu laden“** ist als Toast mit `ToastBody=<script>location.reload()</script>` umgesetzt
(`websocket/index.php:533-547`, `newtoast.php:246-264`, `youtube.php:327-335`, `history.php:143-150`, `best.php:105-112`).

### 2.5 Medien
- Internetradio: ein `Audio`-Objekt (`app.js:15`), Steuerung über `update.Radio/Radiostation/Radiovolume` (`app.js:465-484`).
- Songtitel-Toast bei Titelwechsel (`app.js:460-463`, `launch_toast`), `!songtitle` zeigt ihn erneut (`app.js:302-304`).
- YouTube: `!video` → `LoadPage("YT", url)` → `cache/pageYT.php` lädt die IFrame-API und erzeugt einen Player;
  Ende/Fehler → `LoadPage(CurrentPage)`.
- Tier-Easter-Egg: Server setzt `Cat` mit Wahrscheinlichkeit 1/99 pro Zyklus; Monitor zeigt eines von 6 GIFs + Sound (`app.js:381-452`).
- Bus: `!bus start` → jQuery-Animation über 8,5 s → `!bus middle` → `!bus end` → zurück (`app.js:238-287`).
- Fun Mode: `cache/pageFUN.php` – Postillon-Schlagzeile + 2 Bilder/WebM aus der DB.
- Glücksrad: `cache/pageWHEEL.php` + `js/wheel.js` + `css/wheel.css`, `!roll a b c` → `startSpin`.
- Schnee: jQuery-Snowfall mit 150 DOM-Flocken bei `Snow=true` (`app.js:488-500`).

## 3. Identifizierte Probleme

### 3.1 Performance und Stabilität (Signage-Client)

| # | Fundstelle | Problem | Schwere | Empfehlung | Nebenwirkungen |
|---|---|---|---|---|---|
| P1 | `monitor/lib/js/Chart.bundle.js:8240` + alle `new Chart` in `monitor/source/page1.php:208`, `page2.php:341`, `page3.php:192`, `page4.php:200`, `page6.php:163`, `page8.php:343,381`, `page10.php:111` | Chart.js 2.3 registriert jede Instanz in `Chart.instances`; gelöscht wird nur bei `destroy()` (8769). Der Client ersetzt den DOM per `.html()` (`app.js:77`) **ohne** `destroy()` → jede Chart-Instanz samt Canvas und Daten bleibt für immer im Speicher. Bei ~30 Charts pro Rotationsdurchlauf (5 min) wächst der Heap im Dauerbetrieb unbegrenzt. | **kritisch** | Chart-Instanzen pro Ansicht erfassen und beim Verlassen zerstören. | keine fachliche |
| P2 | `Chart.bundle.js:10123-10160` | Jede `responsive`-Chart legt ein verstecktes **iFrame** zur Größenerkennung an (eigener Browsing-Context). Pro Seite 3/4 entstehen 12 iFrames; zusammen mit P1 werden sie nie freigegeben. | **kritisch** | Wie P1 (`destroy()` entfernt das iFrame). Zusätzlich Chart-Animationen abschalten. | – |
| P3 | `page6.php:104`, `page8.php:269` | `Chart.pluginService.register({beforeDraw…})` läuft **bei jedem Seitenaufruf** erneut → nach einem Tag hunderte identische Plugins, die bei jedem Zeichenvorgang jeder Chart ausgeführt werden. CPU-Last wächst linear mit der Laufzeit. | **kritisch** | Plugin-Registrierung deduplizieren. | – |
| P4 | `page3.php:168,192`, `page4.php:167,200` | Inline-Skripte deklarieren `const ctx1`, `const data1`, `const chartInstance1` auf oberster Ebene. jQuery führt Skripte global aus; beim zweiten Aufruf von Seite 3 bzw. beim ersten Aufruf von Seite 4 nach Seite 3 wirft der Browser `SyntaxError: Identifier 'ctx1' has already been declared` → **Charts werden ab dem zweiten Durchlauf nicht mehr gezeichnet** (JS-Semantik, nicht im Browser verifiziert). | **hoch** | Skripte einer Ansicht in einem eigenen Funktions-Scope ausführen. | – |
| P5 | `app.js:77-78`, `app.js:45`, `lib/css/animate.min.css` | `bounceInUp`/`bounceInRight` (animate.css) auf dem kompletten Full-HD-Inhaltsbereich während gleichzeitig ~12 Charts mit 1 s-Default-Animation zeichnen → Hauptursache sichtbarer Ruckler beim Seitenwechsel. | **hoch** | Kurzer opacity/translate3d-Übergang (≤ 200 ms), Chart-Animation aus. | Optik |
| P6 | `app.js:379-380`, `monitor/css/style.css:1878-1895` | Jede Sekunde wird `width` von `#loadingbar` und `#cat` geändert und mit `transition: width 1s` animiert → **permanente Layout-Animation** der Navbar. | **hoch** | `transform: scaleX()` bzw. `translateX()`. | – |
| P7 | `app.js:378-529` | Sekündliches `update` wird ungefiltert verarbeitet: `.html()` auf `#page-timer`, `PageStatus`-Schleife mit `removeClass/addClass` auf allen Nav-Einträgen, `radio.setAttribute("src","")` (483) **jede Sekunde**, solange das Radio aus ist (startet jedes Mal den Ressourcenauswahl-Algorithmus des Media-Elements). | **mittel** | Felder deduplizieren, nur Änderungen rendern, Radio-Zustand als Zustandsautomat. | – |
| P8 | `app.js:488-500`, `monitor/js/snowfall.min.jquery.js` | Schnee: 150 DOM-Elemente per JS-Timer bewegt (top/left). | **hoch** (wenn aktiv) | Ein Canvas, begrenzte Flockenzahl, 30 fps, Pause bei unsichtbarem Tab, aus bei `prefers-reduced-motion`. | Optik leicht anders |
| P9 | `app.js:260,276`; `control/app.js:299,305` | Bus-Animation per `$.animate({left})` über 8,5 s → JS-getriebene Layout-Animation. | **mittel** | CSS-Transition auf `transform` (linear, da konstante Bewegung). | – |
| P10 | `monitor/css/style.css:2012-2066` | Songtitel-Toast animiert `bottom` und `min-width` (Keyframes `fadein/expand/shrink/fadeout`). | **mittel** | opacity + transform. | Optik |
| P11 | `monitor/css/wheel.css:139-151` | Glücksrad: `filter: blur(4px)` animiert auf dem gesamten Rad während der Drehung. | **hoch** (bei Nutzung) | Blur entfernen, nur `transform: rotate`. | Optik |
| P12 | `monitor/css/style.css:164,193,210,342,367,404,588,622,1603,1673`, `wheel.css:195`, `page7.php:141,320` | `transition: all`. | **niedrig** | Konkrete Properties. | – |
| P13 | `app.js:124` | Uhr per `setTimeout(startTime, 500)` – zwei DOM-Writes pro Sekunde, nicht auf Sekundengrenze synchronisiert. | **niedrig** | 1×/s, ausgerichtet, nur bei Änderung schreiben. | – |
| P14 | `monitor/index.php:22,24,179` | `?random=uniqid()` an CSS/JS → Browser-Cache bei jedem Laden umgangen. | **mittel** | Content-Hash-Dateinamen (Vite) + lange Cache-Dauer. | – |
| P15 | `monitor/index.php:10-11,17` | Externe Runtime-Requests: Google Fonts (2×), Font Awesome CDN. Bei fehlendem Internet blockierendes CSS / FOUT. | **mittel** | System-Font-Stack, Inline-SVG-Icons. | Schriftbild ändert sich |
| P16 | `monitor/index.php:16-20,171-175` | Ballast: Bootstrap 3 CSS+JS, bootstrap-switch, checkbox3, matchHeight, animate.css, **Chart.bundle.js unminifiziert (701 618 Byte)**. | **mittel** | Nur benötigte Teile, minifiziert. | – |
| P17 | `app.js:146` | `$.ajaxSetup({cache:false})` → jede Seite wird immer komplett neu geladen (kein `304`). | **mittel** | Bedingte Requests (ETag/Last-Modified) auf statische Cache-Dateien. | – |
| P18 | `app.js:51-83` | Kein Timeout, kein Fehlerpfad: Bei Fehler bleibt alte Seite stehen, ohne Hinweis. Bei leerer/defekter Cache-Datei wird leerer bzw. Fehler-HTML angezeigt. | **hoch** | Timeout + AbortController, letzte gültige Ansicht behalten, Fehlerhinweis. | – |
| P19 | `app.js:333-340`, `pageFUN.php:55,73` | Videos (Toast, FUN) werden beim DOM-Austausch nicht pausiert/entladen; `?nocache=<rand>` verhindert Caching. Toast-Video läuft nach Schließen bis zum nächsten `.html('')` weiter (nur bei Timeout geleert). | **mittel** | Media-Manager: pause, `src` entfernen, `load()`. Maximal ein Video. | – |
| P20 | `app.js:390-441` | Für jedes Tier-Easter-Egg neues `Audio()`-Objekt; GIFs bis 363 KB (`monitor/img/michael.gif`), `dog.gif` 210 KB. | **niedrig** | Wiederverwendbares Audio-Element, GIFs nur bei Bedarf. | – |
| P21 | `page1.php:120` | Radarbild `../radar.png?nocache=<uniqid>` (974 545 Byte PNG) bei jedem Aufruf neu geladen und auf dem Pi dekodiert. | **mittel** | Serverseitig skaliert/WebP; Cache über Last-Modified statt Zufallsparameter. | – |
| P22 | `app.js:503` | Slider-Rotation der SCOM-Liste wird nur ausgelöst, wenn `CurrentPage === "6"`; die SCOM-Seite ist aber **Seite 5** (Backup ist 6). Folge: Bei > 15 SCOM-Einträgen werden die weiteren Slides nie angezeigt (vermutlich Relikt einer früheren Nummerierung). | **mittel** (Funktionsfehler) | Slider-Rotation an die Ansicht koppeln, die Slider enthält. | Verhaltensänderung (Fix) |
| P23 | `page5.php:91-96`, `app.js:20,32-49` | Seite 5 überschreibt die globale Variable `PageCount` des Hauptskripts und nutzt globales `i`. | **niedrig** | Gekapselter Zustand pro Ansicht. | – |
| P24 | `app.js:574-581` | Reconnect: Backoff ohne Jitter, ohne Obergrenze der Wartezeit (2^19 s ≈ 6 Tage beim 20. Versuch), danach `alert()` – auf einem unbeaufsichtigten Display blockierend und ohne Recovery. | **kritisch** (Dauerbetrieb) | Unbegrenzte Versuche, max. 30 s, Jitter, Watchdog, kontrollierter Recovery-Reload. | – |
| P25 | `app.js` gesamt | Kein Heartbeat/Watchdog: halb-offene TCP-Verbindungen (z. B. nach Proxy-Timeout) werden nie erkannt. | **hoch** | Watchdog: kein `update` > 10 s → Reconnect. | – |
| P26 | `app.js:186-191`, `control/app.js:356-363` | Control-Seite: `onerror`/`onclose` → **sofortiges `location.reload()` ohne Backoff** → Reload-Schleife, solange der Server nicht erreichbar ist. | **hoch** | Backoff-Reconnect. | – |
| P27 | `app.js:45`, `cache/pageWHEEL.php` | WHEEL-Seite bindet `wheel.css` und `wheel.js` bei jedem Aufruf neu ein (zusätzlicher Request, neues Closure). | **niedrig** | Nativ im App-Bundle. | – |
| P28 | `wheel.js:131-134` mit `server.php:481-483` | Server würfelt `rand(1,20)`, das Rad erwartet 0-basierte Indizes 0…19 (je 20 Einträge, `WheelOfFortune.json`). Index 20 → `return` **ohne Callback** → `isSpinning` bleibt `true`, das Rad reagiert bis zum nächsten Laden nicht mehr (Wahrscheinlichkeit je Spin ≈ 14 %); Index 0 wird nie gewählt. | **mittel** (Funktionsfehler) | Client interpretiert Werte als 1-basiert (`roll-1`) und begrenzt sie. | Verhaltensänderung (Fix) |
| P29 | `index.html`, `monitor/index.php:7`, Meta-Tags | Diverse `no-cache`-Meta-Tags ohne Wirkung auf HTTP-Caching. | **niedrig** | Echte HTTP-Header. | – |

### 3.2 Backend / WebSocket-Server

| # | Fundstelle | Problem | Schwere | Empfehlung | Nebenwirkungen |
|---|---|---|---|---|---|
| S1 | `server.php:198` | `socket_select(..., 0, 10)` → 10 µs Timeout → **Busy-Loop** (ein CPU-Kern dauerhaft ausgelastet). | **hoch** | Timeout 100 ms (Takt bleibt < 1 s genau). | keine |
| S2 | `server.php:396` | `!var`: `${"$split[1]"} = $var` – **jeder** Client kann **jede** globale Variable des Servers überschreiben (`$clients`, `$socket`, `$CycleTime`, …). | **kritisch** (Sicherheit + Stabilität) | Whitelist + Typprüfung. | Freitext-Befehle mit unbekannten Namen werden abgelehnt (Breaking, dokumentiert) |
| S3 | `server.php:375` | `socket_recv(..., 1024)` + `unmask()` (720-738) verarbeitet genau einen Frame ≤ 1024 Byte. Längere Toasts (z. B. Body > ~900 Zeichen, gesendet von `api.php`) werden abgeschnitten → ungültiges JSON. Close-/Ping-Frames werden als Text behandelt. | **hoch** | Frame-Parser mit Längenprüfung, Opcode-Behandlung, größerer Puffer. | – |
| S4 | `server.php:751` | `pack('CCNN', $b1, 127, $length)` – 64-Bit-Länge falsch (nur ein Wert für zwei `N`) → Nachrichten ≥ 64 KiB fehlerhaft. | **mittel** | `pack('CCNN', $b1, 127, 0, $length)`. | – |
| S5 | `server.php:233,405` | `$ProxyMatch` und `$registered` wachsen mit jeder Verbindung und werden **nie bereinigt** → Speicherleck im Dauerbetrieb. | **mittel** | Beim Disconnect entfernen. | – |
| S6 | `server.php:235,449,456,629` | `gethostbyaddr()` (blockierender DNS-Lookup) in der Hauptschleife pro Connect/Disconnect/`!clientlist`. | **mittel** | Ergebnis cachen. | – |
| S7 | `server.php:272` | OpenWeatherMap-Abruf (Schnee) synchron in der Hauptschleife, ohne Timeout. | **mittel** | Stream-Kontext mit Timeout (3 s). | – |
| S8 | `server.php:523-561` | `!urgent`: jede übergebene URL wird **server-seitig** dreifach abgerufen (`get_headers`, `file_get_contents`, `fopen`) – SSRF, keine Größen-/Typbegrenzung, kein Timeout, blockiert alle Clients. `$last_word`-Prüfung (537/556) prüft den falschen Dateinamen (Cache-Datei heißt `<md5>.<ext>`). | **hoch** | Nur http(s), Größenlimit, Timeout, Typ-Whitelist (Bild/Video/Audio), optional Host-Whitelist, ein Abruf. | sehr große Medien werden nicht mehr gecacht |
| S9 | `server.php:210` | `$port = $Connection[1]` überschreibt die Server-Port-Variable mit dem Client-Port; wird danach im Handshake-Header `WebSocket-Location` verwendet (777). | **niedrig** | Eigene Variable. | – |
| S10 | `server.php:208-210` | Ohne `X-Forwarded-For` ist `$ForwardedIP` leer bzw. vom vorherigen Client übrig → falsche IP-Zuordnung und Verbindungszählung. | **mittel** | Fallback auf `socket_getpeername`, Reset pro Handshake. | – |
| S11 | `rendercache.php:147-150` | `file_get_contents` ohne Fehlerprüfung → bei Fehler (Timeout, PHP-Fatal) wird eine **leere bzw. fehlerhafte** Cache-Datei geschrieben; nicht atomar (Client kann halbe Dateien lesen). | **hoch** | Nur bei HTTP 200 und nicht-leerem Inhalt, atomar via Temp-Datei + `rename`. | – |
| S12 | `rendercache.php:106` | Separate Derdack-Verbindung pro Lauf; Statusabfragen doppeln Seitenabfragen (unkritisch, Cron). | **niedrig** | – | – |
| S13 | Quellseiten `die(print_r(sqlsrv_errors()))` (z. B. `page1.php:18`, `page2.php:30`) | Bei DB-Fehler landet ein Fehler-Dump als Seiteninhalt im Cache und wird auf dem Display gezeigt. | **mittel** | rendercache erkennt Fehlerausgabe und behält letzte gültige Datei. | – |
| S14 | `page4.php:91,191` | Nutzt `TERTIARY_STYLE`, `Ter_Color*`, die in `config.template.php` fehlen (in `config.php` vorhanden). | **niedrig** | Template ergänzen. | – |
| S15 | `page8.php:115,126,128` | `CHECKMK_BASE_URL`, `CHECKMK_USER`, `CHECKMK_SECRET` sind weder im Template noch in `config.php` definiert → unter PHP 8 `Error: Undefined constant` → Seite 8 rendert vermutlich nicht (nicht verifizierbar ohne Server). | **hoch** | Template ergänzen; Seite gegen fehlende Konstanten absichern. | – |
| S16 | `songtitle.php`, `api.php:147-161` | Eigene Mini-WebSocket-Clients mit festem `Sec-WebSocket-Key`, ohne Timeout auf `fread`. | **niedrig** | Stream-Timeout. | – |

### 3.3 Sicherheit

| # | Fundstelle | Problem | Schwere |
|---|---|---|---|
| X1 | alle Admin-Seiten (`websocket/*.php`), `control/`, `api.php`, `logs.php` | **Keine Authentifizierung** (kein Login, keine Session, keine IP-Prüfung). Logs lesen/löschen (`logs.php:143-146`, per GET), Feature-Status ändern, Toasts/Videos an alle Displays senden. | **kritisch** |
| X2 | `app.js:325`, `api.php:134` | `ToastBody` wird per `.html()` gerendert → **beliebiges JavaScript auf allen Displays** über eine unauthentifizierte REST-API (wird bewusst für „Reload“ genutzt). | **kritisch** |
| X3 | `server.php:396` | siehe S2 – unauthentifizierte Manipulation des Serverzustands. | **kritisch** |
| X4 | `features.php:7,17,20,23`, `julianometer.php:7` | SQL-Injection (String-Konkatenation von `$_POST`). | **kritisch** |
| X5 | `adphoto.php:28,36` | LDAP-Filter-Injection (`$_GET['name']` ohne `ldap_escape`). | **hoch** |
| X6 | `page2.php:210-212`, `page5.php:58,73`, `page6.php:83-88`, `page9.php:58,65,73`, `pageFUN.php:41,49,55,57,67,73,75`, `applications/ajax/source.php:174-176,286-289,408-414`, `workmode.php:176,201,229,256`, `features.php:113`, `julianometer.php:97-98` | Unescapte DB-Inhalte im HTML (Ticket-Betreff stammt von Endanwendern → Stored XSS auf Displays). | **hoch** |
| X7 | `app.js:340,617,621`, `websocket/index.php:708-709,732,744` | URLs/Songtitel/Dashboard-Namen per String-Konkatenation in HTML. | **hoch** |
| X8 | `history.php:91`, `best.php:75` | `json_encode` ohne `JSON_HEX_TAG` in HTML → XSS im Admin. | **mittel** |
| X9 | `server.php:523-561` | SSRF (siehe S8). | **hoch** |
| X10 | `config.js.php:11-17` | Gibt interne Namen (LDAP-Domain, Base-DN) ungefiltert und unescaped an jeden Browser aus. Keine Passwörter. | **niedrig** |
| X11 | `web.config` (lokal, gitignored) | `Access-Control-Allow-Origin: *`, `httpErrors errorMode="Detailed"` (Detail-Fehlerseiten nach außen), `maxAllowedContentLength=2 GB`. | **mittel** |
| X12 | gesamt | Keine Security-Header (CSP, X-Content-Type-Options, Referrer-Policy). | **mittel** |
| X13 | `api.php` | Kein Rate Limiting, keine Validierung (Farben, Zeiten, Lautstärke, URLs), komplettes `$_POST` wird weitergereicht. | **hoch** |
| X14 | WebSocket | Keine Authentifizierung, keine Origin-Prüfung (`perform_handshaking`, `server.php:756-781`). | **hoch** |
| X15 | `index.html`, `applications/index.html:114` | Hart codierte E-Mail-Adresse im versionierten HTML. | **niedrig** |
| X16 | Quellseiten | SQL-Fehlerdumps (`print_r(sqlsrv_errors())`) mit Server-/Datenbankinterna im HTML. | **mittel** |
| X17 | `logs.php:12` | `file()` lädt komplette Logdatei (bis 10 MB) pro Request; `?clear=1` per GET löscht Logs (CSRF). | **mittel** |

Secrets im Repository: geprüft per `git ls-files` – `config.php` ist nicht versioniert (`.gitignore`). Versionierte
Dateien enthalten keine Passwörter oder API-Keys (Template mit leeren Werten). Im Frontend sichtbar: nur die in X10 genannten Namen.

## 4. Externe Abhängigkeiten (Monitor)

| Ressource | Quelle | Größe | Bemerkung |
|---|---|---|---|
| Roboto Condensed, Lato | `fonts.googleapis.com` | extern | Runtime-Request |
| Font Awesome 5.6.3 | `use.fontawesome.com` | extern (~60 KB CSS + Fonts) | Icon-Font |
| jQuery 2.2.2 | lokal | 86 015 B | von Inline-Skripten der Seiten benötigt (`$`) |
| Bootstrap 3 CSS/JS | lokal | 36 874 B JS | Modal, Grid |
| Chart.js 2.3.0 Bundle (inkl. moment) | lokal | 701 618 B (unminifiziert) | Zeitachsen (`type:'time'`) benötigen moment |
| animate.css, bootstrap-switch, checkbox3, matchHeight, snowfall | lokal | – | |
| YouTube IFrame API | `youtube.com` | extern | nur bei `!video` |
| Bilder | `monitor/img/*.gif` bis 363 KB, `radar.png` 974 KB, Wetter-Icons PNG | | |

## 5. Funktionen mit unklarem Verhalten

1. **`PageStatus`-Semantik** (`rendercache.php:31-144`, `server.php:314-317`): `hasData=true` bedeutet bei Seiten 3,4,5,6,9 „Alarme vorhanden“, bei 1,2,7,8 aber „immer anzeigen“. Der Server meldet daraus `warning` – die Navigation markiert dadurch Temperatur, helpLine, M365 und Updates **dauerhaft als Warnung**. Ob das gewollt ist, ist unklar. Entscheidung im Refactoring: Status wird nur für die Alarmseiten 3,4,5,6,9 als Warnung angezeigt (konfigurierbar), die Rotationslogik bleibt unverändert.
2. **Seiten 11/12**: `.gitignore` und `control/index.php:78-85` kennen page11/12 („XXX“, versteckt), `rendercache.php` erzeugt sie nicht. Server rotiert nur bis 10. Verhalten bewahrt (ein `!fpage 11` lädt `cache/page11.php`, falls vorhanden).
3. **`Freeze`** (`app.js:19,58`): wird nie gesetzt – tote Variable. `debug`-Parameter von `LoadPage` (Laden aus `source/`) wird nirgends aufgerufen.
4. **`!page FUN/WHEEL` ohne `?Name=`** (`app.js:294-298`): Displays ohne Namen ignorieren FUN/WHEEL, alle anderen `!page`-Befehle aber nicht – Absicht vermutlich: unbenannte Displays (z. B. Büro-PCs) zeigen keinen Spaß-Content. Bewahrt.
5. **Mehrere Displays**: Seitenrotation ist global (Server-Zustand), Toasts/Videos können gezielt (`Target`) gesendet werden. Bewahrt.
6. **`Cat` bei Seite 9** (`app.js:382-383`): zeigt statt Tier den Bus (`bus.gif`) – Sinn unklar, bewahrt.
7. **SCOM-Slider** (P22): ob die Rotation absichtlich deaktiviert wurde, ist nicht erkennbar. Wird im Refactoring für die Seite aktiviert, die Slider enthält (dokumentierte Verhaltensänderung).
8. **Glücksrad-Indizes** (P28): Absicht 0- oder 1-basiert unklar; der Fehler (Rad blockiert) ist eindeutig.
9. **`page5.php:73`**: String-Konkatenation endet mit `'<span class="pull-right">' .` und hängt den Rückgabewert von `$objects++` an; `$time` (80) ist undefiniert. Ausgabe vermutlich „Objektzähler“ statt Zeit – unklar, bewahrt.
10. **`workmode.php:126`**: leitet nach 1 h auf `/control` um statt sich selbst zu aktualisieren.
11. **`history.php:157`, `best.php:122`**: rufen nicht existierende `best_new.php` auf → Favoriten speichern/löschen defekt.
12. **`features.php:147`, `julianometer.php:117`**: „Reload All“ ruft undefiniertes `clean()` auf → defekt.
13. **Control: `control/app.js:329-330`**: Wheel-Schalter wird mit `msg.Cycle` statt `msg.Wheel` synchronisiert (Copy-Paste-Fehler, vermutlich).
14. **`config.template.php:56`**: `"Database" => DASHBOARD_SQL_INSTANCE` – vermutlich `DASHBOARD_SQL_DATABASE` gemeint; Wirkung hängt von der echten config.php ab.
15. Intervall der Cronjobs (rendercache, songtitle, weather) ist im Repository nicht dokumentiert.

## 6. Funktionen, die erhalten bleiben müssen

**Signage (Monitor)**
- Server-gesteuerte Seitenrotation (`!page N`, 30 s, Überspringen leerer Seiten), Countdown „Seitenwechsel in“ bzw. „Deaktiviert“.
- Manuelle Seitenwahl (`!fpage N`) über Navigation/Control, `!repeat` beim Verbinden.
- Seiteninhalte 1–10 aus `monitor/cache/pageN.php` (inkl. Chart.js-Diagramme, SCOM-Slider).
- Registrierung `!reg [Dashboard]<Name>` und `!dashboard <Name>` mit `?Name=`; Re-Registrierung bei `auth`.
- Urgent-Toast mit allen Feldern: `ToastSubject`, `ToastBody`, `ToastPicture` (Bild oder mp4/webm), `ToastVideoNoRepeat`, `ToastSound`, `ToastVolume`, `ToastTime`, `ToastColor`, `ToastTextColor`; Targeting.
- „Alle Dashboards neu laden“ (Toast mit `<script>location.reload()</script>`).
- YouTube (`!video`, Shorts/watch/youtu.be, Startzeit `t`, Lautstärke `YTvolume`, Rückkehr zur Seite bei Ende/Fehler, Rotation pausiert).
- Internetradio (an/aus, Sender, Lautstärke), Songtitel-Toast (bei Wechsel und `!songtitle`).
- Fun Mode (`!page FUN`), Glücksrad (`!page WHEEL`, `!roll`), Tier-Easter-Egg (`Cat`), Bus/Brötchen (`!bus`, 10–12 Uhr Brötchen), Schnee (`Snow`).
- Statusanzeige pro Seite in der Navigation (`PageStatus`).
- Uhr.

**Backend**
- WebSocket-Protokoll und alle Befehle aus 2.3 (Rückwärtskompatibilität für control, Admin, songtitle, api).
- REST-API `api.php` (Toast-Felder, `YouTubeURL`, `Target`, Doku per GET).
- Toast- und YouTube-Historie (SQLite), Favoriten, Medien-Cache.
- rendercache.php mit `page_status.json`.
- Admin-Seiten, Control, Workmode, Applications, Logs.
