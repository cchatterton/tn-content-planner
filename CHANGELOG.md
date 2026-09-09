# Changelog

## 0.3.11 - 2026-09-09

- Show a neutral grey dot for Publicly Queryable on native post types, with an explanation that WordPress handles their public visibility.
- Keep Native in its existing colour and display Custom in accessible orange.
- Preserve post-type eligibility, including native Pages.

## 0.3.10 - 2026-09-09

- Hide #screen-id on the Content Planner admin page.

## 0.3.9 - 2026-09-09

- Simplify the runtime settings bar: omit the repeated post-type key, use navy ticks and orange crosses, and declare Native or Custom.
- Keep accessible Enabled/Disabled labels for the setting symbols.
- Align the version watermark with the eyebrow top and the right edge of the last feature tag across screen sizes.

## 0.3.8 - 2026-09-09

- Apply approved linked title, slug and parent changes immediately; save template and relationship metadata without a second review.
- Show runtime CPT settings in place of the non-hierarchical notice.
- Add XP Pattern user assignments, consistent dropdown widths, and a client-side Show Mine / Show All toggle.
- Keep table headings visible while scrolling, move Add row below the table, remove the redundant heading, and align selection checkboxes.
- Correct the unintended 0.4.0–0.6.1 release numbering. Includes all changes since 0.3.4.

## 0.3.4 - 2026-09-09

- Show the installed plugin version as a subtle watermark in the header's top-right corner.
- Read the watermark from the plugin version constant so it reflects the installed package.

## 0.3.3 - 2026-09-09

- Completely hide missing content and featured-image dots, retaining their top/bottom positions.
- Remove parent editor links from dropdowns and review comparisons. Open the parent through its own mapped Post ID instead.
- Reduce tab spacing to 0.05rem.
- Includes 0.3.2's Publicly Queryable-driven custom post-type eligibility.

## 0.3.2 - 2026-09-09

- Use each custom post type's registered Publicly Queryable setting as the sole front-end eligibility switch.
- Stop filtering custom types by Public or Exclude From Search. Keep WordPress's native Posts/Pages visibility handling, edit-permission checks and media exclusion.
- Use the same runtime rule for tabs, scans, REST access, examples and XP Patterns; no site-specific names are used.

## 0.3.1 - 2026-09-09

- Derive planner eligibility from each site's registered post-type settings: public, front-end viewable, and not excluded from search. Custom types must also be publicly queryable. No site-specific post-type names are used.
- Apply eligibility consistently to tabs, scans, REST permissions, examples and XP Patterns.
- Place parent editor links beside their dropdowns as compact icons, keeping row heights consistent.

## 0.3.0 - 2026-09-09

- Scan existing posts into complete linked plans on initial load and tab clicks, preserving pending changes and avoiding duplicate rows.
- Restrict post-type tabs and REST access to WordPress front-end-viewable types.
- Keep scanned posts available as review matches; absorb untouched scan rows when linking a planned item.
- Add XP Patterns with saved-plan counts, short descriptions, todo/in-progress/done status and example-post selection.
- Open mapped posts, parents, review references and pattern examples in a new editor tab.
- Add stacked content and featured-image indicators beside mapped Post IDs.
- Move the Ajax spinner to a fixed bottom-right position without shifting the page.
- Support up to 2,000 plan rows, including existing untitled drafts and legitimate shared slugs.

## 0.2.0 - 2026-09-09

- Review selected rows one at a time, with Item X of Y, Apply & next, retry and skip.
- Suggest same-type matches by exact slug, exact title, then distinct shared title words.
- Accept plan values, adopt existing post values, or create a separate post.
- Add mapped/planned tab counters and a select-all header checkbox with partial selection state.
- Offer linked-post binning separately from removing only the plan row.
- Refresh linked posts on tab clicks with an Ajax spinner, preserving pending edits.
- Restore the header pill styling and bottom-right alignment; remove step buttons and redundant guidance.
- Offset the selected tab down by 1px.

## 0.1.2 - 2026-09-09

- Defaulted new posts to Published, with a Draft selector and publish capability enforcement.
- Simplified the visible header and eyebrow and added Visual hierarchy, CSV import and Publish or draft tags.

- Marked unapplied linked title, slug, parent, template and relationship-flag changes in red with accessible Pending change labels.
- Kept pending indicators after saving and reloading the plan; clear them when the changes are applied.

## 0.1.1 - 2026-09-09

- Replaced row removal text buttons with discreet, labelled trash icons.

- Reduced CSV import and the downloadable template to title and slug only; configure all other fields in the planner.

- Fixed planner tabs and REST access to use public post types, including public custom types with hidden admin UI.
- Excluded non-public internal types while retaining editing capability checks and the attachment exclusion.

## 0.1.0 - 2026-09-09

- Added content WBS planning by post type with HTML title previews and live hierarchy indentation.
- Added CSV templates and import, slug mapping, relationship flags and XP pattern keys.
- Added saved-plan review, selected draft creation and confirmed linked-post changes.
- Added permission checks, conflict detection, circular-parent validation and recoverable batches.
- Added WordPress-native GitHub update discovery and installation support.
