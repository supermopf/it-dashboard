# Performance-Ergebnisse (nach dem Refactoring)

Gleiche Methode, gleiche Testdaten, gleiche Maschine wie in [PERFORMANCE-BASELINE.md](PERFORMANCE-BASELINE.md)
(x86-Laptop, WSL2, Chromium Headless 156, 1920 × 1080, Rotation alle 2 s, 5 min ≈ 150 Seitenwechsel).
Rohdaten: `docs/measurements/legacy.json`, `docs/measurements/new.json`.

> Die Werte gelten für den **Vergleich**. Absolute Werte auf dem Raspberry Pi 4 sind **auf Zielhardware zu
> messen** (siehe Baseline, Abschnitt „Auf Zielhardware zu messen“).

## Vergleich alt ↔ neu

| Kennzahl | Alt | Neu | Veränderung |
|---|---|---|---|
| Chart.js-Instanzen nach 5 min | 529 (wachsend) | **5** (= Diagramme der aktuellen Seite, 0–12) | kein Wachstum |
| Event-Listener nach 5 min | 2 719 (wachsend) | **30** (Schwankung 23–132 je nach Seite) | kein Wachstum |
| JS-Heap nach erzwungener GC | 30,2 MB | **5,3 MB** | −82 %, kein Wachstum |
| JS-Heap Spitze | 39,1 MB | 19,4 MB | |
| Long Tasks (> 50 ms) in 5 min | 46 (max. 175 ms) | **1** (64 ms, einmaliges Parsen von `legacy-vendor.js`) | Budget „keine dauerhaften Tasks > 50 ms“ eingehalten |
| Script-Zeit je 10 s | 458 ms | **13 ms** | −97 % |
| Layout je 10 s | 101 ms | 13 ms | −87 % |
| Style-Neuberechnung je 10 s | 124 ms | 30 ms | −76 % |
| Main-Thread gesamt je 10 s | 1 984 ms | 455 ms | −77 % |
| Seitenwechsel p50 / p95 / max | 1 090 / 1 217 / 1 268 ms | **220 / 243 / 244 ms** | inkl. 180-ms-Crossfade; Rendern geschieht unsichtbar vorher |
| Initialisierung (DOMContentLoaded) | 1 934 ms | **116 ms** | keine externen Fonts/CDN |
| Externe Runtime-Requests | 5 | **0** | |
| WebSocket-Verbindungen | 1 | 1 | |
| Fehlgeschlagene Requests / JS-Fehler | 0 / 0 | 0 / 0 | |
| Aktive Timer (global, max.) | 1 | 5 | Uhr, Health-Check, Watchdog, Debug-Overlay, Slider – alle zentral im Scheduler, wachsen nicht |

### Bundle

| | Alt | Neu |
|---|---|---|
| JS initial | 852,9 KB roh / 155,1 KB gzip | **56,7 KB roh / 19,4 KB gzip** (App) |
| JS bei erster Diagrammansicht | – | + `legacy-vendor.js` 270,3 KB roh / 81,0 KB gzip → **100,4 KB gzip** gesamt |
| CSS | 252,9 KB roh / 34,7 KB gzip | **21,5 KB roh / 4,9 KB gzip** |
| Debug-Modus | – | 1,9 KB gzip, nur mit `?debug=1` geladen |
| Caching | `?random=uniqid()` (nie gecacht) | Dateinamen mit Inhalts-Hash (dauerhaft cachebar), Ansichten mit bedingten Requests |

### WebSocket-Server

| | Alt | Neu |
|---|---|---|
| CPU im Leerlauf (5 Clients, 10 s) | 17,6 % | **0,2 %** |
| RSS nach 300 Reconnects | +76 KB | +4 KB |
| Close-Handshake | keine Antwort, Verbindung bleibt belegt | beantwortet, Verbindung sofort frei |
| Nachrichten > 1024 Byte | abgeschnitten | vollständig (getestet mit 6 000 Byte) |

## Performance-Budgets

| Budget | Ziel | Ergebnis | Status |
|---|---|---|---|
| JavaScript initial | < 150 KB gzip | 19,4 KB (App), 100,4 KB inkl. Legacy-Vendor | ✅ |
| CSS | < 50 KB gzip | 4,9 KB | ✅ |
| Keine dauerhaften Main-Thread-Tasks > 50 ms | – | 1 Long Task (64 ms) beim ersten Laden des Vendor-Bundles, danach keine | ✅ (x86) · Pi: **auf Zielhardware zu messen** |
| Keine fortlaufend steigende Heap-Nutzung | – | Restheap 5,3 MB nach 150 Wechseln, Listener/Charts konstant | ✅ |
| Stabile 30 fps auf dem Pi 4 | 30 fps | – | **auf Zielhardware zu messen** |
| Seitenwechsel ohne Dokument-Reload | – | E2E-Test: 1 Navigation über 21 Seitenwechsel | ✅ |
| Max. 1 WebSocket-Verbindung | 1 | 1 (E2E + Messung) | ✅ |
| Kein Polling schneller als 1×/s | – | kein REST-Polling; Uhr 1×/s, Health-Check 1×/5 s, Scheduler-Minimum 250 ms | ✅ |
| Kein sichtbarer Freeze bei WebSocket-Nachrichten | – | Updates dedupliziert, gebündelt pro Frame; Script-Zeit 13 ms/10 s | ✅ (x86) |

## Abweichungen und Grenzen

- **Legacy-Vendor (81 KB gzip):** Die Seiteninhalte benötigen weiterhin jQuery 2.2.2 und Chart.js 2.3.0 (mit
  moment.js für Zeitachsen). Das Bundle wird erst bei der ersten Diagrammansicht geladen und dann gecacht.
  Abbau nur durch Migration der Seiten auf JSON + native Panels möglich (nicht ohne Zugriff auf die
  Datenquellen testbar).
- **Resize-iFrames von Chart.js 2.3:** Pro sichtbarem Diagramm existiert weiterhin ein verstecktes iFrame
  (bis zu 12 auf Seite 3/4). Sie werden jetzt mit der Ansicht entfernt; ganz vermeiden ließen sie sich nur mit
  `responsive: false` in den Quellseiten oder einer neueren Chart.js-Version.
- **YouTube** benötigt ein iFrame (IFrame-API).
- **Bus-Fahrt (8,5 s) und Glücksrad (Dauer des Spin-Sounds)** sind länger als 300 ms. Beide sind
  funktionale Animationen (konstante Bewegung, linear bzw. Drehkurve), laufen nur auf Befehl und nutzen
  ausschließlich `transform`.
- **Countdown-Balken / Tier-Easter-Egg:** 1-s-Transition auf `transform` im Sekundentakt (konstante Bewegung).
- **Fun Mode mit zwei Videos:** Es spielt nur eines (`maxActiveVideos: 1`), das zweite zeigt das erste Bild.
- **4K:** Rendering in nativer Auflösung (vierfache Pixelzahl). Auf dem Pi 4 **auf Zielhardware zu messen**;
  Empfehlung: 1080p ausgeben.

## Wie auf dem Gerät nachmessen

1. Player-URL um `&debug=1` ergänzen.
2. Nach 1 h, 8 h und 24 h die Overlay-Werte notieren: Heap, Charts, Panels (muss 1 sein), Timer,
   WebSocket-Reconnects, Long Tasks.
3. Erwartung (aus den Messungen hier abgeleitet, nicht gemessen auf dem Pi): Charts ≤ 12, Panels = 1,
   Heap ohne steigenden Trend, WebSocket genau 1.
