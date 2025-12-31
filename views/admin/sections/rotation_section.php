<?php 
// path: ./views/admin/sections/rotation_section.php
$pageName = 'analytics/rotation ';
?>

<div class="section-page" id="rotation-section">
    <div class="page-header">
        <h1>Content Rotation Management</h1>
        <div class="header-actions">
            <a href="<?= BASE_URL ?>/admin/pages" class="btn btn-secondary">
                <i data-feather="arrow-left"></i> Back to Pages
            </a>
        </div>
    </div>

    <!-- Section Tabs -->
    <div class="section-tabs">
        <button class="section-tab-btn active" data-tab="overview">
            <i data-feather="list"></i> Overview
        </button>
        <div id="dynamic-tabs">
            <!-- Page-specific tabs will be added here dynamically -->
        </div>
    </div>

    <!-- Content Container -->
    <div id="rotation-content" class="section-content">
        <div class="loading">Loading...</div>
    </div>
</div>

<script>
// Rotation section navigation controller
const RotationSection = {
    currentTab: 'overview',
    baseUrl: '<?= BASE_URL ?>/admin/rotations-section',
    
    init() {
        this.loadContent('overview');
        this.bindEvents();
    },
    
    bindEvents() {
        // Tab buttons
        document.addEventListener('click', (e) => {
            if (e.target.closest('.section-tab-btn')) {
                const btn = e.target.closest('.section-tab-btn');
                const tab = btn.dataset.tab;
                const pageId = btn.dataset.pageId;
                
                this.switchTab(tab, pageId);
            }
        });
    },
    
    switchTab(tab, pageId = null) {
        // Update UI
        document.querySelectorAll('.section-tab-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        const activeBtn = pageId 
            ? document.querySelector(`.section-tab-btn[data-tab="${tab}"][data-page-id="${pageId}"]`)
            : document.querySelector(`.section-tab-btn[data-tab="${tab}"]`);
            
        if (activeBtn) {
            activeBtn.classList.add('active');
        }
        
        this.currentTab = tab;
        this.loadContent(tab, pageId);
    },
    
    loadContent(tab, pageId = null) {
        const container = document.getElementById('rotation-content');
        container.innerHTML = '<div class="loading">Loading...</div>';
        
        let url = `${this.baseUrl}/${tab}-content`;
        if (pageId) {
            url += `/${pageId}`;
        }
        
        fetch(url)
            .then(response => response.text())
            .then(html => {
                container.innerHTML = html;
                
                // Re-initialize feather icons
                if (typeof feather !== 'undefined') {
                    feather.replace({ class: 'feather-icon' });
                }
                
                // If overview loaded, add dynamic page tabs
                if (tab === 'overview') {
                    this.addPageTabs();
                }
                
                // Re-bind any dynamic event handlers
                this.bindDynamicEvents();
            })
            .catch(error => {
                container.innerHTML = '<div class="alert alert-error">Failed to load content. Please try again.</div>';
                console.error('Error loading content:', error);
            });
    },
    
    addPageTabs() {
        // Extract pages from the loaded content
        const rotationCards = document.querySelectorAll('.rotation-card');
        const dynamicTabsContainer = document.getElementById('dynamic-tabs');
        
        if (rotationCards.length > 0 && dynamicTabsContainer) {
            dynamicTabsContainer.innerHTML = '';
            
            rotationCards.forEach((card, index) => {
                const link = card.querySelector('h3 a');
                if (link) {
                    const pageId = link.href.split('/').pop();
                    const pageTitle = link.textContent.trim();
                    
                    const tabBtn = document.createElement('button');
                    tabBtn.className = 'section-tab-btn';
                    tabBtn.dataset.tab = 'manage';
                    tabBtn.dataset.pageId = pageId;
                    tabBtn.innerHTML = `<i data-feather="settings"></i> ${pageTitle}`;
                    
                    dynamicTabsContainer.appendChild(tabBtn);
                }
            });
            
            // Re-initialize feather icons
            if (typeof feather !== 'undefined') {
                feather.replace({ class: 'feather-icon' });
            }
        }
    },
    
    bindDynamicEvents() {
        // Handle bulk actions
        const bulkForm = document.getElementById('bulk-form');
        if (bulkForm) {
            bulkForm.addEventListener('submit', (e) => {
                // Form submission will still reload page - this is okay for bulk actions
            });
        }
        
        // Handle clone modal
        window.showCloneModal = (sourceId, sourceName) => {
            document.getElementById('clone-source-id').value = sourceId;
            document.getElementById('clone-source-name').textContent = sourceName;
            document.getElementById('clone-modal').style.display = 'flex';
        };
        
        window.closeCloneModal = () => {
            document.getElementById('clone-modal').style.display = 'none';
        };
        
        window.toggleAll = (checkbox) => {
            document.querySelectorAll('.row-checkbox').forEach(cb => {
                cb.checked = checkbox.checked;
            });
        };
    }
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    RotationSection.init();
});
</script>

