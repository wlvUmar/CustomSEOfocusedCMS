let performanceChartInstance = null;
const CHART_COLORS = {
    visits: '#3b82f6',
    clicks: '#10b981',
    phones: '#f59e0b',
    ctr: '#8b5cf6'
};

function getQueryParam(name) {
    try {
        if (typeof URLSearchParams !== 'undefined') {
            return new URLSearchParams(window.location.search).get(name) || '';
        }
    } catch (e) {}
    const query = window.location.search ? window.location.search.substring(1) : '';
    if (!query) return '';
    const pairs = query.split('&');
    for (let i = 0; i < pairs.length; i++) {
        const part = pairs[i].split('=');
        if (decodeURIComponent(part[0] || '') === name) {
            return decodeURIComponent(part[1] || '');
        }
    }
    return '';
}

function getRangeFilter() {
    return getQueryParam('range');
}

document.addEventListener('DOMContentLoaded', function () {
    const performanceCanvas = document.getElementById('performanceChart');
    if (performanceCanvas && typeof Chart !== 'undefined') {
        initPerformanceChart();
        setupScorecardToggles();
    }
    const aggSelect = document.getElementById('aggregationSelect');
    if (aggSelect) {
        aggSelect.style.display = getRangeFilter() ? 'none' : '';
    }
    if (performanceChartInstance) {
        setTimeout(() => performanceChartInstance.resize(), 100);
    }
});

window.addEventListener('resize', () => {
    if (performanceChartInstance) {
        performanceChartInstance.resize();
    }
});

