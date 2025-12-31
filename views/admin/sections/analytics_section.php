<?php 
// path: ./views/admin/sections/analytics_section.php
$pageName = 'analytics/index';
?>

<div class="section-page" id="analytics-section">
    <div class="page-header">
        <h1>Analytics Dashboard</h1>
        <div class="header-actions">
            <select id="analytics-timeframe" class="btn">
                <option value="3">Last 3 Months</option>
                <option value="6" selected>Last 6 Months</option>
                <option value="12">Last 12 Months</option>
            </select>
            
            <a href="<?= BASE_URL ?>/admin/analytics/export" class="btn btn-secondary">
                <i data-feather="download"></i> Export CSV
            </a>
        </div>
    </div>

    <!-- Section Tabs -->
    <div class="section-tabs">
        <button class="section-tab-btn active" data-tab="overview">
            <i data-feather="trending-up"></i> Overview
        </button>
        <button class="section-tab-btn" data-tab="rotation">
            <i data-feather="repeat"></i> Rotation Stats
        </button>
        <button class="section-tab-btn" data-tab="navigation">
            <i data-feather="git-branch"></i> Navigation Flow
        </button>
        <button class="section-tab-btn" data-tab="crawl">
            <i data-feather="zap"></i> Crawl Analysis
        </button>
    </div>

    <!-- Content Container -->
    <div id="analytics-content" class="section-content">
        <div class="loading">Loading...</div>
    </div>
</div>

<style>
.section-tabs {
    display: flex;
    gap: 5px;
    margin: 20px 0;
    border-bottom: 2px solid var(--accent-light);
}

.section-tab-btn {
    padding: 12px 20px;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 14px;
    color: var(--text-muted);
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.section-tab-btn:hover {
    color: var(--text-dark);
}

.section-tab-btn.active {
    color: var(--primary-dark);
    border-bottom-color: var(--primary-dark);
}

.section-content {
    min-height: 400px;
}

.loading {
    text-align: center;
    padding: 60px;
    color: var(--text-muted);
}
</style>

<script>
// Section navigation controller
const AnalyticsSection = {
    currentTab: 'overview',
    baseUrl: '<?= BASE_URL ?>/admin/analytics-section',
    
    init() {
        this.loadContent('overview');
        this.bindEvents();
    },
    
    bindEvents() {
        // Tab buttons
        document.querySelectorAll('.section-tab-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const tab = e.currentTarget.dataset.tab;
                this.switchTab(tab);
            });
        });
        
        // Timeframe selector
        const timeframeSelect = document.getElementById('analytics-timeframe');
        if (timeframeSelect) {
            timeframeSelect.addEventListener('change', () => {
                this.loadContent(this.currentTab);
            });
        }
    },
    
    switchTab(tab) {
        // Update UI
        document.querySelectorAll('.section-tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tab === tab);
        });
        
        this.currentTab = tab;
        this.loadContent(tab);
    },
    
    loadContent(tab) {
        const container = document.getElementById('analytics-content');
        container.innerHTML = '<div class="loading">Loading...</div>';
        
        let url = `${this.baseUrl}/${tab}-content`;
        
        // Add query parameters
        const params = new URLSearchParams();
        
        if (tab === 'overview' || tab === 'rotation' || tab === 'navigation') {
            const months = document.getElementById('analytics-timeframe')?.value || 6;
            params.append('months', months);
        } else if (tab === 'crawl') {
            params.append('days', 30);
        }
        
        if (params.toString()) {
            url += '?' + params.toString();
        }
        
        fetch(url)
            .then(response => response.text())
            .then(html => {
                container.innerHTML = html;
                
                // Re-initialize feather icons
                if (typeof feather !== 'undefined') {
                    feather.replace({ class: 'feather-icon' });
                }
                
                // Initialize charts if present
                if (tab === 'overview' && typeof Chart !== 'undefined') {
                    this.initializeCharts();
                }
            })
            .catch(error => {
                container.innerHTML = '<div class="alert alert-error">Failed to load content. Please try again.</div>';
                console.error('Error loading content:', error);
            });
    },
    
    initializeCharts() {
        // This will be called after content loads
        // Charts will initialize from script tags in the loaded content
    }
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    AnalyticsSection.init();
});
</script>

