// Search Engine Admin Interface JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Handle form submissions with loading states
    const forms = document.querySelectorAll('form[action*="save-config"], form[action*="ping-now"]');
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
                
                // Restore button state after a timeout if form doesn't redirect
                // This handles validation errors or other failures
                const restoreTimeout = setTimeout(function() {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                    if (typeof feather !== 'undefined') {
                        feather.replace();
                    }
                }, 3000);
                
                // Clear timeout if form actually submits successfully
                form.addEventListener('submit', function() {
                    clearTimeout(restoreTimeout);
                });
            }
        });
    });
});


