# AGENTS.md — Project Guardrails for AI Agents

> If you are an AI agent, tool, or automation proposing or making changes in this repository, you **must** follow this document. Violations should block your own PRs.

---

## 0) Project Mission (read first)

- **Goal:** Blazing-fast PWA/SPA that honors the **14 KB first-flight rule** (HTML + inline critical CSS + tiny boot script ≤ **14 KB br/gz**), rendered **server-side by PHP**, with SPA-like navigation and progressive enhancement.
- **Hosting reality:** **PHP-only on the server. No Node runtime** server-side. Node is allowed **locally** for building assets.
- **Approach:** HTML-first + **htmx** navigation. Optional **JS “islands”** (tiny, on-demand) for richer widgets. Prefer **Preact** or vanilla for islands. Do **not** ship React DOM up front.

---

## 1) Non-Negotiables (hard requirements)

1. **14 KB First Flight**
   - `index.html` + **inline critical CSS** + boot module must be **≤ 14 KB compressed**.
   - No webfonts, icon fonts, or heavy CSS frameworks in first flight.
   - Boot script only `import()`s the current route chunk after first paint.

2. **PHP = SSR**
   - All routes render valid HTML from PHP. No JS required to read primary content.
   - SPA feel via **htmx** (or equivalent HTML fragment swaps) and History API.

3. **Micro-Caching**
   - Anonymous GET routes are micro-cached **3–5 s** at PHP/APCu, reverse proxy, or CDN.
   - Bypass cache on auth/session cookies. Use `stale-while-revalidate` grace (30–60 s) where possible.

4. **Service Worker**
   - Register **after first paint** (e.g., `requestIdleCallback`).
   - Cache strategy: App-shell + route HTML (short), static assets with hashes (long). No precaching of large, optional features.

5. **Accessibility & SEO**
   - Pages pass basic a11y (labels, roles, contrast) **server-rendered**.
   - Titles, meta, canonical, and language attributes must be correct at render time.

6. **Security**
   - No inline event handlers. No `eval`/`new Function`.
   - Use prepared statements on PHP DB access. Escape output by default.
   - Respect CSP if present; don’t weaken it without justification.

---

## 2) Allowed / Discouraged / Forbidden

### Allowed (prefer)
- **htmx** for partial page updates.
- **Alpine.js** or **Preact (islands only)** for small interactive components.
- Native Web APIs (Fetch, URL, Intl, IntersectionObserver).
- Minimal CSS (hand-rolled) or **Tailwind** with strict purge, **not** in first flight.

### Discouraged (needs explicit approval in PR)
- React + React-DOM (islands only, code-split, and not in first flight).
- UI kits that ship large CSS/JS (e.g., Bootstrap full bundle). If used, **tree-shaken** and lazy-loaded.

### Forbidden (do not add)
- Server-side Node frameworks (Next/Nuxt/Express runtime).
- Heavy client deps in first paint: React-DOM up front, jQuery, Moment.js, Lottie, large icon fonts.
- Webfonts on first flight; icon fonts at any time (use SVG).

---

## 3) Performance Budgets (CI should fail if exceeded)

- **First Flight:** ≤ **14 KB** (HTML + inline CSS + boot module).
- **Route Chunk (initial route):** ≤ **30 KB** br/gz.
- **Any Lazy Feature Chunk:** ≤ **50 KB** br/gz.
- **Total JS executed on landing:** ≤ **60 ms** on mid-range mobile (Lab).
- **CLS:** < 0.05, **LCP:** < 2.0 s (lab, throttled), **INP:** < 200 ms.

> If a budget is exceeded, your PR must include a justification and an alternative that meets budget.

---

## 4) Architecture Notes

- **Routing:** PHP maps URLs → views. Links render as normal `<a>`; enhanced with `hx-get`, `hx-push-url`, `hx-target` for SPA behavior.
- **Islands:** Mount only within elements annotated like `data-island="component"`. Load islands via `import()` only after the parent becomes visible (IntersectionObserver).
- **State:** Prefer server state + URL params. Client state minimal; avoid global stores unless isolated to an island.
- **Images:** Use responsive `<img srcset>` with **AVIF/WEBP**, lazy by default, explicit dimensions to avoid layout shift. No base64 mega inlines.
- **Fonts:** System stack by default. If brand fonts are required, **subset WOFF2**, lazy-load with `font-display: swap`, never in first flight.

---

## 5) Caching & Headers

- **Server (PHP)**
  - Micro-cache anonymous GETs: **3–5 s TTL**, grace 30–60 s.
  - Don’t cache responses with auth/session cookies.
  - Add `X-Micro-Cache: hit|miss|stale` for observability.

- **Static assets**
  - Filenames with content hash; `Cache-Control: public, max-age=31536000, immutable`.

- **HTML**
  - `Cache-Control: s-maxage=5, stale-while-revalidate=60` for CDN/proxy where applicable.

---

## 6) PWA Rules

- `manifest.webmanifest` minimal and accurate (name, icons, start_url, display).
- Service Worker:
  - Register after first paint.
  - Use **runtime caching** with small caps on HTML (respect micro-cache freshness).
  - Provide an offline fallback route (`/offline`).

---

## 7) Dependency Policy

- Default stance: **zero new deps**.
- If adding a dep:
  1. Justify: why native API or existing code isn’t enough.
  2. Size impact (min+br) on *first flight* and route chunk.
  3. Tree-shaking status and code-split plan.
  4. Security posture (last update, maintenance).
- PRs adding deps without this info should be auto-rejected.

---

## 8) Code Quality & Conventions

- **Languages:** PHP 8.x, TypeScript for any client code.
- **Linters/Formatters:** PHPCS (PHP), ESLint + Prettier (TS/JS), Stylelint if needed.
- **Commits:** Conventional Commits (`feat:`, `fix:`, `perf:`, `build:`…).
- **Tests:** At minimum, unit tests for islands and a Lighthouse CI check with budgets.

---

## 9) Release & Build

- Build tool: Vite/Rollup (local only). Output hashed bundles under `public/assets/`.
- Server cannot run Node; deploy the **built** artifacts only.
- Do not inline JS beyond the tiny boot module in HTML.

---

## 10) PR Checklist (agents must copy/paste and tick)

- [ ] First-flight bundle still ≤ **14 KB** (attach Lighthouse/Bundle report).
- [ ] No new runtime dep, or size/security justification provided.
- [ ] PHP renders complete content without JS; htmx only enhances UX.
- [ ] New routes are code-split and lazy-loaded.
- [ ] SW registers after first paint; no large precache list added.
- [ ] Micro-cache behavior unchanged or improved; bypass respected for auth.
- [ ] No regressions in a11y basics; titles/metas correct.
- [ ] All assets hashed; caching headers appropriate.
- [ ] Budgets (LCP/CLS/INP) pass in CI.

---

## 11) Special Notes for Agents

- If a requirement conflicts with your suggestion, **the requirement wins**. Propose an alternative that keeps the budgets.
- When uncertain, choose **HTML-first** with progressive enhancement.
- Do not remove performance guardrails, budgets, or this file.
- Do not introduce tracking/telemetry without explicit approval and a toggle.

---

*Owner’s intent:* keep the app **instant** for users. Respect the 14 KB first flight, render HTML on the server, sprinkle JS only where it pays for itself, and keep the stack boring and fast.
