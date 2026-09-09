# Validation

## 0.3.17

Release/tag match `68dd958`; remote/local ZIP SHA-256: `d2050dd17a604bcd6ed80c08f36186ba2891ff2ee82aaf5a73fcbda10bb479e8`. Published ZIP installation through the WordPress installer passed and reports 0.3.17.

- 54 scan/pattern and 63 integration checks pass, including unique totals, done-without-example, valid examples, trashed and deleted examples.
- Browser checks verify live status/example counters and unchanged totals under Show Mine, plus existing review and pattern regressions.
- Key/record dots share orange; upper/lower key slots and right alignment pass desktop/mobile checks and Axe.
- PHP lint, JavaScript syntax and diff whitespace checks pass.


## 0.3.16

- Declaration moved before the other settings; the colon inherits the existing native/custom colour. JavaScript syntax, PHP lint and whitespace checks pass.
- Release/tag match `635816e`; remote/local ZIP SHA-256: `ff13fa4a8b29431941b52a73d7dc36766b7e76489482c50c443c25303379d23d`. Published ZIP installation through the WordPress installer passed and reports 0.3.16.


## 0.3.15

Release/tag match `8d66eb3`; remote/local ZIP SHA-256: `e1add5aa8d466942866373082baa5a1fcec874da3ce5b94569e5fe3cb25b9326`. Published ZIP installation through the WordPress installer passed and reports 0.3.15.

- Bulk-bin browser tests cover confirmation/cancel, child protection and ordering, mixed unlinked rows, partial failure, retry, persisted removals and remaining selection.
- The confirmation modal passes Axe WCAG A/AA; Map Selected is present. Existing 31 reconciliation integration checks pass.
- JavaScript syntax, PHP lint and whitespace checks pass.


## 0.3.14

- Native Publicly Queryable presentation now uses the existing affirmative tick; no backend registration or eligibility code changed. JavaScript syntax, PHP lint and whitespace checks pass.
- Release/tag match `d9b0ddb`; remote/local ZIP SHA-256: `a338c6b0b23ae5208698715758ea688724e57576cf868dc9b49dbd5264a73c11`. Published ZIP installation through the WordPress installer passed and reports 0.3.14.


## 0.3.13

Release/tag match `ce4f260`. Remote/local ZIP SHA-256: `9b8e404e2fe9581d9744a84d3fcb4210ee449527b013f37a69de594297611dfd`. Published ZIP installation through the WordPress installer passed and reports 0.3.13.

- Browser checks confirm example links sit beside dropdowns, with matching dropdown and ID-slot widths; screenshot inspected and Axe passed.
- `tests/tab-save.cjs` verifies button order, Cancel and Discard, saving both content and patterns before navigation, and simulated server failure preserving the current tab and edits.
- PHP lint, JavaScript syntax and diff whitespace checks pass.


## 0.3.12

- Browser verification confirms native Pages show an Include in Search tick and retain the grey native Publicly Queryable marker.
- Native/custom rendering and desktop/mobile/narrow Axe checks pass. JavaScript syntax, PHP lint and whitespace checks pass.


## 0.3.11

Release/tag match `85253dd`. Remote/local ZIP SHA-256: `cfcfef9d0f40a3c65dd6ad018296b938182e1f918781c415d8a6c94d681ba45f`. The published package installed successfully through the WordPress installer on the disposable site and reports 0.3.11.

- Browser verification confirms the grey query marker appears for native Pages and not for custom types, and Custom has its own colour class.
- Custom-bar contrast and desktop/mobile/narrow Axe checks pass, alongside existing header alignment, keyboard traversal and selection alignment checks.
- JavaScript syntax, PHP lint and diff whitespace checks pass.


## 0.3.10

- Added the requested page-scoped CSS rule. PHP lint and diff whitespace checks pass.
- Release/tag match `a2117a3`; remote/local ZIP SHA-256: `fbba1f4f7fe56db749f8fa1d81ecc06c136ff3abb0869ec238945d6e5c75ea81`.
- Published ZIP installed successfully through the WordPress installer on the disposable site and reports 0.3.10.


## 0.3.9

Release/tag match `b2c8f8c`. Remote/local ZIP SHA-256: `831eeb2880de52b7d85816a68fc18fe5872e13efabe5a5e4c1a1a28901be9432`. The native update check initially received the older 0.3.8 manifest from GitHub; this was independently confirmed through WordPress HTTP retrieval. Direct installation of the published ZIP through the WordPress installer succeeded and reports 0.3.9. Automatic discovery of 0.3.9 is not verified.

- Desktop, mobile and narrow browser checks verify exact watermark alignment with the eyebrow top and final tag right edge; screenshots visually inspected.
- Axe WCAG A/AA, modal accessibility, keyboard traversal and selection-column alignment pass. JavaScript syntax and PHP lint pass.


## 0.3.8 — corrected release numbering

Published/tagged source `55cd4ae`. GitHub latest resolves to v0.3.8; mistakenly numbered releases were returned to draft. Remote/local ZIP SHA-256: `c47beb1768db9de39b1fe74f801507f31a4701e31e034ac72d81206692465a86`. WordPress installer replacement of the disposable 0.6.1 installation with the public 0.3.8 ZIP succeeded and reports version 0.3.8.

Identical plugin behavior to the verified 0.6.1 build; only version and release documentation changed. The earlier verification records below retain their original version labels for accuracy.


## 0.6.1

