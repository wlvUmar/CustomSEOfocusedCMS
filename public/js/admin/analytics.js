// Analytics Charts JavaScript
// Renders Chart.js charts for analytics dashboard

// Global chart instance for updates
let performanceChartInstance = null;

document.addEventListener('DOMContentLoaded', function () {
    if (window.DEBUG) {
        console.log('=== ANALYTICS DEBUG ===');
        console.log('DOMContentLoaded fired');
        console.log('window.performanceChartData:', window.performanceChartData);
        console.log('Chart.js loaded?', typeof Chart !== 'undefined' ? 'YES' : 'NO');
    }

    const performanceCanvas = document.getElementById('performanceChart');

    if (window.DEBUG) {
        console.log('Canvas elements found?', {
            performanceElement: !!performanceCanvas
        });
    }

    // Initialize performance chart if canvas exists
    if (performanceCanvas && typeof Chart !== 'undefined') {
        if (window.DEBUG) console.log('Initializing performance chart...');
        try {
            initPerformanceChart();
            if (window.DEBUG) console.log('✓ Performance chart initialized successfully');
        } catch (e) {
            console.error('✗ Error initializing performance chart:', e);
        }
    } else {
        if (window.DEBUG) {
            if (!performanceCanvas) console.warn('performanceChart canvas element not found!');
            if (typeof Chart === 'undefined') console.warn('Chart.js not loaded!');
        }
    }

    if (window.DEBUG) console.log('=== END DEBUG ===');
});

function initPerformanceChart() {
    const canvas = document.getElementById('performanceChart');
    if (!canvas) return;

    let chartData = window.performanceChartData || {
        labels: [],
        visits: [],
        clicks: []
    };

    if (window.DEBUG) {
        console.log('Creating performance chart with:', chartData);
    }

    // Store chart instance globally for updates
    performanceChartInstance = new Chart(canvas, {
        type: 'line',
        data: {
            labels: chartData.labels || generateMonthLabels(6),
            datasets: [
                {
                    label: 'Visits',
                    data: chartData.visits || [],
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.05)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#3b82f6',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                },
                {
                    label: 'Clicks',
                    data: chartData.clicks || [],
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.05)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#10b981',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        color: '#64748b',
                        font: { size: 13, weight: '500' },
                        padding: 15,
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                },
                tooltip: {
                    enabled: true,
                    backgroundColor: '#1e293b',
                    titleColor: '#f1f5f9',
                    bodyColor: '#f1f5f9',
                    padding: 12,
                    cornerRadius: 8,
                    displayColors: true
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grace: '5%',
                    grid: {
                        color: '#e2e8f0',
                        drawBorder: false
                    },
                    ticks: {
                        color: '#64748b',
                        font: { size: 12 }
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: '#64748b',
                        font: { size: 12 }
                    }
                }
            }
        }
    });
}

/**
 * Generate month labels for the last N months
 */
function generateMonthLabels(monthsBack) {
    const labels = [];
    const now = new Date();

    for (let i = monthsBack - 1; i >= 0; i--) {
        const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
        const monthName = d.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
        labels.push(monthName);
    }

    return labels;
}
