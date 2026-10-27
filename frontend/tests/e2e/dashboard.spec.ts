import { expect, test } from '@playwright/test';
import { appStats, mock, openMonitor, sendCommand, showPage } from './support';

test.describe('Rotation und Lebenszyklus', () => {
  test('Seitenwechsel ohne Dokument-Reload, Charts und Panels wachsen nicht', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (e) => errors.push(e.message));
    const nav = await openMonitor(page);

    await showPage(page, '3', 'srv-app01');
    expect((await appStats(page)).charts).toBe(12);

    // Zwei volle Durchläufe 1…10
    for (let round = 0; round < 2; round++) {
      for (const p of ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10']) await showPage(page, p);
    }
    await page.waitForTimeout(400);
    const s = await appStats(page);
    expect(nav.navigations()).toBe(1); // nur der initiale Aufruf
    expect(s.panels).toBe(1);
    expect(s.charts).toBe(1); // Seite 10: ein Balkendiagramm
    expect(s.legacy.pluginDuplicatesSkipped).toBeGreaterThan(0);
    expect(await page.locator('.view').count()).toBe(1);
    // Chart.js 2.3 legt pro responsivem Diagramm ein Resize-iFrame an: nur die der aktiven Seite dürfen existieren.
    expect(await page.locator('iframe').count()).toBeLessThanOrEqual(s.charts);
    expect(errors).toEqual([]);
    // Seite 3 erneut: Charts müssen gezeichnet werden (Legacy: const-Redeklaration, AUDIT P4)
    await showPage(page, '3');
    expect((await appStats(page)).charts).toBe(12);
  });

  test('genau eine WebSocket-Verbindung und Registrierung wie Legacy', async ({ page }) => {
    await openMonitor(page, '?Name=Flur');
    await page.waitForTimeout(1500);
    const st = await mock('/state');
    expect(st.clients).toBe(1);
    expect(st.received).toEqual(expect.arrayContaining(['!repeat', '!reg [Dashboard]Flur', '!dashboard Flur']));
  });

  test('Navigation sendet !fpage', async ({ page }) => {
    await openMonitor(page);
    await page.locator('.nav-item[data-page="6"]').click();
    await expect.poll(async () => (await mock('/state')).received).toContain('!fpage 6');
    await expect(page.locator('.topbar-title')).toHaveText('Backup');
  });

  test('doppelte WebSocket-Nachrichten → nur ein Rendern', async ({ page }) => {
    await openMonitor(page);
    await showPage(page, '2');
    const before = (await appStats(page)).metrics.switches;
    await sendCommand('!page 2');
    await sendCommand('!page 2');
    await page.waitForTimeout(1500);
    expect((await appStats(page)).metrics.switches).toBe(before);
    // sekündliche, identische Updates werden dedupliziert
    expect((await appStats(page)).store.fieldsSkipped).toBeGreaterThan(10);
  });

  test('SCOM-Slider rotieren', async ({ page }) => {
    await openMonitor(page);
    await showPage(page, '5', 'scom-host-0');
    await expect(page.locator('#CurrentPage')).toHaveText('1');
    await expect(page.locator('#CurrentPage')).toHaveText('2', { timeout: 12000 });
  });
});

