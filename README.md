# Albert Ruiz de la Oliva — Personal Website

Personal website for Albert Ruiz de la Oliva: Medical Affairs × Artificial
Intelligence × Digital Innovation × Scientific Engagement × Education ×
Community. Positioned around "The Future of Medical Affairs," not as a
traditional MSL portfolio/CV.

Static HTML5 + CSS3 + vanilla JS. No build step, no framework, no npm
dependency — works from any static host (Hostinger, GitHub Pages, Netlify…).

## Design system

White + dark text + purple, Poppins throughout. Design tokens (colors,
spacing, radius, shadows, animation timings) live at the top of `styles.css`
and mirror the brief exactly:

- `--primary: 272 100% 45%` / `--accent: 280 80% 55%`
- `--secondary` / `--muted` / `--border` / `--section-alt` per spec
- Buttons: `.btn-primary` / `.btn-secondary` / `.btn-outline`
- Cards: `.card`, `.expertise-card`, `.content-card`, `.card-elevated`
- Timeline: `.timeline`, `.timeline-item`, `.timeline-dot`
- Reveal animations: `.reveal` (fade-up), `.reveal-fade`, `.reveal-scale`,
  with `.delay-100…500`, gated by `prefers-reduced-motion`.

> Note: this project was built without direct file access to the referenced
> Lovable project `albert-msl-digital-bridge`. The design system was
> reconstructed from the exact design tokens, component classes, spacing and
> behavior described in the build brief (Tailwind-equivalent tokens,
> component names, animation timings). If you can share the original
> project's exported code or a design/Figma reference, it should be a direct
> drop-in comparison against `styles.css`'s token block to confirm an exact
> match — happy to reconcile any pixel-level differences.

## Structure

```
index.html                     ← Home (all sections per brief, in order)
about.html, insights.html, ai-medical-affairs.html,
digital-medical-affairs.html, lab.html, digital-opinion-leaders.html,
projects.html, community.html, resources.html, speaking.html,
work-with-me.html, tools.html, legal.html, privacy.html
insights/                      ← full articles (Article schema)
medical-affairs/, msl/         ← SEO pillar sub-pages
styles.css                     ← single design-system stylesheet
main.js                        ← IIFE entry point (nav, reveals, forms)
lib/manifest.js                ← window.__BRAND__ data
assets/                        ← favicon.svg, og-cover.svg (placeholders)
sitemap.xml, robots.txt, .htaccess
```

## What's live vs. roadmap

Built and content-complete: all 13 primary pages, 2 full Insights articles,
3 SEO pillar pages (`/medical-affairs/*`, `/msl/*`), Person/Article/FAQ/
Breadcrumb schema, OG/Twitter cards, sitemap, robots.txt, `.htaccess`.

Intentionally scaffolded as roadmap (flagged in-page, not faked as live):
- **Ask Albert AI** — visual preview only (`/tools.html`); wiring it to a real
  RAG backend needs an actual API/hosting decision.
- **Interactive Tools** (`/tools.html`) — cards describe each planned tool;
  none are functional yet (no backend in a static site).
- Additional deep pillar URLs (`/ai-medical-affairs/prompts`,
  `/digital-medical-affairs/omnichannel`, `/msl/msl-skills`, etc.) — the
  brief's full URL architecture is documented but not all pages exist yet to
  avoid shipping thin/duplicate content.
- `/en/` `/es/` bilingual trees — `hreflang`/canonical scaffolding is in
  place per page; actual translated content isn't built yet.
- Newsletter and contact forms currently simulate submission (newsletter) or
  open a pre-filled email (contact) — wire to a real ESP/endpoint before
  launch.

## Content integrity

No fabricated employers, projects, press or stats beyond what was provided:
Sanofi, Fresenius Kabi, ESTEVE, PiLeJe, AMGEN (career), DMSL and ROCK&DOL
(named community/education initiatives). Placeholder profile photo uses
initials ("AR"); og-cover/favicon are generated SVGs pending real brand
assets.

## Local preview

```
python3 -m http.server 8000
# open http://localhost:8000/
```

## Deploy

Static files — upload as-is to Hostinger (or any static host) at the domain
root. `.htaccess` handles cache headers; bump `?v=` in the HTML `<script>`/
`<link>` tags on every deploy to bust the browser cache.
