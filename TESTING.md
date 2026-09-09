# Validation — 0.1.0

Test environment: disposable local WordPress 7.1, PHP 8.5.7, MySQL, Chrome through Playwright. No production content was used.

- PHP syntax checks pass for every plugin PHP file; JavaScript syntax check passes.
- 48 WordPress integration assertions pass: save isolation, revision conflict, automatic and explicit-ID slug mapping (including ambiguous duplicates), safe HTML, confirmed title/slug/parent changes, parent-first creation, idempotent repeat selection, content/status preservation, metadata, hierarchy cycles, selected-batch dependencies, stale-post detection, refresh, anonymous/subscriber permissions, malformed input, mutation locking, manifest and redirect lookup, successful release caching, rate-limit backoff, update injection, stale-entry removal and details.
- Browser workflow passes: CSV quoting/import/template download, invalid import atomicity, Font Awesome rendering, selection, review, draft creation, title modal cancel/update/copy, parent move, missing-nonce denial, native plugin row links and no JavaScript errors.
- Axe-core 4.10.3 WCAG A/AA checks pass for the plugin surface at 1600×1050, 800×700 and 390×844, and for the confirmation dialog. Keyboard field traversal, slug normalisation and Escape cancellation pass. Automated checks do not replace a full assistive-technology audit.
- Empty, populated, saved, review, modal and invalid-import states were captured and visually inspected. Native notices sit above the branded hero. The wide planning table remains horizontally scrollable; no page-level horizontal overflow at mobile width.
- Package checks verify a single `tn-content-planner/` ZIP root, licence and readme, no nested ZIP or development files, and matching version metadata. The root ZIP matches the release package.

The interface uses Techn Author Branded mode and the navy/orange tokens in the supplied branding standard, with WordPress controls, scoped CSS and rem dimensions. Font Awesome vendor CSS is unmodified and exempt from the project CSS unit rule.

Not tested: older WordPress/PHP versions, multisite, third-party post-type plugins, multilingual plugins, screen readers and large production datasets. No claims of those tests are made. New-post drafting and update discovery/install are tested on the disposable local site.
