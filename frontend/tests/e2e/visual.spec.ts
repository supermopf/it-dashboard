import { expect, test, type Page } from '@playwright/test';
import { mock, openMonitor, sendCommand, showPage } from './support';

const SHOTS = '../docs/screenshots';

async function assertNoOverflow(page: Page) {
  const o = await page.evaluate(() => ({
    sw: document.documentElement.scrollWidth,
    sh: document.documentElement.scrollHeight,
    w: innerWidth,
    h: innerHeight,
    overflowX: [...document.querySelectorAll('.view.is-active .card, .topbar *')].filter((el) => el.scrollWidth > el.clientWidth + 2 && getComputedStyle(el).overflow === 'visible').length,
  }));
  expect(o.sw).toBeLessThanOrEqual(o.w);
  expect(o.sh).toBeLessThanOrEqual(o.h);
  expect(o.overflowX).toBe(0);
}

for (const vp of [
  { name: 'fullhd', width: 1920, height: 1080, query: '?Name=Test' },
  { name: '4k', width: 3840, height: 2160, query: '?Name=Test&ui=4k' },
]) {
  test.describe(`Viewport ${vp.name}`, () => {
    test.use({ viewport: { width: vp.width, height: vp.height } });

    test('keine Scrollbalken, kein Überlauf, Screenshots', async ({ page }) => {
      await openMonitor(page, vp.query);
      await mock('/update', { PageStatus: { 3: 'warning', 4: 'ok', 5: 'warning', 6: 'ok', 9: 'warning' }, Cycle: true });
      for (const [p, marker] of [
        ['1', 'Isernhagen'],
        ['2', '1234567'],
        ['3', 'srv-app01'],
        ['5', 'scom-host-0'],
        ['6', 'vm-backup'],
        ['9', 'Alarm 0'],
      ] as const) {
        await showPage(page, p, marker);
        await page.waitForTimeout(250);
        await assertNoOverflow(page);
        if (vp.name === 'fullhd' || p === '3') await page.screenshot({ path: `${SHOTS}/${vp.name}-page${p}.png` });
      }
      await sendCommand('!urgent ' + JSON.stringify({ ToastSubject: 'Wartungsfenster heute 18:00', ToastBody: 'Exchange ist zwischen <b>18:00 und 19:00</b> nicht erreichbar.', ToastTime: '20000' }));
      await expect(page.locator('.urgent.is-open')).toBeVisible();
      await page.waitForTimeout(300);
      await page.screenshot({ path: `${SHOTS}/${vp.name}-toast.png` });
    });
  });
}

test('prefers-reduced-motion: keine Übergangsanimationen', async ({ page }) => {
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await openMonitor(page);
  await showPage(page, '2');
  const d = await page.locator('.view.is-active').evaluate((el) => getComputedStyle(el).transitionDuration);
  expect(d.split(',').every((x) => parseFloat(x) === 0)).toBe(true);
  const bar = await page.locator('.cycle-progress-bar').evaluate((el) => getComputedStyle(el).transitionDuration);
  expect(parseFloat(bar)).toBe(0);
  await mock('/update', { Snow: true });
  await page.waitForTimeout(1500);
  expect(await page.locator('canvas.snow').count()).toBe(0);
});

test('Animationen nutzen nur transform/opacity', async ({ page }) => {
  await openMonitor(page);
  const props = await page.evaluate(() => {
    const out = new Set<string>();
    for (const sheet of [...document.styleSheets]) {
      let rules: CSSRuleList;
      try {
        rules = sheet.cssRules;
      } catch {
        continue;
      }
      for (const r of [...rules]) {
        const st = (r as CSSStyleRule).style;
        if (!st) continue;
        const tp = st.transitionProperty;
        if (tp) tp.split(',').forEach((p) => out.add(p.trim()));
      }
    }
    return [...out];
  });
  const allowed = ['transform', 'opacity', 'background-color', 'color', 'none'];
  expect(props.filter((p) => !allowed.includes(p))).toEqual([]);
});
