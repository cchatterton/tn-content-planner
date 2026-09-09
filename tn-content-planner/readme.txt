=== TN Content Planner ===
Contributors:
Tags: content-planning, hierarchy, editorial, csv, drafts
Requires at least: 6.0
Tested up to: 7.1
Stable tag: 0.3.22
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Plan a content WBS by post type, import CSV, arrange parents and create selected WordPress posts.

== Description ==

Scans add existing editable posts to complete linked plans on initial load and tab clicks, preserving pending changes. Custom types are eligible when their registered Publicly Queryable setting is enabled, regardless of Public or Exclude From Search. Native Posts/Pages retain WordPress visibility handling. Edit permission is required and media attachments are excluded. The filter uses no site-specific name list. Drafts remain included for eligible types; trashed posts and auto-drafts are excluded. Removing only a row means the next scan will add its existing post again.

XP Patterns groups saved content items by pattern key with mapped / total item counts, a short description, todo/in-progress/done status an assigned site user and an example post. The description is the widest column. Examples are restricted to mapped items with the same pattern; content-table pattern keys open the example editor in a new tab. Pattern details are saved separately. Mapped Post IDs, review matches and pattern examples open the editor in a new tab. Parent selectors have no extra editor link. Two stacked dots beside mapped Post IDs show stored content (top) and featured image (bottom), visible only when present, with missing indicators completely hidden.


TN Content Planner by Techn provides a two-step content planning workflow in WordPress admin.

1. Plan your WBS: add rows in post-type tabs, edit safe title HTML, choose parents, templates and relationship flags, then save.
2. Review and create: select saved rows and apply new post creations or explicitly confirmed changes to linked posts.

Existing slugs map within the post type and parent for hierarchical types. Different parents may share a slug; sibling duplicates are rejected. Non-hierarchical types retain global slug uniqueness. Slugs are optional in plans and CSV imports, but rows without slugs cannot be selected or mapped. Circular hierarchies and stale edits are rejected.

New posts default to Published, with a Draft option in the review step. Publishing requires the post-type publish capability; users without it can create drafts. Existing posts keep their content and publication status. Removing a linked row offers keeping the post or moving it to the WordPress bin. Binning a linked row saves unsaved edits first and requires delete permission. If saving fails, the post stays in place. Approved linked title, slug and parent changes apply immediately. Linked template and relationship settings save immediately as planning metadata, without changing theme templates. Creation and reconciliation still advance one item at a time during review.

Font Awesome Free 6.7.2 is bundled locally for title previews. Permitted title tags are i, span, strong, em, b and br. Unsafe HTML attributes are removed. Your frontend theme must load its own icon styles if it renders icon markup in post titles.

== Installation ==

1. Upload tn-content-planner.zip via Plugins > Add New > Upload Plugin.
2. Activate TN Content Planner.
3. Open Content Planner in the WordPress admin menu.
4. Add or import rows, save the plan, select rows, and review before applying.

Access requires manage_options and the relevant post-type editing capabilities. The plugin supports per-site plans on multisite; it does not replicate plans across sites.

== Frequently Asked Questions ==

= What does Template do? =
Single, Archive and Custom are planning classifications saved as post metadata. They do not create PHP template files or assign theme templates.

= What is the XP pattern? =
posttype-level-template-count, for example page-1-single-3. Root level is 0 and the count includes only Local, Related, Children, Siblings and Parents. Rows sharing those values share the same pattern key. Each plan row separately has a unique internal ID.

= How does CSV import work? =
Download the blank template from the current post-type tab. Keep the columns in order:
title,slug

Only title and slug are accepted, in that order. Imported rows start with no parent, Single template and all relationship flags unchecked. Set other fields in the planner after importing. Unique existing slugs still map to posts when saved. Standard quoted CSV supports commas, newlines and double quotes in titles. Imports append and must be saved.