function initPerformanceChart() {
    const canvas = document.getElementById('performanceChart');
    if (!canvas) return;

    const data = window.performanceChartData || { labels: [], visits: [], clicks: [], phones: [] };

    const ctrData = data.visits.map((v, i) => {
        const phones = (data.phones[i] || 0);
        return v > 0 ? parseFloat(((phones / v) * 100).toFixed(2)) : 0;
    });

    performanceChartInstance = new Chart(canvas, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [
                {
                    label: 'Визиты',
                    data: data.visits,
                    borderColor: CHART_COLORS.visits,
                    backgroundColor: CHART_COLORS.visits + '10',
                    borderWidth: 2,
                    tension: 0.4,
                    yAxisID: 'y'
                },
                {
                    label: 'Клики',
                    data: data.clicks,
                    borderColor: CHART_COLORS.clicks,
                    backgroundColor: CHART_COLORS.clicks + '10',
                    borderWidth: 2,
                    tension: 0.4,
                    yAxisID: 'y'
                },
                {
                    label: 'Звонки',
                    data: data.phones || [],
                    borderColor: CHART_COLORS.phones,
                    backgroundColor: CHART_COLORS.phones + '10',
                    borderWidth: 2,
                    tension: 0.4,
                    yAxisID: 'y'
                },
                {
                    label: 'CTR',
                    data: ctrData,
                    borderColor: CHART_COLORS.ctr,
                    backgroundColor: 'transparent',
                    borderWidth: 2,
                    borderDash: [5, 5],
                    tension: 0.4,
                    yAxisID: 'y1',
                    hidden: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1e293b',
                    padding: 12,
                    callbacks: {
                        label: function (context) {
                            let label = context.dataset.label || '';
                            if (label) label += ': ';
                            if (context.dataset.yAxisID === 'y1') {
                                label += context.parsed.y + '%';
                            } else {
                                label += context.parsed.y.toLocaleString();
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    beginAtZero: true,
                    title: { display: true, text: 'Volume' }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    beginAtZero: true,
                    grid: { drawOnChartArea: false },
                    title: { display: true, text: 'CTR %' }
                }
            }
        }
    });

    syncTogglesFromChart();
}

function setupScorecardToggles() {
    document.querySelectorAll('.performance-scorecard').forEach((card, index) => {
        card.addEventListener('click', () => {
            const isHidden = !performanceChartInstance.isDatasetVisible(index);
            performanceChartInstance.setDatasetVisibility(index, isHidden);
            performanceChartInstance.update();
            card.classList.toggle('active', isHidden);
        });
    });
}

function syncTogglesFromChart() {
    document.querySelectorAll('.performance-scorecard').forEach((card, index) => {
        const isVisible = performanceChartInstance.isDatasetVisible(index);
        card.classList.toggle('active', isVisible);
    });
}

function formatNumber(n) {
    return Number(n).toLocaleString();
}

function roundOne(n) {
    return Math.round(Number(n) * 10) / 10;
}

function updateScorecards(data) {
    const total = data.total || {};
    const trends = data.trends || {};
    const currentChanges = trends.changes || {};

    const totalVisits = Number(total.total_visits || 0);
    const totalClicks = Number(total.total_clicks || 0);
    const totalPhones = Number(total.total_phone_calls || 0);
    const overallCtr = totalVisits > 0 ? roundOne((totalPhones / totalVisits) * 100) : 0;

    const scValues = document.querySelectorAll('.performance-scorecard .sc-value');
    if (scValues.length >= 4) {
        scValues[0].textContent = formatNumber(totalVisits);
        scValues[1].textContent = formatNumber(totalClicks);
        scValues[2].textContent = formatNumber(totalPhones);
        scValues[3].textContent = overallCtr + '%';
    }

    const scChanges = document.querySelectorAll('.performance-scorecard .sc-change');
    const metrics = ['visits', 'clicks', 'phone_calls', 'ctr'];
    scChanges.forEach((el, i) => {
        if (i >= metrics.length) return;
        const change = currentChanges[metrics[i]];
        if (change !== undefined) {
            el.innerHTML = `<i data-feather="${change >= 0 ? 'trending-up' : 'trending-down'}" style="width: 12px; height: 12px;"></i> ${Math.abs(change)}%`;
            el.className = 'sc-change ' + (change >= 0 ? 'positive' : 'negative');
        }
    });
    try { if (typeof feather !== 'undefined') feather.replace(); } catch (e) {}
}

function updateChartFromResponse(data) {
    if (!performanceChartInstance) return;
    const visits = data.visits || {};
    const clicks = data.clicks || {};
    const phones = data.phone_calls || {};

    const labels = Object.keys(visits);
    const visitsArr = Object.values(visits);
    const clicksArr = Object.values(clicks);
    const phonesArr = Object.values(phones);
    const ctrArr = visitsArr.map((v, i) => {
        const p = (phonesArr[i] || 0);
        return v > 0 ? parseFloat(((p / v) * 100).toFixed(2)) : 0;
    });

    performanceChartInstance.data.labels = labels;
    performanceChartInstance.data.datasets[0].data = visitsArr;
    performanceChartInstance.data.datasets[1].data = clicksArr;
    performanceChartInstance.data.datasets[2].data = phonesArr;
    performanceChartInstance.data.datasets[3].data = ctrArr;
    performanceChartInstance.update();
}

function updatePerformingPagesTable(data) {
    const table = document.getElementById('top-performers-table');
    if (!table) return;
    const tbody = table.querySelector('tbody');
    if (!tbody) return;

    const performers = data.top_performers || [];
    if (performers.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="padding: 40px; text-align: center; color: #94a3b8;">No data available yet</td></tr>';
        return;
    }

    const maxVisits = Math.max(...performers.map(p => Number(p.visits || 0)), 1);
    const maxClicks = Math.max(...performers.map(p => Number(p.clicks || 0)), 1);
    const maxPhones = Math.max(...performers.map(p => Number(p.phone_calls || 0)), 1);

    tbody.innerHTML = performers.map((page, index) => {
        const visits = Number(page.visits || 0);
        const clicks = Number(page.clicks || 0);
        const phoneCalls = Number(page.phone_calls || 0);
        const utmSource = page.utm_source || 'direct';
        const ctr = page.ctr !== undefined ? Number(page.ctr) : (visits > 0 ? roundOne((phoneCalls / visits) * 100) : 0);
        const visitsWidth = (visits / maxVisits) * 100;
        const clicksWidth = (clicks / maxClicks) * 100;
        const phonesWidth = (phoneCalls / maxPhones) * 100;
        const ctrColor = ctr >= 5 ? '#d1fae5' : (ctr >= 2 ? '#fef3c7' : '#fee2e2');
        const ctrTextColor = ctr >= 5 ? '#059669' : (ctr >= 2 ? '#d97706' : '#dc2626');

        return `<tr
            data-page-slug="${escapeHtml(page.page_slug || '')}"
            data-utm-source="${escapeHtml(utmSource)}"
            data-visits="${visits}"
            data-clicks="${clicks}"
            data-phone-calls="${phoneCalls}"
            data-ctr="${ctr}"
            style="background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);"
        >
            <td data-rank-cell style="padding: 16px; border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9; border-left: 1px solid #f1f5f9; border-top-left-radius: 8px; border-bottom-left-radius: 8px; font-weight: 600; color: #94a3b8; font-size: 14px;">${index + 1}</td>
            <td style="padding: 16px; border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: #1e293b; font-weight: 500;">
                <div style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${escapeHtml(page.page_slug || '')}">${escapeHtml(truncateSlug(page.page_slug))}</div>
            </td>
            <td data-label="Visits" style="padding: 16px; border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9;">
                <div style="text-align: right; margin-bottom: 4px; font-size: 14px; font-weight: 600; color: #3b82f6;">${formatNumber(visits)}</div>
                <div style="background: #e0e7ff; height: 6px; border-radius: 3px; overflow: hidden;"><div style="background: #3b82f6; height: 100%; width: ${visitsWidth}%; border-radius: 3px;"></div></div>
            </td>
            <td data-label="Clicks" style="padding: 16px; border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9;">
                <div style="text-align: right; margin-bottom: 4px; font-size: 14px; font-weight: 600; color: #10b981;">${formatNumber(clicks)}</div>
                <div style="background: #d1fae5; height: 6px; border-radius: 3px; overflow: hidden;"><div style="background: #10b981; height: 100%; width: ${clicksWidth}%; border-radius: 3px;"></div></div>
            </td>
            <td data-label="Phone Calls" style="padding: 16px; border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9;">
                <div style="text-align: right; margin-bottom: 4px; font-size: 14px; font-weight: 600; color: #f59e0b;">${formatNumber(phoneCalls)}</div>
                <div style="background: #fef3c7; height: 6px; border-radius: 3px; overflow: hidden;"><div style="background: #f59e0b; height: 100%; width: ${phonesWidth}%; border-radius: 3px;"></div></div>
            </td>
            <td data-label="CTR" style="padding: 16px; border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9; border-right: 1px solid #f1f5f9; border-top-right-radius: 8px; border-bottom-right-radius: 8px; text-align: center;">
                <span style="display: inline-block; padding: 4px 12px; background: ${ctrColor}; color: ${ctrTextColor}; border-radius: 12px; font-size: 13px; font-weight: 600;">${ctr}%</span>
            </td>
        </tr>`;
    }).join('');

    // Re-attach sort event listeners
    attachSortListeners(tbody);
}

function attachSortListeners(tbody) {
    const sortableHeaders = document.querySelectorAll('#top-performers-table thead [data-sort-key]');
    const sortState = { key: '', dir: 'desc' };

    const getValue = (row, key) => {
        if (key === 'page_slug') return String(row.dataset.pageSlug || '').toLowerCase();
        if (key === 'utm_source') return String(row.dataset.utmSource || '').toLowerCase();
        if (key === 'visits') return Number(row.dataset.visits || 0);
        if (key === 'clicks') return Number(row.dataset.clicks || 0);
        if (key === 'phone_calls') return Number(row.dataset.phoneCalls || 0);
        if (key === 'ctr') return Number(row.dataset.ctr || 0);
        return 0;
    };

    const updateIndicators = () => {
        sortableHeaders.forEach((header) => {
            const indicator = header.querySelector('.sort-indicator');
            if (!indicator) return;
            indicator.textContent = header.dataset.sortKey === sortState.key ? (sortState.dir === 'asc' ? '▲' : '▼') : '↕';
        });
    };

    const applySort = () => {
        const rows = Array.from(tbody.querySelectorAll('tr'));
        rows.sort((a, b) => {
            const aVal = getValue(a, sortState.key);
            const bVal = getValue(b, sortState.key);
            if (typeof aVal === 'string') {
                const cmp = aVal.localeCompare(bVal);
                return sortState.dir === 'asc' ? cmp : -cmp;
            }
            return sortState.dir === 'asc' ? aVal - bVal : bVal - aVal;
        });
        rows.forEach((row, index) => {
            const rankCell = row.querySelector('[data-rank-cell]');
            if (rankCell) rankCell.textContent = String(index + 1);
            tbody.appendChild(row);
        });
        updateIndicators();
    };

    sortableHeaders.forEach((header) => {
        header.removeEventListener('click', applySort);
        header.addEventListener('click', function () {
            const key = this.dataset.sortKey;
            if (!key) return;
            if (sortState.key === key) {
                sortState.dir = sortState.dir === 'asc' ? 'desc' : 'asc';
            } else {
                sortState.key = key;
                sortState.dir = key === 'page_slug' || key === 'utm_source' ? 'asc' : 'desc';
            }
            applySort();
        });
    });
}

function updateUtmStatsTable(data) {
    const container = document.getElementById('utmStatsContainer');
    if (!container) return;
    const utmStats = data.utm_stats || [];

    if (utmStats.length === 0) {
        container.style.display = 'none';
        return;
    }
    container.style.display = '';

    const tbody = document.getElementById('utmStatsBody');
    if (!tbody) return;

    const totalClicks = utmStats.reduce((s, r) => s + Number(r.clicks || 0), 0);
    const totalCalls = utmStats.reduce((s, r) => s + Number(r.phone_calls || 0), 0);
    const maxActions = Math.max(...utmStats.map(s => Number(s.clicks || 0) + Number(s.phone_calls || 0)), 1);

    tbody.innerHTML = utmStats.map(source => {
        const clicks = Number(source.clicks || 0);
        const calls = Number(source.phone_calls || 0);
        const total = clicks + calls;
        const pages = Number(source.pages_affected || 0);
        const width = (total / maxActions) * 100;
        const utmName = source.utm_source || 'direct';

        return `<tr style="background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <td style="padding: 16px; border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9; border-left: 1px solid #f1f5f9; border-top-left-radius: 8px; border-bottom-left-radius: 8px; font-weight: 600; color: #1e293b;">${escapeHtml(utmName)}</td>
            <td style="padding: 16px; border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9; text-align: center;">
                <span style="display: inline-block; padding: 4px 12px; background: #d1fae5; color: #059669; border-radius: 6px; font-size: 13px; font-weight: 600;">${formatNumber(clicks)}</span>
            </td>
            <td style="padding: 16px; border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9; text-align: center;">
                <span style="display: inline-block; padding: 4px 12px; background: #fef3c7; color: #d97706; border-radius: 6px; font-size: 13px; font-weight: 600;">${formatNumber(calls)}</span>
            </td>
            <td style="padding: 16px; border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9;">
                <div style="text-align: right; margin-bottom: 4px; font-size: 14px; font-weight: 600; color: #0284c7;">${formatNumber(total)}</div>
                <div style="background: #cffafe; height: 6px; border-radius: 3px; overflow: hidden;"><div style="background: #0284c7; height: 100%; width: ${width}%; border-radius: 3px;"></div></div>
            </td>
            <td style="padding: 16px; border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9; border-right: 1px solid #f1f5f9; border-top-right-radius: 8px; border-bottom-right-radius: 8px; text-align: center;">
                <span style="display: inline-block; padding: 4px 12px; background: #f3e8ff; color: #7c3aed; border-radius: 6px; font-size: 13px; font-weight: 600;">${formatNumber(pages)}</span>
            </td>
        </tr>`;
    }).join('');
}

function updateSlugDropdown(allSlugs) {
    const select = document.getElementById('pageSlugFilter');
    if (!select) return;
    const currentValue = select.value;
    select.innerHTML = '<option value="">All pages</option>' +
        (allSlugs || []).map(s => `<option value="${escapeHtml(s)}" ${currentValue === s ? 'selected' : ''}>${escapeHtml(s)}</option>`).join('');
}

function updateUtmDropdown(allUtmSources) {
    const select = document.getElementById('utmSourceFilter');
    if (!select) return;
    const currentValue = select.value;
    const sorted = (allUtmSources || []).sort();
    let html = '<option value="">All UTM Sources</option>';
    html += '<option value="direct" ' + (currentValue === 'direct' ? 'selected' : '') + '>Direct</option>';
    sorted.forEach(name => {
        if (name === 'direct') return;
        html += `<option value="${escapeHtml(name)}" ${currentValue === name ? 'selected' : ''}>${escapeHtml(name)}</option>`;
    });
    select.innerHTML = html;
}

function truncateSlug(slug, maxLen) {
    if (!slug) return '';
    maxLen = maxLen || 12;
    return slug.length > maxLen ? slug.substring(0, maxLen) + '...' : slug;
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
}

// Main dashboard update function
window.updateDashboard = async function (aggregation) {
    const rangeFilter = getRangeFilter();
    const aggSelect = document.getElementById('aggregationSelect');

    if (aggSelect) {
        aggSelect.style.display = rangeFilter ? 'none' : '';
    }

    if (rangeFilter) {
        aggregation = 'daily';
    } else {
        if (aggSelect) {
            if (!aggregation) {
                aggregation = aggSelect.value;
            } else {
                aggSelect.value = aggregation;
            }
        } else {
            aggregation = aggregation || 'monthly';
        }
    }

    const months = getQueryParam('months') || 6;
    const rangeParam = rangeFilter ? `&range=${encodeURIComponent(rangeFilter)}` : '';
    const slug = document.getElementById('pageSlugFilter') ? document.getElementById('pageSlugFilter').value : '';
    const utmSource = document.getElementById('utmSourceFilter') ? document.getElementById('utmSourceFilter').value : '';

    try {
        const url = `${window.baseUrl}/admin/analytics/getData?months=${months}&aggregation=${aggregation}${rangeParam}&slug=${encodeURIComponent(slug)}&utm_source=${encodeURIComponent(utmSource)}`;
        const response = await fetch(url, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await response.json();

        updateScorecards(data);
        updateChartFromResponse(data);
        updatePerformingPagesTable(data);
        updateUtmStatsTable(data);
        updateSlugDropdown(data.all_page_slugs);
        updateUtmDropdown(data.all_utm_sources);
    } catch (error) {
        console.error('Error fetching dashboard data:', error);
    }
};

// Extracted from views/admin/analytics/index.php — filter fns + table sort + debug.
// Chart data arrives via window.performanceChartData (PHP data-boot kept in template).

// Filter navigation
function updateAnalyticsFilters() {
    const selection = document.getElementById('periodSelect').value;
    const params = new URLSearchParams(window.location.search);

    if (selection.startsWith('range:')) {
        params.set('range', selection.replace('range:', ''));
        params.delete('months');
    } else if (selection.startsWith('months:')) {
        params.set('months', selection.replace('months:', ''));
        params.delete('range');
    }

    const slug = document.getElementById('pageSlugFilter');
    const utm = document.getElementById('utmSourceFilter');
    if (slug && slug.value) params.set('slug', slug.value); else params.delete('slug');
    if (utm && utm.value) params.set('utm_source', utm.value); else params.delete('utm_source');

    history.pushState(null, '', '?' + params.toString());
    updateDashboard();
}

function updateSlugUtmFilters() {
    const params = new URLSearchParams(window.location.search);
    const slug = document.getElementById('pageSlugFilter');
    const utm = document.getElementById('utmSourceFilter');
    if (slug && slug.value) params.set('slug', slug.value); else params.delete('slug');
    if (utm && utm.value) params.set('utm_source', utm.value); else params.delete('utm_source');
    history.pushState(null, '', '?' + params.toString());
    updateDashboard();
}

// Debug + initial table sort
// Chart update function is now defined in analytics.js

if (window.DEBUG) {
    console.log('=== ANALYTICS DATA DEBUG ===');
    console.log('Performance data:', window.performanceChartData);
    console.log('=== END DATA DEBUG ===');
}

// Update debug panel (only if DEBUG enabled)
if (window.DEBUG) {
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            const chartJsEl = document.getElementById('debug-chartjs');
            const performanceEl = document.getElementById('debug-visits');
            const canvasEl = document.getElementById('debug-canvas');
            
            if (chartJsEl) chartJsEl.textContent = (typeof Chart !== 'undefined') ? '✓ YES' : '✗ NO';
            if (performanceEl) {
                performanceEl.parentElement.querySelector('strong').textContent = 'Performance Data:';
                performanceEl.textContent = (window.performanceChartData && window.performanceChartData.labels.length > 0) 
                    ? '✓ ' + window.performanceChartData.labels.length + ' data points'
                    : '✗ No data';
            }
            
            const clicksEl = document.getElementById('debug-clicks');
            if (clicksEl) clicksEl.parentElement.style.display = 'none'; // Hide old clicks check
            
            const performanceCanvas = !!document.getElementById('performanceChart');
            const topPagesCanvas = !!document.getElementById('topPagesChart');
            if (canvasEl) canvasEl.textContent = (performanceCanvas && topPagesCanvas) ? '✓ Both found' : '✗ Missing: ' + 
                (!performanceCanvas ? 'performance' : '') + 
                (!topPagesCanvas ? ' topPages' : '');
        }, 100);
    });
}

