/**
 * Alpine.data('adminDashboard') - Admin dashboard with deferred widget loading.
 *
 * The Blade view ships only the lightweight stat tiles + registration status
 * synchronously. All heavy widgets (User Statistics, System Resources history,
 * Recent Activity, Site Status, System Status) render skeleton placeholders
 * and are populated by fetching the cached `admin.api.dashboard-data` payload
 * on mount. The cache is warmed every minute by the scheduler so this fetch
 * is essentially free.
 */
import Alpine from '@alpinejs/csp';
import Chart from 'chart.js/auto';
import { applyChartDefaults, observeTheme, seriesColors } from '../../lib/chart-theme.js';
// Badge colour classes per status come from the PHP enums (data-badge-classes on the dashboard root),
// so Blade and this script cannot drift apart.
const BADGE_CLASSES = { service: {}, impact: {}, registration: {} };
const NEUTRAL_BADGE_CLASSES = 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200';
const SYSTEM_SERVICE_SLUGS = ['database', 'redis', 'queue', 'disk'];
const REGISTRATION_BADGE_BASE = 'inline-flex items-center justify-center rounded-full px-3 py-1.5 text-sm font-semibold ';
const formatNumber = (value) => Number(value || 0).toLocaleString();
/** Full literal class strings so Tailwind can see them (never build class names at runtime). */
const SUMMARY_TONES = {
    primary: { card: 'from-primary-50 to-primary-100 dark:from-primary-900 dark:to-primary-800', label: 'text-link dark:text-primary-300', value: 'text-primary-800 dark:text-primary-100' },
    success: { card: 'from-success-50 to-success-100 dark:from-success-900 dark:to-success-800', label: 'text-success-700 dark:text-success-300', value: 'text-success-800 dark:text-success-100' },
    violet: { card: 'from-violet-50 to-violet-100 dark:from-violet-900 dark:to-violet-800', label: 'text-violet-700 dark:text-violet-300', value: 'text-violet-800 dark:text-violet-100' },
    orange: { card: 'from-orange-50 to-orange-100 dark:from-orange-900 dark:to-orange-800', label: 'text-orange-700 dark:text-orange-300', value: 'text-orange-800 dark:text-orange-100' },
    pink: { card: 'from-pink-50 to-pink-100 dark:from-pink-900 dark:to-pink-800', label: 'text-pink-700 dark:text-pink-300', value: 'text-pink-800 dark:text-pink-100' },
};

