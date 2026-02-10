/**
 * Alpine.data('adminDashboard') - Admin dashboard charts (Chart.js)
 * Alpine.data('recentActivity') - Auto-refreshing recent activity
 */
import Alpine from '@alpinejs/csp';
import Chart from 'chart.js/auto';

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

Alpine.data('adminDashboard', () => ({
    init() {
        this._initCharts();
    },

    _initCharts() {
        this._initChart('downloadsChart', 'bar', 'Downloads', 'rgba(34,197,94,0.7)', 'rgba(34,197,94,1)', false);
        this._initChart('apiHitsChart', 'line', 'API Hits', 'rgba(168,85,247,0.2)', 'rgba(168,85,247,1)', true);
        this._initChart('downloadsMinuteChart', 'line', 'Downloads', 'rgba(34,197,94,0.2)', 'rgba(34,197,94,1)', true);
        this._initChart('apiHitsMinuteChart', 'line', 'API Hits', 'rgba(168,85,247,0.2)', 'rgba(168,85,247,1)', true);
        this._initPercentChart('cpuHistory24hChart', 'CPU Usage %', 'rgba(249,115,22,0.2)', 'rgba(249,115,22,1)');
        this._initPercentChart('ramHistory24hChart', 'RAM Usage %', 'rgba(6,182,212,0.2)', 'rgba(6,182,212,1)');
        this._initPercentChart('cpuHistory30dChart', 'CPU Usage %', 'rgba(249,115,22,0.2)', 'rgba(249,115,22,1)');
        this._initPercentChart('ramHistory30dChart', 'RAM Usage %', 'rgba(6,182,212,0.2)', 'rgba(6,182,212,1)');
    },

    _initChart(canvasId, type, label, bg, border, fill) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;
        const data = JSON.parse(canvas.dataset.chartData || canvas.dataset.history || '[]');
        if (data.length === 0) return;
        new Chart(canvas, {
            type, data: {
                labels: data.map(i => i.time),
                datasets: [{ label, data: data.map(i => i.count), backgroundColor: bg, borderColor: border, borderWidth: type === 'bar' ? 1 : 2, fill, tension: 0.4 }]
            },
            options: { responsive: true, maintainAspectRatio: true, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }, plugins: { legend: { display: false } } }
        });
    },

    _initPercentChart(canvasId, label, bg, border) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;
        const data = JSON.parse(canvas.dataset.history || '[]');
        if (data.length === 0) return;
        new Chart(canvas, {
            type: 'line', data: {
                labels: data.map(i => i.time),
                datasets: [{ label, data: data.map(i => i.value), backgroundColor: bg, borderColor: border, borderWidth: 2, fill: true, tension: 0.4 }]
            },
            options: { responsive: true, maintainAspectRatio: true, scales: { y: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' } } }, plugins: { legend: { display: false } } }
        });
    }
}));

Alpine.data('recentActivity', () => ({
    _interval: null,

    init() {
        const url = this.$el.dataset.refreshUrl;
        if (!url) return;
        this._interval = setInterval(() => this._refresh(url), 20 * 60 * 1000);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) this._refresh(url); });
    },

    _refresh(url) {
        fetch(url, { method: 'GET', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(r => { if (!r.ok) throw new Error('fail'); return r.json(); })
        .then(data => {
            if (!data.success || !data.activities) return;
            this.$el.innerHTML = '';
            if (data.activities.length === 0) {
                this.$el.innerHTML = '<p class="text-gray-500 dark:text-gray-400 text-center py-4">No recent activity</p>';
                return;
            }
            data.activities.forEach(a => {
                const div = document.createElement('div');
                div.className = 'flex items-start activity-item rounded-lg p-2 hover:bg-gray-50 dark:hover:bg-gray-900 transition-colors';
                div.innerHTML = '<div class="w-8 h-8 ' + a.icon_bg + ' rounded-full flex items-center justify-center mr-3 flex-shrink-0"><i class="fas fa-' + a.icon + ' ' + a.icon_color + ' text-sm"></i></div><div class="flex-1"><p class="text-sm text-gray-800 dark:text-gray-200">' + escapeHtml(a.message) + '</p><p class="text-xs text-gray-500 dark:text-gray-400">' + escapeHtml(a.created_at) + '</p></div>';
                this.$el.appendChild(div);
            });
            const ts = document.getElementById('activity-last-updated');
            if (ts) ts.innerHTML = '<i class="fas fa-sync-alt"></i> Last updated: ' + new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        })
        .catch(() => {});
    },

    destroy() {
        if (this._interval) clearInterval(this._interval);
    }
}));
