<!-- Fixture page8 – synthetisch, siehe tests/fixtures/generate.mjs -->
<div class="row"><div class="col-lg-3"><div class="card"><div class="card-header"><div class="card-title"><div class="title">Server Welle 1</div></div></div><div class="card-body"><canvas id="ChartSWelle1"></canvas></div></div></div><div class="col-lg-3"><div class="card"><div class="card-header"><div class="card-title"><div class="title">Server Welle 2</div></div></div><div class="card-body"><canvas id="ChartSWelle2"></canvas></div></div></div><div class="col-lg-3"><div class="card"><div class="card-header"><div class="card-title"><div class="title">Server Welle 3</div></div></div><div class="card-body"><canvas id="ChartSWelle3"></canvas></div></div></div><div class="col-lg-3"><div class="card"><div class="card-header"><div class="card-title"><div class="title">Ubuntu</div></div></div><div class="card-body"><canvas id="ChartUbuntu"></canvas>
    <ul class="list-group"><li class='list-group-item active'>Servername<span class='pull-right'>Updates (Security / Normal)</span></li>
    <li class='list-group-item small text-lowercase'><span>ubuntu-0</span><span>0 / 0</span></li><li class='list-group-item small text-lowercase'><span>ubuntu-1</span><span>1 / 3</span></li><li class='list-group-item small text-lowercase'><span>ubuntu-2 <i class="fa fa-refresh"></i></span><span>2 / 6</span></li><li class='list-group-item small text-lowercase'><span>ubuntu-3</span><span>3 / 9</span></li><li class='list-group-item small text-lowercase'><span>ubuntu-4</span><span>4 / 12</span></li><li class='list-group-item small text-lowercase'><span>ubuntu-5</span><span>5 / 15</span></li></ul></div></div></div></div>
    <script>
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
    });var ctxSWelle1 = $('#ChartSWelle1').get(0).getContext('2d'); ctxSWelle1.canvas.width = 430; ctxSWelle1.canvas.height = 310;
      var dataSWelle1 = { labels: ["Success","In Progress","Errors","Other","Unknown"], datasets: [{ data: [60,29,41,80,73], backgroundColor: ["#12AF12","#FFA500","#CC3300","#808080","#CACACA"] }] };
      var Backup = new Chart(ctxSWelle1, { type: 'doughnut', data: dataSWelle1, options: { segmentShowStroke: false, responsive: true, maintainAspectRatio: true, animation:{ animateScale:true },
        elements: { center: { text: '87.5%', color: '#12AF12', fontStyle: 'Roboto Condensed', sidePadding: 20 } } } });var ctxSWelle2 = $('#ChartSWelle2').get(0).getContext('2d'); ctxSWelle2.canvas.width = 430; ctxSWelle2.canvas.height = 310;
      var dataSWelle2 = { labels: ["Success","In Progress","Errors","Other","Unknown"], datasets: [{ data: [18,21,71,36,50], backgroundColor: ["#12AF12","#FFA500","#CC3300","#808080","#CACACA"] }] };
      var Backup = new Chart(ctxSWelle2, { type: 'doughnut', data: dataSWelle2, options: { segmentShowStroke: false, responsive: true, maintainAspectRatio: true, animation:{ animateScale:true },
        elements: { center: { text: '87.5%', color: '#12AF12', fontStyle: 'Roboto Condensed', sidePadding: 20 } } } });var ctxSWelle3 = $('#ChartSWelle3').get(0).getContext('2d'); ctxSWelle3.canvas.width = 430; ctxSWelle3.canvas.height = 310;
      var dataSWelle3 = { labels: ["Success","In Progress","Errors","Other","Unknown"], datasets: [{ data: [10,43,68,74,65], backgroundColor: ["#12AF12","#FFA500","#CC3300","#808080","#CACACA"] }] };
      var Backup = new Chart(ctxSWelle3, { type: 'doughnut', data: dataSWelle3, options: { segmentShowStroke: false, responsive: true, maintainAspectRatio: true, animation:{ animateScale:true },
        elements: { center: { text: '87.5%', color: '#12AF12', fontStyle: 'Roboto Condensed', sidePadding: 20 } } } });var ctxUbuntu = $('#ChartUbuntu').get(0).getContext('2d'); ctxUbuntu.canvas.width = 430; ctxUbuntu.canvas.height = 310;
    var dataUbuntu = { labels: ['Aktuell','Updates offen','Reboot nötig','Security Updates','Nicht unterstützt'], datasets: [{ data: [20,5,2,3,1], backgroundColor: ['#12AF12','#E5D800','#FFA500','#E56A5A','#CC0000'] }] };
    var Ubuntu = new Chart(ctxUbuntu, { type: 'doughnut', data: dataUbuntu, options: { segmentShowStroke: false, responsive: true, maintainAspectRatio: true, animation: { animateScale: true },
      elements: { center: { text: '64.52%', color: '#12AF12', fontStyle: 'Roboto Condensed', sidePadding: 20 } } } });
    </script>
