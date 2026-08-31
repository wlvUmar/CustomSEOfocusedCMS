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
DOC;

    public const HARD_RULES = "Only touch data via tools. Keep RU/UZ separate. On failure say plainly; don't retry >1 silently.";
}
