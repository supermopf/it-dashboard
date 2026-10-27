// Legacy-Abhängigkeiten der serverseitig gerenderten Ansichten (monitor/cache/pageN.php).
// Die Inline-Skripte dort erwarten globale `$`/`jQuery` und `Chart` (2.x-API, xAxes/pluginService).
// jQuery registriert sich selbst global (siehe Plugin jqueryGlobalMode in vite.config.ts).
import '../../../monitor/lib/js/jquery.min.js';
import Chart from '../../../monitor/lib/js/Chart.bundle.js';

window.Chart = Chart;
