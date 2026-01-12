// Analytics Charts JavaScript
// Renders Chart.js charts for analytics dashboard

document.addEventListener('DOMContentLoaded', function() {
    if (window.DEBUG) {
        console.log('=== ANALYTICS DEBUG ===');
        console.log('DOMContentLoaded fired');
        console.log('window.visitsChartData:', window.visitsChartData);
        console.log('window.clicksChartData:', window.clicksChartData);
        console.log('Chart.js loaded?', typeof Chart !== 'undefined' ? 'YES' : 'NO');
    }
    
    const visitsCanvas = document.getElementById('visitsChart');
    const clicksCanvas = document.getElementById('clicksChart');
    const heatmapCanvas = document.getElementById('heatmapChart');
    const topPagesCanvas = document.getElementById('topPagesChart');
    
    if (window.DEBUG) {
        console.log('Canvas elements found?', {
            visitsElement: !!visitsCanvas,
            clicksElement: !!clicksCanvas,
            heatmapElement: !!heatmapCanvas,
            topPagesElement: !!topPagesCanvas
        });
    }
    
    // Initialize visits chart if canvas exists
    if (visitsCanvas && typeof Chart !== 'undefined') {
        if (window.DEBUG) console.log('Initializing visits chart...');
        try {
            initVisitsChart();
            if (window.DEBUG) console.log('✓ Visits chart initialized successfully');
        } catch (e) {
            console.error('✗ Error initializing visits chart:', e);
        }
    } else {
        if (window.DEBUG) {
            if (!visitsCanvas) console.warn('visitsChart canvas element not found!');
            if (typeof Chart === 'undefined') console.warn('Chart.js not loaded!');
        }
    }
    
    // Initialize clicks chart if canvas exists
    if (clicksCanvas && typeof Chart !== 'undefined') {
        if (window.DEBUG) console.log('Initializing clicks chart...');
        try {
            initClicksChart();
            if (window.DEBUG) console.log('✓ Clicks chart initialized successfully');
        } catch (e) {
            console.error('✗ Error initializing clicks chart:', e);
        }
    } else {
        if (window.DEBUG) {
            if (!clicksCanvas) console.warn('clicksChart canvas element not found!');
            if (typeof Chart === 'undefined') console.warn('Chart.js not loaded!');
        }
    }
    
    // Initialize heatmap if canvas exists
    if (heatmapCanvas && typeof Chart !== 'undefined') {
        if (window.DEBUG) console.log('Initializing heatmap...');
        try {
            initHeatmap();
            if (window.DEBUG) console.log('✓ Heatmap initialized successfully');
        } catch (e) {
            console.error('✗ Error initializing heatmap:', e);
        }
    } else {
        if (window.DEBUG) {
            if (!heatmapCanvas) console.warn('heatmapChart canvas element not found!');
        }
    }

    // Initialize top pages chart if canvas exists
    if (topPagesCanvas && typeof Chart !== 'undefined') {
        if (window.DEBUG) console.log('Initializing top pages chart...');
        try {
            initTopPagesChart();
            if (window.DEBUG) console.log('✓ Top pages chart initialized successfully');
        } catch (e) {
            console.error('✗ Error initializing top pages chart:', e);
        }
    } else {
        if (window.DEBUG) {
            if (!topPagesCanvas) console.warn('topPagesChart canvas element not found!');
        }
    }
    
    if (window.DEBUG) console.log('=== END DEBUG ===');
});