const escapeHtml = (str) => String(str ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
const badgeClass = (status) => 'px-2 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full '
    + (BADGE_CLASSES.service[status] ?? NEUTRAL_BADGE_CLASSES);
const registrationBadgeClass = (status) => REGISTRATION_BADGE_BASE
    + (BADGE_CLASSES.registration[status] ?? NEUTRAL_BADGE_CLASSES);
Alpine.data('adminDashboard', () => ({
    _charts: [],
    _refreshInterval: null,
    _visibilityHandler: null,
    _lastRefreshAt: Date.now(),
    _isRefreshing: false,
    loadError: false,
    init() {
        try {
            Object.assign(BADGE_CLASSES, JSON.parse(this.$el.dataset.badgeClasses || '{}'));
        } catch (_error) {
            // Fall back to neutral pills when the attribute is missing or malformed.
        }
        this._loadDashboardData();
        this._scheduleRefresh();
    },
    _scheduleRefresh() {
        const interval = Number.parseInt(this.$el.dataset.refreshInterval ?? '', 10) || (15 * 60 * 1000);
        this._refreshInterval = window.setInterval(() => this._loadDashboardData(), interval);
        this._visibilityHandler = () => {
            if (!document.hidden && Date.now() - this._lastRefreshAt >= interval) {
                this._loadDashboardData();
            }
        };
        document.addEventListener('visibilitychange', this._visibilityHandler);
    },
    _loadDashboardData() {
        const url = this.$el.dataset.dataUrl;
        if (!url || this._isRefreshing) {
            return;
        }
        this._isRefreshing = true;
        fetch(url, {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            credentials: 'same-origin',
        })
            .then(response => {
                if (!response.ok) throw new Error('Failed to load dashboard data.');
                return response.json();
            })
            .then(payload => {
                this._renderHeadlineStats(payload.stats);
                this._renderRegistrationStatus(payload.registrationStatus);
                this._renderLastRefresh(payload.generated_at_time);
                this._renderUserStats(payload.userStats);
                this._renderSystemMetrics(payload.systemMetrics);
                this._renderRecentActivity(payload.recent_activity);
                this._renderRecentPayments(payload.recent_payments);
                this._renderSiteStatus(payload.serviceStatuses, payload.activeIncidents);
                this._renderSystemServices(payload.serviceStatuses);
                this._lastRefreshAt = Date.now();
                this.loadError = false;
            })
            .catch(() => {
                // Tell the admin instead of leaving the loading skeletons up forever.
                this.loadError = true;
            })
            .finally(() => {
                this._isRefreshing = false;
            });
    },
    retry() {
        this._loadDashboardData();
    },
    _swapWidget(name) {
        const widget = this.$el.querySelector(`[data-widget="${name}"]`);
        if (!widget) return null;
        widget.querySelector('[data-widget-loading]')?.classList.add('hidden');
        const content = widget.querySelector('[data-widget-content]');
        content?.classList.remove('hidden');
        return widget;
    },
    _setStatText(key, value) {
        const el = this.$el.querySelector(`[data-stat="${key}"]`);
        if (el) el.textContent = value;
    },
    _renderHeadlineStats(stats) {
        if (!stats) return;
        const soft = Number(stats.soft_deleted_users ?? 0);
        const perm = Number(stats.permanently_deleted_users ?? 0);
        this._setStatText('releases', formatNumber(stats.releases));
        this._setStatText('releases-today', formatNumber(stats.releases_today));
        this._setStatText('users', formatNumber(stats.users));
        this._setStatText('users-today', formatNumber(stats.users_today));
        this._setStatText('active-groups', formatNumber(stats.active_groups));
        this._setStatText('groups', formatNumber(stats.groups));
        this._setStatText('failed', formatNumber(stats.failed));
        this._setStatText('reported', formatNumber(stats.reported));
        this._setStatText('soft-deleted-users', formatNumber(soft));
        this._setStatText('permanently-deleted-users', formatNumber(perm));
        this._setStatText('deleted-users-total', formatNumber(soft + perm));
    },
    _renderRegistrationStatus(reg) {
        if (!reg) return;
        const effective = this.$el.querySelector('[data-registration="effective-badge"]');
        if (effective) {
            effective.className = registrationBadgeClass(Number(reg.effective_status));
            effective.textContent = String(reg.effective_status_label ?? '');
        }
        const manual = this.$el.querySelector('[data-registration="manual-badge"]');
        if (manual) {
            manual.className = registrationBadgeClass(Number(reg.manual_status));
            manual.textContent = String(reg.manual_status_label ?? '');
        }
        const override = this.$el.querySelector('[data-registration="scheduled-override"]');
        if (override) {
            override.classList.toggle('hidden', !reg.scheduled_override_active);
        }
        const message = this.$el.querySelector('[data-registration="message"]');
        if (message) message.textContent = String(reg.message ?? '');
    },
    _renderLastRefresh(timeText) {
        this._setStatText('last-refresh', timeText ?? '');
    },
    _renderUserStats(stats) {
        if (!stats) return;
        const widget = this._swapWidget('user-stats');
        if (!widget) return;
        const summary = stats.summary ?? {};
        const cards = [
            { label: 'Total Users', value: summary.total_users, tone: 'primary' },
            { label: 'Downloads Today', value: summary.downloads_today, tone: 'success' },
            { label: 'Downloads (last 7d incl. today)', value: summary.downloads_week, tone: 'violet' },
            { label: 'API/RSS Hits Today', value: summary.api_hits_today, tone: 'orange' },
            { label: 'API/RSS Hits (last 7d incl. today)', value: summary.api_hits_week, tone: 'pink' },
        ];
        const summaryGrid = widget.querySelector('[data-summary-grid]');
        if (summaryGrid) {
            summaryGrid.innerHTML = cards.map(c => `
                <div class="bg-linear-to-br ${SUMMARY_TONES[c.tone].card} rounded-lg p-4 text-center">
                    <p class="text-sm ${SUMMARY_TONES[c.tone].label} font-medium mb-1">${escapeHtml(c.label)}</p>
                    <p class="text-2xl font-bold ${SUMMARY_TONES[c.tone].value}">${formatNumber(c.value)}</p>
                </div>
            `).join('');
        }
        const roles = stats.users_by_role ?? [];
        const totalUsers = roles.reduce((sum, r) => sum + Number(r.count || 0), 0);
        const rolesTbody = widget.querySelector('[data-roles-tbody]');
        if (rolesTbody) {
            rolesTbody.innerHTML = roles.length === 0
                ? '<tr><td colspan="3" class="px-4 py-3 text-sm text-center text-gray-500 dark:text-gray-400">No data available</td></tr>'
                : roles.map(r => {
                    const pct = totalUsers > 0 ? ((Number(r.count) / totalUsers) * 100).toFixed(1) : '0';
                    const role = String(r.role ?? '');
                    const roleLabel = role ? role.charAt(0).toUpperCase() + role.slice(1) : '';
                    return `
                        <tr class="hover:bg-gray-100 dark:hover:bg-gray-800 transition">
                            <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 dark:bg-primary-900 text-primary-800 dark:text-primary-200">${escapeHtml(roleLabel)}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-gray-100 font-semibold">${formatNumber(r.count)}</td>
                            <td class="px-4 py-3 text-sm text-right text-gray-600 dark:text-gray-400">${pct}%</td>
                        </tr>`;
                }).join('');
        }
        const downloaders = stats.top_downloaders ?? [];
        const downloadersTbody = widget.querySelector('[data-downloaders-tbody]');
        if (downloadersTbody) {
            const medalIcons = ['fa-medal text-amber-500', 'fa-medal text-gray-400', 'fa-medal text-orange-600'];
            downloadersTbody.innerHTML = downloaders.length === 0
                ? '<tr><td colspan="3" class="px-4 py-3 text-sm text-center text-gray-500 dark:text-gray-400">No downloads in the last 7 days</td></tr>'
                : downloaders.map((d, i) => {
                    const rank = i < 3 ? `<i aria-hidden="true" class="fas ${medalIcons[i]} text-lg"></i>` : `<span class="text-gray-500">${i + 1}</span>`;
                    return `
                        <tr class="hover:bg-gray-100 dark:hover:bg-gray-800 transition">
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">${rank}</td>
                            <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">${escapeHtml(d.username)}</td>
                            <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-gray-100 font-semibold">${formatNumber(d.download_count)}</td>
                        </tr>`;
                }).join('');
        }
        this._renderChart('downloadsChart', 'bar', 'Downloads', stats.downloads_per_hour ?? [], 'success', false);
        this._renderChart('apiHitsChart', 'line', 'API/RSS Hits', stats.api_hits_per_hour ?? [], 'violet', true);
        this._renderChart('downloadsMinuteChart', 'line', 'Downloads', stats.downloads_per_minute ?? [], 'success', true);
        this._renderChart('apiHitsMinuteChart', 'line', 'API/RSS Hits', stats.api_hits_per_minute ?? [], 'violet', true);
    },
    _renderSystemMetrics(metrics) {
        if (!metrics) return;
        const widget = this._swapWidget('system-metrics');
        if (!widget) return;
        const setText = (selector, text) => {
            const el = widget.querySelector(`[data-metric="${selector}"]`);
            if (el) el.textContent = text;
        };
        const cpu = metrics.cpu ?? {};
        const ram = metrics.ram ?? {};
        const load = cpu.load_average ?? {};
        setText('cpu-current', `${Number(cpu.current ?? 0).toFixed(1)}%`);
        setText('cpu-cores', cpu.cores ?? '—');
        setText('cpu-threads', cpu.threads ?? '—');
        setText('cpu-load-1min', load['1min'] ?? '—');
        setText('cpu-load-5min', load['5min'] ?? '—');
        setText('cpu-load-15min', load['15min'] ?? '—');
        const cpuModel = widget.querySelector('[data-metric="cpu-model"]');
        if (cpuModel && cpu.model && cpu.model !== 'Unknown') {
            cpuModel.title = cpu.model;
            cpuModel.innerHTML = `<i aria-hidden="true" class="fas fa-info-circle mr-1"></i>${escapeHtml(cpu.model)}`;
        }
        setText('ram-current', `${Number(ram.percentage ?? 0).toFixed(1)}%`);
        setText('ram-details', `${Number(ram.used ?? 0).toFixed(2)} GB / ${Number(ram.total ?? 0).toFixed(2)} GB`);
        this._renderPercentChart('cpuHistory24hChart', 'CPU Usage %', cpu.history_24h ?? [], 'orange');
        this._renderPercentChart('ramHistory24hChart', 'RAM Usage %', ram.history_24h ?? [], 'cyan');
        this._renderPercentChart('cpuHistory30dChart', 'CPU Usage %', cpu.history_30d ?? [], 'orange');
        this._renderPercentChart('ramHistory30dChart', 'RAM Usage %', ram.history_30d ?? [], 'cyan');
    },
    _renderRecentActivity(activities) {
        const widget = this._swapWidget('recent-activity');
        if (!widget) return;
        const updated = widget.querySelector('[data-metric="activity-updated"]');
        if (updated) {
            updated.textContent = `Updated ${new Date().toLocaleTimeString()}`;
        }
        const container = widget.querySelector('#recent-activity-container');
        if (!container) return;
        if (!Array.isArray(activities) || activities.length === 0) {
            container.innerHTML = '<p class="text-gray-500 dark:text-gray-400 text-center py-4">No recent activity</p>';
            return;
        }
        container.innerHTML = activities.map(activity => {
            let extra = '';
            if (activity.type === 'deleted' && activity.metadata && activity.metadata.deleted_by) {
                const permanent = activity.metadata.permanent ? ', permanent' : '';
                extra = `<span class="text-xs text-gray-500 dark:text-gray-400"> (by ${escapeHtml(activity.metadata.deleted_by)}${escapeHtml(permanent)})</span>`;
            }
            return `
                <div class="flex items-start activity-item rounded-lg p-2 hover:bg-gray-50 dark:hover:bg-gray-900 transition-colors">
                    <div class="w-8 h-8 ${escapeHtml(activity.icon_classes ?? '')} rounded-full flex items-center justify-center mr-3 shrink-0">
                        <i aria-hidden="true" class="fas fa-${escapeHtml(activity.icon)} text-sm"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm text-gray-800 dark:text-gray-200">${escapeHtml(activity.message)}${extra}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">${escapeHtml(activity.created_at_human ?? '')}</p>
                    </div>
                </div>`;
        }).join('');
    },
    _renderRecentPayments(payments) {
        const hasPayments = Array.isArray(payments) && payments.length > 0;
        const existing = this.$el.querySelector('[data-widget="recent-payments"]');
        if (!hasPayments) {
            if (existing) existing.classList.add('hidden');
            return;
        }
        if (existing) existing.classList.remove('hidden');
        const widget = this._swapWidget('recent-payments');
        if (!widget) return;
        const tbody = widget.querySelector('[data-payments-tbody]');
        if (!tbody) return;
        const settledClasses = 'bg-success-100 dark:bg-success-900 text-success-800 dark:text-success-200';
        const pendingClasses = 'bg-warning-100 dark:bg-warning-900 text-warning-800 dark:text-warning-200';
        const otherClasses = 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200';
        tbody.innerHTML = payments.map(p => {
            const status = String(p.payment_status ?? '');
            const isSettled = status.toLowerCase() === 'settled';
            const isPending = status === '' || status.toLowerCase() === 'pending';
            const badge = isSettled ? settledClasses : (isPending ? pendingClasses : otherClasses);
            const statusLabel = status || 'Pending';
            const dateText = p.created_at_formatted ?? p.created_at_human ?? '';
            const item = p.item_description ?? '';
            const itemTrunc = item.length > 40 ? item.slice(0, 40) + '…' : item;
            return `
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-900 transition">
                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">${escapeHtml(dateText)}</td>
                    <td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">${escapeHtml(p.username ?? '')}</td>
                    <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300" title="${escapeHtml(item)}">${escapeHtml(itemTrunc)}</td>
                    <td class="px-4 py-2 whitespace-nowrap text-sm text-right font-semibold text-gray-900 dark:text-gray-100">${escapeHtml(p.invoice_amount ?? '')}</td>
                    <td class="px-4 py-2 whitespace-nowrap text-center">
                        <span class="px-2 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full ${badge}">${escapeHtml(statusLabel)}</span>
                    </td>
                </tr>`;
        }).join('');
    },
    _renderSiteStatus(services, incidents) {
        const widget = this._swapWidget('site-status');
        if (!widget) return;
        const grid = widget.querySelector('[data-services-grid]');
        if (grid) {
            grid.innerHTML = (services ?? []).map(svc => `
                <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50 dark:bg-gray-900 border border-gray-100 dark:border-gray-700">
                    <div>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">${escapeHtml(svc.name)}</span>
                        <span class="block text-xs text-gray-500 dark:text-gray-400">${Number(svc.uptime_percentage).toFixed(2)}% uptime</span>
                    </div>
                    <span class="${badgeClass(svc.status)}">${escapeHtml(svc.status_label)}</span>
                </div>
            `).join('');
        }
        const region = widget.querySelector('[data-incidents-region]');
        if (!region) return;
        if (!Array.isArray(incidents) || incidents.length === 0) {
            region.innerHTML = '<p class="text-sm text-success-600 dark:text-success-400"><i aria-hidden="true" class="fas fa-check-circle mr-1"></i>No active incidents</p>';
            return;
        }
        const visible = incidents.slice(0, 5);
        const overflow = incidents.length - visible.length;
        const items = visible.map(inc => {
            const impactClass = 'px-2 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full shrink-0 '
                + (BADGE_CLASSES.impact[inc.impact] ?? NEUTRAL_BADGE_CLASSES);
            const autoBadge = inc.is_auto
                ? '<span class="ml-1 px-1 py-0.5 text-[10px] leading-3 font-semibold rounded bg-indigo-100 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-200">Auto</span>'
                : '';
            const title = String(inc.title ?? '');
            const truncated = title.length > 50 ? `${title.slice(0, 50)}…` : title;
            return `
                <div class="flex items-start justify-between gap-2 text-sm py-1">
                    <div class="min-w-0">
                        <span class="text-gray-800 dark:text-gray-200 truncate block">${escapeHtml(truncated)}</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">${escapeHtml((inc.services ?? []).join(', '))} &middot; ${escapeHtml(inc.started_at_human ?? '')}${autoBadge}</span>
                    </div>
                    <span class="${impactClass}">${escapeHtml(inc.impact_label)}</span>
                </div>`;
        }).join('');
        region.innerHTML = `
            <h4 class="text-sm font-semibold text-danger-600 dark:text-danger-400 mb-2">
                <i aria-hidden="true" class="fas fa-exclamation-triangle mr-1"></i>${incidents.length} Active Incident${incidents.length === 1 ? '' : 's'}
            </h4>
            <div class="space-y-2">${items}${overflow > 0 ? `<p class="text-xs text-gray-500 dark:text-gray-400 pt-1">+ ${overflow} more</p>` : ''}</div>
        `;
    },
    _renderSystemServices(services) {
        const widget = this._swapWidget('system-status');
        if (!widget) return;
        const container = widget.querySelector('[data-system-services]');
        if (!container) return;
        const bySlug = {};
        (services ?? []).forEach(svc => { bySlug[svc.slug] = svc; });
        container.innerHTML = SYSTEM_SERVICE_SLUGS.map(slug => {
            const svc = bySlug[slug];
            const label = svc?.name ?? (slug.charAt(0).toUpperCase() + slug.slice(1));
            const right = svc
                ? `<span class="${badgeClass(svc.status)}">${escapeHtml(svc.status_label)}</span>`
                : '<span class="px-3 py-1 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-full text-sm">Not configured</span>';
            return `
                <div class="flex items-center justify-between">
                    <span class="text-gray-600 dark:text-gray-400">${escapeHtml(label)}</span>
                    ${right}
                </div>`;
        }).join('');
    },
    _destroyChart(canvasId) {
        const existing = this._charts.find(c => c.canvas?.id === canvasId);
        if (existing) {
            existing.destroy();
            this._charts = this._charts.filter(c => c !== existing);
        }
    },
    _renderChart(canvasId, type, label, data, tone, fill) {
        const canvas = document.getElementById(canvasId);
        if (!canvas || !Array.isArray(data) || data.length === 0) return;
        this._destroyChart(canvasId);
        this._ensureChartTheme();
        const fillAlpha = type === 'bar' ? 0.7 : 0.2;
        const { backgroundColor, borderColor } = seriesColors(tone, fillAlpha);
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
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                plugins: { legend: { display: false } },
            },
        });
        chart.$themeTone = { name: tone, fillAlpha };
        this._charts.push(chart);
    },
    _renderPercentChart(canvasId, label, data, tone) {
        const canvas = document.getElementById(canvasId);
        if (!canvas || !Array.isArray(data) || data.length === 0) return;
        this._destroyChart(canvasId);
        this._ensureChartTheme();
        const { backgroundColor, borderColor } = seriesColors(tone, 0.2);
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
                scales: { y: { beginAtZero: true, max: 100, ticks: { callback: value => value + '%' } } },
                plugins: { legend: { display: false } },
            },
        });
        chart.$themeTone = { name: tone, fillAlpha: 0.2 };
        this._charts.push(chart);
    },
    /** Apply token-based chart defaults once and keep charts in sync with theme changes. */
    _ensureChartTheme() {
        if (this._stopThemeObserver) return;
        applyChartDefaults(Chart);
        this._stopThemeObserver = observeTheme(Chart, () => this._charts);
    },
    _destroyCharts() {
        this._charts.forEach(chart => chart.destroy());
        this._charts = [];
    },
    destroy() {
        if (this._refreshInterval) window.clearInterval(this._refreshInterval);
        if (this._visibilityHandler) document.removeEventListener('visibilitychange', this._visibilityHandler);
        this._stopThemeObserver?.();
        this._destroyCharts();
    },
}));