// Index filter bindings (replaces inline onchange= in analytics/index.php).
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.c-fill[data-w]').forEach(function (el) {
        el.style.width = el.dataset.w + '%';
    });
    var period = document.getElementById('periodSelect');
    if (period) period.addEventListener('change', updateAnalyticsFilters);
    var slug = document.getElementById('pageSlugFilter');
    if (slug) slug.addEventListener('change', updateSlugUtmFilters);
    var utm = document.getElementById('utmSourceFilter');
    if (utm) utm.addEventListener('change', updateSlugUtmFilters);
    var agg = document.getElementById('aggregationSelect');
    if (agg) agg.addEventListener('change', function () { updateDashboard(this.value); });
});

// Rotation module (replaces inline <script> in analytics/rotation.php).
// Chart data arrives via #rotationBoot JSON (PHP data-boot kept in template).
var rotationPagesData = {};
var rotationAggregation = 'daily';
var rotationMonths = 1;
var rotationChart = null;

function rotationFormatDate(dateStr, aggregation) {
    var date = new Date(dateStr);
    if (aggregation === 'daily') return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    if (aggregation === 'weekly') return 'W' + Math.ceil(date.getDate() / 7) + ' ' + date.toLocaleDateString('en-US', { month: 'short' });
    if (aggregation === 'monthly') return date.toLocaleDateString('en-US', { month: 'short', year: '2-digit' });
    return dateStr;
}

