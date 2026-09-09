=== TN Content Planner ===
Contributors:
Tags: content-planning, hierarchy, editorial, csv, drafts
Requires at least: 6.0
Tested up to: 7.1
Stable tag: 0.1.2
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Plan a content WBS by post type, import CSV, arrange parents and create selected WordPress posts.

== Description ==

TN Content Planner by Techn provides a two-step content planning workflow in WordPress admin.

1. Plan your WBS: add rows in post-type tabs, edit safe title HTML, choose parents, templates and relationship flags, then save.
2. Review and create: select saved rows and apply new post creations or explicitly confirmed changes to linked posts.

Existing slugs map to editable posts in the same post type. Ambiguous matches are rejected; use a unique slug. Circular hierarchies, duplicate planned slugs and stale edits are rejected.

New posts default to Published, with a Draft option in the review step. Publishing requires the post-type publish capability; users without it can create drafts. Existing posts keep their content and publication status. Removing a plan row does not delete its post. Saves store the plan only; changes to WordPress posts are applied in Step 2.

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
500 plan rows and 2,000 catalog posts per post type; 1 MB per CSV; 50 selected rows per apply batch; 100 hierarchy levels. Include uncreated ancestors in the selected batch. For non-hierarchical post types, post_parent is stored but native permalinks and editors may not reflect it.

= What if another editor changes a post? =
Saving or applying is stopped when linked title, slug or parent differs from the saved snapshot. Refresh linked posts reloads those fields and discards pending linked changes. Save or copy any unsaved work before refreshing. Removed or trashed linked posts must be restored before refreshing.

= Is applying a batch atomic? =
No. Completed rows are persisted individually. A failure stops the batch and leaves completed rows linked. Retry the remaining rows. New posts have a stable row marker to aid recovery after interrupted requests.

= What happens on deactivation? =
Plans, mappings and generated posts are retained. No content is deleted automatically.

== External services ==

GitHub is used only for plugin update discovery and ZIP downloads via WordPress. Requests send the server IP, standard HTTP headers and plugin version. No content plan or post data is sent. Successful checks are cached; network failures do not prevent planning. Update metadata is requested from raw.githubusercontent.com, with github.com and api.github.com as fallbacks. Updates are downloaded from GitHub release asset infrastructure.
Terms: https://docs.github.com/en/site-policy/github-terms/github-terms-of-service
Privacy: https://docs.github.com/en/site-policy/privacy-policies/github-general-privacy-statement

== Changelog ==

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
