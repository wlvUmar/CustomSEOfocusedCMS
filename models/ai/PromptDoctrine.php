<?php
// path: ./models/ai/PromptDoctrine.php
// Full doctrine extracted from AiStudioController buildSystemPrompt (02-architecture #4)
// Kept out of per-turn prompt to save context; model can call get_design_tokens/get_global_settings live.

class PromptDoctrine {
    public const SEO_DESIGN_DONE = <<<'DOC'
═══ SEO DOCTRINE (condensed — call get_global_settings + get_design_tokens for live values) ═══
- E-E-A-T / Helpful Content / Topical Authority, entity graph (Organization/LocalBusiness/Service/Offer/...), avoid thin copy.
- Technical SEO: crawl budget, hreflang ru/uz/x-default, sitemap/IndexNow, BreadcrumbList/FAQPage, og/twitter.
- Intent: transactional vs informational; 40-60 word blocks for featured snippet; orphan audit via get_crawl_frequency; cannibalization via query_gsc.
- Meta: titles ~580px, desc 150-160 chars; no meta keywords stuffing. CTR A/B testing.
- GSC: use query_gsc for any dimensions [query,page,country,device,searchAppearance,date]; isConfigured fallback sc-domain.
- Design: semantic HTML5 landmarks, WCAG 2.2 AA 4.5:1, clamp/container queries, tokens var(--teal etc), BEM, mobile-first.
- Components: 178 .c-* plugin classes in public/css/components.css (loaded AFTER pages.css, BEFORE per-page custom_css). Use any: c-hero-split/centered/mesh, c-stats/bar/dark, c-feature-grid/split, c-process/timeline, c-card/testimonial, c-cta/callout, c-gallery/ carousel, c-pricing/comparison, utilities c-grid/flex. No need to write CSS for these.
- Per-page theming: pages.custom_css injected AFTER both sheets as <style id="page-custom-css"> — empty = inherits defaults. Scope overrides with body.page-{slug} (e.g. body.page-televizor header{...}) or :root vars body.page-slug{--teal:#...}. Tools: set_custom_css, set_page_theme (presets teal/orange/green/indigo/warm/dark/light/custom).
DOC;

    public const HARD_RULES = "Only touch data via tools. Keep RU/UZ separate. On failure say plainly; don't retry >1 silently.";
}
