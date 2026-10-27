# Architektur

Dieses Dokument beschreibt die Zielarchitektur nach dem Refactoring. Den Ist-Zustand vorher beschreibt
[AUDIT.md](AUDIT.md).

## Überblick

```
 Raspberry Pi 4 (Yodeck, Chromium)                  Windows-Server / IIS (C:\inetpub\dashboard)
 ┌──────────────────────────────────────┐          ┌──────────────────────────────────────────────┐
 │ /monitor/?Name=<Dashboard>           │  HTTP    │ monitor/index.php  → App-Shell + Konfig-JSON │
 │  ┌────────────────────────────────┐  │◀────────▶│ monitor/dist/      → Vite-Build (gehasht)    │
 │  │ App-Shell (persistent)         │  │          │ monitor/cache/pageN.php ← rendercache (Cron) │
 │  │  Topbar · Stage · Overlays     │  │          │ monitor/legacy.php → alte Oberfläche (Rollback)
 │  │  Store · Scheduler · Logger    │  │          │                                              │
 │  │  WsClient (genau 1 Verbindung) │──┼── WS ───▶│ websocket/server.php (Port 9000)             │
 │  │  ViewCache (letzte gültige)    │  │          │ websocket/api.php (REST) ── WS ──▶ server    │
 │  │  PanelHost → 1 aktives Panel   │  │          │ Admin, Control, songtitle, weather (wie bisher)
 │  └────────────────────────────────┘  │          └──────────────────────────────────────────────┘
 └──────────────────────────────────────┘
```

Das PHP-Backend bleibt in Struktur und Protokoll erhalten. Neu ist ausschließlich der Signage-Client
(`frontend/` → `monitor/dist/`). Control, Adminbereich und `applications/` nutzen weiterhin ihre bisherigen
Oberflächen (mit Sicherheitskorrekturen).

## Technologieentscheidung

**Vanilla TypeScript + Vite (nur Build).** Kein UI-Framework. Begründung:

- Die Inhalte der zehn Dashboard-Seiten sind serverseitig gerendertes HTML mit Chart.js-2-Inline-Skripten
  (`monitor/source/pageN.php`). Ein virtuelles DOM brächte hier keinen Vorteil – der Großteil des DOMs
  stammt vom Server, das Framework würde nur die Shell (Kopfzeile, Toasts) rendern.
- Die Shell hat wenige, klar abgegrenzte Zustände (Countdown, Verbindungsstatus, aktive Seite, Toasts). Ein
  ~100 Zeilen großer Store mit Feld-Abonnements und Frame-Batching deckt das ab.
- Budget: App-JS 19 KB gzip statt 150 KB Budget. Ein Framework würde den Pi-Parse-Aufwand ohne Nutzen erhöhen.

## Module (`frontend/src`)

