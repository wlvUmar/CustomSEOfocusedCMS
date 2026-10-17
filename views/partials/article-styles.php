<?php
// path: ./views/partials/article-styles.php
// Relocated verbatim from views/templates/article.php - no logic changes.
?>
    <style>
    /* Article Page Specific Styles */
    main.article-page {
        padding-top: 2.5rem !important;
        padding-bottom: 5rem !important;
        position: relative;
        z-index: 1; /* Low z-index base */
        background:
            radial-gradient(1200px 400px at 10% -10%, rgba(240, 242, 232, 0.9), transparent 70%),
            radial-gradient(800px 360px at 90% 0%, rgba(230, 228, 208, 0.65), transparent 70%);
    }
    
    .article-container {
        max-width: 1240px;
        padding: 0 24px;
    }

    .unified-article-card {
        background: #fff;
        border-radius: 18px;
        overflow: hidden;
        box-shadow: 0 16px 40px rgba(0,0,0,0.08);
        margin-bottom: 3rem;
        position: relative;
        z-index: 1;
        border: 1px solid rgba(0,0,0,0.04);
    }
    
    /* Hero Header */
    .article-card-header {
        
        background: linear-gradient(180deg, #e6e4d0 0%, #f3f1df 100%);;
        padding: 46px 56px 34px;
        text-align: center;
        border-bottom: 1px solid rgba(0,0,0,0.04);
        position: relative;
    }

    .article-card-header::after {
        content: '';
        position: absolute;
        inset: 0;
        background-image:
            radial-gradient(circle at 20% 20%, rgba(255,255,255,0.5), transparent 35%),
            radial-gradient(circle at 80% 0%, rgba(255,255,255,0.35), transparent 40%);
        pointer-events: none;
    }
    
    .header-content {
        max-width: 860px;
        margin: 0 auto;
        position: relative;
        z-index: 1;
    }

    .article-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 12px;
        border-radius: 999px;
        background: rgba(48, 48, 52, 0.08);
        color: #2f2f32;
        font-size: 0.78rem;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        font-weight: 700;
        margin-bottom: 14px;
    }
    
    /* Removed article-badge style */
    
    .article-card-header h1 {
        font-size: 2.6rem;
        color: #1a1a1c;
        margin: 0 0 0.8rem;
        line-height: 1.1;
        letter-spacing: -0.6px;
        font-weight: 800;
    }

    .article-subtitle {
        margin: 0 auto 1.2rem;
        font-size: 1.05rem;
        color: #4a4a4d;
        max-width: 720px;
        line-height: 1.65;
    }
    
    .article-meta {
        display: flex;
        justify-content: flex-end;
        gap: 14px;
        color: #3a3a3d;
        font-size: 0.9rem;
        font-weight: 600;
        flex-wrap: wrap;
    }
    
    .article-meta span {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: rgba(255,255,255,0.7);
        border: 1px solid rgba(0,0,0,0.06);
        padding: 6px 12px;
        border-radius: 999px;
    }
    
    /* Feature Image */
    .article-card-image {
        background: #f0f0f0;
        border-bottom: 1px solid rgba(0,0,0,0.04);
        max-height: 520px;
        overflow: hidden;
    }
    
    .article-card-image img {
        width: 100%;
        height: auto;
        display: block;
        object-fit: cover;
    }
    
    /* Content Body */
    .article-card-body {
        padding: 46px 60px 56px;
        font-size: 1.1rem;
        line-height: 1.85;
        color: #2c2c2e;
        background: #fff;
    }

    /* Related Page Banner (CTA) */
    .related-page-banner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #303034; /* Dark Accent */
        color: #EDEBD7;
        padding: 20px 30px;
        border-radius: 8px;
        margin-bottom: 30px;
        text-decoration: none;
        box-shadow: 0 4px 15px rgba(48, 48, 52, 0.2);
        transition: transform 0.2s, box-shadow 0.2s;
        border-left: 6px solid #f5f3e5;
    }

    .related-page-banner:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(48, 48, 52, 0.3);
    }

    .rpb-content {
        display: flex;
        flex-direction: column;
    }

    .rpb-label {
        font-size: 0.8rem;
        text-transform: uppercase;
        opacity: 0.8;
        letter-spacing: 1px;
    }

    .rpb-title {
        font-size: 1.2rem;
        font-weight: bold;
        color: #fff;
    }

    .rpb-arrow {
        font-size: 1.2rem;
        font-weight: bold;
        background: rgba(255,255,255,0.1);
        padding: 8px 16px;
        border-radius: 20px;
        transition: background 0.2s;
    }

    .related-page-banner:hover .rpb-arrow {
        background: rgba(255,255,255,0.2);
    }
    
    /* Typography inside article */
    .article-card-body p {
        margin: 0 0 1.4rem;
    }

    .article-card-body h2,
    .article-card-body h3,
    .article-card-body h4 {
        margin: 2.2rem 0 1rem;
        color: #111;
        line-height: 1.25;
    }

    .article-card-body h2 {
        font-size: 1.7rem;
    }

    .article-card-body h3 {
        font-size: 1.35rem;
    }

    .article-card-body h4 {
        font-size: 1.15rem;
        letter-spacing: 0.2px;
    }

    .article-card-body ul,
    .article-card-body ol {
        margin: 0 0 1.4rem;
        padding-left: 1.6rem;
    }

    .article-card-body li {
        margin: 0.35rem 0;
    }

    .article-card-body a {
        color: #2b6a62;
        text-decoration: underline;
        text-decoration-thickness: 2px;
        text-underline-offset: 3px;
    }

    .article-card-body blockquote {
        margin: 1.8rem 0;
        padding: 1rem 1.2rem;
        border-left: 4px solid #2b6a62;
        background: #f6f7f2;
        color: #2f3b35;
        font-style: italic;
    }

    .article-card-body img,
    .article-card-body video {
        max-width: 100%;
        height: auto;
        border-radius: 10px;
        display: block;
        margin: 1.6rem auto;
    }

    .article-card-body hr {
        border: 0;
        border-top: 1px solid #ececec;
        margin: 2rem 0;
    }

    .article-card-body table {
        width: 100%;
        border-collapse: collapse;
        margin: 1.6rem 0;
        font-size: 0.98rem;
    }

    .article-card-body th,
    .article-card-body td {
        border: 1px solid #e3e3e3;
        padding: 10px 12px;
        text-align: left;
    }

    .article-card-body thead th {
        background: #f6f6f6;
        font-weight: 600;
    }

    /* Breadcrumbs override */
    .breadcrumbs {
        margin-bottom: 1rem;
        color: #666;
    }
    .breadcrumbs a {
        color: #444;
        text-decoration: none;
        font-weight: 500;
    }
    .breadcrumbs a:hover { text-decoration: underline; }
    .breadcrumbs .separator { margin: 0 8px; color: #999; }
    .breadcrumbs .current { color: #888; }
    
    /* Responsive */
    @media (max-width: 768px) {
        .article-hero-section {
            padding: 30px 0 80px;
        }
        .article-content-wrapper {
            padding: 24px;
        }
        .article-featured-image {
            margin: -24px -24px 24px -24px;
        }
        .article-card-header {
            padding: 34px 22px 26px;
        }
        .article-card-header h1 {
            font-size: 2rem;
        }
        .article-subtitle {
            font-size: 1rem;
        }
        .article-card-body {
            padding: 32px 24px 40px;
            font-size: 1.02rem;
        }
        .hero-content h1 {
            font-size: 1.8rem;
        }
        .related-page-banner {
            flex-direction: column;
            align-items: flex-start;
            gap: 15px;
        }
        .rpb-arrow {
            align-self: flex-end;
        }
    }

    /* Internal Links Grid */
    .internal-links-section {
        margin-top: 3rem;
        padding-top: 2rem;
        border-top: 1px solid #eee;
    }
    .internal-links-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 15px;
    }
    .internal-link-card {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px;
        background: #fff;
        border: 1px solid #e5e5e5;
        border-radius: 6px;
        text-decoration: none;
        color: #333;
        transition: all 0.2s;
    }
    .internal-link-card:hover {
        background: #fff;
        border-color: #ccc;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }

    /* Related Articles */
    .related-articles-section {
        margin-top: 3rem;
    }
    .related-articles-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }
    .related-article-card {
        display: block;
        background: #fff;
        border-radius: 8px;
        overflow: hidden;
        text-decoration: none;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transition: transform 0.2s;
    }
    .related-article-card:hover {
        transform: translateY(-5px);
    }
    .related-article-card img {
        width: 100%;
        height: 160px;
        object-fit: cover;
    }
    .related-article-content {
        padding: 15px;
    }
    .related-article-content h3 {
        font-size: 1.1rem;
        margin: 0 0 10px;
        color: #111;
    }
    .related-article-content p {
        font-size: 0.9rem;
        color: #666;
        margin: 0;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }</style>
