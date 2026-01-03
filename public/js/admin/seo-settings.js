// FILE: public/js/admin/seo-settings.js
// Moved from inline script in views/admin/seo/settings.php

function generateOrgSchema() {
    const preview = document.getElementById('org-schema-preview');
    
    const data = {
        type: document.querySelector('[name="org_type"]').value,
        name: document.querySelector('[name="site_name_ru"]').value,
        url: window.location.origin,
        logo: document.querySelector('[name="org_logo"]').value,
        description: document.querySelector('[name="org_description_ru"]').value,
        telephone: document.querySelector('[name="phone"]').value,
        email: document.querySelector('[name="email"]').value,
        address: document.querySelector('[name="address_ru"]').value,
        city: document.querySelector('[name="city"]').value,
        region: document.querySelector('[name="region"]').value,
        postal: document.querySelector('[name="postal_code"]').value,
        country: document.querySelector('[name="country"]').value,
        openingHours: document.querySelector('[name="opening_hours"]').value.split('\n').filter(Boolean),
        priceRange: document.querySelector('[name="price_range"]').value,
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
        "name": data.name,
        "url": data.url
    };
    
    if (data.logo) schema.logo = data.logo;
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
    
    if (data.openingHours.length) schema.openingHours = data.openingHours;
    if (data.priceRange) schema.priceRange = data.priceRange;
    if (data.sameAs.length) schema.sameAs = data.sameAs;
    
    preview.textContent = JSON.stringify(schema, null, 2);
    preview.style.display = 'block';
}