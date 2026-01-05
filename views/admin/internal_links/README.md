# Internal Links Manager - Documentation

## Overview
A comprehensive internal linking management system that helps create a dense network of interconnected pages for better SEO and user navigation.

## Features

### 1. **Central Dashboard** (`/admin/internal-links`)
- **Network Statistics**: View total pages, total links, network density percentage, and orphan pages
- **All Pages Overview**: See all pages with their outgoing/incoming link counts
- **Widget Status**: Check which pages have the link widget enabled/disabled
- **Bulk Actions**: Perform actions on multiple pages at once
- **Auto-Connect**: Automatically create links between pages

### 2. **Page-Specific Management** (`/admin/internal-links/manage/{pageId}`)
- **Outgoing Links**: Manage links from this page to other pages
- **Drag & Drop Reordering**: Reorder links by dragging
- **Incoming Links**: See which pages link to this page
- **Widget Toggle**: Enable/disable the link widget for the specific page
- **Search**: Find pages quickly when adding new links

### 3. **Auto-Connect Strategies**

#### All-to-All (Dense Network)
Creates maximum connections between all pages. Every page links to every other page.
- **Best for**: Small to medium sites (5-20 pages)
- **Result**: Maximum network density, strongest internal linking

#### Hub Model (Popular to All)
Connects all pages to the most viewed/popular pages.
- **Best for**: Sites with clear authority pages
- **Result**: Star topology with popular pages as hubs

#### Smart/Related (Content-Based)
Connects pages based on content similarity and keywords.
- **Best for**: Large sites with diverse content
- **Result**: Contextually relevant links, natural network growth

### 4. **Bulk Actions**
- **Add Links**: Add a specific page as a link on multiple selected pages
- **Remove Links**: Remove a specific page from multiple selected pages
- **Enable/Disable Widget**: Toggle widget visibility on multiple pages

## Usage

### Adding Links Manually
1. Go to `/admin/internal-links`
2. Click "Manage" on any page
3. Select pages from "Available Pages" section
4. Click "Add" to create the link
5. Drag to reorder links

### Auto-Connecting Pages
1. Go to `/admin/internal-links`
2. Click "Auto-Connect Pages"
3. Choose a strategy:
   - **All-to-All**: Maximum connections
   - **Hub Model**: Connect to popular pages
   - **Smart**: Content-based connections
4. Click "Auto-Connect"

### Bulk Operations
1. Select multiple pages using checkboxes
2. Click "Bulk Actions"
3. Choose action (add/remove links, enable/disable widget)
4. Select target page (if adding/removing links)
5. Apply changes

## Network Metrics

### Network Density
Percentage of actual links vs. maximum possible links.
- **Formula**: `(Total Links / Max Possible Links) × 100`
- **Max Possible**: `Total Pages × (Total Pages - 1)`

### Orphan Pages
Pages with no incoming or outgoing links. Should be minimized for SEO.

### Average Links Per Page
Total links divided by total pages. Aim for 3-10 links per page.

## SEO Benefits

1. **Improved Crawlability**: Search engines discover pages more efficiently
2. **Link Equity Distribution**: PageRank flows between connected pages
3. **Reduced Orphan Pages**: No pages left isolated
4. **Better User Experience**: Visitors find related content easily
5. **Topic Clustering**: Related pages form topical authority clusters

## Best Practices

1. **Start Small**: Don't overwhelm with links. 3-7 links per page is ideal
2. **Relevant Links**: Use Smart strategy for contextually relevant connections
3. **Monitor Density**: Aim for 20-40% network density for most sites
4. **Regular Updates**: Add links to new pages as content grows
5. **Widget Placement**: Enable widget on high-traffic pages for maximum impact

## Technical Details

### Database Tables Used
- `pages`: Main pages table
- `page_link_widgets`: Stores link relationships
- `analytics_internal_links`: Tracks link click performance

### Key Files
- Controller: `controllers/admin/InternalLinksController.php`
- Model: `models/LinkWidget.php`
- Views: `views/admin/internal_links/`
- JavaScript: `public/js/admin/internal-links*.js`
- CSS: `public/css/admin/internal_links/`

## Integration with Existing Features

### Link Widget
Links are displayed on pages using the link widget system.
- Toggle per page: `/admin/internal-links/manage/{pageId}`
- Widget appears in page footer or sidebar (depending on theme)

### Analytics
Link clicks are tracked via `analytics_internal_links` table.
- View stats: `/admin/analytics/navigation`
- Track popular paths and navigation flow

## Migration from Old System

The old per-page link management (`/admin/link-widget/manage/{pageId}`) still works but is deprecated. Use the new central manager instead for better overview and bulk operations.

## Future Enhancements

- Visual network graph visualization
- A/B testing for link placement
- Smart link suggestions based on user behavior
- Automatic broken link detection
- Link performance scoring