function initVisitsChart() {
    const canvas = document.getElementById('visitsChart');
    if (!canvas) return;
    
    let chartData = {
        labels: [],
        data: []
    };
    
    if (window.visitsChartData) {
        chartData = window.visitsChartData;
        if (window.DEBUG) console.log('Using window.visitsChartData:', chartData);
    } else {
        if (window.DEBUG) console.warn('window.visitsChartData not found, using empty data');
    }
    
    new Chart(canvas, {
        type: 'line',
        data: {
            labels: chartData.labels || generateMonthLabels(6),
            datasets: [{
                label: 'Visits',
                data: chartData.data || [],
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#3b82f6',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: true,
                    labels: {
                        color: '#64748b',
                        font: { size: 14, weight: '500' }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#e2e8f0' },
                    ticks: { color: '#64748b' }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#64748b' }
                }
            }
        }
    });
}

function initClicksChart() {
    const canvas = document.getElementById('clicksChart');
    if (!canvas) return;
    
    let chartData = {
        labels: [],
        data: []
    };
    
    if (window.clicksChartData) {
        chartData = window.clicksChartData;
        if (window.DEBUG) console.log('Using window.clicksChartData:', chartData);
    } else {
        if (window.DEBUG) console.warn('window.clicksChartData not found, using empty data');
    }
    
    new Chart(canvas, {
        type: 'line',
        data: {
            labels: chartData.labels || generateMonthLabels(6),
            datasets: [{
                label: 'Clicks',
                data: chartData.data || [],
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#10b981',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: true,
                    labels: {
                        color: '#64748b',
                        font: { size: 14, weight: '500' }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#e2e8f0' },
                    ticks: { color: '#64748b' }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#64748b' }
                }
            }
        }
    });
}

function initHeatmap() {
    const canvas = document.getElementById('heatmapChart');
    if (!canvas) return;
    
    const hours = generateHourLabels();
    const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    
    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: hours,
            datasets: days.map((day, index) => ({
                label: day,
                data: hours.map(() => Math.floor(Math.random() * 100)),
                backgroundColor: [
                    '#3b82f6',
                    '#10b981',
                    '#f59e0b',
                    '#ef4444',
                    '#8b5cf6',
                    '#ec4899',
                    '#14b8a6'
                ][index % 7]
            }))
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: true,
                    labels: {
                        color: '#64748b',
                        font: { size: 12 }
                    }
                }
            },
            scales: {
                x: {
                    stacked: true,
                    grid: { color: '#e2e8f0' },
                    ticks: { color: '#64748b' }
                },
                y: {
                    stacked: true,
                    ticks: { color: '#64748b' }
                }
            }
        }
    });
}

function initTopPagesChart() {
    const canvas = document.getElementById('topPagesChart');
    if (!canvas) return;
    
    let topPagesData = window.topPagesChartData;
    if (!topPagesData || !topPagesData.labels || topPagesData.labels.length === 0) {
        if (window.DEBUG) console.warn('No top pages data available', topPagesData);
        return;
    }

    if (window.DEBUG) {
        console.log('Creating top pages chart with:', topPagesData);
    }
    
    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: topPagesData.labels,
            datasets: [
                {
                    label: 'Visits',
                    data: topPagesData.visits || [],
                    backgroundColor: '#3b82f6',
                    borderRadius: 4,
                    borderSkipped: false
                },
                {
                    label: 'Clicks',
                    data: topPagesData.clicks || [],
                    backgroundColor: '#10b981',
                    borderRadius: 4,
                    borderSkipped: false
                }
            ]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: true,
                    labels: {
                        color: '#64748b',
                        font: { size: 14, weight: '500' },
                        padding: 15
                    }
                },
                tooltip: {
                    enabled: true,
                    backgroundColor: '#1e293b',
                    padding: 10,
                    titleColor: '#f1f5f9',
                    bodyColor: '#f1f5f9'
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    grid: { color: '#e2e8f0', drawBorder: true },
                    ticks: { color: '#64748b' }
                },
                y: {
                    ticks: { color: '#64748b', font: { size: 12 } }
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
    const today = new Date();
    
    for (let i = monthsBack - 1; i >= 0; i--) {
        const date = new Date(today.getFullYear(), today.getMonth() - i, 1);
        const monthName = date.toLocaleString('default', { month: 'short', year: 'numeric' });
        labels.push(monthName);
    }
    
    return labels;
}

/**
 * Generate hour labels (0:00, 1:00, ..., 23:00)
 */
function generateHourLabels() {
    const labels = [];
    for (let i = 0; i < 24; i++) {
        const hour = i.toString().padStart(2, '0');
        labels.push(`${hour}:00`);
    }
    return labels;
}
