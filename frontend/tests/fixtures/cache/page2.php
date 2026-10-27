<!-- Fixture page2 – synthetisch, siehe tests/fixtures/generate.mjs -->
<div class="row"><div class="col-lg-6"><div class="card dark summary-inline"><div class="card-body"><div class="content">
    <div class="title">Heute eröffnete Tickets</div><div class="title">1234567</div></div><div class="clear-both"></div></div></div></div>
    <div class="col-lg-6"><div class="card dark summary-inline"><div class="card-body"><div class="content"><div class="title">Heute geschlossene Tickets</div><div class="title">98</div></div><div class="clear-both"></div></div></div></div></div>
    <div class="row"><div class="col-lg-6"><div class="card"><div class="card-header"><div class="card-title"><div class="title">Infotisch - offene Tickets nach Zuordnung und Zeit</div></div></div>
    <div class="card-body no-padding"><canvas id="TicketChart" class="chart" width="478" height="260" style="width: 478px; height: 260px;"></canvas></div></div></div>
    <div class="col-lg-6"><div class="card summary-inline"><div class="card-header"><div class="card-title"><div class="title">Tickets mit Priorität hoch🔥 oder top💥</div></div></div>
    <div class="card-body"><table class="table table-responsive"><thead><tr><th style="width: 0%;" class="h4"></th><th style="width: 10%;" class="h4">Ticketnummer</th><th style="width: 10%;" class="h4">Anforderer</th><th class="h4">Betreff</th></tr></thead><tbody><tr class='helplinetable'><td class='h4 text'><span>💥</span></td><td class='h4 text'><span>T2026100000</span></td>
      <td class='h4 text'><span>Anforderer Mit Einem Außergewöhnlich Langen Doppelnamen-0</span></td><td class='h4 text'><span>Sehr langer Betreff: Drucker im dritten Obergeschoss druckt nur noch leere Seiten und gibt seltsame Geräusche von sich Drucker im dritten Obergeschoss druckt nur noch leere Seiten und gibt seltsame Geräusche von sich Drucker im dritten Obergeschoss druckt nur noch leere Seiten und gibt seltsame Geräusche von sich </span></td></tr><tr class='helplinetable'><td class='h4 text'><span>🔥</span></td><td class='h4 text'><span>T2026100001</span></td>
      <td class='h4 text'><span>Anforderer Mit Einem Außergewöhnlich Langen Doppelnamen-1</span></td><td class='h4 text'><span>Störung VPN-Zugang Standort 1</span></td></tr><tr class='helplinetable'><td class='h4 text'><span>🔥</span></td><td class='h4 text'><span>T2026100002</span></td>
      <td class='h4 text'><span>Anforderer Mit Einem Außergewöhnlich Langen Doppelnamen-2</span></td><td class='h4 text'><span>Störung VPN-Zugang Standort 2</span></td></tr><tr class='helplinetable'><td class='h4 text'><span>💥</span></td><td class='h4 text'><span>T2026100003</span></td>
      <td class='h4 text'><span>Anforderer Mit Einem Außergewöhnlich Langen Doppelnamen-3</span></td><td class='h4 text'><span>Störung VPN-Zugang Standort 3</span></td></tr><tr class='helplinetable'><td class='h4 text'><span>🔥</span></td><td class='h4 text'><span>T2026100004</span></td>
      <td class='h4 text'><span>Anforderer Mit Einem Außergewöhnlich Langen Doppelnamen-4</span></td><td class='h4 text'><span>Störung VPN-Zugang Standort 4</span></td></tr><tr class='helplinetable'><td class='h4 text'><span>🔥</span></td><td class='h4 text'><span>T2026100005</span></td>
      <td class='h4 text'><span>Anforderer Mit Einem Außergewöhnlich Langen Doppelnamen-5</span></td><td class='h4 text'><span>Störung VPN-Zugang Standort 5</span></td></tr><tr class='helplinetable'><td class='h4 text'><span>💥</span></td><td class='h4 text'><span>T2026100006</span></td>
      <td class='h4 text'><span>Anforderer Mit Einem Außergewöhnlich Langen Doppelnamen-6</span></td><td class='h4 text'><span>Störung VPN-Zugang Standort 6</span></td></tr><tr class='helplinetable'><td class='h4 text'><span>🔥</span></td><td class='h4 text'><span>T2026100007</span></td>
      <td class='h4 text'><span>Anforderer Mit Einem Außergewöhnlich Langen Doppelnamen-7</span></td><td class='h4 text'><span>Störung VPN-Zugang Standort 7</span></td></tr></tbody></table></div></div></div></div>
    <script>
    var options = { scales: { xAxes: [{ ticks: { fontSize: 15 } }], yAxes: [{ ticks: { min: 0, fontSize: 15 } }] }, elements: { line: { tension: 0.1 } }, borderCapStyle: 'butt' };
    var data = { labels: ["0:00","1:00","2:00","3:00","4:00","5:00","6:00","7:00","8:00","9:00","10:00","11:00"], datasets: [
      { label: 'ITSM Service Desk', data: [2,29,23,1,4,17,2,2,13,2,8,17], spanGaps: true, fill: false, borderColor: ['rgba(115, 255, 0, 1)'], borderWidth: 1 },
      { label: 'Workplace Service 2nd Level', data: [24,13,18,8,18,9,13,15,16,8,7,15], spanGaps: true, fill: false, borderColor: ['rgba(228, 224, 18, 1)'], borderWidth: 1 },
      { label: 'Infrastructure 2nd Level', data: [30,18,8,17,18,5,1,22,6,9,16,2], spanGaps: true, fill: false, borderColor: ['rgba(247, 142, 5, 1)'], borderWidth: 1 },
      { label: 'Infrastructure 3rd Level', data: [28,7,11,18,29,22,21,26,0,4,3,19], spanGaps: true, fill: false, borderColor: ['rgba(245, 14, 64, 1)'], borderWidth: 1 } ] };
    ctx = $('#TicketChart').get(0).getContext('2d');
    var TicketChart = new Chart(ctx, { type: 'line', data: data, options: options, lineAtIndex: [10] });
    </script>
