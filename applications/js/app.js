$(function () {
    $(".navbar-expand-toggle").click(function () {
        $(".app-container").toggleClass("expanded");
        return $(".navbar-expand-toggle").toggleClass("fa-rotate-90");
    });
    return $(".navbar-right-expand-toggle").click(function () {
        $(".navbar-right").toggleClass("expanded");
        return $(".navbar-right-expand-toggle").toggleClass("fa-rotate-90");
    });
});

$(function () {
    return $('.toggle-checkbox').bootstrapSwitch({
        size: "small"
    });
});

$(function () {
    return $('.match-height').matchHeight();
});

$(function () {
    return $(".side-menu .nav .dropdown").on('show.bs.collapse', function () {
        return $(".side-menu .nav .dropdown .collapse").collapse('hide');
    });
});

function startTime() {
    var today = new Date();
    var h = today.getHours();
    var m = today.getMinutes();
    var s = today.getSeconds();
    m = checkTime(m);
    s = checkTime(s);
    document.getElementById('clock').innerHTML =
        h + ":" + m + ":" + s;
    var t = setTimeout(startTime, 500);
}

function checkTime(i) {
    if (i < 10) {
        i = "0" + i
    }  // add zero in front of numbers < 10
    return i;
}

var options = {
    hover: {
        mode: "x-axis",
        animationDuration: 400
    },
    // Elements options apply to all of the options unless overridden in a dataset
    // In this case, we are setting the border of each horizontal bar to be 2px wide and green
    scales: {
        xAxes: [{
            categoryPercentage: 0.5,
            barPercentage: 1.0,
            ticks: {
                max: 100,
                min: 0
            }
        }]
    },
    responsive: true,
    legend: {
        position: 'right'
    }
};

var options_line = {
    elements: {
        point: {
            radius: 1
        }
    },
    tooltips: {
        enabled: true,
        intersect: false,
        mode: 'index'
    },
    hover: {
        mode: 'index',
        intersect: false,
        animationDuration: 0
    },
    scales: {
        xAxes: [{
            ticks: {
                maxRotation: 0,
                autoSkipPadding: 20,
                fontSize: 15
            }
        }],
        yAxes: [{
            ticks: {
                max: 100,
                min: 0,
                fontSize: 15
            }
        }]
    },
    spanGaps: true
};