function updateChartPage(pageSlug) {
    if (!pageSlug || !rotationPagesData[rotationAggregation] || !rotationPagesData[rotationAggregation][pageSlug]) return;
    var data = rotationPagesData[rotationAggregation][pageSlug];
    if (rotationChart) {
        rotationChart.data.labels = data.dates.map(function (d) { return rotationFormatDate(d, rotationAggregation); });
        rotationChart.data.datasets[0].data = data.visits;
        rotationChart.data.datasets[1].data = data.clicks;
        rotationChart.data.datasets[2].data = data.calls;
        rotationChart.data.datasets[3].data = data.ctr;
        rotationChart.update();
    }
}

function updateAggregation(aggregation) {
    rotationAggregation = aggregation;
    var pageSelector = document.getElementById('pageSelector');
    var selectedPage = pageSelector ? pageSelector.value : '';
    if (selectedPage) {
        updateChartPage(selectedPage);
    } else {
        var firstPage = Object.keys(rotationPagesData[aggregation] || {})[0];
        if (firstPage && pageSelector) {
            pageSelector.value = firstPage;
            updateChartPage(firstPage);
        }
    }
}

function updateMonthsFilter(months) {
    window.location.href = window.location.pathname + '?months=' + encodeURIComponent(months);
}

document.addEventListener('DOMContentLoaded', function () {
    var boot = document.getElementById('rotationBoot');
    if (!boot) return;
    try {
        var parsed = JSON.parse(boot.textContent);
        rotationPagesData = parsed.data || {};
        rotationMonths = parsed.months || 1;
    } catch (e) { return; }
    var pageSelector = document.getElementById('pageSelector');
    if (pageSelector) pageSelector.addEventListener('change', function () { updateChartPage(this.value); });
    var aggSelector = document.getElementById('aggregationSelector');
    if (aggSelector) aggSelector.addEventListener('change', function () { updateAggregation(this.value); });
    var monthsSelector = document.getElementById('monthsSelect');
    if (monthsSelector) monthsSelector.addEventListener('change', function () { updateMonthsFilter(this.value); });
    var firstPageKey = Object.keys(rotationPagesData.daily || {})[0];
    var firstData = firstPageKey ? rotationPagesData.daily[firstPageKey] : null;
    if (!firstData) return;
    var ctx = document.getElementById('mainChart');
    if (ctx && typeof Chart !== 'undefined') {
        rotationChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: firstData.dates.map(function (d) { return rotationFormatDate(d, 'daily'); }),
                datasets: [
                    { label: 'Визиты', data: firstData.visits, borderColor: '#3b82f6', backgroundColor: '#3b82f610', tension: 0.4, yAxisID: 'y', fill: true },
                    { label: 'Клики', data: firstData.clicks, borderColor: '#10b981', backgroundColor: '#10b98110', tension: 0.4, yAxisID: 'y', fill: false, borderWidth: 2 },
                    { label: 'Звонки', data: firstData.calls, borderColor: '#ef4444', backgroundColor: '#ef444410', tension: 0.4, yAxisID: 'y', fill: false, borderWidth: 2 },
                    { label: 'CTR %', data: firstData.ctr, borderColor: '#f59e0b', backgroundColor: 'transparent', borderWidth: 2, tension: 0.4, yAxisID: 'y1', pointRadius: 4, pointBackgroundColor: '#f59e0b' }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { display: true, position: 'top', labels: { font: { size: 12 }, boxWidth: 14 } } },
                scales: {
                    x: { ticks: { maxRotation: 45, minRotation: 0 } },
                    y: { type: 'linear', display: true, position: 'left', title: { display: true, text: 'Количество', font: { size: 12 } } },
                    y1: { type: 'linear', display: true, position: 'right', title: { display: true, text: 'CTR %', font: { size: 12 } }, grid: { drawOnChartArea: false } }
                }
            }
        });
        if (pageSelector) pageSelector.value = firstPageKey;
    }
});

