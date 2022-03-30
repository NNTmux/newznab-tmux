// Demo Grouped Bar Chart
// 
// The style configurations in this demo are
// intended to match the Material Design styling.
// Use this demo chart as a starting point and for
// reference when creating charts within an app.
// 
// Chart.js v3 is being used, which is currently
// in beta. For the v3 docs, visit
// https://www.chartjs.org/docs/master/

var ctx = document.getElementById('myGroupedBarChart').getContext('2d');
var myBarChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: ['January', 'February', 'March', 'April', 'May', 'June'],
        datasets: [{
                label: 'Last Year',
                backgroundColor: primaryColorOpacity50,
                borderColor: primaryColorOpacity50,
                borderRadius: 4,
                maxBarThickness: 32,
                data: [215, 2265, 4215, 6251, 8921, 11984],
            },
            {
                label: 'This Year',
                backgroundColor: primaryColor,
                borderColor: primaryColor,
                borderRadius: 4,
                maxBarThickness: 32,
                data: [1235, 3402, 5312, 7841, 9877, 14256],
            },
        ],
    },
    options: {
        scales: {
            x: {
                time: {
                    unit: 'month'
                },
                gridLines: {
                    display: false
                },
                ticks: {
                    maxTicksLimit: 12
                },
            },
            y: {
                ticks: {
                    min: 0,
                    max: 15000,
                    maxTicksLimit: 5
                },
                gridLines: {
                    color: 'rgba(0, 0, 0, .075)',
                },
            },
        },
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                displayColors: true
            }
        },
    }
});
