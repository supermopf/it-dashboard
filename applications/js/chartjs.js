$(function () {
    var ctx, data, myLineChart, options;
    Chart.defaults.global.responsive = true;
    ctx = $('#line-chart').get(0).getContext('2d');
    options = {
        scaleShowGridLines: true,
        scaleGridLineColor: "rgba(0,0,0,.05)",
        scaleGridLineWidth: 1,
        scaleShowHorizontalLines: true,
        scaleShowVerticalLines: true,
        bezierCurve: false,
        bezierCurveTension: 0.4,
        pointDot: true,
        pointDotRadius: 4,
        pointDotStrokeWidth: 1,
        pointHitDetectionRadius: 20,
        datasetStroke: true,
        datasetStrokeWidth: 2,
        datasetFill: true,
        legendTemplate: "<ul class=\"<%=name.toLowerCase()%>-legend\"><% for (var i=0; i<datasets.length; i++){%><li><span style=\"background-color:<%=datasets[i].strokeColor%>\"></span><%if(datasets[i].label){%><%=datasets[i].label%><%}%></li><%}%></ul>"
    };
    data = {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
        datasets: [
            {
                label: "My First dataset",
                fillColor: "rgba(26, 188, 156,0.2)",
                strokeColor: "#1ABC9C",
                pointColor: "#1ABC9C",
                pointStrokeColor: "#fff",
                pointHighlightFill: "#fff",
                pointHighlightStroke: "#1ABC9C",
                data: [65, 59, 80, 81, 56, 55, 40]
            }, {
                label: "My Second dataset",
                fillColor: "rgba(34, 167, 240,0.2)",
                strokeColor: "#22A7F0",
                pointColor: "#22A7F0",
                pointStrokeColor: "#fff",
                pointHighlightFill: "#fff",
                pointHighlightStroke: "#22A7F0",
                data: [28, 48, 40, 19, 86, 27, 90]
            }
        ]
    };
    myLineChart = new Chart(ctx).Line(data, options);
});