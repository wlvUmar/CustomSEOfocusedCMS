<?php
// UPDATED: views/admin/seo/settings.php
// Changes: Moved inline styles to CSS file, inline scripts to JS file
// Set pageName for automatic CSS loading

$pageName = 'seo/settings';
require BASE_PATH . '/views/admin/layout/header.php';
?>

<h1>Global SEO Settings & JSON-LD Schemas</h1>

<form method="POST" action="<?= BASE_URL ?>/admin/seo/save" class="admin-form">
    <div class="tabs">
        <button type="button" class="tab-btn active" onclick="switchTab('general')">General Info</button>
        <button type="button" class="tab-btn" onclick="switchTab('meta')">Default Meta</button>
        <button type="button" class="tab-btn" onclick="switchTab('organization')">Organization Schema</button>
        <button type="button" class="tab-btn" onclick="switchTab('website')">Website Schema</button>
    </div>
    
    <!-- GENERAL TAB -->
    <div id="tab-general" class="tab-content active">
        <div class="form-section">
            <h3>Site Information</h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Site Name (RU)*</label>
                    <input type="text" name="site_name_ru" value="<?= e($settings['site_name_ru'] ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Site Name (UZ)*</label>
                    <input type="text" name="site_name_uz" value="<?= e($settings['site_name_uz'] ?? '') ?>" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" value="<?= e($settings['phone'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?= e($settings['email'] ?? '') ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Address (RU)</label>
                    <textarea name="address_ru" rows="2"><?= e($settings['address_ru'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>Address (UZ)</label>
                    <textarea name="address_uz" rows="2"><?= e($settings['address_uz'] ?? '') ?></textarea>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>City</label>
                    <input type="text" name="city" value="<?= e($settings['city'] ?? 'Tashkent') ?>">
                </div>
                
                <div class="form-group">
                    <label>Region/State</label>
                    <input type="text" name="region" value="<?= e($settings['region'] ?? 'Tashkent') ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Postal Code</label>
                    <input type="text" name="postal_code" value="<?= e($settings['postal_code'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label>Country Code</label>
                    <input type="text" name="country" value="<?= e($settings['country'] ?? 'UZ') ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Working Hours (RU)</label>
                    <input type="text" name="working_hours_ru" value="<?= e($settings['working_hours_ru'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label>Working Hours (UZ)</label>
                    <input type="text" name="working_hours_uz" value="<?= e($settings['working_hours_uz'] ?? '') ?>">
                </div>
            </div>
            
            <h3 style="margin-top: 30px;">Global Service Settings (for auto-generated schemas)</h3>
            <p class="help-text">
                These settings are used to automatically generate Service schemas on each page using the page title and description.
            </p>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Default Service Type</label>
                    <input type="text" name="service_type" value="<?= e($settings['service_type'] ?? 'Service') ?>" 
                           placeholder="e.g., Repair, Recycling, Buyback">
                    <small style="color: #666;">Used for all auto-generated Service schemas</small>
                </div>
                
                <div class="form-group">
                    <label>Area Served</label>
                    <input type="text" name="area_served" value="<?= e($settings['area_served'] ?? '') ?>" 
                           placeholder="Tashkent, Uzbekistan">
                    <small style="color: #666;">Geographic area your services cover</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- META TAB -->
    <div id="tab-meta" class="tab-content">
        <div class="form-section">
            <h3>Default Meta Tags</h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Meta Keywords (RU)</label>
                    <textarea name="meta_keywords_ru" rows="3"><?= e($settings['meta_keywords_ru'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>Meta Keywords (UZ)</label>
                    <textarea name="meta_keywords_uz" rows="3"><?= e($settings['meta_keywords_uz'] ?? '') ?></textarea>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Meta Description (RU)</label>
                    <textarea name="meta_description_ru" rows="3"><?= e($settings['meta_description_ru'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>Meta Description (UZ)</label>
                    <textarea name="meta_description_uz" rows="3"><?= e($settings['meta_description_uz'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ORGANIZATION SCHEMA TAB -->
    <div id="tab-organization" class="tab-content">
        <div class="form-section">
            <h3>Organization / LocalBusiness Schema</h3>
            <p class="help-text">
                This schema helps search engines understand your business. It appears on your homepage and contact pages.
            </p>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Organization Type</label>
                    <select name="org_type">
                        <option value="LocalBusiness" <?= ($settings['org_type'] ?? '') === 'LocalBusiness' ? 'selected' : '' ?>>Local Business</option>
                        <option value="Organization" <?= ($settings['org_type'] ?? '') === 'Organization' ? 'selected' : '' ?>>Organization</option>
                        <option value="Store" <?= ($settings['org_type'] ?? '') === 'Store' ? 'selected' : '' ?>>Store</option>
                        <option value="HomeAndConstructionBusiness" <?= ($settings['org_type'] ?? '') === 'HomeAndConstructionBusiness' ? 'selected' : '' ?>>Home & Construction</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Organization Name (RU)</label>
                    <input type="text" name="org_name_ru" value="<?= e($settings['org_name_ru'] ?? $settings['site_name_ru'] ?? '') ?>" placeholder="Company name in Russian">
                </div>
                
                <div class="form-group">
                    <label>Organization Name (UZ)</label>
                    <input type="text" name="org_name_uz" value="<?= e($settings['org_name_uz'] ?? $settings['site_name_uz'] ?? '') ?>" placeholder="Company name in Uzbek">
                </div>
                
                <div class="form-group">
                    <label>Logo URL</label>
                    <input type="text" name="org_logo" value="<?= e($settings['org_logo'] ?? '') ?>" 
                           placeholder="https://example.com/logo.png">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Description (RU)</label>
                    <textarea name="org_description_ru" rows="3"><?= e($settings['org_description_ru'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>Description (UZ)</label>
                    <textarea name="org_description_uz" rows="3"><?= e($settings['org_description_uz'] ?? '') ?></textarea>
                </div>
            </div>
            
            <div class="form-group">
                <label>Opening Hours (one per line, format: Mo-Fr 09:00-18:00)</label>
                <textarea name="opening_hours" rows="3" placeholder="Mo-Fr 09:00-18:00
Sa 10:00-15:00"><?= e($settings['opening_hours'] ?? '') ?></textarea>
            </div>
            
            <div class="form-group">
                <label>Price Range (e.g., $$, $$$)</label>
                <input type="text" name="price_range" value="<?= e($settings['price_range'] ?? '') ?>" placeholder="$$">
            </div>
            
            <h4>Social Media Links</h4>
            <div class="form-row">
                <div class="form-group">
                    <label>Facebook URL</label>
                    <input type="text" name="social_facebook" value="<?= e($settings['social_facebook'] ?? '') ?>" 
                           placeholder="https://facebook.com/yourpage">
                </div>
                
                <div class="form-group">
                    <label>Instagram URL</label>
                    <input type="text" name="social_instagram" value="<?= e($settings['social_instagram'] ?? '') ?>" 
                           placeholder="https://instagram.com/yourpage">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Twitter URL</label>
                    <input type="text" name="social_twitter" value="<?= e($settings['social_twitter'] ?? '') ?>" 
                           placeholder="https://twitter.com/yourpage">
                </div>
                
                <div class="form-group">
                    <label>YouTube URL</label>
                    <input type="text" name="social_youtube" value="<?= e($settings['social_youtube'] ?? '') ?>" 
                           placeholder="https://youtube.com/c/yourchannel">
                </div>
            </div>
            
            <button type="button" onclick="generateOrgSchema()" class="btn">
                <i data-feather="code"></i> Preview Generated Schema
            </button>
            
            <pre id="org-schema-preview" style="display: none;"></pre>
        </div>
    </div>
    
    <!-- WEBSITE SCHEMA TAB -->
    <div id="tab-website" class="tab-content">
        <div class="form-section">
            <h3>Website Schema</h3>
            <p class="help-text">
                This schema describes your website to search engines.
            </p>
            
            <div class="info-banner">
                <strong><i data-feather="book"></i> Note:</strong> This schema is auto-generated from your Site Name and Meta Description. 
                It will be included on your homepage automatically.
            </div>
            
            <div style="background: #f9fafb; padding: 15px; border-radius: 6px; border-left: 3px solid #10b981;">
                <strong>Auto-Generated Schema Preview:</strong>
                <pre>{
  "@context": "https://schema.org",
  "@type": "WebSite",
  "name": "<?= e($settings['site_name_ru'] ?? 'Your Site') ?>",
  "url": "<?= BASE_URL ?>",
  "description": "<?= e($settings['meta_description_ru'] ?? '') ?>"
}</pre>
            </div>
        </div>
    </div>
    
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">
            <i data-feather="save"></i> Save All Settings
        </button>
    </div>
</form>

<!-- Load external JavaScript -->
<script src="<?= BASE_URL ?>/js/admin/seo-settings.js"></script>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>