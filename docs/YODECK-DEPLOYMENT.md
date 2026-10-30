# Deployment: IIS-Server + Yodeck (Raspberry Pi 4)

Rollen: Der **Windows-Server mit IIS** (`C:\inetpub\dashboard`) betreibt PHP, den WebSocket-Server und die
Cronjobs. Der **Yodeck-Player auf dem Raspberry Pi 4** zeigt nur die Seite `/monitor/` an.

> Angaben, die sich aus dem Repository nicht belegen lassen (z. B. bisherige Startart des WebSocket-Servers,
> Cron-Intervalle, Yodeck-Einstellungen), sind als **„prüfen“** gekennzeichnet.

## 1. Build-Schritte

Der Build läuft auf einem beliebigen Rechner mit Node.js ≥ 20.19 (getestet: Node 22.22 in WSL Ubuntu 26.04).
Der IIS-Server braucht **kein** Node.js.

```bash
cd frontend
npm ci
npm run build        # Typecheck + App + Legacy-Vendor + Debug-Probe → ../monitor/dist/
npm run analyze      # Bundle-Größen gegen Budgets prüfen
```

Ergebnis in `monitor/dist/`:

| Datei | Inhalt |
|---|---|
| `.vite/manifest.json` | Zuordnung Einstieg → gehashte Dateien (liest `monitor/index.php`) |
| `assets/main-<hash>.js`, `assets/main-<hash>.css` | App-Shell |
| `assets/debug-<hash>.js` | Debug-Modus (nur mit `?debug=1` geladen) |
| `legacy-vendor.js` | jQuery 2.2.2 + Chart.js 2.3.0 für die Seiteninhalte |
| `debug-probe.js` | Mess-Probe für Vergleichsmessungen |

Vor der Auslieferung empfohlen: `npm run check` (Lint, Typecheck, Unit-, PHP- und Browser-Tests).

## 2. PHP-Version und Erweiterungen

- **PHP 8.x** (Code ist für PHP 8.5 angepasst, Commit „PHP 8.5 Compatibility“; Tests liefen mit PHP 8.5.4 CLI).
- Erweiterungen:

| Erweiterung | Wofür |
|---|---|
| `sockets` | `websocket/server.php` |
| `sqlsrv` (Microsoft Drivers for PHP for SQL Server) | alle Dashboard-Seiten, rendercache, Admin |
| `pdo_sqlite` | Toast-/YouTube-Historie (`websocket/toasts.db`) |
| `curl` | M365 (Seite 7), Checkmk (Seite 8), songtitle |
| `openssl` | HTTPS-Abrufe |
| `fileinfo` | Typprüfung der Toast-Medien |
| `mbstring` | Validierung der REST-API |
| `ldap` | `websocket/adphoto.php` |
| `gd` | nicht mehr benötigt (Radarbild entfällt, siehe MIGRATION.md) |

## 3. Deployment-Verzeichnis und Dateien

```
C:\inetpub\dashboard\            ← Projektwurzel (Pfad durch server.php/weather.php vorgegeben)
├─ config.php                    ← nicht versioniert
├─ monitor\index.php             ← neue Shell (Fallback: legacy.php)
├─ monitor\dist\                 ← Build-Ergebnis (nicht versioniert, siehe 10.)
├─ monitor\cache\                ← rendercache schreibt hier
├─ websocket\cache, imgcache\    ← Toast-Medien
├─ websocket\logs\               ← websocket.log (+ .1 … .5)
└─ websocket\toasts.db
```

## 4. Besitz- und Dateirechte (NTFS)