test.describe('Zuverlässigkeit', () => {
  test('WebSocket-Reconnect nach Verbindungsabbruch, Offline-Banner', async ({ page }) => {
    await openMonitor(page);
    await showPage(page, '2', 'Heute eröffnete Tickets');
    await mock('/pause', { ms: 9000 });
    await expect(page.locator('.offline-banner')).toBeVisible({ timeout: 12000 });
    await expect(page.locator('.offline-banner')).toContainText('Letzte erfolgreiche Aktualisierung');
    // Inhalt bleibt stehen
    await expect(page.locator('.view.is-active')).toContainText('Heute eröffnete Tickets');
    await expect(page.locator('.chip-ok')).toBeVisible({ timeout: 40000 });
    await expect(page.locator('.offline-banner')).toBeHidden();
    await page.waitForTimeout(1500);
    const s = await appStats(page);
    expect(s.ws.reconnects).toBeGreaterThanOrEqual(1);
    expect(s.ws.activeSockets).toBe(1);
    expect((await mock('/state')).clients).toBe(1);
  });

  test('halb-offene Verbindung wird vom Watchdog erkannt', async ({ page }) => {
    await openMonitor(page);
    await mock('/silence', { on: true });
    await expect(page.locator('.chip-ok')).toBeHidden({ timeout: 15000 });
    await mock('/silence', { on: false });
    await expect(page.locator('.chip-ok')).toBeVisible({ timeout: 30000 });
  });

  test('API-Fehler → letzte gültige Ansicht bleibt, als veraltet markiert', async ({ page }) => {
    await openMonitor(page);
    await showPage(page, '6', 'vm-backup-0');
    await page.route('**/page6.php', (r) => r.abort());
    await showPage(page, '7');
    await sendCommand('!page 6');
    await expect(page.locator('.topbar-freshness')).toContainText('Veraltet', { timeout: 10000 });
    await expect(page.locator('.view.is-active')).toContainText('vm-backup-0');
  });

  test('Fehlerausgabe (SQL-Dump) wird nicht angezeigt – letzte gültige Ansicht bleibt', async ({ page }) => {
    await openMonitor(page);
    await showPage(page, '2', 'Heute eröffnete Tickets');
    await sendCommand('!page SQLERR');
    await expect(page.locator('.topbar-freshness')).toContainText('Veraltet', { timeout: 10000 });
    await expect(page.locator('.view.is-active')).toContainText('Heute eröffnete Tickets');
    await expect(page.locator('body')).not.toContainText('SQLSTATE');
  });

  test('Fehlerausgabe ohne vorherige Ansicht → Hinweis statt Dump', async ({ page }) => {
    await mock('/reset');
    await mock('/update', { Page: 1 });
    await page.route('**/page1.php', (r) => r.fulfill({ status: 200, body: '<pre>Array\n(\n [0] => Array ( [SQLSTATE] => 08001 )' }));
    await page.goto('/monitor/?Name=Test');
    await expect(page.locator('.view.is-active')).toContainText('Daten nicht verfügbar', { timeout: 15000 });
    await expect(page.locator('body')).not.toContainText('SQLSTATE');
  });

  test('langsame API: kein Freeze, Timeout nach 4 s', async ({ page }) => {
    await openMonitor(page);
    await showPage(page, '1');
    await page.route('**/page9.php', async (r) => {
      await new Promise((res) => setTimeout(res, 6000));
      await r.continue().catch(() => undefined);
    });
    await sendCommand('!page 9');
    // Uhr läuft weiter (Main Thread frei)
    const t1 = await page.locator('.topbar-clock').textContent();
    await page.waitForTimeout(1500);
    expect(await page.locator('.topbar-clock').textContent()).not.toBe(t1);
    await expect.poll(async () => (await appStats(page)).metrics.failedApiCalls, { timeout: 10000 }).toBeGreaterThan(0);
  });

  test('fehlerhafte und übergroße WebSocket-Nachrichten', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (e) => errors.push(e.message));
    await openMonitor(page);
    await mock('/raw', { data: '{kaputt' });
    await mock('/raw', { data: JSON.stringify({ type: 'update', CD: 'NaN', Page: { x: 1 }, PageStatus: 'x', SongTitle: 'x'.repeat(100000) }) });
    await sendCommand('!urgent {kein json');
    await sendCommand('!page ../../etc/passwd');
    await page.waitForTimeout(1000);
    expect(errors).toEqual([]);
    await expect(page.locator('.chip-ok')).toBeVisible();
  });

  test('Reload-Toast → kontrollierter Reload', async ({ page }) => {
    const nav = await openMonitor(page);
    await sendCommand('!urgent ' + JSON.stringify({ ToastBody: '<script>location.reload()</script>', ToastHistory: 'false' }));
    await expect.poll(() => nav.navigations(), { timeout: 10000 }).toBe(2);
    await expect(page.locator('.chip-ok')).toBeVisible({ timeout: 15000 });
  });
});

test.describe('Inhalte und Medien', () => {
  test('Urgent-Toast mit langem Text, Bild-Fallback und Schließen', async ({ page }) => {
    await openMonitor(page);
    await sendCommand(
      '!urgent ' +
        JSON.stringify({ ToastSubject: 'Störung <script>alert(1)</script>Mail', ToastBody: 'Sehr langer Text '.repeat(200), ToastPicture: '/nicht/da.png', ToastTime: '3000', ToastColor: '#b00020' }),
    );
    const card = page.locator('.urgent.is-open .urgent-card');
    await expect(card).toBeVisible();
    await expect(page.locator('.urgent-title')).toHaveText('Störung Mail');
    const box = await card.boundingBox();
    expect(box!.height).toBeLessThanOrEqual(1080);
    await expect(page.locator('.urgent')).toBeHidden({ timeout: 6000 });
  });

  test('Toast ohne ?Name wird ignoriert (Legacy-Verhalten)', async ({ page }) => {
    await openMonitor(page, '');
    await sendCommand('!urgent ' + JSON.stringify({ ToastBody: 'x' }));
    await page.waitForTimeout(800);
    await expect(page.locator('.urgent')).toBeHidden();
  });

  test('fehlende Bilder und nicht erreichbare Videos', async ({ page }) => {
    await openMonitor(page);
    await showPage(page, '1', 'Isernhagen');
    await expect(page.locator('.view.is-active .img-missing')).toBeVisible();
    await showPage(page, 'FUN', 'Testschlagzeile');
    await expect(page.locator('.view.is-active .img-missing')).toBeVisible();
    const s = await appStats(page);
    expect(s.media.playingVideo).toBeLessThanOrEqual(1);
  });

  test('Glücksrad dreht (1-basierte Würfelwerte, inkl. 20)', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await openMonitor(page);
    await showPage(page, 'WHEEL');
    await sendCommand('!roll 20 1 7');
    await expect(page.locator('.wheel-result .wheel-part')).toHaveCount(3, { timeout: 10000 });
  });

  test('leere Daten → Hinweis', async ({ page }) => {
    await openMonitor(page);
    await sendCommand('!page EMPTY');
    await expect(page.locator('.view.is-active')).toContainText(/nicht verfügbar|Keine Einträge/, { timeout: 10000 });
  });

  test('Bus fährt und meldet middle/BusActive', async ({ page }) => {
    await openMonitor(page);
    await sendCommand('!bus start');
    await expect(page.locator('.bus-lane')).toBeVisible();
    await expect.poll(async () => (await mock('/state')).received, { timeout: 15000 }).toEqual(expect.arrayContaining(['!var BusActive True', '!bus middle']));
  });

  test('Legacy-Oberfläche bleibt über ?legacy=1 erreichbar (Rollback)', async ({ page }) => {
    await page.goto('/monitor/?legacy=1&Name=Test');
    await expect(page.locator('#navigation')).toBeAttached();
  });
});
