/**
 * Alpine.data('adminDashboard') - Admin dashboard charts with widget auto-refresh.
 */
import Alpine from '@alpinejs/csp';
import Chart from 'chart.js/auto';

Alpine.data('adminDashboard', () => ({
    _charts: [],
    _refreshInterval: null,
    _visibilityHandler: null,
    _lastRefreshAt: Date.now(),
    _isRefreshing: false,

    init() {
        this._initCharts();
        this._scheduleRefresh();
    },

    _scheduleRefresh() {
        const url = this.$el.dataset.refreshUrl;
        const interval = Number.parseInt(this.$el.dataset.refreshInterval ?? '', 10) || (15 * 60 * 1000);

        if (!url) {
            return;
        }

        this._refreshInterval = window.setInterval(() => this._refreshDashboard(url), interval);
        this._visibilityHandler = () => {
            if (!document.hidden && Date.now() - this._lastRefreshAt >= interval) {
                this._refreshDashboard(url);
            }
        };

        document.addEventListener('visibilitychange', this._visibilityHandler);
    },

    _refreshDashboard(url) {
        if (this._isRefreshing) {
            return;
        }

        const currentContent = this.$el.querySelector('[data-dashboard-content]');

        if (!currentContent) {
            return;
        }

        this._isRefreshing = true;

        fetch(url, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'text/html',
            },
            credentials: 'same-origin',
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Failed to refresh dashboard.');
                }

                return response.text();
            })
            .then(html => {
                const parsedDocument = new DOMParser().parseFromString(html, 'text/html');
                const nextContent = parsedDocument.querySelector('#adminDashboard [data-dashboard-content]');

                if (!nextContent) {
                    return;
                }

                this._destroyCharts();
                currentContent.innerHTML = nextContent.innerHTML;
                this._initCharts();
                this._lastRefreshAt = Date.now();
            })
            .catch(() => {})
            .finally(() => {
                this._isRefreshing = false;
            });
    },

    _initCharts() {
        this._destroyCharts();
        this._initChart('downloadsChart', 'bar', 'Downloads', 'rgba(34,197,94,0.7)', 'rgba(34,197,94,1)', false);
        this._initChart('apiHitsChart', 'line', 'API Hits', 'rgba(168,85,247,0.2)', 'rgba(168,85,247,1)', true);
        this._initChart('downloadsMinuteChart', 'line', 'Downloads', 'rgba(34,197,94,0.2)', 'rgba(34,197,94,1)', true);
        this._initChart('apiHitsMinuteChart', 'line', 'API Hits', 'rgba(168,85,247,0.2)', 'rgba(168,85,247,1)', true);
        this._initPercentChart('cpuHistory24hChart', 'CPU Usage %', 'rgba(249,115,22,0.2)', 'rgba(249,115,22,1)');
        this._initPercentChart('ramHistory24hChart', 'RAM Usage %', 'rgba(6,182,212,0.2)', 'rgba(6,182,212,1)');
        this._initPercentChart('cpuHistory30dChart', 'CPU Usage %', 'rgba(249,115,22,0.2)', 'rgba(249,115,22,1)');
        this._initPercentChart('ramHistory30dChart', 'RAM Usage %', 'rgba(6,182,212,0.2)', 'rgba(6,182,212,1)');
    },

    _parseChartData(rawData) {
        try {
            return JSON.parse(rawData || '[]');
        } catch {
            return [];
        }
    },

    _initChart(canvasId, type, label, backgroundColor, borderColor, fill) {
        const canvas = document.getElementById(canvasId);

        if (!canvas) {
            return;
        }

        const data = this._parseChartData(canvas.dataset.chartData || canvas.dataset.history);

        if (data.length === 0) {
            return;
        }

        const chart = new Chart(canvas, {
            type,
            data: {
                labels: data.map(item => item.time),
                datasets: [{
                    label,
                    data: data.map(item => item.count),
                    backgroundColor,
                    borderColor,
                    borderWidth: type === 'bar' ? 1 : 2,
                    fill,
                    tension: 0.4,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
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

        this._charts.push(chart);
    },

    _initPercentChart(canvasId, label, backgroundColor, borderColor) {
        const canvas = document.getElementById(canvasId);

        if (!canvas) {
            return;
        }

        const data = this._parseChartData(canvas.dataset.history);

        if (data.length === 0) {
            return;
        }

        const chart = new Chart(canvas, {
            type: 'line',
            data: {
                labels: data.map(item => item.time),
                datasets: [{
                    label,
                    data: data.map(item => item.value),
                    backgroundColor,
                    borderColor,
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: value => value + '%',
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

        this._charts.push(chart);
    },

    _destroyCharts() {
        this._charts.forEach(chart => chart.destroy());
        this._charts = [];
    },

    destroy() {
        if (this._refreshInterval) {
            window.clearInterval(this._refreshInterval);
        }

        if (this._visibilityHandler) {
            document.removeEventListener('visibilitychange', this._visibilityHandler);
        }

        this._destroyCharts();
    },
}));