Release/tag match `55ed542`. Remote/local ZIP SHA-256: `550d4c76b05b82d8a153dcca5543a88e25bedb05ff726eb3a57b89402827712c`. The first native check reported 0.4.0 current; after the new GitHub manifest became available, a retry discovered and installed 0.6.1, retained the saved plan, and reported it current. This validates the disposable site, not the user site’s updater issue.

- Browser checks compare horizontal positions of every selection checkbox, including the header. Desktop/mobile/narrow and modal Axe checks pass.


## 0.6.0

- Browser tests verify the original pattern rows remain mounted while Show Mine / Show All filters without network requests, and visible assignments match the logged-in user.
- Sticky heading position below the admin bar, bottom Add row placement and heading removal pass browser assertions. Existing assignment, equal-width dropdown, immediate-edit, reconciliation and Axe desktop/mobile checks pass.
- JavaScript syntax and PHP lint pass.


## 0.5.0

- 49 scan/pattern checks pass, including assigned-user persistence, site user listing and rejection of unknown assignments.
- Browser coverage confirms assignments survive reload and Example post dropdown widths match. Desktop/mobile Axe and existing immediate-edit/reconciliation regressions pass.
- PHP and JavaScript syntax checks pass.


## 0.4.0

Release/tag match `5726118`; remote/local ZIP SHA-256 `79b54934fe1f8c99cc9ad16587d7aed048a632c501a65419ffa9c9d3c7874332`. Native WordPress upgrade to 0.4.0 passed and retained the saved plan.

- 42 immediate-change checks cover field-only updates, all five flags, template metadata, XP keys, retained content/status, stale snapshots, invalid parents, unapproved changes and earlier saved approvals.
- Existing 63 integration, 31 reconciliation and 46 scan/visibility checks pass.
- Browser checks confirm modal approvals and direct metadata edits persist without Save/Review and clear pending markers. Existing reconciliation, CSV, patterns and counts coverage passes.
- Runtime CPT settings replace the non-hierarchical notice. Desktop/mobile/narrow layouts and modal pass Axe WCAG A/AA checks.
- PHP lint and JavaScript syntax checks pass.


## 0.3.4

Release/tag match `685bcc8`. Remote/local ZIP SHA-256: `3a9e94fd7bc0d567873fa32352049906ba5351c401e2737cf6535414f6503ced`. Native update to 0.3.4 succeeded on the disposable WordPress site and retained the saved plan. The following check reported the installed version current.

- Header watermark is sourced from the installed PHP version constant. Responsive bounds and Axe checks cover desktop, narrow and mobile widths.
- PHP template lint and package/version checks pass.


## 0.3.3

The first native update check immediately after publication still advertised 0.3.2 and installed that version. The later 0.3.4 check discovered and installed the current package successfully. This is not verification of the user site’s reported updater issue.

- Browser regression verifies present dots remain visible, missing dots are hidden (including both absent), and parent cells contain no editor link. Existing review, pattern editing and desktop/mobile Axe checks pass.
- JavaScript syntax and ZIP/version checks pass. Includes the 0.3.2 eligibility regression coverage.


## 0.3.2

- 46 scan/pattern checks pass, including all eight combinations of Public, Publicly Queryable and Exclude From Search. Custom-type visibility and REST access follow only Publicly Queryable. Runtime changes, native Posts/Pages and XP Pattern filtering are covered.
- 63 existing WordPress integration checks pass. PHP lint and package/version checks pass.


## 0.3.1

Release/tag match `863c4b7`. Remote and local ZIP SHA-256: `82ee0e81670f67e4b1962c44b317f932aa5296d528df7609e657a5470534cdae`. Native WordPress upgrade from 0.3.0 to 0.3.1 passed, retained the plan, and reported the installed version current afterward.

- 63 existing integration checks and 32 scan/pattern checks pass, including public/queryable/search-exclusion combinations, runtime settings changes, built-in Pages retention, REST denial and XP Pattern filtering.
- Browser regression and desktop/mobile Axe checks pass. The inline parent control has the same height as its dropdown; its icon opens the editor in a new tab. The control screenshot was visually inspected.
- PHP/JavaScript syntax and package/version checks pass.


## 0.3.0

Release and tag match source commit `264590b`. Remote and local ZIP SHA-256: `ab4d1c729f927741b00c962a76f628b76c96eb95d1d911101935a73c2f540b3f`. WordPress detected and installed 0.3.0 from 0.2.0 through the native plugin screen, retained the plan, and then reported the installed version current.

- 63 existing integration checks, 31 reconciliation checks and 27 scan, front-end visibility, pattern metadata and content/image indicator checks pass.
- Browser coverage includes complete scans, scanned-match reconciliation, pattern metadata persistence, editor-link targets, indicator labels, and unchanged table coordinates during the fixed spinner.
- Desktop/mobile pattern and review layouts pass Axe WCAG A/AA audits.


## 0.2.0

Published release and tag match commit `eb81122`; the remote ZIP SHA-256 matches the local package: `ba6b1f20d188abe390ebc1f59139f0b5c1e05270297aad220fa63e79ce42415a`. Native WordPress update from 0.1.2 to 0.2.0 succeeded and retained the saved plan. The subsequent check reported the installed version current.

- 63 existing WordPress integration checks and 31 reconciliation, refresh and bin checks pass.
- Browser coverage passes for ordered matching, all reconciliation choices, advancement, failed retry, skip, header checkbox states, live counts, tab refresh/spinner and linked-post binning.
- Desktop/mobile review and desktop/mobile/narrow planner layouts pass Axe WCAG A/AA checks; keyboard field traversal and the modal audit pass. Planner, review and bin screenshots were visually inspected.
- PHP/JavaScript syntax, package layout and version alignment were checked.


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
