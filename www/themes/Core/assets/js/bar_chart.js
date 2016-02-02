// CATEGORIES CHART
$(function () {

    var data = [["January", 10], ["February", 8], ["March", 4], ["April", 13], ["May", 17], ["June", 9]];

    $.plot("#placeholder", [data], {
        series: {
            bars: {
                show: true,
                barWidth: 0.6,
                align: "center"
            }
        },
        xaxis: {
            mode: "categories",
            tickLength: 0
        }
    });


});

//END CATEGORIES CHART

// ERROR CHART
$(function () {

    function drawArrow(ctx, x, y, radius) {
        ctx.beginPath();
        ctx.moveTo(x + radius, y + radius);
        ctx.lineTo(x, y);
        ctx.lineTo(x - radius, y + radius);
        ctx.stroke();
    }

    function drawSemiCircle(ctx, x, y, radius) {
        ctx.beginPath();
        ctx.arc(x, y, radius, 0, Math.PI, false);
        ctx.moveTo(x - radius, y);
        ctx.lineTo(x + radius, y);
        ctx.stroke();
    }

    var data1 = [
        [1, 1, .5, .1, .3],
        [2, 2, .3, .5, .2],
        [3, 3, .9, .5, .2],
        [1.5, -.05, .5, .1, .3],
        [3.15, 1., .5, .1, .3],
        [2.5, -1., .5, .1, .3]
    ];

    var data1_points = {
        show: true,
        radius: 5,
        fillColor: "blue",
        errorbars: "xy",
        xerr: { show: true, asymmetric: true, upperCap: "-", lowerCap: "-" },
        yerr: { show: true, color: "red", upperCap: "-" }
    };

    var data2 = [
        [.7, 3, .2, .4],
        [1.5, 2.2, .3, .4],
        [2.3, 1, .5, .2]
    ];

    var data2_points = {
        show: true,
        radius: 5,
        errorbars: "y",
        yerr: { show: true, asymmetric: true, upperCap: drawArrow, lowerCap: drawSemiCircle }
    };

    var data3 = [
        [1, 2, .4],
        [2, 0.5, .3],
        [2.7, 2, .5]
    ];

    var data3_points = {
        //do not show points
        radius: 0,
        errorbars: "y",
        yerr: { show: true, upperCap: "-", lowerCap: "-", radius: 5 }
    };

    var data4 = [
        [1.3, 1],
        [1.75, 2.5],
        [2.5, 0.5]
    ];

    var data4_errors = [0.1, 0.4, 0.2];
    for (var i = 0; i < data4.length; i++) {
        data4_errors[i] = data4[i].concat(data4_errors[i])
    }

    var data = [
        { color: "blue", points: data1_points, data: data1, label: "data1" },
        { color: "red", points: data2_points, data: data2, label: "data2" },
        { color: "green", lines: { show: true }, points: data3_points, data: data3, label: "data3" },
        // bars with errors
        { color: "orange", bars: { show: true, align: "center", barWidth: 0.25 }, data: data4, label: "data4" },
        { color: "orange", points: data3_points, data: data4_errors }
    ];

    $.plot($("#placeholderEB"), data, {
        legend: {
            position: "sw",
            show: true
        },
        series: {
            lines: {
                show: false
            }
        },
        xaxis: {
            min: 0.6,
            max: 3.1
        },
        yaxis: {
            min: 0,
            max: 3.5
        },
        zoom: {
            interactive: true
        },
        pan: {
            interactive: true
        }
    });

    // Add the Flot version string to the footer

});
// END ERROR CHART

// STACKING CHART
$(function () {

    var d1 = [];
    for (var i = 0; i <= 10; i += 1) {
        d1.push([i, parseInt(Math.random() * 30)]);
    }

    var d2 = [];
    for (var i = 0; i <= 10; i += 1) {
        d2.push([i, parseInt(Math.random() * 30)]);
    }

    var d3 = [];
    for (var i = 0; i <= 10; i += 1) {
        d3.push([i, parseInt(Math.random() * 30)]);
    }

    var stack = 0,
        bars = true,
        lines = false,
        steps = false;

    function plotWithOptions() {
        $.plot("#placeholderStack", [d1, d2, d3], {
            series: {
                stack: stack,
                lines: {
                    show: lines,
                    fill: true,
                    steps: steps
                },
                bars: {
                    show: bars,
                    barWidth: 0.6
                }
            }
        });
    }

    plotWithOptions();

    $(".stackControls button").click(function (e) {
        e.preventDefault();
        stack = $(this).text() == "With stacking" ? true : null;
        plotWithOptions();
    });

    $(".graphControls button").click(function (e) {
        e.preventDefault();
        bars = $(this).text().indexOf("Bars") != -1;
        lines = $(this).text().indexOf("Lines") != -1;
        steps = $(this).text().indexOf("steps") != -1;
        plotWithOptions();
    });

    // Add the Flot version string to the footer

});
// END STACKING CHART