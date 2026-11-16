/**
 * Alpine.data('adminPayments') - Revenue and payment-count chart on the admin payments page.
 *
 * The series is rendered server-side for the active filters and read from `data-series`:
 * { labels: string[], revenue: number[], payments: number[] }.
 */
import Alpine from '@alpinejs/csp';
import Chart from 'chart.js/auto';
import { applyChartDefaults, observeTheme, seriesColors } from '../../lib/chart-theme.js';

const REVENUE_TONE = { name: 'success', fillAlpha: 0.7 };
const PAYMENTS_TONE = { name: 'primary', fillAlpha: 0.2 };

Alpine.data('adminPayments', () => {
    // Kept out of Alpine's reactive state: proxying a Chart instance breaks its internals.
    let chart = null;
    let stopThemeObserver = null;

    return {
        init() {
            let series;
            try {
                series = JSON.parse(this.$el.dataset.series || '{}');
            } catch (_error) {
                return;
            }

            const canvas = this.$el.querySelector('canvas');
            if (!canvas || !Array.isArray(series.labels) || series.labels.length === 0) return;

            applyChartDefaults(Chart);
            chart = new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: series.labels,
                    datasets: [
                        {
                            type: 'bar',
                            label: 'Revenue',
                            data: series.revenue,
                            ...seriesColors(REVENUE_TONE.name, REVENUE_TONE.fillAlpha),
                            borderWidth: 1,
                            yAxisID: 'y',
                            order: 2,
                        },
                        {
                            type: 'line',
                            label: 'Payments',
                            data: series.payments,
                            ...seriesColors(PAYMENTS_TONE.name, PAYMENTS_TONE.fillAlpha),
                            borderWidth: 2,
                            tension: 0.3,
                            yAxisID: 'y1',
                            order: 1,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        y: { beginAtZero: true, title: { display: true, text: 'Revenue' } },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            grid: { drawOnChartArea: false },
                            ticks: { precision: 0 },
                            title: { display: true, text: 'Payments' },
                        },
                    },
                    plugins: { legend: { position: 'bottom' } },
                },
            });
            chart.$themeTones = [REVENUE_TONE, PAYMENTS_TONE];
            stopThemeObserver = observeTheme(Chart, () => (chart ? [chart] : []));
        },

        destroy() {
            stopThemeObserver?.();
            chart?.destroy();
            chart = null;
        },
    };
});
