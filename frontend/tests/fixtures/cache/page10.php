<!-- Fixture page10 – synthetisch, siehe tests/fixtures/generate.mjs -->
<div class="row"><div class="col-lg-6"><h1 class="text-center">Verbleibendes Kontingent: 7</h1><canvas id="JulianChart"></canvas></div>
  <div class="col-lg-6"><table class="table"><thead><tr><th>Datum</th><th>Grund</th><th>Wer</th></tr></thead><tbody>
  <tr><td>02.10.2026</td><td>Kaffeemaschine entkalkt</td><td>J.</td></tr><tr><td>14.10.2026</td><td>Server ohne Ticket neu gestartet</td><td>J.</td></tr><tr><td>20.10.2026</td><td>—</td><td>J.</td></tr></tbody></table></div></div>
  <script>
  ctx = $('#JulianChart').get(0).getContext('2d');
  var options = { scales: { xAxes: [{ ticks: { fontSize: 15 } }], yAxes: [{ ticks: { min: 0, max: 10, fontSize: 15 } }] }, elements: { line: { tension: 0.3 } }, legend: { display: false }, borderCapStyle: 'butt' };
  var bar_chart = new Chart(ctx, { type: 'bar', data: { labels: ["Verbrauchtes Julian-Kontingent"], datasets: [{ data: [3], label: "Julian-Kontingent", backgroundColor: ['rgba(255,99,132,1)'], borderColor: ['rgba(255, 99, 132, 0.2)'], borderWidth: 1 }] }, options: options });
  </script>