= What are the limits? =
2,000 plan rows and 2,000 catalog posts per post type; 1 MB per CSV; 100 hierarchy levels. Review uncreated ancestors before their children. For non-hierarchical post types, post_parent is stored but native permalinks and editors may not reflect it.

= What if another editor changes a post? =
Saving or applying is stopped when linked title, slug or parent differs from the saved snapshot. Clicking a post-type tab refreshes unchanged linked rows with a loading spinner while preserving saved pending changes. Clicking any tab automatically saves unsaved content or pattern edits before switching. Manual Save remains available; save failures preserve the current tab and edits. Removed or trashed linked posts must be restored before refreshing.

= How does review work? =
Selected items are shown one at a time as Item X of Y. Match suggestions use exact slug, exact title, then distinct shared title words. Accept source applies plan values, Accept destination adopts the existing post values, and Create new makes a separate post. Apply advances on success; failure stays on the current item. Skip leaves an item selected for later. New posts have a stable row marker to aid recovery after interrupted requests.

= What happens on deactivation? =
Plans, mappings and generated posts are retained. No content is deleted automatically.

== External services ==

GitHub is used only for plugin update discovery and ZIP downloads via WordPress. Requests send the server IP, standard HTTP headers and plugin version. No content plan or post data is sent. Successful checks are cached; network failures do not prevent planning. Update metadata is requested from raw.githubusercontent.com, with github.com and api.github.com as fallbacks. Updates are downloaded from GitHub release asset infrastructure.
Terms: https://docs.github.com/en/site-policy/github-terms/github-terms-of-service
Privacy: https://docs.github.com/en/site-policy/privacy-policies/github-general-privacy-statement

== Changelog ==

= 0.3.22 =
- Add a right-aligned grey open padlock to each post-type settings bar; locked state uses an orange closed padlock.
- Save pending edits before locking and persist the lock per post type across reloads.
- Disable editing and action controls while locked; preserve tab navigation and unlocking.
- Reject planner mutations on the server while locked and skip automatic scans until unlocked.


= 0.3.21 =
- Remove the two introductory and match-ranking instruction paragraphs from review.
- Show the source/destination comparison only when an existing match is selected or already linked.


= 0.3.20 =
- Trim leading and trailing whitespace from entered titles, slugs and XP Pattern descriptions before saving.
- Apply trimming to automatic saves, CSV imports and approved linked edits; preserve title HTML and internal spacing.
- Handle pasted non-breaking spaces and validate description lengths after trimming.


= 0.3.19 =
- Enable moving a linked post to the bin when the plan has unsaved edits.
- Automatically save first; if saving fails, keep the post and all edits intact.


= 0.3.18 =
- Allow hierarchical posts to share a slug under different parents; use parent-aware mapping, scanning, collision checks and creation.
- Allow blank slugs in saved plans and CSV imports; disable selection and mapping until a slug is entered.
- Show mapped / total content counts per XP Pattern and restrict examples to mapped items with that pattern.
- Make descriptions the widest XP Pattern column and link content-table pattern keys to their example editor.
- Save automatically before switching tabs, retain manual Save, and preserve the current tab and edits if saving fails.


= 0.3.17 =
* Add done-with-example / total counts to the XP Patterns tab.
* Add a bottom-right content/image key and orange record indicators.


= 0.3.16 =
* Move Native: or Custom: to the start of the settings bar, with matching colon colour.


= 0.3.15 =
* Add Send selected to bin with confirmation, child protection and retry after failures.
* Rename the review action to Map Selected.


= 0.3.14 =
* Show a Publicly Queryable tick for native types instead of the grey dot.


= 0.3.13 =
* Align example-post links beside dropdowns, reserving the same space for every row.
* Offer Discard, Cancel and Save plan now before switching tabs with unsaved changes.


= 0.3.12 =
* Show Include in Search with a tick for included types and a cross for excluded types.
* Keep the grey native marker only for Publicly Queryable.


