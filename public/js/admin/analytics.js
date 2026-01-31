// Analytics Charts JavaScript
// Renders Chart.js charts for analytics dashboard (GSC-style)

let performanceChartInstance = null;
const CHART_COLORS = {
    visits: '#3b82f6',
    clicks: '#10b981',
    phones: '#f59e0b',
    ctr: '#8b5cf6'
};
const RANGE_FILTER = new URLSearchParams(window.location.search).get('range') || '';

document.addEventListener('DOMContentLoaded', function () {
    const performanceCanvas = document.getElementById('performanceChart');
    if (performanceCanvas && typeof Chart !== 'undefined') {
        initPerformanceChart();
        setupScorecardToggles();
    }

    if (RANGE_FILTER) {
        const weeklyBtn = document.getElementById('btn-weekly');
        const monthlyBtn = document.getElementById('btn-monthly');
        if (weeklyBtn) { weeklyBtn.disabled = true; weeklyBtn.style.opacity = '0.5'; weeklyBtn.style.cursor = 'not-allowed'; }
        if (monthlyBtn) { monthlyBtn.disabled = true; monthlyBtn.style.opacity = '0.5'; monthlyBtn.style.cursor = 'not-allowed'; }
    }
});

function initPerformanceChart() {
    const canvas = document.getElementById('performanceChart');
    if (!canvas) return;

    const data = window.performanceChartData || { labels: [], visits: [], clicks: [], phones: [] };

    // Calculate CTR array
    const ctrData = data.visits.map((v, i) => {
        const c = (data.clicks[i] || 0) + (data.phones[i] || 0);
        return v > 0 ? parseFloat(((c / v) * 100).toFixed(2)) : 0;
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
                    hidden: true // Hidden by default like GSC
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false }, // Use custom scorecards instead
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

    // Initial sync with active toggles
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

// Override global updatePerformanceChart to handle multiple datasets
const originalUpdateChart = window.updatePerformanceChart;
window.updatePerformanceChart = async function (aggregation) {
    if (RANGE_FILTER && aggregation !== 'daily') {
        aggregation = 'daily';
    }

    // Call UI update part of original if it was specific
    document.querySelectorAll('.agg-toggle-btn').forEach(btn => {
        btn.style.background = 'white'; btn.style.color = '#64748b'; btn.style.borderColor = '#e2e8f0';
    });
    const activeBtn = document.getElementById(`btn-${aggregation}`);
    if (activeBtn) {
        activeBtn.style.background = '#3b82f6'; activeBtn.style.color = 'white'; activeBtn.style.borderColor = '#3b82f6';
    }

    try {
        const months = new URLSearchParams(window.location.search).get('months') || 6;
        const rangeParam = RANGE_FILTER ? `&range=${encodeURIComponent(RANGE_FILTER)}` : '';
        const response = await fetch(`${window.baseUrl}/admin/analytics/getData?months=${months}&aggregation=${aggregation}${rangeParam}`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await response.json();

        if (performanceChartInstance && data.visits) {
            performanceChartInstance.data.labels = Object.keys(data.visits);
            performanceChartInstance.data.datasets[0].data = Object.values(data.visits);
            performanceChartInstance.data.datasets[1].data = Object.values(data.clicks);
            performanceChartInstance.data.datasets[2].data = Object.values(data.phone_calls);

            // Recalculate CTR
            const visitsArr = Object.values(data.visits);
            const clicksArr = Object.values(data.clicks);
            const phonesArr = Object.values(data.phone_calls);
            const newCtr = visitsArr.map((v, i) => {
                const c = (clicksArr[i] || 0) + (phonesArr[i] || 0);
                return v > 0 ? parseFloat(((c / v) * 100).toFixed(2)) : 0;
            });
            performanceChartInstance.data.datasets[3].data = newCtr;

            performanceChartInstance.update();
        }
    } catch (error) {
        console.error('Error fetching chart data:', error);
    }
};
