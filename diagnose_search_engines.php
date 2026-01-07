<?php
/**
 * Search Engine Diagnostic Script
 * Run this from command line: php  .php
 * Or access via browser (place in public folder)
 */

// Adjust path based on location
if (php_sapi_name() === 'cli') {
    // Running from command line
    require_once __DIR__ . '/config/init.php';
} else {
    // Running from browser
    require_once '../config/init.php';
}

require_once __DIR__ . '/core/Database.php';

echo "===========================================\n";
echo "Search Engine Configuration Diagnostic\n";
echo "===========================================\n\n";

$db = Database::getInstance();

// Check if search_engine_config table exists
echo "1. Checking if tables exist...\n";
try {
    $result = $db->fetchOne("SHOW TABLES LIKE 'search_engine_config'");
    if ($result) {
        echo "   ✓ search_engine_config table exists\n";
    } else {
        echo "   ✗ search_engine_config table DOES NOT EXIST\n";
        echo "   → Run: database/search_engine_migration.sql\n\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "   ✗ Error checking tables: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Check search_submissions table
try {
    $result = $db->fetchOne("SHOW TABLES LIKE 'search_submissions'");
    if ($result) {
        echo "   ✓ search_submissions table exists\n";
    } else {
        echo "   ✗ search_submissions table DOES NOT EXIST\n";
        echo "   → Run: database/search_engine_migration.sql\n\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Check search_submission_status table
try {
    $result = $db->fetchOne("SHOW TABLES LIKE 'search_submission_status'");
    if ($result) {
        echo "   ✓ search_submission_status table exists\n\n";
    } else {
        echo "   ✗ search_submission_status table DOES NOT EXIST\n";
        echo "   → Run: database/search_engine_migration.sql\n\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Check configured engines
echo "2. Checking configured engines...\n";
try {
    $engines = $db->fetchAll("SELECT * FROM search_engine_config ORDER BY engine");
    if (empty($engines)) {
        echo "   ✗ No engines configured\n";
        echo "   → Run: database/search_engine_migration.sql\n";
        echo "   → Or run: database/add_indexnow_engines.sql\n\n";
        exit(1);
    }
    
    echo "   Found " . count($engines) . " engines:\n";
    foreach ($engines as $engine) {
        $status = $engine['enabled'] ? '✓ ENABLED' : '✗ DISABLED';
        $apiKey = !empty($engine['api_key']) ? 'YES' : 'NO';
        echo "   - {$engine['engine']}: $status | API Key: $apiKey | Rate: {$engine['submissions_today']}/{$engine['rate_limit_per_day']}\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Check enabled engines
echo "3. Checking enabled engines...\n";
try {
    $enabledEngines = $db->fetchAll("SELECT * FROM search_engine_config WHERE enabled = 1");
    if (empty($enabledEngines)) {
        echo "   ✗ NO ENGINES ARE ENABLED!\n";
        echo "   → Go to Admin → Search Engines → Configuration\n";
        echo "   → Enable at least Bing\n\n";
        exit(1);
    }
    
    echo "   ✓ " . count($enabledEngines) . " engine(s) enabled:\n";
    foreach ($enabledEngines as $engine) {
        echo "   - {$engine['engine']}\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Check API key for Bing
echo "4. Checking Bing API key...\n";
try {
    $bing = $db->fetchOne("SELECT * FROM search_engine_config WHERE engine = 'bing'");
    if ($bing && !empty($bing['api_key'])) {
        echo "   ✓ Bing API key exists: {$bing['api_key']}\n";
        
        // Check if key file exists
        $keyFile = BASE_PATH . '/public/' . $bing['api_key'] . '.txt';
        if (file_exists($keyFile)) {
            $content = file_get_contents($keyFile);
            if ($content === $bing['api_key']) {
                echo "   ✓ Key file exists and is valid\n";
            } else {
                echo "   ✗ Key file exists but content doesn't match\n";
                echo "   → Content: $content\n";
                echo "   → Expected: {$bing['api_key']}\n";
            }
        } else {
            echo "   ✗ Key file DOES NOT EXIST: $keyFile\n";
            echo "   → Creating now...\n";
            file_put_contents($keyFile, $bing['api_key']);
            echo "   ✓ Key file created\n";
        }
    } else {
        echo "   ⚠ Bing API key not generated yet\n";
        echo "   → Will auto-generate on first submission\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n\n";
}

// Check views
echo "5. Checking database views...\n";
$views = ['v_submission_stats', 'v_recent_submissions', 'v_unsubmitted_pages', 'v_pages_due_resubmit'];
foreach ($views as $view) {
    try {
        $result = $db->fetchOne("SHOW TABLES LIKE '$view'");
        if ($result) {
            echo "   ✓ $view exists\n";
        } else {
            echo "   ⚠ $view does not exist (will use fallback queries)\n";
        }
    } catch (Exception $e) {
        echo "   ✗ Error checking $view: " . $e->getMessage() . "\n";
    }
}
echo "\n";

// Check recent submissions
echo "6. Checking submission history...\n";
try {
    $count = $db->fetchOne("SELECT COUNT(*) as count FROM search_submissions");
    echo "   Total submissions: {$count['count']}\n";
    
    if ($count['count'] > 0) {
        $recent = $db->fetchAll("SELECT * FROM search_submissions ORDER BY submitted_at DESC LIMIT 5");
        echo "   Recent submissions:\n";
        foreach ($recent as $sub) {
            echo "   - {$sub['page_slug']} → {$sub['search_engine']} [{$sub['status']}] at {$sub['submitted_at']}\n";
        }
    }
    echo "\n";
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n\n";
}

echo "===========================================\n";
echo "Diagnostic Complete\n";
echo "===========================================\n";

if (isset($enabledEngines) && count($enabledEngines) > 0) {
    echo "\n✓ System appears to be configured correctly!\n";
    echo "\nNext steps:\n";
    echo "1. Try submitting a page from Admin → Search Engines → Submit\n";
    echo "2. Check PHP error log for detailed submission logs\n";
    echo "3. Check Admin → Search Engines → Dashboard for results\n";
} else {
    echo "\n✗ Configuration incomplete. Follow the suggestions above.\n";
}
