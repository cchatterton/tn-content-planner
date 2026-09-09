# TN Content Planner

Author: Techn · Version: 0.2.0 · Branding mode: Author Branded

A WordPress content planning wizard: plan a WBS by post type, then review and create selected posts or apply confirmed changes to linked posts.

## Install

Upload the root `tn-content-planner.zip` through WordPress Plugins → Add New → Upload Plugin. Activate it, then open **Content Planner**. GitHub release updates appear in the native Plugins screen.

## Scope and decisions

- The task header reads Content Planner, with the eyebrow Plan. Organise. Publish. and three feature tags: Visual hierarchy, CSV import, Publish or draft. Plugin metadata retains TN Content Planner / Techn authorship.

- Unapplied linked-post title, slug, parent, template and relationship-flag changes are marked red with a Pending change label. Saving preserves these indicators; applying the changes clears them.

- Tabs include public registered post types the current user can edit, even when their native admin UI is hidden. Attachments and non-public internal types are excluded.

- Tabs show mapped/planned counts. Click a tab to refresh linked data; the loading spinner remains visible until completion. The table header checkbox selects or clears all rows and shows partial selection.
- Review selected rows one at a time: Item X of Y. Apply advances only after success; Skip leaves the item selected for later. Parents are reviewed first.
- Match ranking: exact slug, exact title, then the number of distinct shared title words within the same post type. Repeated words count once. Up to five suggestions are shown, plus the currently linked post where needed.
- Accept source applies the plan title, slug, parent, template and flags to the chosen post while preserving its content/status. Accept destination adopts the chosen post’s values into the plan without changing the post. Create new makes a separate post with a unique slug. Planned children follow the new row reference but move in WordPress only when reviewed.
- Plan save is separate from post creation and mutation. Modal choices stage title, slug and parent changes for review and apply.
- “Create new plan item” keeps the original mapped item and gives the copy a unique slug, adding a suffix if necessary.
- Parent references can target planned rows or existing posts. Root depth is 0. The display moves and indents descendants immediately.
- XP pattern is `posttype-level-template-flagcount`, e.g. `page-1-single-3`. It identifies a pattern combination, not an individual row. Identical combinations intentionally share a key; rows have independent UUIDs.
- Single / Archive / Custom and the five flags are planning metadata, not theme-template generation or automatic related-content queries.
- New posts default to Published, with a Draft option in the review step; updates retain existing content and publication status. Publishing requires the post type’s publish capability; users without it can create drafts.
- Slug mapping uses the current post type. Ambiguous existing slugs are rejected; choose a unique slug. Duplicate planned slugs are rejected, including an attempted rename onto another post's slug.
- Font Awesome Free is bundled for admin preview. Safe HTML allowlist: `i`, `span`, `strong`, `em`, `b`, `br`; `class` and `aria-hidden` on `i`/`span`. Frontend icon loading belongs to the active theme.
- Limits: 500 rows / 2,000 catalog posts per type; 1 MB CSV; 100 hierarchy levels. Post types with no native hierarchy still store `post_parent`, without changing their permalink rules.

## CSV

Use the per-tab **Download CSV template** button. The blank CSV contains:

```csv
title,slug
```

Only `title` and `slug` are accepted, in that order. Imported rows start with no parent, Single template and all relationship flags unchecked. Set the other fields in the planner after import. Slugs are normalised and unique existing slugs still map to posts when saved. Imports append, never replace; invalid imports leave the plan unchanged. Standard CSV quoting supports HTML, commas and newlines in titles.

## Data and recovery

Each site's `tncp_plan_{post_type}` option stores a revision and rows, including stable row IDs, post links, snapshots and confirmed pending changes. Options do not autoload. Generated posts use `_tncp_row_id` as a durable recovery marker, plus `_tncp_template`, `_tncp_flags` and `_tncp_pattern` metadata. `tncp_lock_{post_type}` serialises writes; an interrupted request's lock expires after ten minutes.

A stale plan revision or linked title/slug/parent stops mutation. Clicking a post-type tab reloads linked values for unchanged rows and preserves saved pending edits. Unsaved edits require an explicit discard before reloading. A trashed/deleted mapped post must be restored or its row removed. For a pending row with an external conflict, review the current destination before choosing which values to accept.

Removing a linked row offers **Remove row only** or **Remove row & move post to bin**. Binning requires a saved plan, delete permission and an enabled WordPress bin; permanent deletion is never used. Planned children must be moved first. Deactivation/uninstall retains plans and content.

Each review action persists independently. Failures stay on the current item for retry; completed items remain linked. The legacy batch endpoint remains available, capped at 50 selected rows. Third-party WordPress save filters may adjust submitted fields; the plugin records actual values and stops for review.

## Development and release

Source is in `tn-content-planner/`; repository tooling is outside the distributable.

```sh
find tn-content-planner -name '*.php' -exec php -l {} \;
node --check tn-content-planner/scripts/tn-content-planner.js
wp eval-file tests/integration.php --path=/path/to/disposable/wordpress
wp eval-file tests/reconciliation.php --path=/path/to/disposable/wordpress
wp eval-file tests/seed-review.php --path=/path/to/disposable/wordpress > /tmp/tncp-review-fixture.json
TNCP_TEST_PASSWORD=your-local-password TNCP_REVIEW_FIXTURE=/tmp/tncp-review-fixture.json node tests/browser.cjs
scripts/build-plugin-zip.sh
```

The browser suite requires Playwright and Chrome, and uses a disposable WordPress site at `127.0.0.1:8765`, with test administrator `tncp_admin`. The seed replaces the page plan and removes prior browser-review fixtures; use only a disposable database. PHP integration tests clean up their own generated posts and restore the original page plan.

Follow [codex-standards](https://github.com/cchatterton/codex-standards): general development, WordPress plugin, branding/UX and GitHub update standards. Release versions must match in the header, constant, readme Stable tag, update manifest and changelog. Build and commit the root ZIP, push, publish a matching `vX.Y.Z` release with that exact ZIP, then verify native WordPress update delivery.

The manifest-first updater falls back to the public latest-release redirect and only then GitHub's API. Successful release cache and failure backoff are separate. Manual checks are capability-gated and nonce-protected. No plan data is sent to GitHub.

## Licences

Plugin: GPL v2 or later. Font Awesome Free 6.7.2: CSS code MIT, fonts SIL OFL 1.1, icons CC BY 4.0. The upstream licence is included under `tn-content-planner/assets/fontawesome/LICENSE.txt`.