// Crawl module (replaces inline <script> in analytics/crawl.php).
// Chart data arrives via #crawlBoot JSON (PHP data-boot kept in template).
function toggleCrawlDetails() {
    var el = document.getElementById('detailed-data');
    var arrow = document.getElementById('details-arrow');
    if (!el) return;
    var hidden = el.classList.toggle('c-hidden');
    var toggle = document.getElementById('crawlDetailsToggle');
    if (toggle) toggle.setAttribute('aria-expanded', hidden ? 'false' : 'true');
    if (arrow) arrow.setAttribute('data-feather', hidden ? 'chevron-down' : 'chevron-up');
    try { if (typeof feather !== 'undefined') feather.replace(); } catch (e) {}
}

document.addEventListener('DOMContentLoaded', function () {
    var boot = document.getElementById('crawlBoot');
    var days = document.getElementById('crawlDaysSelect');
    if (days) days.addEventListener('change', function () { window.location = '?days=' + this.value; });
    var toggle = document.getElementById('crawlDetailsToggle');
    if (toggle) {
        toggle.addEventListener('click', toggleCrawlDetails);
        toggle.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggleCrawlDetails(); }
        });
    }
    if (!boot || typeof Chart === 'undefined') return;
    var data;
    try { data = JSON.parse(boot.textContent); } catch (e) { return; }
    try { if (typeof feather !== 'undefined') feather.replace(); } catch (e) {}

    var activityCtx = document.getElementById('botActivityChart');
    if (activityCtx) {
        var dailyStats = data.dailyStats || [];
        var dates = Array.from(new Set(dailyStats.map(function (i) { return i.visit_date; })));
        var bots = Array.from(new Set(dailyStats.map(function (i) { return i.bot_type; })));
        var colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'];
        var datasets = bots.map(function (bot, index) {
            var hex = colors[index % colors.length];
            return {
                label: bot.charAt(0).toUpperCase() + bot.slice(1),
                data: dates.map(function (date) {
                    var entry = dailyStats.find(function (d) { return d.visit_date === date && d.bot_type === bot; });
                    return entry ? entry.visits : 0;
                }),
                borderColor: hex, borderWidth: 2, tension: 0.3, fill: true
            };
        });
        new Chart(activityCtx, {
            type: 'line',
            data: {
                labels: dates.map(function (d) { return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }); }),
                datasets: datasets
            },
            options: { responsive: true, interaction: { mode: 'index', intersect: false }, plugins: { legend: { position: 'top' } }, scales: { y: { beginAtZero: true } } }
        });
    }

    var distCtx = document.getElementById('botDistributionChart');
    if (distCtx) {
        var botSummary = data.botSummary || [];
        new Chart(distCtx, {
            type: 'doughnut',
            data: {
                labels: botSummary.map(function (b) { return b.bot_type.charAt(0).toUpperCase() + b.bot_type.slice(1); }),
                datasets: [{ data: botSummary.map(function (b) { return b.total_visits; }), backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'], borderWidth: 0 }]
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
        });
    }

    var topCtx = document.getElementById('topPagesChart');
    if (topCtx) {
        var topPages = data.topPages || [];
        new Chart(topCtx, {
            type: 'bar',
            data: {
                labels: topPages.map(function (p) { return '/' + p.page_slug; }),
                datasets: [{ label: 'Визиты ботов', data: topPages.map(function (p) { return p.total_visits; }), backgroundColor: '#3b82f6', borderRadius: 4 }]
            },
            options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } } }
        });
    }
});


