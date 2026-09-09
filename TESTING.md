# Validation

## 0.1.2

The published `v0.1.2` release ZIP was verified against the local package (SHA-256 `607ea4652e45e007f8417ecb9ab411656bd362e9197b2c50f3e86ed329b60139`). WordPress discovered and installed the update from 0.1.1 through the native plugin screen, preserved the saved plan and then reported the version current.

- 63 WordPress integration checks pass, including Published as the default for new content, explicit Draft creation, publishing permission enforcement and rejection of unsupported statuses.
- Browser regression verifies that pending title, slug, parent, template and relationship-flag indicators appear, survive save/reload and clear after application. The review selector defaults to Published and switches to Draft. The simplified header, eyebrow and all three feature tags are verified.
- Desktop pending-change states pass Axe WCAG A/AA checks. Desktop, mobile and narrow-width checks, keyboard navigation and modal accessibility also pass. Screenshots of the red pending states, feature tags and status selector were visually reviewed.
- PHP/JavaScript syntax checks and ZIP/version checks pass. The WordPress, general development and update standards remain applied. The Techn navy/orange palette remains, with visible header branding removed as requested.


## 0.1.1

- 60 WordPress integration checks pass, including public built-in and custom post types, public types with hidden admin UI, exclusion of non-public types and attachments, capability enforcement, and save/create through the REST API for a public custom type.
- Browser regression passes with public/hidden-UI/non-public fixtures. Public tabs appear; internal types are absent. Two-column CSV imports, the exact blank `title,slug` template, default planning fields and rejection of extra columns are verified.
- Row actions render an icon with no visible text, a tooltip and accessible name. Cancelling removal keeps the row. Existing linked-post confirmations and draft creation still pass.
- Axe WCAG A/AA, keyboard navigation and modal checks pass at desktop, mobile and narrow widths. The trash controls were visually reviewed in the horizontally scrolled table.
- PHP and JavaScript syntax checks pass. Techn Author Branded styling and the previously applied standards remain in place.

## 0.1.0

Test environment: disposable local WordPress 7.1, PHP 8.5.7, MySQL, Chrome through Playwright. No production content was used.

- PHP syntax checks pass for every plugin PHP file; JavaScript syntax check passes.
- 48 WordPress integration assertions pass: save isolation, revision conflict, automatic and explicit-ID slug mapping (including ambiguous duplicates), safe HTML, confirmed title/slug/parent changes, parent-first creation, idempotent repeat selection, content/status preservation, metadata, hierarchy cycles, selected-batch dependencies, stale-post detection, refresh, anonymous/subscriber permissions, malformed input, mutation locking, manifest and redirect lookup, successful release caching, rate-limit backoff, update injection, stale-entry removal and details.
- Browser workflow passes: CSV quoting/import/template download, invalid import atomicity, Font Awesome rendering, selection, review, draft creation, title modal cancel/update/copy, parent move, missing-nonce denial, native plugin row links and no JavaScript errors.
- Axe-core 4.10.3 WCAG A/AA checks pass for the plugin surface at 1600×1050, 800×700 and 390×844, and for the confirmation dialog. Keyboard field traversal, slug normalisation and Escape cancellation pass. Automated checks do not replace a full assistive-technology audit.
- Empty, populated, saved, review, modal and invalid-import states were captured and visually inspected. Native notices sit above the branded hero. The wide planning table remains horizontally scrollable; no page-level horizontal overflow at mobile width.
- Package checks verify a single `tn-content-planner/` ZIP root, licence and readme, no nested ZIP or development files, and matching version metadata. The root ZIP matches the release package.

The interface uses Techn Author Branded mode and the navy/orange tokens in the supplied branding standard, with WordPress controls, scoped CSS and rem dimensions. Font Awesome vendor CSS is unmodified and exempt from the project CSS unit rule.

Not tested: older WordPress/PHP versions, multisite, third-party post-type plugins, multilingual plugins, screen readers and large production datasets. No claims of those tests are made. New-post drafting and update discovery/install are tested on the disposable local site.

## Published release verification

Release `v0.1.0` was published and verified with its `tn-content-planner.zip` asset. The remote tag points to source commit `873804acf1b8ef0164ca26401670bd80c3b233dd`. The downloaded release asset and committed root ZIP share SHA-256 `14c1294043b8159ca90383f8009b2642f26ad40a416c39c62ffdb5a40798b7db`.

On the disposable site, an isolated installed copy was labelled 0.0.9 as an update fixture. The native plugin-row **Check for updates** displayed the 0.1.0 update, **update now** downloaded and installed the public release ZIP, the next check reported the installed version as current, and the saved page plan remained available. The repository source and released version were not modified by that fixture.
