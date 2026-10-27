<!-- Fixture page6 – synthetisch, siehe tests/fixtures/generate.mjs -->
<div class="row"><div class="col-sm-8 col-xs-12"><table class="table table-responsive table-striped"><thead><tr>
    <th width="10%" class="h4">Typ</th><th width="20%" class="h4">Name</th><th width="30%" class="h4">Location</th><th width="15%" class="h4">Latest Snapshot</th><th width="15%" class="h4">Last Pull</th><th width="10%" class="h4">SLA</th></tr></thead><tbody><tr><td class='h4'>VMware VM</td><td class='h4'>vm-backup-0</td><td class='h4'>Rechenzentrum 0 / Cluster A / Ressourcenpool mit langem Namen</td><td class='h4'>26.10.2026 22:00</td><td class='h4'>27.10.2026 06:00</td><td class='h4'>Gold</td></tr><tr><td class='h4'>VMware VM</td><td class='h4'>vm-backup-1</td><td class='h4'>Rechenzentrum 1 / Cluster A / Ressourcenpool mit langem Namen</td><td class='h4'>26.10.2026 22:01</td><td class='h4'>27.10.2026 06:00</td><td class='h4'>Gold</td></tr><tr><td class='h4'>VMware VM</td><td class='h4'>vm-backup-2</td><td class='h4'>Rechenzentrum 2 / Cluster A / Ressourcenpool mit langem Namen</td><td class='h4'>26.10.2026 22:02</td><td class='h4'>27.10.2026 06:00</td><td class='h4'>Gold</td></tr><tr><td class='h4'>VMware VM</td><td class='h4'>vm-backup-3</td><td class='h4'>Rechenzentrum 3 / Cluster A / Ressourcenpool mit langem Namen</td><td class='h4'>26.10.2026 22:03</td><td class='h4'>27.10.2026 06:00</td><td class='h4'>Gold</td></tr><tr><td class='h4'>VMware VM</td><td class='h4'>vm-backup-4</td><td class='h4'>Rechenzentrum 4 / Cluster A / Ressourcenpool mit langem Namen</td><td class='h4'>26.10.2026 22:04</td><td class='h4'>27.10.2026 06:00</td><td class='h4'>Gold</td></tr><tr><td class='h4'>VMware VM</td><td class='h4'>vm-backup-5</td><td class='h4'>Rechenzentrum 5 / Cluster A / Ressourcenpool mit langem Namen</td><td class='h4'>26.10.2026 22:05</td><td class='h4'>27.10.2026 06:00</td><td class='h4'>Gold</td></tr></tbody></table></div>
    <div class="col-sm-4 col-xs-12"><div><canvas id="Backup" class="chart"></canvas></div></div></div>
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
    });
    var ctx = $('#Backup').get(0).getContext('2d');
    var data = { labels: ["Successful","Partially Successful","Failed"], datasets: [{ data: [812,12,6], backgroundColor: ["#12AF12","#FFD700","#CC3300"] }] };
    var Backup = new Chart(ctx, { type: 'doughnut', data: data, options: { responsive: true, maintainAspectRatio: true, animation: { animateScale: true },
      elements: { center: { text: '97.83%', color: '#12AF12', fontStyle: 'Roboto Condensed', sidePadding: 20 } } } });
    </script>
