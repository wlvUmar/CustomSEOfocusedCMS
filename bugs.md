# 🛠️ Complete Bug Fix Guide

## Quick Start (5 Minutes)

```bash
# Step 1: Save the diagnostic script
# Copy the "System Diagnostic Script" artifact content
# Save as: diagnose.php

# Step 2: Run diagnostics
php diagnose.php > diagnostic_report.txt

# Step 3: Review the report
cat diagnostic_report.txt

# Step 4: Save the fix script
# Copy the "Automated Fix Script" artifact content  
# Save as: fix_bugs.php

# Step 5: BACKUP EVERYTHING
mysqldump -u username -p database_name > backup_$(date +%Y%m%d).sql
tar -czf files_backup_$(date +%Y%m%d).tar.gz .

# Step 6: Run automated fixes
php fix_bugs.php

# Step 7: Run diagnostics again
php diagnose.php

# Step 8: Test your site
curl http://localhost/admin/login
```

---

## Manual Fixes Required

### Fix #1: Update PageAdminController.php

**File:** `./controllers/admin/PageAdminController.php`

**Problem:** Weak error handling in search engine notification

**Fix:** Replace the try-catch block in `save()` method around line 60:

```php
// REPLACE THIS:
try {
    if ($data['is_published']) {
        $this->notifier->notifyPageChange($data['slug'], 'update', null, $_SESSION['user_id'] ?? null);
    }
} catch (Exception $e) {
    error_log("Search engine notification failed: " . $e->getMessage());
}

// WITH THIS:
try {
    if ($data['is_published']) {
        $result = $this->notifier->notifyPageChange(
            $data['slug'], 
            $id ? 'update' : 'create', 
            null, 
            $_SESSION['user_id'] ?? null
        );
        
        // Log detailed results
        $successCount = 0;
        $failCount = 0;
        foreach ($result as $engine => $res) {
            if ($res['status'] === 'success') {
                $successCount++;
                error_log("✓ Search engine notification SUCCESS: $engine for {$data['slug']}");
            } else {
                $failCount++;
                error_log("✗ Search engine notification FAILED: $engine for {$data['slug']} - " . 
                         ($res['message'] ?? 'unknown error'));
            }
        }
        
        // Add admin feedback (optional)
        if ($successCount > 0) {
            $_SESSION['success'] .= " (Notified $successCount search engines)";
        }
    }
} catch (Exception $e) {
    error_log("CRITICAL: Search engine notification exception: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    // Don't throw - page save still succeeded
}
```

---

### Fix #2: Update SearchEngineNotifier Rate Limiting

**File:** `./models/SearchEngineNotifier.php`

**Problem:** Rate limit increments before submission completes

**Fix:** 

1. Find the `checkRateLimit()` method (around line 150)
2. Replace it with:

```php
private function checkRateLimit($engine) {
    if (!isset($this->config[$engine])) {
        error_log("Rate limit check: Engine $engine not configured");
        return false;
    }
    
    $config = $this->config[$engine];
    $limit = $config['rate_limit_per_day'];
    $current = $config['submissions_today'];
    
    error_log("Rate limit check for $engine: $current / $limit");
    
    // Only check, don't increment yet
    return $current < $limit;
}

// Add this NEW method after checkRateLimit():
private function incrementRateLimit($engine) {
    $sql = "UPDATE search_engine_config 
            SET submissions_today = submissions_today + 1 
            WHERE engine = ?";
    
    try {
        $this->db->query($sql, [$engine]);
        error_log("Incremented rate limit for $engine");
    } catch (Exception $e) {
        error_log("Failed to increment rate limit for $engine: " . $e->getMessage());
    }
}
```

3. Find the `notifyPageChange()` method (around line 45)
4. Replace the rate limit check section:

```php
// FIND THIS:
// Check rate limit
if (!$this->checkRateLimit($engine)) {
    error_log("Rate limit reached for $engine");
    $this->logSubmission($slug, $url, $engine, $type, 'rate_limited', null, 
        'Daily rate limit reached', $rotationMonth, $userId);
    continue;
}

// REPLACE WITH:
// Check rate limit BEFORE submission
if (!$this->checkRateLimit($engine)) {
    error_log("Rate limit reached for $engine");
    $this->logSubmission($slug, $url, $engine, $type, 'rate_limited', null, 
        'Daily rate limit reached', $rotationMonth, $userId);
    $results[$engine] = [
        'status' => 'rate_limited',
        'code' => null,
        'message' => 'Daily rate limit reached'
    ];
    continue;
}
```

5. Find where submissions happen (around line 100), after logging:

