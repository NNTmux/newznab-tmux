// Demo Pie Chart
// 
// The style configurations in this demo are
// intended to match the Material Design styling.
// Use this demo chart as a starting point and for
// reference when creating charts within an app.
// 
// Chart.js v3 is being used, which is currently
// in beta. For the v3 docs, visit
// https://www.chartjs.org/docs/master/

var ctx = document.getElementById('myPieChart').getContext('2d');
var myPieChart = new Chart(ctx, {
    type: 'pie',
    data: {
        labels: ['Alpha', 'Beta', 'Gamma', 'Delta'],
        datasets: [{
            data: [27.21, 15.58, 11.25, 8.32],
            backgroundColor: [primaryColor, infoColor, secondaryColor, warningColor],
        }],
    },
});
