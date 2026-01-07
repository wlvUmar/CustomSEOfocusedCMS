import os
import sys
import re

EXTENSIONS = {".html", ".php", ".js", ".sql", ".css"}
SPECIAL_FILES = {".htaccess"}

# Feature definitions - maps feature keywords to relevant files/patterns
FEATURES = {
    "architecture": {
        "keywords": [],
        "models": ["Page.php", "SEO.php", "User.php", "FAQ.php", "ContentRotation.php", "Analytics.php", "JsonLdGenerator.php", "LinkWidget.php", "Media.php"],
        "controllers": ["PageController.php", "AuthController.php", "DashboardController.php", "PageAdminController.php", "RotationAdminController.php", "FAQAdminController.php", "InternalLinksController.php", "MediaController.php", "AnalyticsController.php", "SEOController.php", "PreviewController.php", "SitemapController.php", "LinkWidgetController.php"],
        "views": ["layout/", "templates/", "dashboard.php", "login.php"],
        "css": ["admin.css"],
        "js": ["admin.js", "link-tracking.js"],
    },
    "auth": {
        "keywords": [],
        "models": ["User.php"],
        "controllers": ["AuthController.php"],
        "views": ["login.php", "layout/"],
        "css": ["admin.css"],
    },
    "seo": {
        "keywords": [],
        "models": ["SEO.php", "JsonLdGenerator.php", "Page.php"],
        "controllers": ["SEOController.php", "SitemapController.php", "PageController.php", "PageAdminController.php"],
        "views": ["seo/", "pages/", "templates/"],
        "css": ["admin.css", "seo/"],
        "js": ["seo-settings.js"],
    },
    "pages": {
        "keywords": [],
        "models": ["Page.php", "SEO.php", "ContentRotation.php"],
        "controllers": ["PageController.php", "PageAdminController.php", "PreviewController.php"],
        "views": ["pages/", "templates/", "layout/"],
        "css": ["admin.css"],
        "js": ["admin.js", "preview.js"],
    },
    "media": {
        "keywords": [],
        "models": ["Media.php"],
        "controllers": ["MediaController.php"],
        "views": ["media/", "layout/"],
        "css": ["admin.css", "media/"],
        "js": ["media_manager.js", "admin.js"],
    },
    "analytics": {
        "keywords": [],
        "models": ["Analytics.php", "Page.php"],
        "controllers": ["AnalyticsController.php"],
        "views": ["analytics/", "layout/"],
        "css": ["admin.css"],
        "js": ["link-tracking.js", "admin.js"],
    },
    "rotation": {
        "keywords": [],
        "models": ["ContentRotation.php", "Page.php"],
        "controllers": ["RotationAdminController.php", "PageAdminController.php"],
        "views": ["rotations/", "layout/"],
        "css": ["admin.css"],
        "js": ["rotation-manage.js", "admin.js"],
    },
    "faq": {
        "keywords": [],
        "models": ["FAQ.php", "Page.php"],
        "controllers": ["FAQAdminController.php"],
        "views": ["faqs/", "layout/"],
        "css": ["admin.css"],
        "js": ["admin.js"],
    },
    "links": {
        "keywords": [],
        "models": ["LinkWidget.php", "Page.php"],
        "controllers": ["InternalLinksController.php", "LinkWidgetController.php", "PageAdminController.php"],
        "views": ["internal_links/", "link_widget/", "layout/"],
        "css": ["admin.css"],
        "js": ["internal-links.js", "internal-links-manage.js", "admin.js"],
    },
    "dashboard": {
        "keywords": [],
        "models": ["Analytics.php", "Page.php"],
        "controllers": ["DashboardController.php"],
        "views": ["dashboard.php", "layout/"],
        "css": ["admin.css"],
        "js": ["admin.js"],
    },
    "preview": {
        "keywords": [],
        "models": ["Page.php", "ContentRotation.php"],
        "controllers": ["PreviewController.php", "PageController.php"],
        "views": ["preview.php", "templates/"],
        "js": ["preview.js"],
    },
    "search_engine": {
        "keywords": ["indexnow", "bing", "yandex", "search engine", "indexnow", "sitemap", "search_submissions", "search_engine_config"],
        "models": ["SearchEngineNotifier.php"],
        "controllers": ["SearchEngineController.php", "SitemapController.php"],
        "views": ["admin/search-engine/", "admin/seo/", "admin/seo/"],
        "css": ["admin/search-engine.css"],
        "js": ["admin/search-engine.js"],
    },
}

