// FILE: public/js/admin/seo-settings.js
// Moved from inline script in views/admin/seo/settings.php

function generateOrgSchema() {
    const preview = document.getElementById('org-schema-preview');
    
    // If admin provided custom JSON-LD and enabled checkbox, show that raw JSON (validate)
    const customToggle = document.querySelector('[name="org_schema_custom"]');
    const rawTextarea = document.querySelector('[name="organization_schema_raw"]');
    if (customToggle && customToggle.checked && rawTextarea && rawTextarea.value.trim()) {
        try {
            const parsed = JSON.parse(rawTextarea.value);
            preview.textContent = JSON.stringify(parsed, null, 2);
            preview.style.display = 'block';
            return;
        } catch (e) {
            preview.textContent = 'Invalid JSON: ' + e.message;
            preview.style.display = 'block';
            return;
        }
    }

    const data = {
        type: document.querySelector('[name="org_type"]').value,
        name: (document.querySelector('[name="org_name_ru"]') ? document.querySelector('[name="org_name_ru"]').value : document.querySelector('[name="site_name_ru"]')?.value || ''),
        url: window.location.origin,
        logo: document.querySelector('[name="org_logo"]').value,
        image: document.querySelector('[name="org_logo"]').value,
        description: document.querySelector('[name="org_description_ru"]').value,
        telephone: document.querySelector('[name="phone"]') ? document.querySelector('[name="phone"]').value : '',
        email: document.querySelector('[name="email"]') ? document.querySelector('[name="email"]').value : '',
        address: document.querySelector('[name="address_ru"]').value,
        city: document.querySelector('[name="city"]').value,
        region: document.querySelector('[name="region"]').value,
        postal: document.querySelector('[name="postal_code"]').value,
        country: document.querySelector('[name="country"]').value,
        latitude: document.querySelector('[name="org_latitude"]') ? document.querySelector('[name="org_latitude"]').value : '',
        longitude: document.querySelector('[name="org_longitude"]') ? document.querySelector('[name="org_longitude"]').value : '',
        openingHours: document.querySelector('[name="opening_hours"]').value.split('\n').filter(Boolean),
        priceRange: document.querySelector('[name="price_range"]').value,
        areaServed: document.querySelector('[name="area_served"]') ? document.querySelector('[name="area_served"]').value : '',
        sameAs: [
            document.querySelector('[name="social_facebook"]').value,
            document.querySelector('[name="social_instagram"]').value,
            document.querySelector('[name="social_twitter"]').value,
            document.querySelector('[name="social_youtube"]').value
        ].filter(Boolean)
    };
    
    const schema = {
        "@context": "https://schema.org",
        "@type": data.type,
        "@id": window.location.origin + '#organization',
        "name": data.name,
        "url": data.url
    };
    
    if (data.logo) schema.logo = data.logo;
    if (data.image) schema.image = data.image;
    if (data.description) schema.description = data.description;
    if (data.telephone) schema.telephone = data.telephone;
    if (data.email) schema.email = data.email;
    
    if (data.address) {
        schema.address = {
            "@type": "PostalAddress",
            "streetAddress": data.address,
            "addressLocality": data.city,
            "addressRegion": data.region,
            "postalCode": data.postal,
            "addressCountry": data.country
        };
    }
    
    if (data.latitude && data.longitude) {
        schema.geo = { '@type': 'GeoCoordinates', latitude: parseFloat(data.latitude), longitude: parseFloat(data.longitude) };
    }
    
    if (data.openingHours.length) schema.openingHours = data.openingHours;
    if (data.priceRange) schema.priceRange = data.priceRange;
    if (data.sameAs.length) schema.sameAs = data.sameAs;
    if (data.areaServed) schema.areaServed = { '@type': 'City', name: data.areaServed };
    
    preview.textContent = JSON.stringify(schema, null, 2);
    preview.style.display = 'block';
}

// Intercept form submit to save via AJAX and show inline alert instead of navigating away
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form.admin-form');
    if (!form) return;

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) { submitBtn.disabled = true; }

        const fd = new FormData(form);
        try {
            const res = await fetch(form.action, { method: 'POST', body: fd, credentials: 'same-origin' });
            if (res.ok) {
                showAlert('SEO settings saved successfully', 'success');
            } else {
                showAlert('Failed to save SEO settings', 'error');
            }
        } catch (err) {
            showAlert('Network error saving settings', 'error');
        } finally {
            if (submitBtn) { submitBtn.disabled = false; }
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    });
});

function showAlert(message, type) {
    const existing = document.querySelector('.alert');
    if (existing) existing.remove();
    const div = document.createElement('div');
    div.className = 'alert ' + (type === 'success' ? 'alert-success' : 'alert-error');
    div.textContent = message;
    const container = document.querySelector('.admin-wrapper') || document.body;
    container.insertBefore(div, container.firstChild);
    setTimeout(() => { div.style.transition = 'opacity 0.3s ease'; div.style.opacity = '0'; setTimeout(()=>div.remove(),300); }, 4000);
}