```php
// AFTER THIS LINE:
$this->logSubmission($slug, $url, $engine, $type, 
    $result['status'], $result['code'], $result['message'], 
    $rotationMonth, $userId);

// ADD THIS:
// Increment rate limit ONLY on successful submission
if ($result['status'] === 'success') {
    $this->incrementRateLimit($engine);
}
```

---

### Fix #3: Add Missing Indexes to Database

**Run this SQL** (if automated script failed):

```sql
-- Connect to your database first:
mysql -u username -p database_name

-- Then run these:

-- Analytics indexes
ALTER TABLE `analytics` 
    ADD INDEX IF NOT EXISTS `idx_slug_date` (`page_slug`, `date`),
    ADD INDEX IF NOT EXISTS `idx_language` (`language`);

-- Search submissions indexes
ALTER TABLE `search_submissions`
    ADD INDEX IF NOT EXISTS `idx_slug_engine` (`page_slug`, `search_engine`),
    ADD INDEX IF NOT EXISTS `idx_status_date` (`status`, `submitted_at`);

-- Bot visits index
ALTER TABLE `analytics_bot_visits`
    ADD INDEX IF NOT EXISTS `idx_bot_page` (`bot_type`, `page_slug`);

-- Show indexes to verify
SHOW INDEX FROM analytics;
SHOW INDEX FROM search_submissions;
SHOW INDEX FROM analytics_bot_visits;
```

---

### Fix #4: Validate Slug Input

**File:** `./controllers/admin/PageAdminController.php`

**Add this helper** to the top of the file (after the class declaration):

```php
class PageAdminController extends Controller {
    private $pageModel;
    private $notifier;

    // ADD THIS METHOD:
    private function sanitizeSlug($slug) {
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        
        if (empty($slug)) {
            throw new Exception('Invalid slug: cannot be empty');
        }
        
        return $slug;
    }
    
    // Rest of class...
```

**Update the save() method** to use it:

```php
// FIND THIS LINE:
$data = [
    'slug' => trim($_POST['slug']),

// REPLACE WITH:
$data = [
    'slug' => $this->sanitizeSlug($_POST['slug']),
```

---

### Fix #5: Add Better Session Validation

**File:** `./controllers/admin/AuthController.php`

**Update the login() method** around line 30:

```php
// FIND THIS:
if ($user && password_verify($password, $user['password'])) {
    $_SESSION['user_id'] = $user['id'];
    
    // ... rest

// REPLACE WITH:
if ($user && password_verify($password, $user['password'])) {
    // Regenerate session ID to prevent fixation
    session_regenerate_id(true);
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    
    // Log successful login
    error_log("User logged in successfully: {$user['username']} (ID: {$user['id']})");
    
    // ... rest
```

---

### Fix #6: Create Missing API Key File

**Run this PHP script once:**

```php
<?php
// create_api_key.php - Run once to generate IndexNow key

require_once './config/init.php';
require_once './models/SearchEngineNotifier.php';

try {
    $notifier = new SearchEngineNotifier();
    $apiKey = $notifier->regenerateApiKey();
    
    echo "✅ API Key generated: $apiKey\n";
    echo "✅ File created: ./public/$apiKey.txt\n";
    echo "✅ Verify at: " . BASE_URL . "/$apiKey.txt\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
```

Then verify:
```bash
php create_api_key.php
curl http://your-domain.com/[the-key].txt
```

---

## Verification Checklist

After applying all fixes, verify everything works:

### ✅ Basic Tests

```bash
# 1. Run diagnostics
php diagnose.php

# 2. Check error logs
tail -50 ./logs/php_errors.log

# 3. Test admin login
curl -I http://localhost/admin/login

# 4. Test sitemap
curl http://localhost/sitemap.xml

# 5. Check API key file
curl http://localhost/[your-api-key].txt
```

### ✅ Admin Panel Tests

1. **Login:** Go to `/admin/login` - should work
2. **Dashboard:** Should show without errors
3. **Pages:** Create/edit a page - should save
4. **Search Engines:** Go to `/admin/search-engine` - should load
5. **Configuration:** Go to `/admin/search-engine/config` - check API key exists

### ✅ Search Engine Tests

1. **Manual Submission:**
   - Go to `/admin/search-engine/submit`
   - Select a page
   - Submit to Bing
   - Should see success message

2. **Automatic Submission:**
   - Edit any page
   - Save it
   - Go to `/admin/search-engine`
   - Should see new submission in Recent Activity

3. **View History:**
   - Click "View History" on any page
   - Should see submission timeline

### ✅ Database Tests

```sql
-- Check tables exist
SHOW TABLES LIKE 'search_%';

-- Check views exist
SHOW FULL TABLES WHERE Table_type = 'VIEW';

-- Check indexes
SHOW INDEX FROM analytics;
SHOW INDEX FROM search_submissions;

-- Check data
SELECT * FROM search_engine_config;
SELECT * FROM search_submissions ORDER BY submitted_at DESC LIMIT 5;
```

