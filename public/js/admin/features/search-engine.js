// Search Engine Admin Interface JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Handle form submissions with loading states
    const forms = document.querySelectorAll('form[action*="submit"], form[action*="save-config"]');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn && !submitBtn.disabled) {
                submitBtn.disabled = true;
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i data-feather="loader"></i> Submitting...';
                if (typeof feather !== 'undefined') {
                    feather.replace();
                }
            }
        });
    });

    // Confirm before batch submission
    const batchForm = document.getElementById('batch-submit-form');
    if (batchForm) {
        batchForm.addEventListener('submit', function(e) {
            const checked = this.querySelectorAll('input[name="slugs[]"]:checked').length;
            if (checked === 0) {
                e.preventDefault();
                alert('Please select at least one page');
                return;
            }
            
            if (!confirm(`Submit ${checked} page(s) to search engines?`)) {
                e.preventDefault();
            }
        });
    }

    // Select all checkbox functionality
    const selectAllBtn = document.getElementById('select-all');
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function() {
            const checkboxes = document.querySelectorAll('input[name="slugs[]"]');
            checkboxes.forEach(cb => cb.checked = true);
        });
    }

    const selectNoneBtn = document.getElementById('select-none');
    if (selectNoneBtn) {
        selectNoneBtn.addEventListener('click', function() {
            const checkboxes = document.querySelectorAll('input[name="slugs[]"]');
            checkboxes.forEach(cb => cb.checked = false);
        });
    }

    // Copy API key to clipboard
    const apiKeyInputs = document.querySelectorAll('input[name*="api_key"]');
    apiKeyInputs.forEach(input => {
        if (input.value) {
            const copyBtn = document.createElement('button');
            copyBtn.type = 'button';
            copyBtn.className = 'btn btn-sm btn-secondary';
            copyBtn.innerHTML = '<i data-feather="copy"></i> Copy';
            copyBtn.style.marginLeft = '10px';
            
            copyBtn.addEventListener('click', function() {
                input.select();
                document.execCommand('copy');
                this.innerHTML = '<i data-feather="check"></i> Copied!';
                if (typeof feather !== 'undefined') {
                    feather.replace();
                }
                
                setTimeout(() => {
                    this.innerHTML = '<i data-feather="copy"></i> Copy';
                    if (typeof feather !== 'undefined') {
                        feather.replace();
                    }
                }, 2000);
            });
            
            if (!input.parentElement.querySelector('.btn-secondary')) {
                input.parentElement.style.display = 'flex';
                input.parentElement.style.gap = '10px';
                input.parentElement.appendChild(copyBtn);
                if (typeof feather !== 'undefined') {
                    feather.replace();
                }
            }
        }
    });

    // Highlight animation for recent submissions
    const recentItems = document.querySelectorAll('.timeline-item, tr');
    if (recentItems.length > 0 && recentItems[0].classList.contains('timeline-item')) {
        recentItems[0].style.animation = 'highlight 2s ease';
    }
});

// Add highlight animation CSS if not already present
if (!document.getElementById('search-engine-animations')) {
    const style = document.createElement('style');
    style.id = 'search-engine-animations';
    style.textContent = `
        @keyframes highlight {
            0%, 100% { background: transparent; }
            50% { background: rgba(14, 165, 233, 0.1); }
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .btn.loading {
            position: relative;
            color: transparent;
        }
        .btn.loading::after {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            top: 50%;
            left: 50%;
            margin-left: -8px;
            margin-top: -8px;
            border: 2px solid transparent;
            border-top-color: currentColor;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
    `;
    document.head.appendChild(style);
}

// Test connection function (for config page)
async function testConnection(engine) {
    const formData = new FormData();
    formData.append('engine', engine);
    
    try {
        const response = await fetch(window.location.origin + '/admin/search-engine/test-connection', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(`✓ Connection to ${engine} successful!`);
        } else {
            alert(`✗ Connection failed: ${result.message}`);
        }
    } catch (error) {
        alert(`✗ Error testing connection: ${error.message}`);
    }
}
