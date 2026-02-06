/**
 * Admin Dashboard Charts and Recent Activity
 */

function initAdminDashboardCharts() {
    // Only initialize if Chart.js is available and we're on a page with charts
    if (typeof Chart === 'undefined') {
        // If Chart.js is not loaded yet, wait for it
        let attempts = 0;
        const maxAttempts = 30; // Try for up to 3 seconds
        const checkChartJs = setInterval(() => {
            attempts++;
            if (typeof Chart !== 'undefined') {
                clearInterval(checkChartJs);
                initializeAllDashboardCharts();
            } else if (attempts >= maxAttempts) {
                clearInterval(checkChartJs);
                console.warn('Chart.js not loaded - charts will not be displayed');
            }
        }, 100);
        return;
    }

    initializeAllDashboardCharts();
}

function initializeAllDashboardCharts() {
    // Check if any chart canvas elements exist
    const hasUserStatsCharts = document.getElementById('downloadsChart') ||
                                 document.getElementById('apiHitsChart') ||
                                 document.getElementById('downloadsMinuteChart') ||
                                 document.getElementById('apiHitsMinuteChart');

    const hasSystemCharts = document.getElementById('cpuHistory24hChart') ||
                            document.getElementById('ramHistory24hChart') ||
                            document.getElementById('cpuHistory30dChart') ||
                            document.getElementById('ramHistory30dChart');

    if (!hasUserStatsCharts && !hasSystemCharts) {
        return; // Not on admin dashboard or charts not present
    }

    // Initialize user statistics charts
    initializeUserStatCharts();

    // Initialize system metrics charts
    initializeSystemMetricsCharts();
}

