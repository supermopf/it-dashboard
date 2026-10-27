# Sicherheit

Stand nach dem Refactoring. Befunde und Fundstellen: [AUDIT.md §3.3](AUDIT.md). Breaking Changes:
[MIGRATION.md](MIGRATION.md).

## Umgesetzt

| Thema | Maßnahme | Datei(en) |
|---|---|---|
| Secrets im Repository | geprüft: `config.php` ist gitignored, versionierte Dateien enthalten keine Passwörter/Keys | `.gitignore` |
| Secrets im Frontend | Monitor erhält nur `wsUrl` + Optionen; `config.js.php` (Altoberflächen) per `json_encode` | `monitor/index.php`, `config.js.php` |
| Konfiguration über Environment | `DASHBOARD_CONFIG` wählt eine Konfigurationsdatei | alle Einstiegspunkte |
| HTML-Escaping | `h()` für alle DB-Inhalte der Monitor-Seiten und Fun-Seite; Client-Liste im Server | `lib/security.php`, `monitor/source/*`, `monitor/cache/pageFUN.php`, `websocket/server.php` |
| Sichere JSON-Ausgabe | `json_for_html()` (`JSON_HEX_TAG` usw.) für eingebettetes JSON | `lib/security.php` |
| ToastBody/Subject | HTML-Allowlist im Monitor; Skripte werden nicht ausgeführt. Nur der bekannte Reload-Toast löst einen kontrollierten Reload aus | `frontend/src/util/sanitize.ts`, `features/urgent-toast.ts` |
| ToastPicture/ToastSound/Medien-URLs | Client: nur http(s)/relativ, keine `javascript:`/`data:`; Server: nur http(s), Typ-Whitelist per `finfo` (Bild/Video bzw. Audio), Größenlimit, 5 s Timeout, optionale Host-Whitelist | `util/url.ts`, `websocket/lib/protocol.php` |
| REST-Parameter | Validierung aller Felder, unbekannte Felder werden verworfen, Fehler → HTTP 400 mit Begründung | `websocket/lib/api_validation.php`, `api.php` |
| Rate Limiting | 30 Anfragen/min/IP (konfigurierbar), HTTP 429 | `api.php`, `lib/security.php` |
| API-Schlüssel | optional `TOAST_API_KEY` für externe Aufrufer | `api.php` |
| WebSocket `!var` | nur freigegebene Variablen mit Typprüfung (vorher: beliebige globale Variable überschreibbar) | `websocket/lib/protocol.php` |
| WebSocket-Origin | optionale Origin-Whitelist | `server.php` |
| WebSocket-Befehle | optional nur aus vertrauenswürdigen Netzen | `server.php` |
| WebSocket-Frames | Längenprüfung (max. 1 MiB), korrekte Opcode-Behandlung, Close-Handshake | `websocket/lib/protocol.php`, `server.php` |
| SQL-Injection | parametrisierte Abfragen | `features.php`, `julianometer.php` |
| LDAP-Injection | `ldap_escape` | `adphoto.php` |
| CSRF Logs | Löschen nur per POST | `logs.php` |
| Admin-/Control-Zugriff | optionale Benutzer-/Netz-Whitelist | `lib/security.php` + Admin-Seiten |
| Header | `X-Content-Type-Options: nosniff`, `Referrer-Policy: same-origin`, CSP für Monitor und API | `monitor/index.php`, `api.php`, `config.js.php` |
| Logs | Monitor: Ringpuffer, Meldungen gekürzt, keine Toast-Inhalte; Server: USERMESSAGE auf 500 Zeichen gekürzt | `core/log.ts`, `server.php` |

### Content-Security-Policy des Monitors

```
default-src 'self';
script-src 'self' 'unsafe-eval' https://www.youtube.com https://s.ytimg.com;
style-src 'self' 'unsafe-inline';
img-src * data: blob:;  media-src * blob:;
connect-src 'self' <Origin von WEBSOCKET_URL>;
frame-src https://www.youtube.com https://www.youtube-nocookie.com;
font-src 'self' data:;  object-src 'none';  base-uri 'self'
```

