// The style configurations for Chart.js are
// intended to match the Material Design styling.
//
// Chart.js v3 is being used, which is currently
// in beta. For the v3 docs, visit
// https://www.chartjs.org/docs/master/

// * * * * * * * *
// * * Colors  * *
// * * * * * * * *

// Get the color values from CSS variables

// Primary
var primaryColor = getComputedStyle(document.documentElement)
    .getPropertyValue('--bs-primary')
    .trim();

// Secondary
var secondaryColor = getComputedStyle(document.documentElement)
    .getPropertyValue('--bs-secondary')
    .trim();

// Info
var infoColor = getComputedStyle(document.documentElement)
    .getPropertyValue('--bs-info')
    .trim();

// Success
var successColor = getComputedStyle(document.documentElement)
    .getPropertyValue('--bs-success')
    .trim();

// Warning
var warningColor = getComputedStyle(document.documentElement)
    .getPropertyValue('--bs-warning')
    .trim();

// Danger
var dangerColor = getComputedStyle(document.documentElement)
    .getPropertyValue('--bs-danger')
    .trim();

// Border Color
var borderColor = getComputedStyle(document.documentElement)
    .getPropertyValue('--bs-border-color')
    .trim();

// Text Muted Color
var textMutedColor = getComputedStyle(document.documentElement)
    .getPropertyValue('--bs-text-muted-color')
    .trim();

// Function to convert a hex code to RGB
// to create opacity where needed
window.hexToRgb = function hexToRgb(hex) {
    var result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
    return result ?
        {
            r: parseInt(result[1], 16),
            g: parseInt(result[2], 16),
            b: parseInt(result[3], 16),
        } :
        null;
};

// Get the RGB value for the primary color
var primaryColorRgb = window.hexToRgb(primaryColor);
var secondaryColorRgb = window.hexToRgb(secondaryColor);
var successColorRgb = window.hexToRgb(successColor);
var warningColorRgb = window.hexToRgb(warningColor);
var dangerColorRgb = window.hexToRgb(dangerColor);
var infoColorRgb = window.hexToRgb(infoColor);

// Create a 10% opacity for the primary color
// (Used on area chart)
var primaryColorOpacity10 =
    'rgba(' + primaryColorRgb.r + ',' + primaryColorRgb.g + ',' + primaryColorRgb.b + ',0.1)';

var secondaryColorOpacity10 =
    'rgba(' + secondaryColorRgb.r + ',' + secondaryColorRgb.g + ',' + secondaryColorRgb.b + ',0.1)';

var successColorOpacity10 =
    'rgba(' + successColorRgb.r + ',' + successColorRgb.g + ',' + successColorRgb.b + ',0.1)';

var warningColorOpacity10 =
    'rgba(' + warningColorRgb.r + ',' + warningColorRgb.g + ',' + warningColorRgb.b + ',0.1)';

var dangerColorOpacity10 =
    'rgba(' + dangerColorRgb.r + ',' + dangerColorRgb.g + ',' + dangerColorRgb.b + ',0.1)';

var infoColorOpacity10 =
    'rgba(' + infoColorRgb.r + ',' + infoColorRgb.g + ',' + infoColorRgb.b + ',0.1)';

// Create a 50% opacity for the primary color
// (Used on grouped bar charts)
var primaryColorOpacity50 =
    'rgba(' + primaryColorRgb.r + ',' + primaryColorRgb.g + ',' + primaryColorRgb.b + ',0.5)';

// * * * * * * * * * * * *
// * * Global Defaults * *
// * * * * * * * * * * * *

// Global defaults chart font styling
// Use Roboto font to match Material styling
// and additional font faces for fallbacks.
(Chart.defaults.font.family = 'Roboto'),
'system-ui',
'-apple-system',
'Segoe UI',
'Helvetica Neue',
'Arial',
'Noto Sans',
'Liberation Sans',
'sans-serif',
'Apple Color Emoji',
'Segoe UI Emoji',
'Segoe UI Symbol',
'Noto Color Emoji';
Chart.defaults.font.color = textMutedColor;

// * * * * * * * * * * * * *
// * * Tooltip Defaults  * *
// * * * * * * * * * * * * *

// Global defaults for chart tooltip styling
Chart.defaults.plugins.tooltip.backgroundColor = 'white';
Chart.defaults.plugins.tooltip.titleColor = textMutedColor;
Chart.defaults.plugins.tooltip.titleMarginBottom = 16;
Chart.defaults.plugins.tooltip.bodyColor = textMutedColor;
Chart.defaults.plugins.tooltip.footerColor = textMutedColor;
Chart.defaults.plugins.tooltip.footerMarginTop = 16;
Chart.defaults.plugins.tooltip.xPadding = 16;
Chart.defaults.plugins.tooltip.yPadding = 16;
Chart.defaults.plugins.tooltip.caretPadding = 16;
Chart.defaults.plugins.tooltip.caretSize = 0;
Chart.defaults.plugins.tooltip.corderRadius = 0;
Chart.defaults.plugins.tooltip.displayColors = false; // Change to true to see display colors in tooltips
Chart.defaults.plugins.tooltip.boxWidth = 12;
Chart.defaults.plugins.tooltip.boxHeight = 5;
Chart.defaults.plugins.tooltip.borderColor = borderColor;
Chart.defaults.plugins.tooltip.borderWidth = 1;
