(function(window) {
    'use strict';

    window.lrRedirectAnalyticsInit = function(initConfig) {
        const config = initConfig || {};

        if (window.lrRedirectAnalyticsBound) {
            if (window.lrAnalyticsInit) {
                window.lrAnalyticsInit(config);
            }
            return;
        }
        window.lrRedirectAnalyticsBound = true;

        if (window.lrAnalyticsInit) {
            window.lrAnalyticsInit(config);
        }

        const chartColors = window.lrChartColors || [
            '#0d78f2', '#10b981', '#ef4444', '#f59e0b', '#8b5cf6', '#06b6d4',
            '#ec4899', '#84cc16', '#f97316', '#6366f1', '#14b8a6', '#f43f5e'
        ];

        const strings = config.strings || {};
        const dataEndpoint = (window.Craft && Craft.getActionUrl && config.dataEndpoint)
            ? Craft.getActionUrl(config.dataEndpoint)
            : config.dataEndpoint;

        function destroyChart(canvasId, prefix) {
            const chartKey = canvasId.replace(/-/g, '_');
            if (window.lrChartInstances && window.lrChartInstances[prefix] && window.lrChartInstances[prefix][chartKey]) {
                window.lrChartInstances[prefix][chartKey].destroy();
                delete window.lrChartInstances[prefix][chartKey];
            }
        }

        function resetChartState(canvas) {
            if (!canvas) return;
            canvas.style.display = '';
            const parent = canvas.parentElement || canvas.parentNode;
            if (!parent) return;
            parent.querySelectorAll('.zilch').forEach(el => el.remove());
        }

        function renderEmptyState(canvasId, message, prefix) {
            const canvas = document.getElementById(canvasId);
            if (!canvas) return;
            resetChartState(canvas);
            destroyChart(canvasId, prefix);
            canvas.style.display = 'none';
            const emptyMsg = document.createElement('div');
            emptyMsg.className = 'zilch';
            emptyMsg.style.padding = '48px 24px';
            emptyMsg.innerHTML = '<p>' + message + '</p>';
            canvas.parentNode.appendChild(emptyMsg);
        }

        function requestData(type, params, onSuccess, onError) {
            if (!dataEndpoint) {
                if (onError) onError();
                return;
            }

            const data = Object.assign({ type: type }, params || {});

            if (config.csrfName && config.csrfToken) {
                data[config.csrfName] = config.csrfToken;
            }

            if (typeof $ !== 'undefined' && $.ajax) {
                $.ajax({
                    url: dataEndpoint,
                    type: 'POST',
                    dataType: 'json',
                    data: data,
                    success: function(response) {
                        if (response && response.success) {
                            onSuccess(response.data || null);
                        } else if (onError) {
                            onError();
                        }
                    },
                    error: function() {
                        if (onError) onError();
                    }
                });
                return;
            }

            const formData = new FormData();
            Object.keys(data).forEach(function(key) {
                formData.append(key, data[key]);
            });

            fetch(dataEndpoint, {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(response) {
                if (response && response.success) {
                    onSuccess(response.data || null);
                } else if (onError) {
                    onError();
                }
            })
            .catch(function() {
                if (onError) onError();
            });
        }

        document.addEventListener('lr:analyticsInit', function(e) {
            const eventConfig = e.detail && e.detail.config ? e.detail.config : (window.lrAnalyticsConfig || {});
            const dateRange = eventConfig.dateRange || config.dateRange || 'last7days';
            const siteId = eventConfig.siteId || config.siteId || '';
            const prefix = eventConfig.prefix || 'analytics';

            loadAllCharts(dateRange, siteId, prefix);
        });

        function loadAllCharts(dateRange, siteId, prefix) {
            const baseParams = { dateRange: dateRange, siteId: siteId };

            requestData('chart', baseParams, function(data) {
                renderTrendChart(data || [], prefix);
            }, function() {
                renderTrendChart([], prefix);
            });

            requestData('bots', baseParams, function(data) {
                renderBotChart(data || null, prefix);
            }, function() {
                renderBotChart(null, prefix);
            });

            requestData('devices', baseParams, function(data) {
                renderDeviceChart(data || null, prefix);
            }, function() {
                renderDeviceChart(null, prefix);
            });

            requestData('browsers', baseParams, function(data) {
                renderBrowserChart(data || null, prefix);
            }, function() {
                renderBrowserChart(null, prefix);
            });

            requestData('os', baseParams, function(data) {
                renderOsChart(data || null, prefix);
            }, function() {
                renderOsChart(null, prefix);
            });
        }

        function renderTrendChart(data, prefix) {
            const ctx = document.getElementById('404-trend-chart');
            if (!ctx) return;
            resetChartState(ctx);
            if (!data || !data.length) {
                renderEmptyState('404-trend-chart', strings.noTrend || 'No trend data available.', prefix);
                return;
            }

            window.lrCreateChart('404-trend-chart', 'line', {
                labels: data.map(d => d.date),
                datasets: [
                    { label: strings.handledLabel || 'Handled', data: data.map(d => d.handled), borderColor: '#10B981', backgroundColor: '#10B981', tension: 0.1, fill: false },
                    { label: strings.unhandledLabel || 'Unhandled', data: data.map(d => d.unhandled), borderColor: '#EF4444', backgroundColor: '#EF4444', tension: 0.1, fill: false }
                ]
            }, {
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 } } },
                plugins: { legend: { position: 'bottom', align: 'center', labels: { usePointStyle: true, pointStyle: 'circle', padding: 20 } } }
            });
        }

        function renderBotChart(data, prefix) {
            const ctx = document.getElementById('bot-chart');
            if (!ctx) return;
            resetChartState(ctx);
            if (!data || !data.chart || !data.chart.labels || !data.chart.labels.length) {
                renderEmptyState('bot-chart', strings.noBot || 'No bot data available.', prefix);
                return;
            }

            window.lrCreateChart('bot-chart', 'doughnut', {
                labels: data.chart.labels,
                datasets: [{ data: data.chart.values, backgroundColor: [chartColors[1], chartColors[2]] }]
            }, {
                plugins: { legend: { position: 'bottom' } }
            });

            const botText = document.getElementById('bot-percentage-text');
            if (botText) {
                botText.innerHTML = '<strong>' + data.botPercentage + '%</strong> ' + (strings.botTraffic || 'of traffic is from bots');
            }
        }

        function renderDeviceChart(data, prefix) {
            const ctx = document.getElementById('device-chart');
            if (!ctx) return;
            resetChartState(ctx);
            if (!data || !data.labels || !data.labels.length) {
                renderEmptyState('device-chart', strings.noDevice || 'No device data available.', prefix);
                return;
            }

            window.lrCreateChart('device-chart', 'doughnut', {
                labels: data.labels,
                datasets: [{ data: data.values, backgroundColor: chartColors.slice(0, data.labels.length) }]
            }, {
                plugins: { legend: { position: 'bottom' } }
            });
        }

        function renderBrowserChart(data, prefix) {
            const ctx = document.getElementById('browser-chart');
            if (!ctx) return;
            resetChartState(ctx);
            if (!data || !data.labels || !data.labels.length) {
                renderEmptyState('browser-chart', strings.noBrowser || 'No browser data available.', prefix);
                return;
            }

            window.lrCreateChart('browser-chart', 'bar', {
                labels: data.labels,
                datasets: [{ label: strings.redirectsLabel || '404s', data: data.values, backgroundColor: chartColors[0], borderColor: chartColors[0], borderWidth: 1 }]
            }, {
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 } } },
                plugins: { legend: { display: false } }
            });
        }

        function renderOsChart(data, prefix) {
            const ctx = document.getElementById('os-chart');
            if (!ctx) return;
            resetChartState(ctx);
            if (!data || !data.labels || !data.labels.length) {
                renderEmptyState('os-chart', strings.noOs || 'No OS data available.', prefix);
                return;
            }

            window.lrCreateChart('os-chart', 'doughnut', {
                labels: data.labels,
                datasets: [{ data: data.values, backgroundColor: chartColors }]
            }, {
                plugins: { legend: { position: 'bottom' } }
            });
        }
    };
})(window);
