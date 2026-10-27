// CPU-Last und Speicher von websocket/server.php im Leerlauf und unter Reconnect-Last.
//   node scripts/measure-server.mjs <projektwurzel> <label> [port]
// Die Projektwurzel muss websocket/server.php und eine config.php (bzw. DASHBOARD_CONFIG) enthalten.
import { spawn } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import WebSocket from 'ws';

const [root = resolve(import.meta.dirname, '../..'), label = 'current', portArg] = process.argv.slice(2);
const port = Number(portArg ?? 9250);
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const ticks = (pid) => {
  const s = readFileSync(`/proc/${pid}/stat`, 'utf8').split(' ');
  return Number(s[13]) + Number(s[14]);
};
const rssKb = (pid) => Number(/VmRSS:\s+(\d+)/.exec(readFileSync(`/proc/${pid}/status`, 'utf8'))?.[1] ?? 0);

const php = spawn('php', ['websocket/server.php'], {
  cwd: root,
  stdio: 'ignore',
  env: { ...process.env, DASHBOARD_CONFIG: resolve(import.meta.dirname, '../tests/fixtures/config.server.php'), TEST_SERVER_PORT: String(port) },
});
await sleep(1000);
const open = (i) =>
  new Promise((res, rej) => {
    const ws = new WebSocket(`ws://127.0.0.1:${port}/`, { headers: { 'X-Forwarded-For': `10.0.${i % 250}.${(i * 7) % 250}:5${i % 1000}` } });
    ws.on('open', () => res(ws));
    ws.on('error', rej);
  });

const result = { label };
try {
  const clients = [];
  for (let i = 0; i < 5; i++) clients.push(await open(i));
  const t0 = ticks(php.pid);
  await sleep(10_000);
  result.idleCpuPercent = Math.round(((ticks(php.pid) - t0) / 100 / 10) * 1000) / 10;
  result.rssKbStart = rssKb(php.pid);
  // 300 Verbindungsauf-/-abbauten von unterschiedlichen (weitergeleiteten) Adressen
  for (let i = 0; i < 300; i++) {
    const ws = await open(1000 + i);
    ws.close();
    if (i % 25 === 0) await sleep(50);
  }
  await sleep(2000);
  result.rssKbAfter300Reconnects = rssKb(php.pid);
  for (const c of clients) c.close();
} catch (err) {
  result.error = String(err);
} finally {
  php.kill();
}
console.log(JSON.stringify(result));