= 0.3.11 =
* Use a grey Publicly Queryable marker for native types and orange text for Custom.
* Preserve inclusion of native Pages.


= 0.3.10 =
* Hide #screen-id on the Content Planner admin page.


= 0.3.9 =
* Simplify runtime settings to navy ticks, orange crosses and a Native/Custom declaration.
* Align the header version with the eyebrow and final feature tag.


= 0.3.8 =
* Apply approved linked changes and planning settings immediately.
* Add runtime CPT diagnostics, XP Pattern assignments and client-side filtering.
* Align dropdowns and selection checkboxes, retain table headings while scrolling, and move Add row below the table.
* Correct the unintended release numbering; includes all changes since 0.3.4.

= 0.3.4 =
* Show the installed version as a subtle watermark at the top right of the header.

= 0.3.3 =
* Completely hide missing content and featured-image dots, retaining their top/bottom positions.
* Remove parent editor links from dropdowns and review comparisons. Open the parent through its own mapped Post ID instead.
* Reduce tab spacing to 0.05rem.
* Includes 0.3.2's Publicly Queryable-driven custom post-type eligibility.

= 0.3.2 =
* Use each custom post type's registered Publicly Queryable setting as the sole front-end eligibility switch.
* Stop filtering custom types by Public or Exclude From Search. Keep WordPress's native Posts/Pages visibility handling, edit-permission checks and media exclusion.
* Use the same runtime rule for tabs, scans, REST access, examples and XP Patterns; no site-specific names are used.

= 0.3.1 =
* Derive planner eligibility from each site's registered post-type settings: public, front-end viewable, and not excluded from search. Custom types must also be publicly queryable. No site-specific post-type names are used.
* Apply eligibility consistently to tabs, scans, REST permissions, examples and XP Patterns.
* Place parent editor links beside their dropdowns as compact icons, keeping row heights consistent.

= 0.3.0 =
* Scan existing posts into complete linked plans on initial load and tab clicks, preserving pending changes and avoiding duplicate rows.
* Restrict post-type tabs and REST access to WordPress front-end-viewable types.
* Keep scanned posts available as review matches; absorb untouched scan rows when linking a planned item.
* Add XP Patterns with saved-plan counts, short descriptions, todo/in-progress/done status and example-post selection.
* Open mapped posts, parents, review references and pattern examples in a new editor tab.
* Add stacked content and featured-image indicators beside mapped Post IDs.
* Move the Ajax spinner to a fixed bottom-right position without shifting the page.
* Support up to 2,000 plan rows, including existing untitled drafts and legitimate shared slugs.

= 0.2.0 =
* Review selected rows one at a time, with Item X of Y, Apply & next, retry and skip.
* Suggest same-type matches by exact slug, exact title, then distinct shared title words.
* Accept plan values, adopt existing post values, or create a separate post.
* Add mapped/planned tab counters and a select-all header checkbox with partial selection state.
* Offer linked-post binning separately from removing only the plan row.
* Refresh linked posts on tab clicks with an Ajax spinner, preserving pending edits.
* Restore the header pill styling and bottom-right alignment; remove step buttons and redundant guidance.
* Offset the selected tab down by 1px.

= 0.1.2 =
* Default new posts to Published with a Draft option and publishing capability enforcement.
* Simplified the header and added three descriptive feature tags.
* Added red Pending change indicators for unapplied linked title, slug, parent, template and relationship-flag changes.

= 0.1.1 =

* Replaced row removal text buttons with discreet, labelled trash icons.
* Reduced CSV import and the downloadable template to title and slug only.
* Use public post types for planner tabs and REST access, including public custom types with hidden admin UI. Exclude non-public internal types.

= 0.1.0 =
* Initial WBS editor, CSV import, safe HTML previews, hierarchy planning and XP patterns.
* Selected draft creation and explicitly confirmed linked-post changes.
* Conflict, permission and hierarchy validation, and native GitHub updates.
