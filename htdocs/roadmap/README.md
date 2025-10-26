# Nexus Online — Vision 2025 Roadmap

This directory hosts the self-contained roadmap experience that powers `/roadmap` on thenexus.online. It is framework-free and uses Tailwind CSS from the CDN together with vanilla JavaScript.

## Files

| File | Purpose |
| --- | --- |
| `index.php` | Main roadmap page, wrapped with the site-wide PHP header/footer includes. |
| `roadmap.json` | Authoritative data source that powers the page and the embeddable widget. |
| `roadmap.js` | Client-side logic for filters, rendering, accessibility, JSON-LD, and the edit modal. |
| `roadmap.css` | Light utility styles (progress bars, chips, modal). |
| `widget.js` / `widget.css` | Compact roadmap widget that can be embedded elsewhere on the site. |
| `save.php` | Minimal POST handler that writes validated JSON back to `roadmap.json`. |

## Editing the roadmap

1. Open `/roadmap/` in a browser while logged in to an environment that can reach the PHP runtime.
2. Click **Edit JSON** → **Validate** to check structural issues before saving.
3. **Save** will POST to `/roadmap/save.php`. The PHP script performs the same guardrails (required fields, progress bounds). Make sure the web server user has write access to `roadmap.json` and keep backups.
4. If the POST fails (e.g., read-only hosting), the UI automatically downloads the updated `roadmap.json` so you can deploy manually.

### Manual editing

`roadmap.json` is grouped by phase. Each item supports:

- `id` – unique slug used for deep links and dependencies.
- `title` – plain string; a trailing `*` is appended automatically when `shipped` is `true`.
- `category` – array of roadmap categories displayed as chips.
- `status` – one of `Planned`, `In Progress`, or `Shipped`.
- `progress` – number from 0–100 that drives the progress bar.
- `phase` – Roman numeral key that matches the `phases` map and filter.
- `shipped` – boolean flag that drives the shipped-only toggle and the title star.
- `description` – short marketing-friendly summary.
- `dependencies` (optional) – array of other `id` values. Cards render deep links.
- `surveyTags` – array of survey alignment badges (`Nostalgia`, `RPG`, `PvP`, `PvE`, `New Mechanics`, `New Maps`, `Grind`, `Social`, `Visual`, `Economy`). These also power the “Relevance” sort weightings.

Keep the `filters` arrays in sync with any new categories or phases so the UI populates correctly.

## Deployment checklist

- Upload all files under `htdocs/roadmap/` to the web root, preserving paths.
- Ensure PHP has permission to write `roadmap.json` if you plan to save edits directly from the browser.
- Verify `/roadmap/index.php` loads without console errors, filters persist via URL parameters, and the light/dark toggle remembers the selected mode.
- Confirm `/roadmap/widget.js` works when embedded (see below). The widget fetches `roadmap.json`, so CORS must allow same-origin requests.

## Embedding the mini roadmap widget

Paste this snippet wherever you want the compact view to appear (e.g., homepage sidebar):

```html
<div id="nx-roadmap-widget"></div>
<script src="/roadmap/widget.js" defer></script>
<link rel="stylesheet" href="/roadmap/widget.css">
```

The widget renders the six highest “Relevance” items (weighted toward Nostalgia and RPG tags) with title, status, and a micro progress bar. Clicking a card opens `/roadmap/?q=<title>`.

## SEO helpers

`roadmap.js` injects meta tags for description, Open Graph, and Twitter cards, plus a JSON-LD `CreativeWorkSeries` that enumerates every roadmap item with deep links (`https://thenexus.online/roadmap/#<id>`).

### Sitemap snippet

Add this line to your sitemap to keep crawlers aware of the roadmap:

```xml
<url>
  <loc>https://thenexus.online/roadmap/</loc>
  <changefreq>weekly</changefreq>
  <priority>0.7</priority>
</url>
```

## Local testing tips

- Serve the project through PHP (e.g., `php -S localhost:8080 -t htdocs`) so `index.php` and `save.php` execute correctly.
- When testing widget styles, include `widget.css` in the host page.
- Use keyboard navigation (`Tab`/`Shift+Tab`) to verify focus states on filters, cards, and the modal close button.