function initializeUserStatCharts() {
    // Downloads Chart (Last 7 Days - Hourly)
    const downloadsCanvas = document.getElementById('downloadsChart');
    if (downloadsCanvas) {
        const data = JSON.parse(downloadsCanvas.dataset.chartData || '[]');
        if (data.length > 0) {
            new Chart(downloadsCanvas, {
                type: 'bar',
                data: {
                    labels: data.map(item => item.time),
                    datasets: [{
                        label: 'Downloads',
                        data: data.map(item => item.count),
                        backgroundColor: 'rgba(34, 197, 94, 0.7)',
                        borderColor: 'rgba(34, 197, 94, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }
    }

    // API Hits Chart (Last 7 Days - Hourly)
    const apiHitsCanvas = document.getElementById('apiHitsChart');
    if (apiHitsCanvas) {
        const data = JSON.parse(apiHitsCanvas.dataset.chartData || '[]');
        if (data.length > 0) {
            new Chart(apiHitsCanvas, {
                type: 'line',
                data: {
                    labels: data.map(item => item.time),
                    datasets: [{
                        label: 'API Hits',
                        data: data.map(item => item.count),
                        backgroundColor: 'rgba(168, 85, 247, 0.2)',
                        borderColor: 'rgba(168, 85, 247, 1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }
    }

    // Downloads Per Minute Chart (Last 60 Minutes)
    const downloadsMinuteCanvas = document.getElementById('downloadsMinuteChart');
    if (downloadsMinuteCanvas) {
        const data = JSON.parse(downloadsMinuteCanvas.dataset.chartData || '[]');
        if (data.length > 0) {
            new Chart(downloadsMinuteCanvas, {
                type: 'line',
                data: {
                    labels: data.map(item => item.time),
                    datasets: [{
                        label: 'Downloads',
                        data: data.map(item => item.count),
                        backgroundColor: 'rgba(34, 197, 94, 0.2)',
                        borderColor: 'rgba(34, 197, 94, 1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }
    }

    // API Hits Per Minute Chart (Last 60 Minutes)
    const apiHitsMinuteCanvas = document.getElementById('apiHitsMinuteChart');
    if (apiHitsMinuteCanvas) {
        const data = JSON.parse(apiHitsMinuteCanvas.dataset.chartData || '[]');
        if (data.length > 0) {
            new Chart(apiHitsMinuteCanvas, {
                type: 'line',
                data: {
                    labels: data.map(item => item.time),
                    datasets: [{
                        label: 'API Hits',
                        data: data.map(item => item.count),
                        backgroundColor: 'rgba(168, 85, 247, 0.2)',
                        borderColor: 'rgba(168, 85, 247, 1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }
    }
}

function initializeSystemMetricsCharts() {
    // CPU Usage 24h Chart
    const cpuHistory24hCanvas = document.getElementById('cpuHistory24hChart');
    if (cpuHistory24hCanvas) {
        const history = JSON.parse(cpuHistory24hCanvas.dataset.history || '[]');
        if (history.length > 0) {
            new Chart(cpuHistory24hCanvas, {
                type: 'line',
                data: {
                    labels: history.map(item => item.time),
                    datasets: [{
                        label: 'CPU Usage %',
                        data: history.map(item => item.value),
                        backgroundColor: 'rgba(249, 115, 22, 0.2)',
                        borderColor: 'rgba(249, 115, 22, 1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }
    }

    // RAM Usage 24h Chart
    const ramHistory24hCanvas = document.getElementById('ramHistory24hChart');
    if (ramHistory24hCanvas) {
        const history = JSON.parse(ramHistory24hCanvas.dataset.history || '[]');
        if (history.length > 0) {
            new Chart(ramHistory24hCanvas, {
                type: 'line',
                data: {
                    labels: history.map(item => item.time),
                    datasets: [{
                        label: 'RAM Usage %',
                        data: history.map(item => item.value),
                        backgroundColor: 'rgba(6, 182, 212, 0.2)',
                        borderColor: 'rgba(6, 182, 212, 1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }
    }

    // CPU Usage 30d Chart
    const cpuHistory30dCanvas = document.getElementById('cpuHistory30dChart');
    if (cpuHistory30dCanvas) {
        const history = JSON.parse(cpuHistory30dCanvas.dataset.history || '[]');
        if (history.length > 0) {
            new Chart(cpuHistory30dCanvas, {
                type: 'line',
                data: {
                    labels: history.map(item => item.time),
                    datasets: [{
                        label: 'CPU Usage %',
                        data: history.map(item => item.value),
                        backgroundColor: 'rgba(249, 115, 22, 0.2)',
                        borderColor: 'rgba(249, 115, 22, 1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }
    }

    // RAM Usage 30d Chart
    const ramHistory30dCanvas = document.getElementById('ramHistory30dChart');
    if (ramHistory30dCanvas) {
        const history = JSON.parse(ramHistory30dCanvas.dataset.history || '[]');
        if (history.length > 0) {
            new Chart(ramHistory30dCanvas, {
                type: 'line',
                data: {
                    labels: history.map(item => item.time),
                    datasets: [{
                        label: 'RAM Usage %',
                        data: history.map(item => item.value),
                        backgroundColor: 'rgba(6, 182, 212, 0.2)',
                        borderColor: 'rgba(6, 182, 212, 1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }
    }
}

function initRecentActivityRefresh() {
    const activityContainer = document.getElementById('recent-activity-container');
    if (!activityContainer) {
        return; // Not on admin dashboard page
    }

    const refreshUrl = activityContainer.getAttribute('data-refresh-url');
    if (!refreshUrl) {
        return;
    }

    // Function to refresh activity data
    function refreshRecentActivity() {
        fetch(refreshUrl, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            credentials: 'same-origin'
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.activities) {
                updateActivityDisplay(data.activities);
                updateLastRefreshTime();
            }
        })
        .catch(error => {
            console.error('Error refreshing recent activity:', error);
        });
    }

    // Function to update the activity display
    function updateActivityDisplay(activities) {
        const container = document.getElementById('recent-activity-container');
        if (!container) return;

        // Clear existing content
        container.innerHTML = '';

        if (activities.length === 0) {
            container.innerHTML = '<p class="text-gray-500 dark:text-gray-400 text-center py-4" id="no-activity-message">No recent activity</p>';
            return;
        }

        // Add each activity item
        activities.forEach(activity => {
            const activityItem = document.createElement('div');
            activityItem.className = 'flex items-start activity-item rounded-lg p-2 hover:bg-gray-50 dark:hover:bg-gray-900 transition-colors';

            activityItem.innerHTML = `
                <div class="w-8 h-8 ${activity.icon_bg} rounded-full flex items-center justify-center mr-3 flex-shrink-0">
                    <i class="fas fa-${activity.icon} ${activity.icon_color} text-sm"></i>
                </div>
                <div class="flex-1">
                    <p class="text-sm text-gray-800 dark:text-gray-200">${escapeHtml(activity.message)}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">${escapeHtml(activity.created_at)}</p>
                </div>
            `;

            container.appendChild(activityItem);
        });
    }

    // Function to update last refresh time
    function updateLastRefreshTime() {
        const lastUpdatedElement = document.getElementById('activity-last-updated');
        if (lastUpdatedElement) {
            const now = new Date();
            const timeString = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            lastUpdatedElement.innerHTML = `<i class="fas fa-sync-alt"></i> Last updated: ${timeString}`;
        }
    }

    // Helper function to escape HTML to prevent XSS
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Set up auto-refresh every 20 minutes (1200000 milliseconds)
    const refreshInterval = 20 * 60 * 1000; // 20 minutes in milliseconds
    setInterval(refreshRecentActivity, refreshInterval);

    // Also refresh on page visibility change (when user comes back to tab)
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            refreshRecentActivity();
        }
    });
}

export { initAdminDashboardCharts, initRecentActivityRefresh };
