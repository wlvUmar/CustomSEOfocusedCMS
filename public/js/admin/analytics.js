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
    const range = getRangeFilter();
    if (range) {
        const weeklyBtn = document.getElementById('btn-weekly');
        const monthlyBtn = document.getElementById('btn-monthly');
        if (weeklyBtn) { weeklyBtn.disabled = true; weeklyBtn.style.opacity = '0.5'; weeklyBtn.style.cursor = 'not-allowed'; }
        if (monthlyBtn) { monthlyBtn.disabled = true; monthlyBtn.style.opacity = '0.5'; monthlyBtn.style.cursor = 'not-allowed'; }
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
                    label: 'Visits',
                    data: data.visits,
                    borderColor: CHART_COLORS.visits,
                    backgroundColor: CHART_COLORS.visits + '10',
                    borderWidth: 2,
                    tension: 0.4,
                    yAxisID: 'y'
                },
                {
                    label: 'Clicks',
                    data: data.clicks,
                    borderColor: CHART_COLORS.clicks,
                    backgroundColor: CHART_COLORS.clicks + '10',
                    borderWidth: 2,
                    tension: 0.4,
                    yAxisID: 'y'
                },
                {
                    label: 'Phone Calls',
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
    const metrics = ['visits', 'clicks', 'phone_calls'];
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
        tbody.innerHTML = '<tr><td colspan="7" style="padding: 40px; text-align: center; color: #94a3b8;">No data available yet</td></tr>';
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
                <div style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${escapeHtml(page.page_slug || '')}">${escapeHtml(page.page_slug || '')}</div>
            </td>
            <td style="padding: 16px; border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: #1e293b; font-weight: 500;">
                <span style="display: inline-block; padding: 4px 8px; background: #f0f9ff; color: #0284c7; border-radius: 6px; font-size: 12px; font-weight: 600;">${escapeHtml(utmSource)}</span>
            </td>
            <td style="padding: 16px; border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9;">
                <div style="text-align: right; margin-bottom: 4px; font-size: 14px; font-weight: 600; color: #3b82f6;">${formatNumber(visits)}</div>
                <div style="background: #e0e7ff; height: 6px; border-radius: 3px; overflow: hidden;"><div style="background: #3b82f6; height: 100%; width: ${visitsWidth}%; border-radius: 3px;"></div></div>
            </td>
            <td style="padding: 16px; border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9;">
                <div style="text-align: right; margin-bottom: 4px; font-size: 14px; font-weight: 600; color: #10b981;">${formatNumber(clicks)}</div>
                <div style="background: #d1fae5; height: 6px; border-radius: 3px; overflow: hidden;"><div style="background: #10b981; height: 100%; width: ${clicksWidth}%; border-radius: 3px;"></div></div>
            </td>
            <td style="padding: 16px; border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9;">
                <div style="text-align: right; margin-bottom: 4px; font-size: 14px; font-weight: 600; color: #f59e0b;">${formatNumber(phoneCalls)}</div>
                <div style="background: #fef3c7; height: 6px; border-radius: 3px; overflow: hidden;"><div style="background: #f59e0b; height: 100%; width: ${phonesWidth}%; border-radius: 3px;"></div></div>
            </td>
            <td style="padding: 16px; border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9; border-right: 1px solid #f1f5f9; border-top-right-radius: 8px; border-bottom-right-radius: 8px; text-align: center;">
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

function updateSlugDropdown(pageStats) {
    const select = document.getElementById('pageSlugFilter');
    if (!select) return;
    const currentValue = select.value;
    const slugs = {};
    (pageStats || []).forEach(ps => { if (ps.page_slug) slugs[ps.page_slug] = true; });
    const sorted = Object.keys(slugs).sort();
    select.innerHTML = '<option value="">All pages</option>' +
        sorted.map(s => `<option value="${escapeHtml(s)}" ${currentValue === s ? 'selected' : ''}>${escapeHtml(s)}</option>`).join('');
}

function updateUtmDropdown(utmStats) {
    const select = document.getElementById('utmSourceFilter');
    if (!select) return;
    const currentValue = select.value;
    const sources = {};
    (utmStats || []).forEach(s => {
        const name = s.utm_source || 'direct';
        sources[name] = true;
    });
    const sorted = Object.keys(sources).sort();
    let html = '<option value="">All UTM Sources</option>';
    html += '<option value="direct" ' + (currentValue === 'direct' ? 'selected' : '') + '>Direct</option>';
    sorted.forEach(name => {
        if (name === 'direct') return;
        html += `<option value="${escapeHtml(name)}" ${currentValue === name ? 'selected' : ''}>${escapeHtml(name)}</option>`;
    });
    select.innerHTML = html;
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
    if (!aggregation) {
        aggregation = rangeFilter ? 'daily' : (window.currentAggregation || 'monthly');
    }
    if (rangeFilter && aggregation !== 'daily') {
        return;
    }

    // Update aggregation button styles
    document.querySelectorAll('.agg-toggle-btn').forEach(btn => {
        btn.style.background = 'white'; btn.style.color = '#64748b'; btn.style.borderColor = '#e2e8f0';
    });
    const activeBtn = document.getElementById(`btn-${aggregation}`);
    if (activeBtn) {
        activeBtn.style.background = '#3b82f6'; activeBtn.style.color = 'white'; activeBtn.style.borderColor = '#3b82f6';
    }

    window.currentAggregation = aggregation;

    // Enable/disable aggregation buttons based on range mode
    const weeklyBtn = document.getElementById('btn-weekly');
    const monthlyBtn = document.getElementById('btn-monthly');
    if (weeklyBtn) { weeklyBtn.disabled = !!rangeFilter; weeklyBtn.style.opacity = rangeFilter ? '0.5' : ''; weeklyBtn.style.cursor = rangeFilter ? 'not-allowed' : ''; }
    if (monthlyBtn) { monthlyBtn.disabled = !!rangeFilter; monthlyBtn.style.opacity = rangeFilter ? '0.5' : ''; monthlyBtn.style.cursor = rangeFilter ? 'not-allowed' : ''; }

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
        updateSlugDropdown(data.page_stats);
        updateUtmDropdown(data.utm_stats);
    } catch (error) {
        console.error('Error fetching dashboard data:', error);
    }
};
