import type { App } from '../app';
import { log } from '../core/log';
import { panelStats } from '../panels/panel';
import { installProbe } from './probe';

/**
 * Debug-Modus (?debug=1 oder localStorage „dashboard.debug“ = „1“).
 * Zeigt ein kleines Overlay und stellt `window.__dashboardDebug` bereit:
 *   __dashboardDebug.report()  – Zusammenfassung
 *   __dashboardDebug.samples() – Zeitreihe (alle 5 s, max. 720 Einträge = 1 h)
 * Hinweis: Timer-Instrumentierung greift erst ab dem Laden dieses Moduls.
 */
export function startDebug(app: App): void {
  log.consoleLevel = 'debug';
  const probe = installProbe({
    panels: () => panelStats.live,
    extra: () => app.stats(),
  });
  probe.mark('debug:start');

  const box = document.createElement('pre');
  box.className = 'debug-overlay';
  document.body.append(box);

  const render = () => {
    const s = probe.sample();
    const st = app.stats() as Record<string, any>; // eslint-disable-line @typescript-eslint/no-explicit-any
    box.textContent = [
      `init ${Math.round(app.metrics.initMs)} ms · switch ${Math.round(app.metrics.lastSwitchMs)} ms (${app.metrics.switches})`,
      `DOM ${s.domNodes} · iframes ${s.iframes} · charts ${s.charts} · panels ${panelStats.live}`,
      `timer app ${st.timers} · global to ${s.timeouts} / iv ${s.intervals}`,
      `ws ${st.wsState} open ${s.wsOpen} · reconnects ${st.ws.reconnects} · msgs ${st.ws.messages}`,
      `heap ${s.heapUsedMb ?? 'n/a'} MB · longtasks ${s.longTasks} (max ${s.longTaskMaxMs} ms)`,
      `media audio ${s.playingAudio} · video ${s.playingVideo} · http fail ${st.http.failures}/${st.http.requests}`,
    ].join('\n');
  };
  render();
  app.scheduler.interval(render, 5000, null, { label: 'debug-overlay' });

  (window as unknown as { __dashboardDebug: unknown }).__dashboardDebug = {
    report: () => probe.report(),
    samples: () => probe.samples(),
    log: () => log.entries(),
    stats: () => app.stats(),
  };
}
