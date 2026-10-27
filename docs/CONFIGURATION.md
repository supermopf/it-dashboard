# Konfiguration

Die Konfiguration liegt serverseitig in `config.php` (nicht versioniert, Vorlage: `config.template.php`).
Alle neuen Einstellungen sind **optional**; ohne sie verhält sich das System wie bisher.

## Speicherort / Environment

| Variable | Wirkung |
|---|---|
| `DASHBOARD_CONFIG` (Environment) | Absoluter Pfad zu einer alternativen `config.php`. Wird von `monitor/index.php`, `config.js.php`, `websocket/server.php`, `api.php`, `rendercache.php` und den Admin-Seiten ausgewertet. Für Tests und für getrennte Konfigurationen (z. B. Staging). Ohne die Variable: `config.php` im Projektverzeichnis. |

Geheimnisse (SQL-Instanzen, LDAP-Bind, M365-Client-Secret, Checkmk, API-Keys) bleiben ausschließlich in
`config.php`. `monitor/index.php` gibt nur `WEBSOCKET_URL` und `MONITOR_OPTIONS` an den Browser.
`config.js.php` (für die alten Oberflächen) gibt wie bisher `LDAP_DOMAIN`, `LDAP_BASE_DN`,
`DASHBOARD_DOMAIN`, `DASHBOARD_BASE_URL`, `WEBSOCKET_URL` aus – jetzt per `json_encode`.

## Bestehende Konstanten

Unverändert: `LDAP_*`, `*_SQL_INSTANCE`, `*_SQL_DATABASE`, `APIKEY_openweathermap`, `DEFAULT_RADIOSTATION*`,
`M365_*`, `DASHBOARD_DOMAIN`, `DASHBOARD_BASE_URL`, `WEBSOCKET_URL`, `SUPPORT_EMAIL`, Farbkonstanten.

Im Template **ergänzt** (wurden von den Seiten schon benutzt, fehlten aber in der Vorlage):

| Konstante | Genutzt von |
|---|---|
| `TERTIARY_STYLE`, `Ter_Color`, `Ter_Color_Border` | `monitor/source/page4.php` |
| `JULIAN_KONTINGENT` | `monitor/source/page10.php` |
| `CHECKMK_BASE_URL`, `CHECKMK_USER`, `CHECKMK_SECRET`, optional `CA_BUNDLE` | `monitor/source/page8.php` (fehlen sie, wird der Ubuntu-Block übersprungen) |

## WebSocket-Server (`websocket/server.php`)

| Konstante | Standard | Bedeutung |
|---|---|---|
| `WEBSOCKET_PORT` | `9000` | Port des Servers (auch von `api.php` genutzt) |
| `WEBSOCKET_ALLOWED_ORIGINS` | – (alle) | Liste erlaubter `Origin`-Header beim Handshake, z. B. `array('https://dashboard.example.local')`. Clients ohne Origin (PHP-Skripte wie `api.php`, `songtitle.php`) bleiben erlaubt. |
| `WEBSOCKET_TRUSTED_COMMAND_NETWORKS` | – (alle) | CIDR-Liste. Steuernde Befehle (`!var`, `!fpage`, `!page`, `!urgent`, `!video`, `!spin`, `!bus`, …) werden nur von diesen Netzen angenommen. Registrierung (`!reg`, `!dashboard`, `!repeat`, `!clientlist`) bleibt für alle offen. **Monitore, Control-Tablets und der Server selbst (127.0.0.1) müssen enthalten sein.** |
| `TOAST_MEDIA_ALLOWED_HOSTS` | – (alle) | Hosts, von denen `ToastPicture`/`ToastSound` heruntergeladen werden dürfen (Subdomains eingeschlossen). |
| `TOAST_MEDIA_MAX_BYTES` | 25 MiB | Größenlimit pro Toast-Medium |

## REST-API (`websocket/api.php`)

