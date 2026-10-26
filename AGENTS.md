# AGENTS.md — AI Studio + SEO Operator

- Bilingual RU/UZ. RU natural first, RU↔UZ parity on every write. RU drafts go live — UZ must match before publish.
- Prefer `.c-*` classes (c-hero-split, c-stats, c-feature-grid, etc.) + var(--teal) etc. No inline style="". Owner maintains CSS.
- Stats answers: one tool call (get_monthly_summary or get_site_overview), paste markdown verbatim, max 3 bullets. Never re-call same read with same args in one session — reuse history.
- Destructive tools (set_field full overwrite, delete_faq, set_rotation, restore_page_revision) need approval. Others auto-execute even >800 chars. Every write is snapshotted to page_revisions.
- SEO doctrine: W3C HTML5, Lighthouse 95+, WCAG 2.2 AA, hreflang ru/uz/x-default, BreadcrumbList/FAQPage, 40-60 word featured-snippet blocks. GSC queries are intent signals — max 1 exact-match per section.
- Page anatomy: slug = whole page (hero/body/FAQ/widgets/header/footer auto-assembled). content_ru/uz = body only — never draft shell parts into content.
- Page themes: `pages.theme` (default|screen=TV|frost=fridge|wave=washer|flame=gas stove|breeze=A/C|rug=carpet|wood=furniture|fresh=other appliances) restyles the whole page (hero/cards/steps/FAQ/links/fonts), text untouched. Set only via `set_page_style`, only when asked or for a new product page; read_skill page-themes first. Never imitate a theme with custom CSS/inline style. `set_page_theme` = colour tokens only. Mobile-first; keep card/step text short.
- Token workflow (proposal in docs/seo-operator-setup.md): Hermes reads via Bearer token GET /api/seo/v1/* — never store tokens in AGENTS.md.
- No fabricated data: every ranking/traffic claim must cite GSC/SERP/analytics tool output with timestamp. Report blockers honestly, never fake.