| Modul | Aufgabe |
|---|---|
| `main.ts` | Einstieg: Konfiguration lesen, UI-Skala (Full HD / 4K), App starten, Debug-Modus nachladen |
| `app.ts` | Orchestrierung: WebSocket-Befehle → Ansichten/Features, Store-Bindings, Health, Recovery |
| `config.ts` | Konfiguration aus `<script id="dashboard-config">` (von `monitor/index.php`), Defaults |
| `core/scheduler.ts` | **Zentraler Scheduler**: alle Timer der App, Besitzer-basiertes Aufräumen, Pause bei verborgenem Dokument, Ausrichtung auf Sekundengrenzen |
| `core/scope.ts` | Lebensdauer-Container: Listener, Timer, Object-URLs, Aufräumfunktionen |
| `core/store.ts` | **State Store**: Deduplizierung, Feld-Abonnements, höchstens ein Rendern pro Animation Frame |
| `core/ws-client.ts` | **Genau eine WebSocket-Verbindung**, Backoff mit Jitter, Watchdog, Verbindungs-Timeout |
| `core/view-cache.ts` | Laden der Ansichten mit Timeout/AbortController, bedingte Requests (304), letzte gültige Version in Speicher + localStorage, Erkennung von Fehlerausgaben |
| `core/http.ts` | `fetch` mit Timeout und Fehlerklassen |
| `core/recovery.ts` | Reload-Schutz (max. N automatische Reloads pro Stunde) |
| `core/log.ts` | Logger mit Ringpuffer (200 Einträge) |
| `protocol/messages.ts` | Parser für Servernachrichten (unverändertes Protokoll), Normalisierung |
| `panels/panel.ts` | Panel-Lebenszyklus (`mount/update/activate/deactivate/destroy`) |
| `panels/panel-host.ts` | Eine aktive Ansicht, Crossfade, **Fehlergrenze**, „neuester Wechsel gewinnt“ |
| `panels/fragment-panel.ts` | Legacy-Ansichten aus `monitor/cache/pageN.php` (siehe unten) |
| `panels/youtube-panel.ts` | YouTube (ersetzt `pageYT.php`) |
| `panels/wheel-panel.ts` | Glücksrad (ersetzt `pageWHEEL.php` + `wheel.js`) |
| `panels/message-panel.ts` | Lade-, Fehler- und Leer-Zustände |
| `legacy/legacy-runtime.ts` | Lädt jQuery 2.2 + Chart.js 2.3 einmalig (`dist/legacy-vendor.js`), konfiguriert Chart.js, dedupliziert Plugins, zerstört Charts |
| `features/*` | Kopfzeile, Urgent-Toast, Songtitel, Radio, Tier-Easter-Egg, Bus, Schnee, Offline-Banner |
| `media/media-manager.ts` | Alle Audio-/Video-Elemente; max. ein aktives Video; vollständiges Entladen |
| `debug/*` | Debug-Modus und Mess-Probe (eigener Chunk, nur bei Bedarf geladen) |

## Datenfluss

1. `monitor/index.php` liefert die Shell mit Konfiguration (`wsUrl`, Pfade, Optionen) – keine Geheimnisse.
2. `WsClient` verbindet sich, sendet wie bisher `!repeat`, `!reg [Dashboard]<Name>` und nach der ersten
   Servernachricht `!dashboard <Name>`.
3. `update` (jede Sekunde) → `parseUpdate` → `store.patch()`. Unveränderte Felder werden verworfen; geänderte
   Felder lösen genau die abonnierten Bereiche aus (Countdown, Navigation, Radio, Schnee …), gebündelt pro Frame.
4. `!page N` → `ViewCache.load(N)` (Timeout, ggf. 304) → `PanelHost.show(FragmentPanel)`.
   Gleiche Seite mit gleichen Daten → kein neues Rendern.
5. Kurz vor dem Seitenwechsel (`CD ≤ 4 s`) sagt die App die nächste Seite voraus (gleiche Regel wie
   `server.php`) und lädt deren HTML vor – ohne zu rendern.

## Panel-Lebenszyklus

```
new Panel(ctx) ─▶ mount(root) ─▶ activate() ⇄ deactivate() ─▶ destroy()
                   DOM, Daten      Timer, Medien    Timer stoppen,    Charts/Medien/Listener/
                   (unsichtbar)    starten          Medien pausieren  Object-URLs freigeben,
                                                                       DOM leeren, Referenzen lösen
```

- `update(data)` wirkt nur im aktiven Zustand.
- Jedes Panel hat zwei Scopes: `scope` (mount→destroy) und `activeScope` (activate→deactivate).
- `PanelHost` mountet die neue Ansicht **unsichtbar** (opacity 0) – die teure Arbeit (DOM, Charts) passiert,
  während nichts animiert. Danach folgt ein Crossfade (180 ms ein, 140 ms aus, nur opacity/translate3d),
  anschließend wird die alte Ansicht zerstört. Es existieren höchstens zwei Ansichten gleichzeitig, und das
  nur während des Übergangs.
- Wirft `mount()`, ersetzt eine Fallback-Ansicht die fehlerhafte (Fehlergrenze pro Panel).

## Legacy-Ansichten (FragmentPanel)

Die zehn Dashboard-Seiten bleiben serverseitig gerendert (keine neuen Schnittstellen, keine erfundenen
Datenfelder). Das `FragmentPanel` macht sie dauerbetriebstauglich:

