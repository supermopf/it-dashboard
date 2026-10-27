// Mock von websocket/server.php für Browser-Tests und Messungen.
// Protokoll identisch zum Original (siehe docs/AUDIT.md §2): `update` jede Sekunde, `!page N` beim Zyklus,
// Befehle !repeat, !fpage, !var, !reg, !dashboard, sonstige werden als `command` an alle weitergeleitet.
//
// Steuerung per HTTP (Port WS_PORT+1):
//   GET  /state                 → { clients, received, page, ... }
//   POST /send     {message}    → `command` an alle
//   POST /raw      {data}       → Rohdaten an alle (für fehlerhafte Nachrichten)
//   POST /update   {...}        → Felder des Update-Zustands setzen
//   POST /drop                  → alle Verbindungen hart trennen
//   POST /pause    {ms}         → Server für ms nicht erreichbar (neue Verbindungen werden abgewiesen)
//   POST /cycle    {ms}         → automatische Rotation alle ms (0 = aus)
//   POST /silence  {on}         → keine Updates mehr senden (halb-offene Verbindung simulieren)
//   POST /reset
import { WebSocketServer } from 'ws';
import http from 'node:http';

const WS_PORT = Number(process.env.WS_PORT ?? 9100);
const CTRL_PORT = WS_PORT + 1;
const CYCLE = 30;

const initial = () => ({
  Page: 1,
  FUN: false,
  Wheel: false,
  Radio: false,
  Radiostation: '',
  Radiovolume: 0.01,
  YTvolume: 0.01,
  Cat: false,
  Snow: false,
  BusActive: false,
  Cycle: true,
  SongTitle: '',
  RadioStationIcon: '',
  PageStatus: { 1: 'warning', 2: 'warning', 3: 'warning', 4: 'warning', 5: 'warning', 6: 'warning', 7: 'warning', 8: 'warning', 9: 'warning', 10: 'warning' },
});

let state = initial();
let cycleStarted = Date.now();
let cycleMs = 0;
let cycleTimer = null;
let paused = false;
let silent = false;
let received = [];
const clients = new Set();

const wss = new WebSocketServer({ port: WS_PORT });
const boolStr = (b) => (b ? 'true' : 'false');

function updateMessage() {
  const elapsed = Math.floor((Date.now() - cycleStarted) / 1000);
  return JSON.stringify({
    type: 'update',
    Page: state.Page,
    FUN: boolStr(state.FUN),
    Wheel: boolStr(state.Wheel),
    Radio: boolStr(state.Radio),
    Radiostation: state.Radiostation,
    Radiovolume: state.Radiovolume,
    YTvolume: state.YTvolume,
    Cat: boolStr(state.Cat),
    Snow: boolStr(state.Snow),
    BusActive: boolStr(state.BusActive),
    CD: Math.max(0, CYCLE - 1 - (elapsed % CYCLE)),
    Cycle: boolStr(state.Cycle),
    SongTitle: state.SongTitle,
    RadioStationIcon: state.RadioStationIcon,
    PageStatus: state.PageStatus,
  });
}

function broadcast(data) {
  for (const c of clients) if (c.readyState === 1) c.send(data);
}
const command = (message) => broadcast(JSON.stringify({ type: 'command', message }));

setInterval(() => {
  if (!silent) broadcast(updateMessage());
}, 1000);

function nextPage() {
  let p = state.Page >= 10 ? 1 : state.Page + 1;
  for (let i = 0; i < 10 && state.PageStatus[p] === 'ok'; i++) p = p >= 10 ? 1 : p + 1;
  state.Page = p;
  cycleStarted = Date.now();
  command(`!page ${p}`);
}

wss.on('connection', (ws, req) => {
  if (paused) {
    ws.terminate();
    return;
  }
  clients.add(ws);
  ws.on('close', () => clients.delete(ws));
  ws.on('message', (buf) => {
    let msg;
    try {
      msg = JSON.parse(buf.toString()).message;
    } catch {
      return;
    }
    if (typeof msg !== 'string') return;
    received.push(msg);
    if (received.length > 1000) received = received.slice(-500);
    if (msg === '!repeat') ws.send(JSON.stringify({ type: 'command', message: `!page ${state.Page}` }));
    else if (msg.startsWith('!fpage ')) {
      state.Page = Number(msg.slice(7)) || state.Page;
      cycleStarted = Date.now();
      command(`!page ${state.Page}`);
    } else if (msg.startsWith('!var ')) {
      const [, name, value] = msg.split(' ');
      if (name in state) state[name] = value === 'True' ? true : value === 'False' ? false : decodeURIComponent(value ?? '');
    } else if (msg.startsWith('!reg') || msg.startsWith('!dashboard')) {
      ws.dashboardName = msg;
    } else command(msg);
  });
  void req;
});

function body(req) {
  return new Promise((resolve) => {
    let s = '';
    req.on('data', (c) => (s += c));
    req.on('end', () => {
      try {
        resolve(s ? JSON.parse(s) : {});
      } catch {
        resolve({});
      }
    });
  });
}

http
  .createServer(async (req, res) => {
    const b = req.method === 'POST' ? await body(req) : {};
    const ok = (data = { ok: true }) => {
      res.writeHead(200, { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' });
      res.end(JSON.stringify(data));
    };
    switch (req.url) {
      case '/state':
        return ok({ clients: clients.size, received, state, paused, silent });
      case '/send':
        command(String(b.message ?? ''));
        return ok();
      case '/raw':
        broadcast(String(b.data ?? ''));
        return ok();
      case '/update':
        Object.assign(state, b);
        return ok();
      case '/drop':
        for (const c of clients) c.terminate();
        return ok();
      case '/pause':
        paused = true;
        for (const c of clients) c.terminate();
        setTimeout(() => (paused = false), Number(b.ms ?? 5000));
        return ok();
      case '/silence':
        silent = !!b.on;
        return ok();
      case '/cycle':
        cycleMs = Number(b.ms ?? 0);
        if (cycleTimer) clearInterval(cycleTimer);
        cycleTimer = cycleMs > 0 ? setInterval(nextPage, cycleMs) : null;
        return ok();
      case '/reset':
        state = initial();
        received = [];
        paused = false;
        silent = false;
        if (cycleTimer) clearInterval(cycleTimer);
        cycleTimer = null;
        return ok();
      default:
        res.writeHead(404);
        res.end();
    }
  })
  .listen(CTRL_PORT, '127.0.0.1');

console.log(`mock ws on ${WS_PORT}, control on ${CTRL_PORT}`);
