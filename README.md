# it-dashboard
![Dashboard Example](/example/weather.png?raw=true "Weather Page")

This is a Dashboard written in PHP.

You want to monitor your infrastructure and also share memes with your colleagues?
Then this may be the right thing for you!

This application cycles through multiple PHP-pages and provides an REST-API for
important alerts or fun stuff.

The pages are easy to replace, so you can show your own performance data or whatever.

Most of the data in my pages are collected via a PowerShell Script,
which saves the data in a Database.

Features
========

* REST-API for ToastMessages
* Replaceable PHP-Pages
* Remote-Control Page for Tablets
* YouTube Support
* Internet Radio with Streaminfo
* Fun Mode (Every Page is filled with memes)
* Fun Stuff (e.g. A Bus driving through every screen)
* Caching
* WebSocket
* Adminpanel

Signage-Frontend (Monitor)
==========================

Die Signage-Ansicht `/monitor/` ist eine persistente App-Shell in Vanilla TypeScript (Build mit Vite),
optimiert für den Dauerbetrieb auf einem Raspberry Pi 4 (Yodeck): keine Dokument-Reloads beim Seitenwechsel,
genau eine WebSocket-Verbindung mit Reconnect, Offline-Fallback mit letztem gültigen Datenstand, kein
wachsender Speicherverbrauch. Die bisherige Oberfläche bleibt als Rollback unter `/monitor/?legacy=1`
erreichbar.

```bash
cd frontend
npm ci
npm run build      # → monitor/dist/
npm run check      # Lint, Typecheck, Unit-, PHP- und Browser-Tests, Bundle-Analyse
```

Ohne Build liefert `monitor/index.php` automatisch die alte Oberfläche aus.

| Dokument | Inhalt |
|---|---|
| [docs/AUDIT.md](docs/AUDIT.md) | Bestandsaufnahme, Befunde mit Fundstellen, unklare Funktionen |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Zielarchitektur, Module, Panel-Lebenszyklus |
| [docs/PERFORMANCE-BASELINE.md](docs/PERFORMANCE-BASELINE.md) | Messmethode und Werte vor dem Refactoring |
| [docs/PERFORMANCE-RESULTS.md](docs/PERFORMANCE-RESULTS.md) | Werte danach, Budgets, Abweichungen |
| [docs/YODECK-DEPLOYMENT.md](docs/YODECK-DEPLOYMENT.md) | Build, PHP, Cron, Rechte, Yodeck, Recovery, Rollback |
| [docs/CONFIGURATION.md](docs/CONFIGURATION.md) | alle Einstellungen |
| [docs/SECURITY.md](docs/SECURITY.md) | Sicherheitsmaßnahmen, Empfehlungen |
| [docs/MIGRATION.md](docs/MIGRATION.md) | Breaking Changes, Verhaltensänderungen |

Server
=======

To use the application, you need a Websocket.

The WebSocket is started by running: `/websocket/server.php`

Configuration lives in `config.php` (copy `config.template.php`). An alternative file can be selected with
the environment variable `DASHBOARD_CONFIG`.

REST-API
========

The API is reachable under: `/websocket/api.php`

|Parameter|Method|Required|String|Default|Description|
|--- |--- |--- |--- |--- |--- |
|ToastSubject|POST|No|String||Titele of Toast (max. 500 chars)|
|ToastBody|POST|No|String||Text of Toast (max. 20000 chars, limited HTML)|
|ToastPicture|POST|No|String||Picture/Video of Toast (absolute http(s) URL)|
|ToastColor|POST|No|String|#FA2A00|Color of Toast|
|ToastTextColor|POST|No|String|#FFFFFF|Color of Heading|
|ToastSound|POST|No|String|Win XP Error|URL of Sound (absolute http(s) URL)|
|ToastTime|POST|No|Integer|30000|Time until Toast hides in ms (1000–3600000)|
|ToastVolume|POST|No|Float|0.5|Volume from 0 to 1|
|ToastHistory|POST|No|Boolean|true|If true, the Toast is saved in the History|
|ToastVideoNoRepeat|POST|No|Boolean|false|If true, the Toast is hidden, when the Video finished|
|Target|POST|No|String|Alle|Name of the dashboard (`?Name=`) that should show the Toast|
|YouTubeURL|POST|No|String||Starts a YouTube video (youtube.com / youtu.be)|

Responses: `200 OK`, `400` (invalid parameters, with reason), `401` (API key required, see `TOAST_API_KEY`),
`429` (rate limit), `503` (WebSocket server unreachable).

Caching
=======

To use the caching, you need to set up a cronjob which executes `/monitor/rendercache.php`

This Script gets all pages in the `/monitor/source` folder and saves them in `/monitor/cache`.
Failed renders (empty response, SQL error dump) no longer overwrite the last valid cache file.
