<?php require BASE_PATH . '/views/admin/layout/header.php'; ?>

<h1>Global SEO Settings</h1>

<form method="POST" action="<?= BASE_URL ?>/admin/seo/save" class="admin-form">
    <div class="form-section">
        <h3>Site Information</h3>
        
        <div class="form-row">
            <div class="form-group">
                <label>Site Name (RU)*</label>
                <input type="text" name="site_name_ru" value="<?= $settings['site_name_ru'] ?? '' ?>" required>
            </div>
            
            <div class="form-group">
                <label>Site Name (UZ)*</label>
                <input type="text" name="site_name_uz" value="<?= $settings['site_name_uz'] ?? '' ?>" required>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Phone</label>
                <input type="text" name="phone" value="<?= $settings['phone'] ?? '' ?>">
            </div>
            
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="<?= $settings['email'] ?? '' ?>">
            </div>
        </div>
    </div>
    
    <div class="form-section">
        <h3>Default Meta Tags</h3>
        
        <div class="form-row">
            <div class="form-group">
                <label>Meta Keywords (RU)</label>
                <textarea name="meta_keywords_ru" rows="3"><?= $settings['meta_keywords_ru'] ?? '' ?></textarea>
            </div>
            
            <div class="form-group">
                <label>Meta Keywords (UZ)</label>
                <textarea name="meta_keywords_uz" rows="3"><?= $settings['meta_keywords_uz'] ?? '' ?></textarea>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Meta Description (RU)</label>
                <textarea name="meta_description_ru" rows="3"><?= $settings['meta_description_ru'] ?? '' ?></textarea>
            </div>
            
            <div class="form-group">
                <label>Meta Description (UZ)</label>
                <textarea name="meta_description_uz" rows="3"><?= $settings['meta_description_uz'] ?? '' ?></textarea>
            </div>
        </div>
    </div>
    
    <div class="form-section">
        <h3>Contact Information</h3>
        
        <div class="form-row">
            <div class="form-group">
                <label>Address (RU)</label>
                <textarea name="address_ru" rows="2"><?= $settings['address_ru'] ?? '' ?></textarea>
            </div>
            
            <div class="form-group">
                <label>Address (UZ)</label>
                <textarea name="address_uz" rows="2"><?= $settings['address_uz'] ?? '' ?></textarea>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Working Hours (RU)</label>
                <input type="text" name="working_hours_ru" value="<?= $settings['working_hours_ru'] ?? '' ?>">
            </div>
            
            <div class="form-group">
                <label>Working Hours (UZ)</label>
                <input type="text" name="working_hours_uz" value="<?= $settings['working_hours_uz'] ?? '' ?>">
            </div>
        </div>
    </div>
    
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save Settings</button>
    </div>
</form>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>