def is_relevant_file(filepath, feature_config):
    """Check if file is relevant to the feature based on patterns"""
    filename = os.path.basename(filepath)
    filepath_lower = filepath.lower().replace("\\", "/")
    
    # Check specific file lists
    for key in ["models", "controllers", "views", "js", "css"]:
        if key in feature_config:
            for pattern in feature_config[key]:
                if pattern.endswith("/"):
                    if pattern.lower() in filepath_lower:
                        return True
                elif pattern.lower() in filename.lower():
                    return True
    
    return False

def scan_file_for_keywords(filepath, keywords):
    """Scan file content for feature keywords"""
    try:
        with open(filepath, "r", encoding="utf-8", errors="replace") as f:
            content = f.read().lower()
            for keyword in keywords:
                if keyword.lower() in content:
                    return True
    except Exception:
        pass
    return False

def gather_feature_files(feature_name):
    """Gather all files relevant to a specific feature"""
    if feature_name not in FEATURES:
        print(f"Error: Feature '{feature_name}' not found.")
        print(f"Available features: {', '.join(FEATURES.keys())}")
        sys.exit(1)
    
    feature_config = FEATURES[feature_name]
    relevant_files = []
    
    # Always include core files (Router, Database, Controller, helpers, config, SQL schema)
    core_patterns = [
        "core/Router.php", "core/Database.php", "core/Controller.php", 
        "core/helpers.php", "config/config.php", "config/database.php",
        "config/init.php", "config/security.php", "public/index.php",
        "database/schema.sql", ".sql"
    ]
    
    for root, dirs, files in os.walk("."):
        dirs[:] = [d for d in dirs if d not in {"node_modules", "vendor", ".git"}]
        
        for file in sorted(files):
            _, ext = os.path.splitext(file)
            
            if ext in EXTENSIONS or file in SPECIAL_FILES:
                path = os.path.join(root, file).replace("\\", "/")
                
                # Check if it's a core file
                is_core = any(core_pattern in path for core_pattern in core_patterns)
                
                # Check if directly relevant to feature
                is_feature_file = is_relevant_file(path, feature_config)
                
                # Check if contains feature keywords
                has_keywords = scan_file_for_keywords(path, feature_config["keywords"]) if not is_core and not is_feature_file else False
                
                if is_core or is_feature_file or has_keywords:
                    relevant_files.append(path)
    
    return relevant_files

def generate_codebase(files, output_file):
    """Generate codebase file from list of files"""
    total_lines = 0
    
    with open(output_file, "w", encoding="utf-8") as out:
        for path in files:
            out.write(f"# {os.path.basename(path)}\n")
            out.write(f"# path: {path}\n\n")
            
            try:
                with open(path, "r", encoding="utf-8", errors="replace") as f:
                    content = f.read()
                    lines = content.count('\n') + 1
                    total_lines += lines
                    out.write(content)
            except Exception as e:
                out.write(f"# ERROR reading file: {e}\n")
            
            out.write("\n\n" + "=" * 80 + "\n\n")
    
    return total_lines

def main():
    if len(sys.argv) < 2:
        print("Usage: python parse.py <feature_name>")
        print("       python parse.py all")
        print(f"\nAvailable features: {', '.join(FEATURES.keys())}")
        sys.exit(1)
    
    feature = sys.argv[1].lower().replace('-', '_')
    
    if feature == "all":
        # Generate complete codebase (original behavior)
        output_file = "all_source_files.txt"
        files = []
        for root, dirs, dir_files in os.walk("."):
            dirs[:] = [d for d in dirs if d not in {"node_modules", "vendor", ".git"}]
            for file in sorted(dir_files):
                _, ext = os.path.splitext(file)
                if ext in EXTENSIONS or file in SPECIAL_FILES:
                    files.append(os.path.join(root, file).replace("\\", "/"))
        
        total_lines = generate_codebase(files, output_file)
        print(f"Generated complete codebase: {output_file}")
        print(f"Total files: {len(files)}")
        print(f"Total lines: {total_lines:,}")
    else:
        # Generate feature-specific codebase
        output_file = f"{feature}_codebase.txt"
        files = gather_feature_files(feature)
        total_lines = generate_codebase(files, output_file)
        
        print(f"Generated {feature} feature codebase: {output_file}")
        print(f"Total files: {len(files)}")
        print(f"Total lines: {total_lines:,}")
        print(f"\nIncluded files:")
        for f in files:
            print(f"  - {f}")

if __name__ == "__main__":
    main()
