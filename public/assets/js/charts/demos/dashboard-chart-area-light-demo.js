// Demo Smooth Area Chart
// 
// The style configurations in this demo are
// intended to match the Material Design styling.
// Use this demo chart as a starting point and for
// reference when creating charts within an app.
// 
// Chart.js v3 is being used, which is currently
// in beta. For the v3 docs, visit
// https://www.chartjs.org/docs/master/

var ctx = document.getElementById('dashboardAreaChartLight').getContext('2d');

var gradient = ctx.createLinearGradient(0, 0, 0, 400);
gradient.addColorStop(0, 'rgba(255,255,255,0.3)');
gradient.addColorStop(1, 'rgba(255,255,255,0)');

var myLineChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: ['Mar 1', 'Mar 2', 'Mar 3', 'Mar 4', 'Mar 5', 'Mar 6', 'Mar 7', 'Mar 8', 'Mar 9', 'Mar 10', 'Mar 11', 'Mar 12', 'Mar 13'],
        datasets: [{
            fill: {
                target: 'origin',
                above: gradient,
            },
            borderColor: 'rgba(255, 255, 255, 1)',
            label: 'Sessions',
            tension: 0.3, // setting tension to 0 disables bezier curves, using a value from 0 to 1 will change the curvature of the line
            pointBackgroundColor: 'rgba(255, 255, 255, 1)',
            pointBorderWidth: 0,
            pointHitRadius: 30,
            pointHoverBackgroundColor: 'rgba(255, 255, 255, 1)',
            pointHoverRadius: 5,
            pointRadius: 0,
            data: [10000, 30162, 26263, 18394, 18287, 28682, 31274, 33259, 25849, 24159, 32651, 31984, 38451],
        }],
    },
    options: {
        scales: {
            x: {
                time: {
                    unit: 'date'
                },
                gridLines: {
                    borderDash: [5, 10],
                    borderDashOffset: 5,
                    color: 'rgba(255, 255, 255, .3)',
                },
                ticks: {
                    color: 'rgba(255, 255, 255, .75)',
                    maxTicksLimit: 7
                },
            },
            y: {
                ticks: {
                    display: false,
                },
                gridLines: {
                    display: false
                },
            },
        },
        plugins: {
            legend: {
                display: false
            },
        },
    }
});
