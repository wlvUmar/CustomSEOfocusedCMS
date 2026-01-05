# SEO-Focused CMS with Content Rotation

A powerful, SEO-optimized Content Management System built with PHP, featuring monthly content rotation, comprehensive analytics, and bilingual support (Russian/Uzbek).

## 🚀 Features

### Content Management
- **Bilingual Support**: Full Russian and Uzbek language support
- **Monthly Content Rotation**: Automatically display different content based on the month
- **Template Engine**: Jinja-like syntax with variables, loops, and conditionals
- **FAQ Management**: Per-page FAQ sections with structured data
- **Media Library**: Enhanced media manager with direct insertion capabilities

### SEO Optimization
- **Page-Level SEO**: Meta titles, descriptions, keywords for each page
- **Open Graph Support**: Facebook/social media optimization
- **JSON-LD Schema**: Structured data for rich snippets
- **Automatic Sitemap**: XML sitemap generation with language alternates
- **Robots.txt**: Dynamic robots.txt based on environment
- **Canonical URLs**: Proper canonicalization and hreflang tags

### Analytics & Tracking
- **Visit Tracking**: Page views and click-through rates
- **Navigation Flow**: Internal link tracking and user journey analysis
- **Rotation Analytics**: Track which content variations perform best
- **Crawl Analysis**: Monitor search engine crawl frequency
- **Monthly Trends**: Compare performance month-over-month

### Admin Panel
- **Intuitive Interface**: Modern, responsive admin dashboard
- **Preview System**: Preview pages with specific month rotations
- **Bulk Operations**: Upload and manage multiple items at once
- **Version Control**: Track content changes and updates
- **Security**: CSRF protection, rate limiting, session management

## 📋 Requirements

- PHP 7.4 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Apache with mod_rewrite
- 64MB PHP memory limit minimum
- Support for `.htaccess` files

## 🔧 Installation

### 1. Clone or Upload Files

Upload all files to your web server. The structure should be:

```
your-domain.com/
├── config/
├── controllers/
├── core/
├── database/
├── models/
├── public/
│   ├── css/
│   ├── js/
│   ├── uploads/
│   └── index.php (entry point)
├── views/
└── logs/
```

### 2. Database Setup

1. Import the database schema:
```bash
mysql -u username -p database_name < database/schema.sql
```

2. Update database credentials in `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

### 3. Create Admin User

Run this SQL to create an admin account:
```sql
INSERT INTO users (username, password, email) 
VALUES ('admin', '$2y$10$YourHashedPasswordHere', 'admin@example.com');
```

To generate a password hash:
```php
<?php echo password_hash('your_password', PASSWORD_DEFAULT); ?>
```

### 4. Set Permissions

```bash
chmod 755 public/uploads
chmod 755 logs
```

### 5. Configure Base URL

Update `config/config.php`:
```php
define('BASE_URL', 'https://your-domain.com');
```

### 6. Add New Routes

Add the routes from the "Routes Update" artifact to `public/index.php`.

## 🎨 Usage

### Creating Pages

1. Navigate to **Admin Panel > Pages > Add New**
2. Fill in titles and content for both languages
3. Enable "Content Rotation" if you want monthly variations
4. Set SEO metadata (optional - uses global defaults if empty)
5. Save and publish

### Setting Up Content Rotation

1. Create a page and enable "Content Rotation"
2. Go to **Content Rotation > Manage** for that page
3. Create content variations for different months
4. Each month can have:
   - Custom title and description
   - Different content
   - Month-specific SEO tags
   - Unique JSON-LD schema

### Template Variables

Use these in any content field:

```html
<!-- Page data -->
{{page.title}}
{{page.slug}}

<!-- Global settings -->
{{global.phone}}
{{global.email}}
{{global.address}}
{{global.site_name}}

<!-- Date variables -->
{{date.year}}
{{date.month}}
{{date.month_name}}

<!-- Loops -->
{% for item in items %}
  <li>{{item.name}}</li>
{% endfor %}

<!-- Conditionals -->
{% if rotation.active %}
  <p>This is rotating content for {{date.month_name}}!</p>
{% endif %}
```

### Managing Media

1. Go to **Admin Panel > Media**
2. Upload single or multiple images
3. Click "Insert" on any image to get:
   - Direct URL
   - HTML code
   - Markdown code
4. Choose size (Full, Medium, Thumbnail)
5. Copy and paste into your content

### Preview System

Preview how a page looks in any month:

1. **From Rotation Manager**: Click "Preview Page" button
2. **Direct URL**: `/admin/preview/{page_id}?month={1-12}&lang={ru|uz}`
3. Change month and language in real-time
4. See exactly what visitors will see

### Analytics

Access comprehensive analytics:

- **Overview**: General performance metrics
- **Rotation Stats**: Which content variations perform best
- **Navigation Flow**: How users move between pages
- **Crawl Analysis**: Search engine crawl frequency

Export data as CSV for external analysis.

### SEO Management

#### Sitemap
- Automatically generated at `/sitemap.xml`
- Includes all published pages in both languages
- Updates automatically when content changes
- Submit to Google Search Console and Bing Webmaster

#### Robots.txt
- Dynamic based on environment
- Production: Allows all crawlers
- Development: Blocks all crawlers
- Access at `/robots.txt`

## 🔒 Security Features

- **CSRF Protection**: All forms protected
- **Rate Limiting**: Prevents brute force attacks
- **Session Security**: Regular ID regeneration
- **Input Validation**: All uploads and inputs validated
- **SQL Injection Prevention**: Prepared statements
- **XSS Protection**: Output escaping
- **Security Headers**: CSP, X-Frame-Options, etc.

## 📊 Database Tables

- `pages` - Page content and metadata
- `content_rotations` - Monthly content variations
- `faqs` - Frequently asked questions
- `media` - Uploaded images
- `analytics` - Daily visit/click tracking
- `analytics_monthly` - Monthly aggregated data
- `analytics_rotations` - Rotation performance tracking
- `analytics_internal_links` - Navigation flow data
- `seo_settings` - Global SEO configuration
- `users` - Admin users

## 🎯 Best Practices

### Content Rotation
- Create all 12 months for complete coverage
- Use current month references: `{{date.month_name}}`
- Preview before saving
- Monitor rotation analytics to see what works

### SEO
- Write unique meta descriptions for each page
- Use rotation for seasonal content
- Fill in all OpenGraph tags
- Add relevant JSON-LD schema
- Submit sitemap to search engines

### Performance
- Optimize images before upload (max 5MB)
- Use content rotation to keep content fresh
- Monitor crawl frequency
- Track internal link effectiveness

### Analytics
- Check rotation effectiveness monthly
- Optimize low-performing content
- Improve internal linking based on navigation data
- Export data regularly for backup

## 🔄 Deployment

### Development to Production

1. Update `config/config.php`:
```php
define('BASE_URL', 'https://production-domain.com');
```

2. Update `config/security.php`:
```php
define('IS_PRODUCTION', true);
```

3. Clear any test data from database

4. Submit sitemap to search engines

5. Verify robots.txt allows crawling



## 🤝 Contributing

This is a private/client project. For questions or support, contact the development team.

## 📄 License

Proprietary - All rights reserved.

## 🔗 Links

- Admin Panel: `/admin`
- Sitemap: `/sitemap.xml`
- Robots: `/robots.txt`
- Analytics: `/admin/analytics`

## 📞 Support

For technical support or questions, contact the development team.

---

**Built with ❤️ for SEO excellence**