| Problem im Altbestand | Lösung |
|---|---|
| Inline-Skripte global ausgeführt (`var`-Lecks), alle Diagramme in einem Task (AUDIT P4) | alle Skripte einer Ansicht in **einem Funktions-Scope** (async `Function`), je Skript ein eigener Task (Yield per `MessageChannel`), Abbruch, wenn die Ansicht inzwischen zerstört wurde; ein fehlerhaftes Skript stoppt die übrigen nicht |
| Chart-Instanzen nie zerstört, `Chart.instances` wächst, Resize-iFrames bleiben (P1/P2) | `destroy()` aller Charts der Ansicht beim Verlassen |
| `pluginService.register` bei jedem Besuch (P3) | Registrierung dedupliziert |
| 1 s Chart-Animationen + animate.css-Bounce beim Wechsel (P5) | Chart-Animation aus, kurzer Crossfade |
| Timer aus Skripten | geschattete `setTimeout`/`setInterval`, beim Zerstören entfernt |
| Videos laufen weiter | Start erst bei `activate()`, Pause bei `deactivate()`, Entladen bei `destroy()` |
| SCOM-Slider rotierten nie (P22) | Slider-Rotation alle 10 s im aktiven Zustand |
| Font Awesome per CDN | Inline-SVG-Ersatz für die genutzten Icons |
| fehlende Bilder | Platzhalter „Bild nicht verfügbar“ |
| leere Seite | Hinweis „Keine Einträge“ |

## Zuverlässigkeit

- **WebSocket**: unbegrenzte Reconnects mit exponentiellem Backoff (1 s … 30 s) und Jitter, genau ein
  Reconnect-Timer, Watchdog (kein `update` > 10 s → Neuaufbau), Verbindungs-Timeout 10 s, sofortiger Versuch
  bei `online`/Sichtbarwerden.
- **Daten**: letzte gültige Ansicht bleibt bei Fehlern stehen und wird als „Veraltet · Stand HH:MM“
  markiert. Fehlerdumps (`<pre>Array…SQLSTATE`) werden nie angezeigt.
- **Offline-Banner** nach 5 s ohne Verbindung mit Zeitpunkt der letzten erfolgreichen Aktualisierung.
- **Health** in der Kopfzeile: „Live“ / „Verbinde …“ / „Offline“ (Icon + Text, nicht nur Farbe).
- **Recovery-Reload** (optional, Standard an): nach 10 min ohne WebSocket – nur wenn der Webserver per HTTP
  erreichbar ist (sonst bliebe Chromium auf einer Fehlerseite) und höchstens 3× pro Stunde
  (`localStorage`-Protokoll, schützt gegen Reload-Schleifen). Optionaler Heap-Grenzwert.
- **Hintergrund**: Bei verborgenem Dokument pausieren alle Timer mit `pauseWhenHidden`; rAF-basierte Arbeit
  ruht automatisch; beim Sichtbarwerden genau ein Neustart je Timer.
- **Logs**: Ringpuffer, Meldungen auf 300 Zeichen gekürzt, keine Toast-Inhalte.

## Backend-Änderungen

Siehe [SECURITY.md](SECURITY.md) und [MIGRATION.md](MIGRATION.md). Kurzfassung: Frame-Parser und
Close-Handshake im WebSocket-Server, `!var`-Whitelist, kein Busy-Loop, Medien-Cache mit Limits,
API-Validierung/Rate-Limit, Escaping in den Quellseiten, atomarer Cache.

## Bewusste Grenzen

- Die Seiteninhalte nutzen weiterhin Chart.js 2.3 und jQuery 2.2 (in `legacy-vendor.js`, 81 KB gzip, erst
  bei der ersten Diagrammansicht geladen). Eine Migration der Seiten auf JSON-Endpunkte + native Panels ist
  möglich (das Panel-Interface ist dafür vorbereitet), erfordert aber Zugriff auf die Datenbanken zum Testen.
- `new Function` setzt `'unsafe-eval'` in der CSP voraus (siehe SECURITY.md).
- YouTube erfordert ein iFrame (Vorgabe der IFrame-API).
