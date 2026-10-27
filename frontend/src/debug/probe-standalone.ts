// Eigenständiger Build (dist/debug-probe.js) zur Messung der Legacy-Oberfläche.
// Muss vor allen Seitenskripten laufen (z. B. Playwright addInitScript).
import { installProbe } from './probe';

installProbe();
