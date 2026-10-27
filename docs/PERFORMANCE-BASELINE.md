# Performance-Baseline (vor dem Refactoring)

Gemessen wurde die **bisherige Monitor-Oberfläche** (Stand `ccc32bb`, heute `monitor/legacy.php`) unter
identischen, reproduzierbaren Bedingungen. Die Werte nach dem Refactoring stehen in
[PERFORMANCE-RESULTS.md](PERFORMANCE-RESULTS.md).

> **Wichtig:** Alle Werte stammen von einem x86-Laptop (Intel Core i5-1335U, WSL2 Ubuntu 26.04,
> Chromium Headless Shell 156), **nicht** vom Raspberry Pi 4. Sie eignen sich für den *Vergleich* alt ↔ neu
> und für Trends (Wachstum über die Zeit). Absolute Zeiten sind auf dem Pi 4 erfahrungsgemäß um ein
> Mehrfaches höher. Werte, die nur auf dem Gerät aussagekräftig sind, sind als **„auf Zielhardware zu messen“**
> gekennzeichnet. Es wurden keine Werte geschätzt oder hochgerechnet.

## Messmethode

### Werkzeuge

| Werkzeug | Zweck |
|---|---|
| `frontend/scripts/measure.mjs` | Startet `php -S` mit Testkonfiguration, den Mock-WebSocket-Server (Protokoll wie `server.php`) und Chromium; misst alt (`?legacy=1`) und neu nacheinander |
| `frontend/src/debug/probe.ts` → `monitor/dist/debug-probe.js` | Mess-Probe (per Init-Script vor allen Seitenskripten injiziert): Timer, WebSocket-Verbindungen, fetch/XHR-Fehler, Long Tasks (PerformanceObserver), Medienwiedergabe, DOM-Größe, iFrames, Chart-Instanzen, Heap |
| Chrome DevTools Protocol (`Performance.getMetrics`) | JS-Heap, Event-Listener, Script-/Layout-/Style-Zeit je Intervall |
| `frontend/scripts/summarize.mjs` | Auswertung der Rohdaten |
| `frontend/scripts/measure-server.mjs` | CPU und Speicher von `websocket/server.php` |
| Rohdaten | `docs/measurements/legacy.json` |

### Ablauf

1. Testdaten: `frontend/tests/fixtures/cache/page1…10.php` – synthetische Seiten mit identischer Struktur
   und identischen Inline-Skripten wie `monitor/source/pageN.php` (4 bzw. 12 Diagramme auf Seite 1/3/4,
   `pluginService.register` auf Seite 6/8, SCOM-Slider, lange Texte, große Zahlen, fehlendes Bild).
   Für die alte Oberfläche werden sie temporär nach `monitor/cache/` kopiert.
2. Viewport 1920 × 1080, `deviceScaleFactor` 1.
3. Seite laden, 4 s warten (Initialisierung).
4. **Beschleunigte Rotation:** Der Mock-Server sendet alle **2 s** `!page N` (1 → 10 → 1 …) – 15× schneller
   als im Betrieb (30 s). 5 Minuten = ~150 Seitenwechsel ≈ 75 Minuten Realbetrieb.
5. Alle 10 s eine Stichprobe (30 Stichproben).
6. Am Ende erzwungene Garbage Collection (`--js-flags=--expose-gc`), dann Restheap und Listener.
7. Seitenwechseldauer (alt): Zeit vom Eintreffen der WebSocket-Nachricht `!page` bis zum Ende der
   `bounceInUp`-Animation des Inhaltsbereichs plus zwei Frames.

### Reproduzieren

```bash
cd frontend
npm run build                         # baut u. a. monitor/dist/debug-probe.js
node scripts/measure.mjs --minutes 5 --interval 2000 --only legacy
node scripts/summarize.mjs
```

### Debug-Modus für Messungen auf dem Gerät

Auf dem Raspberry Pi die Yodeck-URL um `&debug=1` ergänzen. Das Overlay zeigt alle 5 s: Initialisierungszeit,
Dauer des letzten Seitenwechsels, DOM-Knoten, iFrames, Chart-Instanzen, Panel-Instanzen, Timer (App und
global), WebSocket-Zustand/Reconnects/Nachrichten, Heap (nur Chromium), Long Tasks, spielende Audio-/Video-
Elemente, fehlgeschlagene HTTP-Requests. `window.__dashboardDebug.report()` liefert zusätzlich die Ladezeiten
externer Ressourcen. Ohne `debug=1` wird kein Debug-Code geladen (eigener Chunk); im Normalbetrieb laufen nur
einfache Zähler mit.

## Ergebnisse – alte Oberfläche

### Bundle und Abhängigkeiten

| Kennzahl | Wert |
|---|---|
| JavaScript (8 Dateien) | **852,9 KB roh / 155,1 KB gzip**, davon Chart.bundle.js 685 KB unminifiziert |
| CSS (6 Dateien) | **252,9 KB roh / 34,7 KB gzip** |
| Externe Runtime-Requests | 5 (Google Fonts 2× CSS + Schriftdateien, Font Awesome CSS + Webfonts) |
| Cache-Busting | `?random=uniqid()` an `style.css`, `darkmode.css`, `app.js` → kein Browser-Cache |

