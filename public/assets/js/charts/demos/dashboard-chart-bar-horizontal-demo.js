// Demo Horizontal Bar Chart
// 
// The style configurations in this demo are
// intended to match the Material Design styling.
// Use this demo chart as a starting point and for
// reference when creating charts within an app.
// 
// Chart.js v3 is being used, which is currently
// in beta. For the v3 docs, visit
// https://www.chartjs.org/docs/master/

var ctx = document.getElementById('dashboardHorizontalBarChart').getContext('2d');
var myBarChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: ['United States', 'India', 'South Korea', 'United Kingdom', 'Canada', 'Brazil', 'Indonesia'],
        datasets: [{
                label: 'This Year',
                backgroundColor: primaryColor,
                borderColor: primaryColor,
                borderRadius: 4,
                maxBarThickness: 32,
                data: [15, 8.7, 7.5, 4.4, 3.8, 2.7, 2.3],
            },
            {
                label: 'Last Year',
                backgroundColor: primaryColorOpacity50,
                borderColor: primaryColorOpacity50,
                borderRadius: 4,
                maxBarThickness: 32,
                data: [14.2, 8.2, 7.1, 4.2, 3.5, 2.1, 1.7],
            },
        ],
    },
    options: {
        indexAxis: 'y',
        scales: {
            x: {
                gridLines: {
                    display: false
                },
                ticks: {
                    maxTicksLimit: 6
                },
            },
            y: {
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
