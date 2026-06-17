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
        const createRedirectUrl = config.createRedirectUrl || '';

        let currentDateRange = config.dateRange || 'last7days';
        let currentSiteId = config.siteId || '';
        let currentPrefix = config.prefix || 'rm';

        // Guard flags: reset on date/site change, checked before loading
        let recentUnhandledLoaded = false;
        let trafficDevicesLoaded = false;
        let geographicLoaded = false;

        function destroyChart(canvasId, prefix) {
            var chartKey = canvasId.replace(/-/g, '_');
            if (window.lrChartInstances && window.lrChartInstances[prefix] && window.lrChartInstances[prefix][chartKey]) {
                window.lrChartInstances[prefix][chartKey].destroy();
                delete window.lrChartInstances[prefix][chartKey];
            }
        }

        function resetChartState(canvas) {
            if (!canvas) return;
            canvas.style.display = '';
            var parent = canvas.parentElement || canvas.parentNode;
            if (!parent) return;
            parent.querySelectorAll('.zilch').forEach(function(el) { el.remove(); });
        }

        function renderEmptyState(canvasId, message, prefix) {
            var canvas = document.getElementById(canvasId);
            if (!canvas) return;
            resetChartState(canvas);
            destroyChart(canvasId, prefix);
            canvas.style.display = 'none';
            var emptyMsg = document.createElement('div');
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

            var data = Object.assign({ type: type }, params || {});

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

            var formData = new FormData();
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

        function getActiveTabId() {
            var hash = window.location.hash ? window.location.hash.substring(1) : '';
            if (hash && document.getElementById(hash)) {
                return hash;
            }
            var visible = document.querySelector('.lr-tab-content:not(.hidden)');
            return visible ? visible.id : 'overview';
        }

        // ── Overview tab: trend chart only (stat cards + most common are server-rendered) ──

        function loadOverviewCharts(dateRange, siteId, prefix) {
            var baseParams = { dateRange: dateRange, siteId: siteId };

            requestData('chart', baseParams, function(data) {
                renderTrendChart(data || [], prefix);
            }, function() {
                renderTrendChart([], prefix);
            });

            // Load recent unhandled via AJAX
            loadRecentUnhandled(dateRange, siteId);
        }

        function renderTrendChart(data, prefix) {
            var ctx = document.getElementById('404-trend-chart');
            if (!ctx) return;
            resetChartState(ctx);
            if (!data || !data.length) {
                renderEmptyState('404-trend-chart', strings.noTrend || 'No trend data available.', prefix);
                return;
            }

            window.lrCreateChart('404-trend-chart', 'line', {
                labels: data.map(function(d) { return d.date; }),
                datasets: [
                    { label: strings.handledLabel || 'Handled', data: data.map(function(d) { return d.handled; }), borderColor: '#10B981', backgroundColor: '#10B981', tension: 0.1, fill: false },
                    { label: strings.unhandledLabel || 'Unhandled', data: data.map(function(d) { return d.unhandled; }), borderColor: '#EF4444', backgroundColor: '#EF4444', tension: 0.1, fill: false }
                ]
            }, {
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 } } },
                plugins: { legend: { position: 'bottom', align: 'center', labels: { usePointStyle: true, pointStyle: 'circle', padding: 20 } } }
            });
        }

        // ── Recent Unhandled 404s (Overview tab, AJAX-loaded) ──

        function isSafeUrl(url) {
            return typeof url === 'string' && (
                url.indexOf('/') === 0 ||
                url.indexOf('http://') === 0 ||
                url.indexOf('https://') === 0
            );
        }

        function requestTypeLabel(type) {
            if (type === 'system') return strings.system || 'System';
            if (type === 'bot') return strings.bot || 'Bot';
            if (type === 'probe') return strings.probe || 'Probe';
            return strings.normal || 'Normal';
        }

        function loadRecentUnhandled(dateRange, siteId) {
            if (recentUnhandledLoaded) return;
            requestData('recent-unhandled', { dateRange: dateRange, siteId: siteId }, function(data) {
                renderRecentUnhandled(data);
                recentUnhandledLoaded = true;
            }, function() {
                renderRecentUnhandled(null);
                recentUnhandledLoaded = true;
            });
        }

        function renderRecentUnhandled(data) {
            var tbody = document.getElementById('recent-unhandled-body');
            if (!tbody) return;

            if (!data || data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="thin light">' + Craft.escapeHtml(strings.noRecentUnhandled || 'No unhandled 404s! Great job!') + '</td></tr>';
                return;
            }

            var html = '';
            data.forEach(function(stat) {
                var urlDisplay = stat.url.length > 80 ? stat.url.substring(0, 80) + '...' : stat.url;
                var referrerDisplay = stat.referrer ? (stat.referrer.length > 40 ? stat.referrer.substring(0, 40) + '...' : stat.referrer) : '-';
                var dateTimeDisplay = '';
                if (stat.date && stat.time) {
                    dateTimeDisplay = Craft.escapeHtml(stat.date) + ' ' + Craft.escapeHtml(stat.time);
                } else if (stat.date) {
                    dateTimeDisplay = Craft.escapeHtml(stat.date);
                } else {
                    dateTimeDisplay = '\u2014';
                }

                var createUrl = createRedirectUrl;
                if (createUrl && stat.url) {
                    createUrl += (createUrl.indexOf('?') !== -1 ? '&' : '?') + 'sourceUrl=' + encodeURIComponent(stat.url);
                }

                var urlCell = isSafeUrl(stat.url)
                    ? '<a class="label-link" href="' + Craft.escapeHtml(encodeURI(stat.url)) + '" target="_blank" rel="noopener noreferrer"><span><code>' + Craft.escapeHtml(urlDisplay) + '</code></span></a>'
                    : '<span><code>' + Craft.escapeHtml(urlDisplay) + '</code></span>';
                var requestType = stat.requestType || 'normal';
                var agentTitle = [stat.botCategory, stat.botProducerName].filter(Boolean).join(' · ');
                var agentCell = stat.botName
                    ? '<span' + (agentTitle ? ' title="' + Craft.escapeHtml(agentTitle) + '"' : '') + '>' + Craft.escapeHtml(stat.botName) + '</span>'
                    : '-';

                html += '<tr>' +
                    '<td>' + urlCell + '</td>' +
                    '<td>' + Craft.escapeHtml(stat.siteName || '\u2014') + '</td>' +
                    '<td>' + Number(stat.count).toLocaleString() + '</td>' +
                    '<td>' + Craft.escapeHtml(requestTypeLabel(requestType)) + '</td>' +
                    '<td>' + agentCell + '</td>' +
                    '<td>' + Craft.escapeHtml(referrerDisplay) + '</td>' +
                    '<td>' + dateTimeDisplay + '</td>' +
                    '<td>' + (createUrl ? '<a href="' + Craft.escapeHtml(createUrl) + '" class="btn submit icon add" title="' + Craft.escapeHtml(strings.createRedirect || 'Create redirect') + '"></a>' : '') + '</td>' +
                    '</tr>';
            });

            tbody.innerHTML = html;
        }

        // ── Traffic & Devices tab (lazy-loaded) ──

        function loadTrafficDevicesData(dateRange, siteId, prefix) {
            if (trafficDevicesLoaded) return;
            var baseParams = { dateRange: dateRange, siteId: siteId };

            requestData('bots', baseParams, function(data) {
                renderBotChart(data || null, prefix);
                renderBotStats(data);
            }, function() {
                renderBotChart(null, prefix);
                renderBotStats(null);
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
                trafficDevicesLoaded = true;
            }, function() {
                renderOsChart(null, prefix);
                trafficDevicesLoaded = true;
            });
        }

        function renderBotChart(data, prefix) {
            var ctx = document.getElementById('bot-chart');
            if (!ctx) return;
            resetChartState(ctx);
            if (!data || !data.chart || !data.chart.types || !data.chart.types.length) {
                renderEmptyState('bot-chart', strings.noRequestType || strings.noBot || 'No request type data available.', prefix);
                return;
            }

            window.lrCreateChart('bot-chart', 'doughnut', {
                labels: data.chart.types.map(requestTypeLabel),
                datasets: [{ data: data.chart.values, backgroundColor: [chartColors[0], chartColors[5], chartColors[2], chartColors[3]] }]
            }, {
                plugins: { legend: { position: 'bottom' } }
            });

            var botText = document.getElementById('bot-percentage-text');
            if (botText) {
                botText.innerHTML = Craft.escapeHtml(strings.nonHumanTraffic || 'Non-human traffic') + ': <strong>' + Craft.escapeHtml(String(data.nonHumanPercentage || 0)) + '%</strong>';
            }
        }

        function renderBotStats(data) {
            var tbody = document.getElementById('top-bots-body');
            if (!tbody) return;

            var topAgents = data && data.topAgents ? data.topAgents : [];
            if (topAgents.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="thin light" style="text-align: center;">' + Craft.escapeHtml(strings.noAgentData || strings.noBotData || 'No agent data available') + '</td></tr>';
                return;
            }

            var html = '';
            topAgents.forEach(function(agent) {
                html += '<tr>' +
                    '<td>' + Craft.escapeHtml(agent.botName || '-') + '</td>' +
                    '<td>' + Craft.escapeHtml(requestTypeLabel(agent.requestType || 'normal')) + '</td>' +
                    '<td>' + Craft.escapeHtml(agent.botCategory || '-') + '</td>' +
                    '<td>' + Craft.escapeHtml(agent.botProducerName || '-') + '</td>' +
                    '<td>' + Number(agent.count).toLocaleString() + '</td>' +
                    '</tr>';
            });

            tbody.innerHTML = html;
        }

        function renderDeviceChart(data, prefix) {
            var ctx = document.getElementById('device-chart');
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
            var ctx = document.getElementById('browser-chart');
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
            var ctx = document.getElementById('os-chart');
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

        // ── Geographic tab (lazy-loaded) ──

        function loadGeographicData(dateRange, siteId) {
            if (geographicLoaded) return;
            var baseParams = { dateRange: dateRange, siteId: siteId };

            requestData('countries', baseParams, function(data) {
                renderCountries(data);
            }, function() {
                renderCountries(null);
            });

            requestData('cities', baseParams, function(data) {
                renderCities(data);
                geographicLoaded = true;
            }, function() {
                renderCities(null);
                geographicLoaded = true;
            });
        }

        function renderCountries(data) {
            var tbody = document.getElementById('countries-body');
            if (!tbody) return;

            if (!data || data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" class="thin light" style="text-align: center;">' + Craft.escapeHtml(strings.noCountryData || 'No country data available') + '</td></tr>';
                return;
            }

            var html = '';
            data.forEach(function(c) {
                html += '<tr>' +
                    '<td>' + Craft.escapeHtml(c.name) + '</td>' +
                    '<td>' + Number(c.count).toLocaleString() + '</td>' +
                    '<td>' + Craft.escapeHtml(String(c.percentage)) + '%</td>' +
                    '</tr>';
            });

            tbody.innerHTML = html;
        }

        function renderCities(data) {
            var tbody = document.getElementById('cities-body');
            if (!tbody) return;

            if (!data || data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="thin light" style="text-align: center;">' + Craft.escapeHtml(strings.noCityData || 'No city data available') + '</td></tr>';
                return;
            }

            var html = '';
            data.forEach(function(c) {
                html += '<tr>' +
                    '<td>' + Craft.escapeHtml(c.city) + '</td>' +
                    '<td>' + Craft.escapeHtml(c.countryName) + '</td>' +
                    '<td>' + Number(c.count).toLocaleString() + '</td>' +
                    '<td>' + Craft.escapeHtml(String(c.percentage)) + '%</td>' +
                    '</tr>';
            });

            tbody.innerHTML = html;
        }

        // ── Tab switching dispatcher ──

        function loadTabData(tabName) {
            if (tabName === 'overview') {
                // Recent unhandled is loaded with guard flag
                loadRecentUnhandled(currentDateRange, currentSiteId);
                return;
            }

            if (tabName === 'traffic-devices') {
                loadTrafficDevicesData(currentDateRange, currentSiteId, currentPrefix);
                return;
            }

            if (tabName === 'geographic') {
                loadGeographicData(currentDateRange, currentSiteId);
                return;
            }
        }

        // ── Init handler (called on page load and date/site changes) ──

        function handleAnalyticsInit(eventConfig) {
            var resolved = eventConfig || window.lrAnalyticsConfig || {};
            currentDateRange = resolved.dateRange || currentDateRange;
            currentSiteId = (resolved.siteId !== undefined && resolved.siteId !== null) ? resolved.siteId : currentSiteId;
            currentPrefix = resolved.prefix || currentPrefix;

            // Reset guard flags so tabs reload with new filters
            recentUnhandledLoaded = false;
            trafficDevicesLoaded = false;
            geographicLoaded = false;

            // Load overview chart (trend) + recent unhandled
            loadOverviewCharts(currentDateRange, currentSiteId, currentPrefix);

            // Load data for whichever tab is currently active
            var activeTab = getActiveTabId();
            loadTabData(activeTab);
        }

        // ── Event listeners ──

        document.addEventListener('lr:analyticsInit', function(e) {
            var eventConfig = e.detail && e.detail.config ? e.detail.config : null;
            handleAnalyticsInit(eventConfig);
        });

        document.addEventListener('lr:tabChanged', function(e) {
            var tabId = e.detail && e.detail.tabId ? e.detail.tabId : getActiveTabId();
            loadTabData(tabId);
        });
    };
})(window);