### Laufzeit (5 min, ~150 Seitenwechsel, x86)

| Kennzahl | Beginn | Ende (5 min) | Bemerkung |
|---|---|---|---|
| **Chart.js-Instanzen** (`Chart.instances`) | 29 | **529** | wächst linear um ~100/min (≈ 3,3 pro Wechsel) – nie freigegeben (AUDIT P1) |
| **Event-Listener** (CDP) | 215 | **2 719** | wächst linear; nach GC 2 684 |
| JS-Heap | 13,1 MB | 33,0 MB (max. 39,1) | **nach erzwungener GC: 30,2 MB** – nicht freigebbar, also Leck |
| DOM-Knoten (Dokument) | 137–248 | – | der alte Inhalt wird zwar aus dem DOM entfernt, bleibt aber über `Chart.instances` im Speicher |
| iFrames (Chart-Resize) | bis 4 | – | pro Diagramm eines (sichtbar nur die der aktuellen Seite) |
| Aktive Timer | ≤ 1 Timeout, 0 Intervalle | – | |
| WebSocket-Verbindungen | 1 | 1 | 0 Reconnects (Server stabil) |
| Fehlgeschlagene Requests | 0 | 0 | |
| JavaScript-Fehler | 0 | 0 | |
| Long Tasks (> 50 ms) | – | **46** in 5 min, max. **175 ms** | |
| Main-Thread-Zeit je 10 s | – | Script **458 ms**, Layout 101 ms, Style 124 ms, gesamt **1 984 ms** (≈ 20 %) | |
| **Seitenwechsel** (Nachricht → Animation fertig) | – | p50 **1 090 ms**, p95 1 217 ms, max. 1 268 ms (n = 151) | dominiert von der 1-s-`bounceInUp`-Animation des gesamten Inhaltsbereichs + 1-s-Chart-Animationen |
| Initialisierung | – | DOMContentLoaded 1 934 ms, load 1 970 ms | inkl. externer Fonts/Font Awesome über das Netz |

**Hochrechnung bewusst nicht vorgenommen.** Die Messung zeigt eindeutig lineares Wachstum von
Chart-Instanzen, Listenern und nicht freigebbarem Heap mit jedem Seitenwechsel. Im Realbetrieb (ein Wechsel
alle 30 s, ~2 900 Wechsel pro Tag) setzt sich das ohne Obergrenze fort, bis der Browser neu geladen wird.
Wie schnell das auf dem Pi 4 mit 4 GB zu spürbarem Ruckeln bzw. Abstürzen führt, ist **auf Zielhardware zu
messen** (Debug-Overlay über 24 h).

### WebSocket-Server (`websocket/server.php`, alt)

Gemessen mit `scripts/measure-server.mjs` (5 verbundene Clients, 10 s Leerlauf, danach 300 Verbindungsauf-
und -abbauten):

| Kennzahl | Wert |
|---|---|
| CPU im Leerlauf | **17,6 %** eines Kerns (Busy-Loop durch `socket_select(…, 0, 10)`, AUDIT S1) |
| RSS vor / nach 300 Reconnects | 28 584 KB / 28 660 KB |

Zusätzlich im Integrationstest festgestellt: Der alte Server beantwortet keine Close-Frames; sauber
schließende Clients belegen ihre Verbindung bis zum Client-Timeout weiter (Verbindungslimit 10 pro IP greift).

## Auf Zielhardware zu messen

| Messgröße | Warum nicht hier |
|---|---|
| Bildrate (fps) bei Seitenwechseln und Toasts | Headless-Chromium auf x86 rendert ohne die GPU-/Compositor-Eigenschaften des Pi 4 |
| Absolute Seitenwechseldauer und Long-Task-Längen | CPU des Pi 4 (Cortex-A72) deutlich langsamer |
| Heap-Wachstum über 24 h im Realtakt (30 s) | nur auf dem Gerät mit `?debug=1` sinnvoll |
| Hardwarebeschleunigung (GPU-Rasterung/-Compositing) in Yodeck | geräte-/softwareabhängig |
| Autoplay-Verhalten (Radio, Toast-Ton) | Policy des Yodeck-Chromiums |
| Dekodierlast großer GIFs/Videos und des Radarbilds (974 KB PNG) | GPU/Videodecoder des Pi |
| Netzwerk-Latenz Pi ↔ IIS ↔ WebSocket | Produktionsnetz |

Empfohlenes Vorgehen auf dem Gerät: je einen Player 24 h mit `/monitor/?legacy=1&Name=…` und
`/monitor/?debug=1&Name=…` betreiben (die Probe der alten Oberfläche liefert `monitor/dist/debug-probe.js`
nur in Playwright; für die alte Oberfläche auf dem Gerät reichen Chromium-Task-Manager bzw. Beobachtung).
