// Demo Bar Chart
// 
// The style configurations in this demo are
// intended to match the Material Design styling.
// Use this demo chart as a starting point and for
// reference when creating charts within an app.
// 
// Chart.js v3 is being used, which is currently
// in beta. For the v3 docs, visit
// https://www.chartjs.org/docs/master/

var ctx = document.getElementById('myBarChart').getContext('2d');
var myBarChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
        datasets: [{
            label: 'Revenue',
            backgroundColor: primaryColor,
            borderColor: primaryColor,
            borderRadius: 4,
            maxBarThickness: 32,
            data: [215, 1235, 2265, 3402, 4215, 5312, 6251, 7841, 8921, 9877, 11984, 14256],
        }],
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
        },
    }
});