| Konstante | Standard | Bedeutung |
|---|---|---|
| `TOAST_API_KEY` | – | Wenn gesetzt, müssen **externe** Aufrufer den Header `X-Api-Key` (oder POST-Feld `ApiKey`) senden. Same-Origin-Aufrufe der Admin-Seiten brauchen keinen Schlüssel. |
| `TOAST_API_RATE_LIMIT` | `30` | Anfragen pro Minute und Client-IP (`0` = aus). Zähler im Temp-Verzeichnis. |

## Zugriffsschutz Admin/Control/Workmode

| Konstante | Bedeutung |
|---|---|
| `ADMIN_ALLOWED_USERS` | Liste erlaubter Benutzer (`REMOTE_USER`/`AUTH_USER`, z. B. bei IIS-Windows-Authentifizierung). Vergleich ohne Groß-/Kleinschreibung, mit oder ohne Domäne. |
| `ADMIN_ALLOWED_NETWORKS` | CIDR-Liste erlaubter Client-Netze |
| `TRUSTED_PROXIES` | Proxys, deren `X-Forwarded-For` ausgewertet wird (Standard `127.0.0.1`, `::1`) |

Ohne `ADMIN_ALLOWED_*` ist der Schutz inaktiv (bisheriges Verhalten). Geschützt werden:
`websocket/index.php`, `newtoast.php`, `youtube.php`, `history.php`, `best.php`, `features.php`,
`julianometer.php`, `logs.php`, `control/index.php`, `workmode.php`.

## Monitor (Signage)

| Konstante | Bedeutung |
|---|---|
| `MONITOR_FORCE_LEGACY` | `true` → alte Oberfläche für alle Displays (Rollback ohne Dateiänderung) |
| `MONITOR_CSP` | eigene Content-Security-Policy statt der Standard-Policy |
| `MONITOR_OPTIONS` | Array mit Frontend-Optionen (nur die folgenden Schlüssel werden übernommen) |

`MONITOR_OPTIONS`:

| Schlüssel | Standard | Bedeutung |
|---|---|---|
| `alertPages` | `['3','4','5','6','9']` | Seiten, deren `PageStatus = warning` als Warnung angezeigt wird (siehe AUDIT §5.1) |
| `cycleSeconds` | `30` | Länge eines Zyklus (für den Countdown-Balken; muss zu `$CycleTime` im Server passen) |
| `fetchTimeoutMs` | `8000` | Timeout für das Laden einer Ansicht |
| `prefetchLeadSeconds` | `4` | So viele Sekunden vor dem Wechsel wird die nächste Seite vorgeladen |
| `maxActiveVideos` | `1` | Maximal gleichzeitig spielende Videos |
| `chartAnimation` | `false` | Chart.js-Animationen (auf dem Pi aus lassen) |
| `snowFlakes` | `60` | Schneeflocken (max. 200) |
| `toastScripts` | `'reload-only'` | `'legacy'` führt `<script>` im ToastBody aus (unsicher, nur Übergang) |
| `recovery` | `{enabled: true, wsDownMs: 600000, maxReloadsPerHour: 3, heapLimitMb: 0}` | Recovery-Reload, siehe ARCHITECTURE.md |
| `wsWatchdogMs` | `10000` | Watchdog für halb-offene Verbindungen |
| `uiScale` | `'auto'` | `1` = Full HD, `2` = 4K, `'auto'` = 2 ab 3000 px Breite |
| `pages` | 10 Standardseiten | Titel/Icons der Navigation: `[{id:'1', title:'Temperatur', icon:'cloud'}, …]` |
| `cachePath` | `'./cache/'` | Pfad der Ansichten relativ zu `monitor/` |

## URL-Parameter des Monitors

| Parameter | Wirkung |
|---|---|
| `Name=<Dashboard>` | wie bisher: Registrierung für Targeting; ohne Namen keine Toasts/Videos/Fun/Rad/Radio |
| `legacy=1` | alte Oberfläche |
| `debug=1` | Debug-Modus (Overlay + `window.__dashboardDebug`) |
| `ui=4k` / `ui=hd` | UI-Skalierung erzwingen |
