// Integrationstest für websocket/server.php (echter PHP-Prozess, echte Sockets).
//   node tests/integration/server-php.mjs
// Prüft: Registrierung, !var-Whitelist, große Frames (> 1024 Byte), mehrere Nachrichten pro Tick,
// !fpage-Broadcast, Targeting, Überleben fehlerhafter Eingaben und CPU-Last im Leerlauf (kein Busy-Loop).
import { spawn } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import WebSocket from 'ws';

const root = resolve(import.meta.dirname, '../../..');
const port = Number(process.env.TEST_SERVER_PORT ?? 9200);
const url = `ws://127.0.0.1:${port}/`;
let failed = 0;
let passed = 0;
const check = (name, cond, detail = '') => {
  if (cond) {
    passed++;
    console.log(`  ✓ ${name}`);
  } else {
    failed++;
    console.log(`  ✗ ${name}${detail ? ` — ${detail}` : ''}`);
  }
};
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

function client(name) {
  return new Promise((resolveClient, reject) => {
    const ws = new WebSocket(url, { headers: { 'X-Forwarded-For': `127.0.0.1:${40000 + Math.floor(Math.random() * 1000)}` } });
    const msgs = [];
    ws.on('message', (d) => msgs.push(JSON.parse(d.toString())));
    ws.on('open', () => {
      ws.send(JSON.stringify({ message: `!reg [${name}]` }));
      resolveClient({ ws, msgs, send: (m) => ws.send(JSON.stringify({ message: m })) });
    });
    ws.on('error', reject);
  });
}

async function waitFor(fn, ms = 4000) {
  const t = Date.now();
  while (Date.now() - t < ms) {
    const v = fn();
    if (v) return v;
    await sleep(50);
  }
  return null;
}

function cpuTicks(pid) {
  const stat = readFileSync(`/proc/${pid}/stat`, 'utf8').split(' ');
  return Number(stat[13]) + Number(stat[14]); // utime + stime
}

const php = spawn('php', ['websocket/server.php'], {
  cwd: root,
  env: { ...process.env, DASHBOARD_CONFIG: resolve(import.meta.dirname, '../fixtures/config.server.php'), TEST_SERVER_PORT: String(port) },
  stdio: ['ignore', 'pipe', 'pipe'],
});
let serverLog = '';
php.stdout.on('data', (d) => (serverLog += d));
php.stderr.on('data', (d) => (serverLog += d));

try {
  await sleep(800);
  console.log('websocket/server.php');
  const dash = await client('Dashboard]Flur');
  dash.send('!dashboard Flur');
  const ctrl = await client('Control');

  const upd = await waitFor(() => dash.msgs.find((m) => m.type === 'update'), 3000);
  check('sendet sekündliche Updates', !!upd);
  check('Update-Format unverändert', upd && upd.Cycle === 'true' && 'PageStatus' in upd && 'CD' in upd);

  ctrl.send('!var Cycle False');
  const off = await waitFor(() => dash.msgs.filter((m) => m.type === 'update').at(-1)?.Cycle === 'false', 3000);
  check('!var Cycle False wirkt', !!off);
  ctrl.send('!var Cycle True');

  ctrl.send('!var clients 0');
  ctrl.send('!var socket 0');
  ctrl.send('!var CycleTime abc');
  dash.msgs.length = 0;
  const alive = await waitFor(() => dash.msgs.find((m) => m.type === 'update'), 3000);
  check('unzulässige !var-Namen brechen den Server nicht (AUDIT S2)', !!alive);
  check('Ablehnung wird geloggt', /!var abgelehnt: !var clients/.test(serverLog));

  dash.msgs.length = 0;
  ctrl.send('!fpage 3');
  const page = await waitFor(() => dash.msgs.find((m) => m.type === 'command' && m.message === '!page 3'), 3000);
  check('!fpage → !page an alle', !!page);

  const body = 'Lang '.repeat(1200); // 6000 Byte → Frame > 1024 Byte (Legacy: abgeschnitten)
  dash.msgs.length = 0;
  ctrl.send('!urgent ' + JSON.stringify({ ToastSubject: 'Test', ToastBody: body, ToastHistory: 'false', Target: 'Flur' }));
  const urgent = await waitFor(() => dash.msgs.find((m) => m.type === 'command' && m.message.startsWith('!urgent ')), 4000);
  let parsed = null;
  try {
    parsed = urgent ? JSON.parse(urgent.message.slice(8)) : null;
  } catch {
    parsed = null;
  }
  check('Toast mit 6000 Byte kommt vollständig an (AUDIT S3)', parsed && parsed.ToastBody === body);
  check('Target wird aus der Nutzlast entfernt', parsed && !('Target' in parsed));

  dash.msgs.length = 0;
  ctrl.send('!bus start');
  ctrl.send('!songtitle now');
  ctrl.send('!spin');
  const burst = await waitFor(() => {
    const c = dash.msgs.filter((m) => m.type === 'command').map((m) => m.message);
    return c.includes('!bus start') && c.includes('!songtitle now') && c.some((x) => x.startsWith('!roll ')) ? c : null;
  }, 4000);
  check('mehrere Nachrichten kurz hintereinander werden alle verarbeitet', !!burst);
  const roll = burst?.find((x) => x.startsWith('!roll '));
  check('!roll liefert drei Werte 1…20', roll && roll.split(' ').slice(1).every((v) => Number(v) >= 1 && Number(v) <= 20));

  ctrl.ws._socket.write(Buffer.from([0x81, 0x85, 1, 2, 3, 4, 0x7a])); // abgeschnittener Frame
  ctrl.send('{kein json');
  await sleep(300);
  dash.msgs.length = 0;
  const stillAlive = await waitFor(() => dash.msgs.find((m) => m.type === 'update'), 3000);
  check('fehlerhafte Frames/JSON werden toleriert', !!stillAlive);

  // Viele Verbindungsauf- und -abbauten (ProxyMatch/registered wuchsen früher unbegrenzt)
  for (let i = 0; i < 30; i++) {
    const c = await client(`Temp${i}`);
    c.ws.close();
  }
  await sleep(500);
  const after = await client('Check');
  const list = [];
  after.ws.on('message', (d) => {
    const m = JSON.parse(d.toString());
    if (m.type === 'dashboardlist') list.push(...m.dashboards);
  });
  after.send('!dashboardlist');
  await waitFor(() => list.length, 3000);
  check('Dashboard-Registry nach 30 Reconnects korrekt', list.length === 1 && list[0] === 'Flur', JSON.stringify(list));

  const t0 = cpuTicks(php.pid);
  await sleep(5000);
  const ticks = cpuTicks(php.pid) - t0; // 100 Ticks = 1 s CPU
  check(`CPU im Leerlauf gering (kein Busy-Loop, AUDIT S1): ${ticks} Ticks in 5 s`, ticks < 100);

  for (const c of [dash, ctrl, after]) c.ws.close();
} catch (err) {
  failed++;
  console.error(err);
} finally {
  php.kill();
  if (failed) console.log(serverLog.split('\n').slice(-30).join('\n'));
  console.log(`\n${passed} bestanden, ${failed} fehlgeschlagen`);
  process.exit(failed ? 1 : 0);
}
