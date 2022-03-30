// Demo Area Chart
//
// The style configurations in this demo are
// intended to match the Material Design styling.
// Use this demo chart as a starting point and for
// reference when creating charts within an app.
//
// Chart.js v3 is being used, which is currently
// in beta. For the v3 docs, visit
// https://www.chartjs.org/docs/master/

var ctx = document.getElementById('dashboardAreaChart').getContext('2d');
var myLineChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        datasets: [
            {
                fill: {
                    target: 'origin',
                    above: primaryColorOpacity10,
                },
                borderColor: primaryColor,
                label: 'Sessions',
                tension: 0.3, // setting tension to 0 disables bezier curves, using a value from 0 to 1 will change the curvature of the line
                pointBackgroundColor: primaryColor,
                pointBorderWidth: 0,
                pointHitRadius: 50,
                pointHoverBackgroundColor: primaryColor,
                pointHoverRadius: 5,
                pointRadius: 0,
                data: [9481, 15684, 13495, 25198, 15498, 18654, 32519],
            },
        ],
    },
    options: {
        scales: {
            x: {
                time: {
                    unit: 'date',
                },
                gridLines: {
                    display: false,
                },
                ticks: {
                    maxTicksLimit: 7,
                },
            },
            y: {
                min: 0,
                max: 40000,
                ticks: {
                    maxTicksLimit: 5,
                },
                gridLines: {
                    color: 'rgba(0, 0, 0, .075)',
                },
            },
        },
        plugins: {
            legend: {
                display: false,
            },
        },
    },
});

let currentLineChart = 'USERS';
const setMyLineChart = (whichChart, keepRotating = false) => {
    autoRotate = keepRotating;
    switch (whichChart) {
        case 'USERS':
            if (currentLineChart === 'USERS') {
                return;
            }
            myLineChart.data.datasets[0].data = [9481, 15684, 13495, 25198, 15498, 18654, 32519];
            myLineChart.data.datasets[0].fill.above = primaryColorOpacity10;
            myLineChart.data.datasets[0].borderColor = primaryColor;
            myLineChart.data.datasets[0].pointBackgroundColor = primaryColor;
            myLineChart.data.datasets[0].pointHoverBackgroundColor = primaryColor;
            myLineChart.update();
            currentLineChart = 'USERS';
            break;
        case 'SESSIONS':
            if (currentLineChart === 'SESSIONS') {
                return;
            }
            myLineChart.data.datasets[0].data = [2634, 9575, 7891, 20299, 9200, 36636, 39113];
            myLineChart.data.datasets[0].fill.above = secondaryColorOpacity10;
            myLineChart.data.datasets[0].borderColor = secondaryColor;
            myLineChart.data.datasets[0].pointBackgroundColor = secondaryColor;
            myLineChart.data.datasets[0].pointHoverBackgroundColor = secondaryColor;
            myLineChart.update();
            currentLineChart = 'SESSIONS';
            break;
        case 'CONVERSIONS':
            if (currentLineChart === 'CONVERSIONS') {
                return;
            }
            myLineChart.data.datasets[0].data = [30000, 20000, 25000, 15000, 20000, 10000, 15000];
            myLineChart.data.datasets[0].fill.above = infoColorOpacity10;
            myLineChart.data.datasets[0].borderColor = infoColor;
            myLineChart.data.datasets[0].pointBackgroundColor = infoColor;
            myLineChart.data.datasets[0].pointHoverBackgroundColor = infoColor;
            myLineChart.update();
            currentLineChart = 'CONVERSIONS';
            break;

        default:
            break;
    }
};

// Rotate the tabs for the demo
let autoRotate = true;

const autoTabCharts = ['USERS', 'SESSIONS', 'CONVERSIONS'];
let chartIndex = 1;

const rotateTabs = () => {
    setTimeout(() => {
        if (!autoRotate) {
            return;
        }
        // Disable rotate if tab-bar is out of viewport
        if (myLineChart.canvas.getBoundingClientRect().top < 97) {
            return rotateTabs();
        }
        document.body.querySelector('mwc-tab-bar').activeIndex = chartIndex;
        setMyLineChart(autoTabCharts[chartIndex], true);
        chartIndex = (chartIndex + 1) % 3;
        rotateTabs();
    }, 3000);
};

rotateTabs();