document.addEventListener('DOMContentLoaded', function () {
    const table = document.getElementById('top-performers-table');
    if (!table) return;

    const tbody = table.querySelector('tbody');
    const sortableHeaders = table.querySelectorAll('thead [data-sort-key]');
    if (!tbody || !sortableHeaders.length) return;

    const sortState = { key: '', dir: 'desc' };

    const getValue = (row, key) => {
        if (key === 'page_slug') return String(row.dataset.pageSlug || '').toLowerCase();
        if (key === 'utm_source') return String(row.dataset.utmSource || '').toLowerCase();
        if (key === 'visits') return Number(row.dataset.visits || 0);
        if (key === 'clicks') return Number(row.dataset.clicks || 0);
        if (key === 'phone_calls') return Number(row.dataset.phoneCalls || 0);
        if (key === 'ctr') return Number(row.dataset.ctr || 0);
        return 0;
    };

    const updateIndicators = () => {
        sortableHeaders.forEach((header) => {
            const indicator = header.querySelector('.sort-indicator');
            if (!indicator) return;
            if (header.dataset.sortKey === sortState.key) {
                indicator.textContent = sortState.dir === 'asc' ? '▲' : '▼';
            } else {
                indicator.textContent = '↕';
            }
        });
    };

    const applySort = () => {
        const rows = Array.from(tbody.querySelectorAll('tr'));
        rows.sort((a, b) => {
            const aVal = getValue(a, sortState.key);
            const bVal = getValue(b, sortState.key);

            if (typeof aVal === 'string' && typeof bVal === 'string') {
                const cmp = aVal.localeCompare(bVal);
                return sortState.dir === 'asc' ? cmp : -cmp;
            }

            const cmp = aVal - bVal;
            return sortState.dir === 'asc' ? cmp : -cmp;
        });

        rows.forEach((row, index) => {
            const rankCell = row.querySelector('[data-rank-cell]');
            if (rankCell) rankCell.textContent = String(index + 1);
            tbody.appendChild(row);
        });

        updateIndicators();
    };

    sortableHeaders.forEach((header) => {
        header.addEventListener('click', function () {
            const key = this.dataset.sortKey;
            if (!key) return;

            if (sortState.key === key) {
                sortState.dir = sortState.dir === 'asc' ? 'desc' : 'asc';
            } else {
                sortState.key = key;
                sortState.dir = key === 'page_slug' || key === 'utm_source' ? 'asc' : 'desc';
            }

            applySort();
        });
    });
});


