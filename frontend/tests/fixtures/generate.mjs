// Erzeugt deterministische Test-Fixtures für monitor/cache/pageN.php.
// Struktur, Klassen und Inline-Skripte entsprechen den Templates in monitor/source/pageN.php
// (inkl. der problematischen Muster: globale const-Deklarationen, wiederholtes pluginService.register,
// responsive Charts). Daten sind synthetisch.
//
//   node tests/fixtures/generate.mjs [zielverzeichnis]
import { mkdirSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

const out = resolve(process.argv[2] ?? new URL('./cache/', import.meta.url).pathname);
mkdirSync(out, { recursive: true });

let seed = 42;
const rnd = () => ((seed = (seed * 1103515245 + 12345) % 2147483648) / 2147483648);
const base = Date.UTC(2026, 9, 27, 8, 0, 0);
const series = (n, lo, hi) => Array.from({ length: n }, () => Math.round(lo + rnd() * (hi - lo)));
const times = (n, stepMin) => Array.from({ length: n }, (_, i) => new Date(base + i * stepMin * 60000).toISOString());

const timeOptions = (lineTension) => `
    var minDate = new Date(${base});
    var options = {
        scales: {
            xAxes: [{ type: 'time', time: { unit: 'minute', min: minDate, max: ${base + 120 * 60000}, displayFormats: { minute: 'HH:mm' }, unitStepSize: 30 },
                      ticks: { fontSize: 15, minRotation: 0, maxRotation: 0, autoSkip: true } }],
            yAxes: [{ ticks: { max: 100, min: 0, fontSize: 15 } }]
        },
        borderCapStyle: 'butt', lineTension: ${lineTension}, spanGaps: true
    };`;

const centerPlugin = `
    Chart.pluginService.register({
        beforeDraw: function (chart) {
            if (chart.config.options.elements.center) {
                var ctx = chart.chart.ctx;
                var centerConfig = chart.config.options.elements.center;
                var fontStyle = centerConfig.fontStyle || 'Arial';
                var txt = centerConfig.text;
                var color = centerConfig.color || '#000';
                var sidePadding = centerConfig.sidePadding || 20;
                var sidePaddingCalculated = (sidePadding / 100) * (chart.innerRadius * 2);
                ctx.font = "30px " + fontStyle;
                var stringWidth = ctx.measureText(txt).width;
                var elementWidth = (chart.innerRadius * 2) - sidePaddingCalculated;
                var widthRatio = elementWidth / stringWidth;
                var newFontSize = Math.floor(30 * widthRatio);
                var elementHeight = (chart.innerRadius * 2);
                var fontSizeToUse = Math.min(newFontSize, elementHeight);
                ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
                var centerX = ((chart.chartArea.left + chart.chartArea.right) / 2);
                var centerY = ((chart.chartArea.top + chart.chartArea.bottom) / 2);
                ctx.font = fontSizeToUse + "px " + fontStyle; ctx.fillStyle = color;
                ctx.fillText(txt, centerX, centerY);
            }
        }
    });`;

const pages = {};

// Seite 1 – Temperatur (4 Charts, Wetterkarte, fehlendes Radarbild)
{
  const locs = ['CECIL', 'Gerberstraße OG', 'Street One', 'Gerberstraße EG'];
  let h = `<div class="row"><div class="col-lg-6"><div class="row"><div class="card yellow summary-inline"><div class="card-body">
  <img height="128px" src="./img/weather/04d.png"/><div class="content"><div class="title">Isernhagen: 11,4 °C</div><div class="title">Bedeckt</div></div><div class="clear-both"></div></div></div></div>
  <div class="row top-buffer"><div class="col-sm-12">`;
  locs.forEach((l, i) => {
    if (i % 2 === 0) h += '<div class="row">';
    h += `<div class="col-sm-6 col-xs-12"><div class="card state-${i === 2 ? 1 : 0}"><div class="card-header"><div class="card-title"><div class="title">
      <div class="col-sm-8">${l}</div><div class="col-sm-2 blue">4${i}%</div><div class="col-sm-2 red">2${i},5°C</div></div></div></div>
      <div class="card-body no-padding"><canvas id="location${i}" class="chart" width="478" height="260" style="width: 478px; height: 260px;"></canvas></div></div></div>`;
    if (i % 2 === 1) h += '</div>';
  });
  h += `</div></div></div><div class="col-lg-6"><div class="card"><div class="card-header"><div class="card-title"><div class="title">Wetter</div></div></div>
  <div class="card-body no-padding"><img class="img-responsive" src="../radar-missing.png?nocache=1" /></div><div class="card-footer"><br></div></div></div></div>
  <script>${timeOptions(0.1)}</script>`;
  locs.forEach((l, i) => {
    const n = 120;
    h += `<script type="text/javascript">
        ctx = $('#location${i}').get(0).getContext('2d');
        var data = { labels: ${JSON.stringify(times(n, 1))}, datasets: [
          { pointRadius: 1, label: "Temperatur in °C", backgroundColor: ['rgba(199, 13, 59, 0.2)'], borderColor: ['rgba(199, 13, 58, 1)'], borderWidth: 1, data: ${JSON.stringify(series(n, 19, 27))} },
          { pointRadius: 1, label: "Luftfeuchtigkeit in %", backgroundColor: ['rgba(69, 150, 155, 0.2)'], borderColor: ['rgba(69, 150, 155, 1)'], borderWidth: 1, data: ${JSON.stringify(series(n, 30, 55))} } ] };
        var chartInstance${i} = new Chart(ctx, {type: 'line', data: data, options: options});
    </script>`;
  });
  pages[1] = h;
}

// Seite 2 – helpLine (KPIs, Chart, Tabelle mit sehr langen Texten)
{
  const rows = Array.from({ length: 8 }, (_, i) => {
    const subject = i === 0
      ? 'Sehr langer Betreff: '.concat('Drucker im dritten Obergeschoss druckt nur noch leere Seiten und gibt seltsame Geräusche von sich '.repeat(3))
      : `Störung VPN-Zugang Standort ${i}`;
    return `<tr class='helplinetable'><td class='h4 text'><span>${i % 3 ? '🔥' : '💥'}</span></td><td class='h4 text'><span>T2026${100000 + i}</span></td>
      <td class='h4 text'><span>Anforderer Mit Einem Außergewöhnlich Langen Doppelnamen-${i}</span></td><td class='h4 text'><span>${subject}</span></td></tr>`;
  }).join('');
  pages[2] = `<div class="row"><div class="col-lg-6"><div class="card dark summary-inline"><div class="card-body"><div class="content">
    <div class="title">Heute eröffnete Tickets</div><div class="title">1234567</div></div><div class="clear-both"></div></div></div></div>
    <div class="col-lg-6"><div class="card dark summary-inline"><div class="card-body"><div class="content"><div class="title">Heute geschlossene Tickets</div><div class="title">98</div></div><div class="clear-both"></div></div></div></div></div>
    <div class="row"><div class="col-lg-6"><div class="card"><div class="card-header"><div class="card-title"><div class="title">Infotisch - offene Tickets nach Zuordnung und Zeit</div></div></div>
    <div class="card-body no-padding"><canvas id="TicketChart" class="chart" width="478" height="260" style="width: 478px; height: 260px;"></canvas></div></div></div>
    <div class="col-lg-6"><div class="card summary-inline"><div class="card-header"><div class="card-title"><div class="title">Tickets mit Priorität hoch🔥 oder top💥</div></div></div>
    <div class="card-body"><table class="table table-responsive"><thead><tr><th style="width: 0%;" class="h4"></th><th style="width: 10%;" class="h4">Ticketnummer</th><th style="width: 10%;" class="h4">Anforderer</th><th class="h4">Betreff</th></tr></thead><tbody>${rows}</tbody></table></div></div></div></div>
    <script>
    var options = { scales: { xAxes: [{ ticks: { fontSize: 15 } }], yAxes: [{ ticks: { min: 0, fontSize: 15 } }] }, elements: { line: { tension: 0.1 } }, borderCapStyle: 'butt' };
    var data = { labels: ${JSON.stringify(Array.from({ length: 12 }, (_, i) => `${i}:00`))}, datasets: [
      { label: 'ITSM Service Desk', data: ${JSON.stringify(series(12, 0, 30))}, spanGaps: true, fill: false, borderColor: ['rgba(115, 255, 0, 1)'], borderWidth: 1 },
      { label: 'Workplace Service 2nd Level', data: ${JSON.stringify(series(12, 0, 30))}, spanGaps: true, fill: false, borderColor: ['rgba(228, 224, 18, 1)'], borderWidth: 1 },
      { label: 'Infrastructure 2nd Level', data: ${JSON.stringify(series(12, 0, 30))}, spanGaps: true, fill: false, borderColor: ['rgba(247, 142, 5, 1)'], borderWidth: 1 },
      { label: 'Infrastructure 3rd Level', data: ${JSON.stringify(series(12, 0, 30))}, spanGaps: true, fill: false, borderColor: ['rgba(245, 14, 64, 1)'], borderWidth: 1 } ] };
    ctx = $('#TicketChart').get(0).getContext('2d');
    var TicketChart = new Chart(ctx, { type: 'line', data: data, options: options, lineAtIndex: [10] });
    </script>`;
}

// Seiten 3/4 – Performance/VMware: 12 Charts mit globalen const-Deklarationen (AUDIT P4)
function perfPage(prefix, withGuests) {
  const names = Array.from({ length: 12 }, (_, i) => `${prefix}${String(i + 1).padStart(2, '0')}${i === 5 ? '-mit-einem-sehr-langen-hostnamen.example.test' : ''}`);
  let h = '<div class="row"><div class="col-lg-12">';
  names.forEach((name, idx) => {
    const i = idx + 1;
    const cpu = idx === 0 ? 95 : idx === 1 ? 85 : 20 + idx * 3;
    h += `<div class="col-sm-3 col-xs-12"><div class="card ${cpu >= 90 ? 'state-2' : cpu >= 80 ? 'state-1' : ''}"><div class="card-header"><div class="card-title"><div class="title">
      <div class="col-sm-${withGuests ? 6 : 8}" id="${name}">${name}</div>${withGuests ? `<div class="col-sm-2" style="color: rgb(32, 120, 200);">${10 + idx}</div>` : ''}
      <div class="col-sm-2" style="color: rgb(204,37,41);">${cpu}%</div><div class="col-sm-2" style="color: rgb(62, 150, 81);">${40 + idx}%</div></div></div></div>
      <div class="card-body no-padding"><canvas id="perf-${name}" class="chart"></canvas></div></div></div>`;
    if (i % 4 === 0) {
      h += '</div></div>';
      if (i < names.length) h += '<div class="row"><div class="col-lg-12">';
    }
  });
  h += `<script>${timeOptions(0)}</script>`;
  names.forEach((name, idx) => {
    const i = idx + 1;
    const n = 40;
    h += `<script type="text/javascript">
        const ctx${i} = $(document.getElementById(${JSON.stringify(`perf-${name}`)})).get(0).getContext("2d");
        const data${i} = { labels: ${JSON.stringify(times(n, 3))}, datasets: [
          { label: "CPU in %", backgroundColor: "rgba(211, 94, 96,0.6)", borderColor: "rgba(204, 37, 41,1)", cubicInterpolationMode: "monotone", pointRadius: 0, borderWidth: 1, data: ${JSON.stringify(series(n, 5, 95))} },
          { label: "RAM in %", backgroundColor: "rgba(132, 186, 91,0.4)", borderColor: "rgba(62, 150, 81,1)", cubicInterpolationMode: "monotone", pointRadius: 0, borderWidth: 1, data: ${JSON.stringify(series(n, 30, 70))} }
          ${withGuests ? `,{ label: "VMs", backgroundColor: "rgba(32,120,200,0.3)", borderColor: "rgba(32,120,200,1)", cubicInterpolationMode: "monotone", pointRadius: 0, borderWidth: 1, data: ${JSON.stringify(series(n, 8, 30))} }` : ''} ] };
        const chartInstance${i} = new Chart(ctx${i}, { type: 'line', data: data${i}, options: options });
    </script>`;
  });
  return h + '</div></div>';
}
pages[3] = perfPage('srv-app', false);
pages[4] = perfPage('esx-node', true);

// Seite 5 – SCOM mit drei Slides
{
  let h = `<div class="row"><div class="col-lg-12">Seite <span id="CurrentPage"></span> von <span id="PageCount"></span></div></div>
  <div class="row"><div class="col-lg-12"><div id="wrapper"><div id="slider1" class="content" style="display: none;">`;
  let objects = 0;
  let slider = 1;
  for (let s = 0; s < 9; s++) {
    if (s > 0) {
      h += '</div>';
      if (objects >= 15) {
        objects = 0;
        h += '</div>';
        slider++;
        h += `<div class="content" id="slider${slider}" style="display: none;">`;
      }
    }
    h += `<a href="#" class="list-group-item active">scom-host-${s}.example.test</a><div class="list-group">`;
    objects++;
    for (let a = 0; a < 4; a++) {
      const warn = (s + a) % 2 === 0;
      h += `<a href="#" class="list-group-item"><i class="fa ${warn ? 'fa-exclamation-triangle' : 'fa-times'}" aria-hidden="true"></i> Objekt ${a} Monitor ${warn ? 'Warning' : 'Critical'} (Offline)<span class="pull-right">${objects}</span></a>`;
      objects++;
    }
  }
  h += `</div></div></div></div></div><script>var i = 0; var PageCount = ${slider}; $('#PageCount').html(PageCount); $('#CurrentPage').html(i); CyclePages();</script>`;
  pages[5] = h;
}

// Seite 6 – Backup (Tabelle, Doughnut, Plugin-Registrierung bei jedem Aufruf)
{
  const rows = Array.from({ length: 6 }, (_, i) =>
    `<tr><td class='h4'>VMware VM</td><td class='h4'>vm-backup-${i}</td><td class='h4'>Rechenzentrum ${i} / Cluster A / Ressourcenpool mit langem Namen</td><td class='h4'>26.10.2026 22:0${i}</td><td class='h4'>27.10.2026 06:00</td><td class='h4'>Gold</td></tr>`).join('');
  pages[6] = `<div class="row"><div class="col-sm-8 col-xs-12"><table class="table table-responsive table-striped"><thead><tr>
    <th width="10%" class="h4">Typ</th><th width="20%" class="h4">Name</th><th width="30%" class="h4">Location</th><th width="15%" class="h4">Latest Snapshot</th><th width="15%" class="h4">Last Pull</th><th width="10%" class="h4">SLA</th></tr></thead><tbody>${rows}</tbody></table></div>
    <div class="col-sm-4 col-xs-12"><div><canvas id="Backup" class="chart"></canvas></div></div></div>
    <script>${centerPlugin}
    var ctx = $('#Backup').get(0).getContext('2d');
    var data = { labels: ["Successful","Partially Successful","Failed"], datasets: [{ data: [812,12,6], backgroundColor: ["#12AF12","#FFD700","#CC3300"] }] };
    var Backup = new Chart(ctx, { type: 'doughnut', data: data, options: { responsive: true, maintainAspectRatio: true, animation: { animateScale: true },
      elements: { center: { text: '97.83%', color: '#12AF12', fontStyle: 'Roboto Condensed', sidePadding: 20 } } } });
    </script>`;
}

// Seite 7 – M365 (Grid mit Inline-Styles, transition: all)
{
  const services = ['Exchange Online', 'SharePoint Online', 'Microsoft Teams', 'OneDrive for Business', 'Microsoft Entra', 'Intune', 'Power BI', 'Planner', 'Forms', 'Viva Engage', 'Dynamics 365', 'Skype for Business'];
  let h = '<div class="row">';
  services.forEach((s, i) => {
    h += `<div class="col-lg-2 col-md-2 col-sm-4"><div class="panel m365-service-panel ${i === 2 ? 'panel-warning' : 'panel-success'}"><div class="panel-body text-center">
      <div class="service-name">${s}</div><div class="small">${i === 2 ? '<i class="fa fa-exclamation-circle"></i> Eingeschränkt' : '<i class="fa fa-check"></i> Betriebsbereit'}</div></div></div></div>`;
  });
  h += `</div><div class="issues-section-header"><i class="fa fa-info-circle"></i> Aktuelle Probleme</div><div class="issues-compact-grid">
    <div class="issue-compact-card issue-warning"><div class="issue-compact-header"><span class="issue-service-name">Microsoft Teams</span><span class="issue-classification">Advisory</span></div>
    <div class="issue-compact-title">Einige Benutzer können keine Besprechungsaufzeichnungen abspielen</div><div class="issue-compact-meta"><i class="fa fa-clock-o"></i> seit 07:12</div></div></div>
    <style>.m365-service-panel { margin-bottom: 8px; min-height: 85px; transition: all 0.2s ease; display: flex; flex-direction: column; }
    .issues-compact-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 10px; }
    .issue-compact-card { padding: 10px; border: 1px solid rgba(255,255,255,0.1); border-radius: 4px; transition: all 0.2s ease; }</style>`;
  pages[7] = h;
}

// Seite 8 – Updates (3 Doughnuts + Ubuntu, erneute Plugin-Registrierung)
{
  const waves = ['SWelle1', 'SWelle2', 'SWelle3'];
  let h = '<div class="row">';
  waves.forEach((w, i) => {
    h += `<div class="col-lg-3"><div class="card"><div class="card-header"><div class="card-title"><div class="title">Server Welle ${i + 1}</div></div></div><div class="card-body"><canvas id="Chart${w}"></canvas></div></div></div>`;
  });
  h += `<div class="col-lg-3"><div class="card"><div class="card-header"><div class="card-title"><div class="title">Ubuntu</div></div></div><div class="card-body"><canvas id="ChartUbuntu"></canvas>
    <ul class="list-group"><li class='list-group-item active'>Servername<span class='pull-right'>Updates (Security / Normal)</span></li>
    ${Array.from({ length: 6 }, (_, i) => `<li class='list-group-item small text-lowercase'><span>ubuntu-${i}${i === 2 ? ' <i class="fa fa-refresh"></i>' : ''}</span><span>${i} / ${i * 3}</span></li>`).join('')}</ul></div></div></div></div>
    <script>${centerPlugin}`;
  waves.forEach((w) => {
    h += `var ctx${w} = $('#Chart${w}').get(0).getContext('2d'); ctx${w}.canvas.width = 430; ctx${w}.canvas.height = 310;
      var data${w} = { labels: ["Success","In Progress","Errors","Other","Unknown"], datasets: [{ data: [${series(5, 1, 80).join(',')}], backgroundColor: ["#12AF12","#FFA500","#CC3300","#808080","#CACACA"] }] };
      var Backup = new Chart(ctx${w}, { type: 'doughnut', data: data${w}, options: { segmentShowStroke: false, responsive: true, maintainAspectRatio: true, animation:{ animateScale:true },
        elements: { center: { text: '87.5%', color: '#12AF12', fontStyle: 'Roboto Condensed', sidePadding: 20 } } } });`;
  });
  h += `var ctxUbuntu = $('#ChartUbuntu').get(0).getContext('2d'); ctxUbuntu.canvas.width = 430; ctxUbuntu.canvas.height = 310;
    var dataUbuntu = { labels: ['Aktuell','Updates offen','Reboot nötig','Security Updates','Nicht unterstützt'], datasets: [{ data: [20,5,2,3,1], backgroundColor: ['#12AF12','#E5D800','#FFA500','#E56A5A','#CC0000'] }] };
    var Ubuntu = new Chart(ctxUbuntu, { type: 'doughnut', data: dataUbuntu, options: { segmentShowStroke: false, responsive: true, maintainAspectRatio: true, animation: { animateScale: true },
      elements: { center: { text: '64.52%', color: '#12AF12', fontStyle: 'Roboto Condensed', sidePadding: 20 } } } });
    </script>`;
  pages[8] = h;
}

// Seite 9 – DERDACK (Karten, „glow“ für neue Meldungen)
{
  let h = '<div class="row"><div class="col-lg-12">';
  for (let i = 0; i < 8; i++) {
    h += `<div class="col-sm-3 col-xs-12"><div class="${i < 2 ? 'glow' : ''}"><div class="card"><div class="card-header bg-danger min-height-header"><div class="card-title"><div class="title text-center">
      <div class="col-sm-12">Alarm ${i}: Dienst ${i % 2 ? 'nicht erreichbar' : 'antwortet langsam'}</div></div></div></div><div class="card-body"><div class="card-text"><div class='min-height-text'>
      <p>${i === 3 ? 'X'.repeat(600) : `Host srv-${i}.example.test meldet einen Fehler im Überwachungsdienst.`}</p></div><hr class="half-rule"/><div><div class='col-sm'><span class='pull-left'>27.10.2026 0${i}:15</span></div>
      <div class='col-sm'><span class='pull-right'>Quittiert von: Bereitschaft ${i}</span></div></div></div></div></div></div></div>`;
    if ((i + 1) % 4 === 0 && i !== 0) {
      h += '<!-- DIV --></div><!-- ROW END --></div>';
      if (i !== 7) h += '<div class="row"><div class="col-lg-12">';
    }
  }
  pages[9] = h;
}

// Seite 10 – Julian-O-Meter (Balkendiagramm)
pages[10] = `<div class="row"><div class="col-lg-6"><h1 class="text-center">Verbleibendes Kontingent: 7</h1><canvas id="JulianChart"></canvas></div>
  <div class="col-lg-6"><table class="table"><thead><tr><th>Datum</th><th>Grund</th><th>Wer</th></tr></thead><tbody>
  <tr><td>02.10.2026</td><td>Kaffeemaschine entkalkt</td><td>J.</td></tr><tr><td>14.10.2026</td><td>Server ohne Ticket neu gestartet</td><td>J.</td></tr><tr><td>20.10.2026</td><td>—</td><td>J.</td></tr></tbody></table></div></div>
  <script>
  ctx = $('#JulianChart').get(0).getContext('2d');
  var options = { scales: { xAxes: [{ ticks: { fontSize: 15 } }], yAxes: [{ ticks: { min: 0, max: 10, fontSize: 15 } }] }, elements: { line: { tension: 0.3 } }, legend: { display: false }, borderCapStyle: 'butt' };
  var bar_chart = new Chart(ctx, { type: 'bar', data: { labels: ["Verbrauchtes Julian-Kontingent"], datasets: [{ data: [3], label: "Julian-Kontingent", backgroundColor: ['rgba(255,99,132,1)'], borderColor: ['rgba(255, 99, 132, 0.2)'], borderWidth: 1 }] }, options: options });
  </script>`;

// FUN – zwei Medien (eines davon Video, eines fehlend)
pages.FUN = `<div class="row"><div class="col-lg-12"><div class="page-header text-center"><h1>+++Testschlagzeile: Admin findet Ursache, Kollegen ungläubig+++</h1></div></div></div>
  <div class="row"><div class="col-lg-6 col-sm-12"><div class="card equal"><div class="card-header"><div class="card-title"><div class="title"><h2><strong>Wenn der Build grün ist</strong></h2></div></div></div>
  <div class="panel-body"><video autoplay loop class="col-xs-12 img-responsive" src="./fixtures-media/missing.webm"></video></div></div></div>
  <div class="col-lg-6 col-sm-12"><div class="card equal"><div class="card-header"><div class="card-title"><div class="title"><h2><strong>Freitagsdeployment</strong></h2></div></div></div>
  <div class="panel-body"><img src="./fixtures-media/missing.gif" class="col-xs-12 img-responsive"></div></div></div></div>`;

// Leere Seite und Fehlerseite (für Fallback-Tests)
pages.EMPTY = '   ';
pages.SQLERR = '<pre>Array\n(\n    [0] => Array\n        (\n            [0] => 08001\n            [SQLSTATE] => 08001\n        )\n)\n';

for (const [k, v] of Object.entries(pages)) {
  writeFileSync(resolve(out, `page${k}.php`), v.trim() === '' ? v : `<!-- Fixture page${k} – synthetisch, siehe tests/fixtures/generate.mjs -->\n${v}\n`);
}
console.log(`Fixtures geschrieben: ${Object.keys(pages).length} Seiten nach ${out}`);