---

## Common Errors & Solutions

### Error: "Class 'SearchEngineNotifier' not found"

**Solution:**
```bash
# 1. Check file exists
ls -la ./models/SearchEngineNotifier.php

# 2. Remove duplicate
rm ./beta/SearchEngineNotifier.php

# 3. Verify no syntax errors
php -l ./models/SearchEngineNotifier.php
```

---

### Error: "Table 'search_engine_config' doesn't exist"

**Solution:**
```bash
mysql -u username -p database_name < ./database/search_engine_migration.sql
```

---

### Error: "Failed to create public directory"

**Solution:**
```bash
# Create manually
mkdir -p ./public
chmod 755 ./public

# Test write permission
touch ./public/test.txt
rm ./public/test.txt
```

---

### Error: "API key file not accessible"

**Solution:**
```bash
# 1. Check file exists
ls -la ./public/*.txt

# 2. Check .htaccess allows access
cat ./public/.htaccess

# Should have:
<FilesMatch "\.(txt)$">
    Allow from all
</FilesMatch>

# 3. Test access
curl -I http://your-domain.com/your-key.txt
```

---

### Error: "Rate limit reached immediately"

**Solution:**
```sql
-- Reset rate limits
UPDATE search_engine_config SET submissions_today = 0;

-- Or increase limit
UPDATE search_engine_config 
SET rate_limit_per_day = 10000 
WHERE engine = 'bing';
```

---

## Performance Optimization

After fixing bugs, optimize:

```sql
-- Analyze tables
ANALYZE TABLE analytics;
ANALYZE TABLE search_submissions;
ANALYZE TABLE content_rotations;

-- Optimize tables
OPTIMIZE TABLE analytics;
OPTIMIZE TABLE search_submissions;

-- Check query performance
EXPLAIN SELECT * FROM search_submissions 
WHERE page_slug = 'test' AND search_engine = 'bing';
```

---

## Monitoring Setup

**Create monitoring script** `./monitor_system.php`:

```php
<?php
require_once './config/init.php';

$errors = [];

// Check error log size
$errorLog = './logs/php_errors.log';
if (file_exists($errorLog) && filesize($errorLog) > 10 * 1024 * 1024) {
    $errors[] = "Error log too large: " . round(filesize($errorLog) / 1024 / 1024, 2) . " MB";
}

// Check database connection
try {
    $db = Database::getInstance();
} catch (Exception $e) {
    $errors[] = "Database connection failed";
}

// Check recent submission failures
try {
    $db = Database::getInstance();
    $stmt = $db->query("
        SELECT COUNT(*) as fails 
        FROM search_submissions 
        WHERE status = 'failed' 
        AND submitted_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $result = $stmt->fetch();
    if ($result['fails'] > 10) {
        $errors[] = "High failure rate: {$result['fails']} failed submissions in last hour";
    }
} catch (Exception $e) {
    // Skip if table doesn't exist
}

// Output
if (empty($errors)) {
    echo "✅ System healthy\n";
    exit(0);
} else {
    echo "⚠️ Issues detected:\n";
    foreach ($errors as $error) {
        echo "  • $error\n";
    }
    exit(1);
}
```

**Add to cron:**
```bash
# Run every 15 minutes
*/15 * * * * php /path/to/monitor_system.php >> /path/to/logs/monitor.log
```

---

## Support & Debugging

If issues persist:

1. **Enable detailed logging:**
   ```php
   // In config/init.php
   ini_set('display_errors', 1);
   error_reporting(E_ALL);
   ```

2. **Check PHP error log:**
   ```bash
   tail -f /var/log/php_errors.log
   tail -f ./logs/php_errors.log
   ```

3. **Test specific function:**
   ```php
   <?php
   require_once './config/init.php';
   require_once './models/SearchEngineNotifier.php';
   
   $notifier = new SearchEngineNotifier();
   $result = $notifier->notifyPageChange('test-page', 'manual');
   print_r($result);
   ```

4. **Check MySQL slow query log:**
   ```sql
   SHOW VARIABLES LIKE 'slow_query_log';
   SHOW VARIABLES LIKE 'long_query_time';
   ```

---

## All Done! 🎉

Your system should now be:
- ✅ Free of critical bugs
- ✅ Properly indexed database
- ✅ Secure and validated
- ✅ Ready for production

**Next Steps:**
1. Test thoroughly on staging
2. Monitor logs for 24 hours
3. Deploy to production
4. Set up automated backups
5. Monitor search engine submissions

Questions? Check the logs first, then debug step by step!