| Pfad | IIS-AppPool-Identität | Konto des WebSocket-/Cron-Dienstes |
|---|---|---|
| gesamtes Projekt | Lesen | Lesen |
| `monitor\cache\` | Lesen | **Ändern** (rendercache) |
| `websocket\cache\`, `websocket\imgcache\` | Lesen | **Ändern** (server.php) |
| `websocket\logs\` | Lesen (logs.php), **Ändern** wenn „Logs löschen“ genutzt wird | **Ändern** |
| `websocket\toasts.db` + Verzeichnis `websocket\` (SQLite-Journal) | **Ändern** (history/best/youtube) | **Ändern** (server.php) |
| `radar.png` (Projektwurzel) | Lesen | **Ändern** (weather.php) |
| `%TEMP%\itdashboard-ratelimit` | **Ändern** (api.php Rate Limit) | – |

In den beschreibbaren Verzeichnissen die Skriptausführung im IIS deaktivieren (siehe SECURITY.md).

## 5. WebSocket-Server starten

```
php C:\inetpub\dashboard\websocket\server.php
```

- Muss dauerhaft laufen (Endlosschleife, Port `WEBSOCKET_PORT`, Standard 9000).
- Empfohlen als Windows-Dienst (z. B. NSSM) oder Aufgabenplanung „Beim Start“ mit „Neu starten bei Fehler“.
  Wie der Server bisher gestartet wird, ist im Repository nicht dokumentiert (`*.bat` ist gitignored) – **prüfen**.
- Nach jedem Update von `websocket/server.php` oder `websocket/lib/*` **neu starten**. Die Displays verbinden
  sich automatisch neu (Backoff, Offline-Banner nur bei > 5 s Unterbrechung).
- Browser verbinden sich über `WEBSOCKET_URL` (typisch `wss://…` über IIS/ARR). Port 9000 nicht direkt von
  außen erreichbar machen.

## 6. Cronjobs (Aufgabenplanung)

| Aufgabe | Befehl | Intervall |
|---|---|---|
| Seiten-Cache | `php C:\inetpub\dashboard\monitor\rendercache.php` | **prüfen** (bisheriges Intervall nicht im Repo; empfohlen 1–2 min, kürzer als ein Rotationsdurchlauf von 5 min) |
| Songtitel | `php C:\inetpub\dashboard\websocket\songtitle.php` | **prüfen** (z. B. 30 s – 1 min, nur sinnvoll bei laufendem Radio) |

`rendercache.php` holt die Seiten per HTTP (`DASHBOARD_BASE_URL/monitor/source/…`), schreibt sie jetzt atomar
und behält bei Fehlern die letzte gültige Version.

## 7. Yodeck- / Browser-Konfiguration

- App-Typ „Web Page“ mit URL `https://<server>/monitor/?Name=<Dashboard-Name>`.
  `Name` ist Pflicht für Toasts, YouTube, Radio, Fun Mode und Glücksrad (wie bisher).
- **Kein** Reload-Intervall in Yodeck setzen: Die Seite aktualisiert sich selbst ohne Dokument-Reload.
  Ist ein Intervall zwingend, mindestens 24 h wählen.
- Debugging an einem Gerät: `…&debug=1` (Overlay unten rechts).

## 8. Empfohlene Auflösung

- **1920 × 1080** (Standard, Gestaltungsziel).
- 4K: Die Oberfläche skaliert ab 3000 px Breite automatisch (`--ui-scale: 2`) oder mit `&ui=4k`.
  Auf dem Pi 4 kostet 4K das Vierfache an Pixeln (Diagramme, Video). Wenn möglich gibt der Player in 1080p aus
  und der Bildschirm skaliert – **auf Zielhardware zu prüfen**.

## 9. Autoplay

- Radio, Toast-Töne und Tier-Sounds benötigen Autoplay **mit Ton**. Chromium erlaubt das nur nach
  Nutzerinteraktion oder mit Policy/Flag (`--autoplay-policy=no-user-gesture-required`). Ob der Yodeck-Player
  das zulässt bzw. konfigurierbar macht, ist **auf Zielhardware zu prüfen**.
- Videos in Seiteninhalten laufen stumm (immer erlaubt). Toast-Videos versuchen es mit Ton und fallen bei
  Blockade auf stumm zurück.
- Blockiertes Autoplay erzeugt keinen Fehler und keine Endlosschleife (die Wiedergabe wird nur übersprungen).

## 10. Hardwarebeschleunigung

- Übergänge nutzen ausschließlich `opacity`/`transform` (Compositor). Das setzt GPU-Rasterung/-Compositing
  im Chromium des Players voraus. Ob Yodeck sie auf dem Pi 4 aktiviert, ist **auf Zielhardware zu prüfen**
  (Indiz: Debug-Overlay zeigt viele Long Tasks bei Seitenwechseln, obwohl wenig gerendert wird).
- Ohne GPU-Compositing bleibt die Oberfläche funktionsfähig; nur die 180-ms-Übergänge werden ruckeliger.
  Mit `prefers-reduced-motion` (falls einstellbar) entfallen sie ganz.

## 11. Log-Pfade

| Log | Ort |
|---|---|
| WebSocket-Server | `websocket\logs\websocket.log` (Rotation bei 10 MB, 5 Archive), Ansicht: `/websocket/logs.php` |
| PHP/IIS | PHP `error_log` gemäß php.ini, IIS-Logs unter `%SystemDrive%\inetpub\logs\LogFiles` |
| songtitle.php | PHP `error_log` (`[SongTitle] …`) |
| Monitor (Browser) | Ringpuffer im Speicher (200 Einträge); mit `?debug=1`: `window.__dashboardDebug.log()` / `.report()` |

## 12. Recovery-Verhalten des Monitors

1. WebSocket weg → Reconnect mit Backoff (1 s → 30 s, Jitter), Offline-Banner nach 5 s, Inhalt bleibt stehen.
2. Server akzeptiert, sendet aber nichts → Watchdog nach 10 s → Neuaufbau.
3. Ansicht nicht ladbar / Fehlerdump → letzte gültige Ansicht bleibt, Kopfzeile „Veraltet · Stand HH:MM“.
4. 10 min ohne WebSocket **und** Webserver per HTTP erreichbar → kontrollierter Reload, höchstens 3× pro Stunde.
   Ist der Webserver nicht erreichbar, wird **nicht** neu geladen (sonst bliebe eine Browser-Fehlerseite stehen).
5. Nach einem Reload ohne Server zeigt der Monitor die zuletzt gespeicherte Ansicht (localStorage) als „Veraltet“.

## 13. Aktualisierung ohne lange Unterbrechung

1. Neues `dist/` bauen.
2. Auf dem Server zuerst **alle Dateien außer** `dist/.vite/manifest.json` kopieren (gehashte Namen, kollidieren
   nicht mit laufenden Displays), **zuletzt** `manifest.json` ersetzen. Alternativ: `dist.new/` hochladen und
   per Umbenennen tauschen (`dist` → `dist.prev`, `dist.new` → `dist`).
3. PHP-Dateien aktualisieren; bei Änderungen am WebSocket-Server diesen neu starten (Displays verbinden neu).
4. Im Admin „Reload All“ – alle Displays laden die neue Version (Unterbrechung < 2 s).
5. Alte gehashte Dateien in `dist/assets/` können später gelöscht werden.

## 14. Rollback

| Ebene | Maßnahme | Wirkung |
|---|---|---|
| ein Display | URL um `&legacy=1` ergänzen | alte Oberfläche |
| alle Displays | in `config.php`: `define("MONITOR_FORCE_LEGACY", true);` + „Reload All“ | alte Oberfläche |
| Frontend-Version | `dist` ← `dist.prev` zurückbenennen + „Reload All“ | vorige Build-Version |
| komplett | Git auf den vorherigen Commit zurücksetzen, WebSocket-Server neu starten | alter Stand |

Ohne `monitor/dist/` liefert `monitor/index.php` automatisch die alte Oberfläche aus.