- `'unsafe-eval'` ist nötig, weil die Inline-Skripte der Legacy-Seiten per `new Function` in einem eigenen
  Scope laufen. `'unsafe-inline'` für Skripte ist **nicht** gesetzt – eingeschleuste `<script>`-Tags (z. B.
  über Datenbankinhalte) werden vom Browser blockiert.
- `img-src`/`media-src *`: Toast-Bilder, Radiostreams und Sender-Icons kommen von beliebigen Hosts. Wer die
  Quellen einschränken will, setzt `MONITOR_CSP`.
- `style-src 'unsafe-inline'`: die Seiten enthalten Inline-Styles.

## Empfohlene Betriebsmaßnahmen (nicht im Code erzwingbar)

1. **IIS-Windows-Authentifizierung** für `/websocket/` (außer `api.php`, falls externe Systeme Toasts senden),
   `/control/`, `/workmode.php`, `/applications/`. Danach `ADMIN_ALLOWED_USERS` setzen. Beispiel
   `web.config` im jeweiligen Verzeichnis:
   ```xml
   <configuration><system.webServer><security><authentication>
     <anonymousAuthentication enabled="false" />
     <windowsAuthentication enabled="true" />
   </authentication></security></system.webServer></configuration>
   ```
   `/monitor/` muss anonym bleiben (Yodeck-Player können sich nicht per Windows-Auth anmelden).
2. **Port 9000 nicht direkt erreichbar machen** – nur über den IIS/ARR-Proxy (WSS). Sonst kann ein Client
   `X-Forwarded-For` fälschen und damit `WEBSOCKET_TRUSTED_COMMAND_NETWORKS` umgehen.
3. `WEBSOCKET_TRUSTED_COMMAND_NETWORKS` auf die Netze der Displays, Tablets und Admin-PCs setzen.
4. `TOAST_API_KEY` setzen, wenn externe Systeme (Monitoring, Skripte) Toasts senden.
5. `web.config` (lokal, nicht versioniert) prüfen:
   - `Access-Control-Allow-Origin: *` entfernen (wird nicht benötigt, alle Aufrufe sind Same-Origin);
   - `httpErrors errorMode="Detailed"` → `DetailedLocalOnly`;
   - `maxAllowedContentLength` (2 GB) deutlich senken (es gibt keine Uploads, nur Toast-Formulare).
6. Für `websocket/cache`, `imgcache`, `logs`, `toasts.db`, `monitor/cache` die Ausführung von Skripten im
   IIS deaktivieren (Handler-Zuordnung), da dort heruntergeladene Dateien liegen.

## Offene Punkte

- **WebSocket-Authentifizierung**: Das Protokoll hat keine Benutzerkennung. Ein Token im Browser-JS wäre für
  jeden Besucher sichtbar und brächte keine echte Sicherheit. Wirksam sind Netzwerkbeschränkung
  (`WEBSOCKET_TRUSTED_COMMAND_NETWORKS`, Proxy) und der Zugriffsschutz der Steuerseiten.
- **Uploads**: Es gibt keine Datei-Uploads. „Uploadgrößen und Dateitypen“ betreffen nur die
  server-seitig heruntergeladenen Toast-Medien (Typ-Whitelist + `TOAST_MEDIA_MAX_BYTES`).
- **Admin-Seiten**: Die Admin-Oberflächen selbst (jQuery 2.0 vom Google-CDN, `innerHTML` mit Servertexten in
  `websocket/index.php`) wurden nicht neu geschrieben. Gefundene XSS-Stellen sind in AUDIT X7/X8
  dokumentiert; die wichtigste Quelle (Client-Liste) wird jetzt serverseitig escaped.
- `applications/` (Desktop-Ansicht) enthält weiterhin unescapte Ausgaben (AUDIT X6) – nicht Teil der
  Signage-Strecke, für eine Folgeaufgabe vorgemerkt.
