import { expect, type Page } from '@playwright/test';

export const CTRL = `http://127.0.0.1:${Number(process.env.WS_PORT ?? 9100) + 1}`;

export async function mock(path: string, body: unknown = {}): Promise<any> { // eslint-disable-line @typescript-eslint/no-explicit-any
  const res = await fetch(CTRL + path, { method: path === '/state' ? 'GET' : 'POST', body: path === '/state' ? undefined : JSON.stringify(body) });
  return res.json();
}

export async function sendCommand(message: string): Promise<void> {
  await mock('/send', { message });
}

/** Öffnet den Monitor, wartet auf Verbindung und erste Ansicht. Zählt echte Dokument-Navigationen. */
export async function openMonitor(page: Page, query = '?Name=Test'): Promise<{ navigations: () => number }> {
  await mock('/reset');
  let navigations = 0;
  page.on('framenavigated', (f) => {
    if (f === page.mainFrame()) navigations++;
  });
  await page.goto(`/monitor/${query}`);
  await expect(page.locator('.chip-ok')).toBeVisible({ timeout: 15000 });
  await expect(page.locator('.view.is-active .legacy-view')).toBeVisible({ timeout: 15000 });
  // Warten, bis kein Ansichtswechsel mehr läuft (sonst zählen spätere Prüfungen alte Übergänge mit).
  await expect.poll(async () => (await appStats(page)).switching, { timeout: 15000 }).toBe(false);
  return { navigations: () => navigations };
}

export async function appStats(page: Page): Promise<any> { // eslint-disable-line @typescript-eslint/no-explicit-any
  return page.evaluate(() => (window as any).__dashboard.stats()); // eslint-disable-line @typescript-eslint/no-explicit-any
}

export async function showPage(page: Page, n: string, marker?: string): Promise<void> {
  const current = await appStats(page);
  // Ist die Seite schon aktiv, rendert die App gleiche Daten bewusst nicht neu → kein neuer Wechsel.
  const before = current.activePanel === n ? current.metrics.switches - 1 : current.metrics.switches;
  await sendCommand(`!page ${n}`);
  // Erst fertig, wenn genau diese Ansicht aktiv ist UND ihr Übergang abgeschlossen wurde.
  await expect
    .poll(async () => {
      const s = await appStats(page);
      return s.activePanel === n && s.metrics.switches > before && !s.switching;
    }, { timeout: 15000 })
    .toBe(true);
  if (marker) await expect(page.locator('.view.is-active')).toContainText(marker);
}
