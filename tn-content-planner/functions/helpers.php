<?php
if (!defined('ABSPATH')) { exit; }

function tncp_error($message, $status = 400) {
    return new WP_Error('tncp_error', $message, array('status' => $status));
}

function tncp_types() {
    $types = get_post_types(array(), 'objects');
    foreach ($types as $name => $type) {
        $front_end = $type->_builtin ? is_post_type_viewable($type) : (bool) $type->publicly_queryable;
        if ('attachment' === $name || !$front_end || !current_user_can($type->cap->edit_posts)) { unset($types[$name]); }
    }
    return $types;
}

/** Runtime registration values, rather than a plugin-specific configuration copy. */
function tncp_type_settings($name) {
    $type = get_post_type_object($name);
    if (!$type) { return array(); }
    return array('locked' => (bool) get_option('tncp_locked_' . $name, false), 'name' => $type->name, 'public' => $type->public, 'publicly_queryable' => $type->publicly_queryable,
        'exclude_from_search' => $type->exclude_from_search, 'hierarchical' => $type->hierarchical, '_builtin' => $type->_builtin);
}

/** Trim pasted Unicode whitespace as well as ordinary spaces and line breaks. */
function tncp_trim($value) {
    return preg_replace('/^[\s\p{Z}\x{FEFF}]+|[\s\p{Z}\x{FEFF}]+$/u', '', $value) ?? trim($value);
}

function tncp_title($title) {
    return tncp_trim(wp_kses($title, array(
        'i' => array('class' => true, 'aria-hidden' => true),
        'span' => array('class' => true, 'aria-hidden' => true),
        'strong' => array(), 'em' => array(), 'b' => array(), 'br' => array(),
    )));
}

function tncp_snapshot($post) {
    return array('title' => $post->post_title, 'slug' => $post->post_name, 'parent' => (int) $post->post_parent);
}

function tncp_post_planning($post_id) {
    $stored_flags = get_post_meta($post_id, '_tncp_flags', true);
    $flags = array();
    foreach (array('local', 'related', 'children', 'siblings', 'parents') as $flag) { $flags[$flag] = !empty($stored_flags[$flag]); }
    $template = get_post_meta($post_id, '_tncp_template', true);
    return array('template' => in_array($template, array('single', 'archive', 'custom'), true) ? $template : 'single', 'flags' => $flags);
}

function tncp_catalog($type) {
    $posts = get_posts(array('post_type' => $type, 'post_status' => array('publish', 'draft', 'pending', 'private', 'future'), 'numberposts' => 2001, 'orderby' => 'ID', 'order' => 'ASC', 'suppress_filters' => false));
    if (count($posts) > 2000) { return tncp_error(__('This post type exceeds the 2,000-post catalog limit. Narrow the post type before planning.', 'tn-content-planner')); }
    $catalog = array();
    foreach ($posts as $post) {
        if (current_user_can('edit_post', $post->ID)) {
            $planning = tncp_post_planning($post->ID);
            $catalog[] = array_merge(array('id' => $post->ID, 'has_content' => '' !== trim($post->post_content), 'has_featured_image' => has_post_thumbnail($post->ID), 'planning' => $planning, 'can_trash' => defined('EMPTY_TRASH_DAYS') && EMPTY_TRASH_DAYS > 0 && current_user_can('delete_post', $post->ID)), tncp_snapshot($post));
        }
    }
    return $catalog;
}

function tncp_plan($type) {
    return get_option('tncp_plan_' . $type, array('revision' => 0, 'rows' => array()));
}

/** Resolve mixed planned/existing ancestry, including existing descendants of planned posts. */
function tncp_ancestry($row, $rows, $type) {
    $by_id = array_column($rows, null, 'id');
    $by_post = array();
    foreach ($rows as $item) { if ($item['post_id']) { $by_post[$item['post_id']] = $item; } }
    $seen = array('row:' . $row['id'] => true);
    if ($row['post_id']) { $seen['post:' . $row['post_id']] = true; }
    $parent = $row['parent'];
    $level = 0;
    while ($parent) {
        if (isset($seen[$parent]) || $level > 100) { return tncp_error(__('The hierarchy contains a circular parent relationship or exceeds 100 levels.', 'tn-content-planner')); }
        $seen[$parent] = true;
        ++$level;
        if (str_starts_with($parent, 'row:')) {
            $ancestor = $by_id[substr($parent, 4)] ?? null;
            if (!$ancestor) { return tncp_error(__('A planned parent is missing.', 'tn-content-planner')); }
            if ($ancestor['post_id']) {
                $post_key = 'post:' . $ancestor['post_id'];
                if (isset($seen[$post_key])) { return tncp_error(__('The hierarchy contains a circular parent relationship.', 'tn-content-planner')); }
                $seen[$post_key] = true;
            }
            $parent = $ancestor['parent'];
        } else {
            $id = (int) substr($parent, 5);
            if (isset($by_post[$id])) {
                $ancestor = $by_post[$id];
                $key = 'row:' . $ancestor['id'];
                if (isset($seen[$key])) { return tncp_error(__('The hierarchy contains a circular parent relationship.', 'tn-content-planner')); }
                $seen[$key] = true;
                $parent = $ancestor['parent'];
            } else {
                $post = get_post($id);
                if (!$post || $post->post_type !== $type || in_array($post->post_status, array('trash', 'auto-draft'), true) || !current_user_can('edit_post', $id)) {
                    return tncp_error(__('An existing parent is unavailable or cannot be edited.', 'tn-content-planner'));
                }
                $parent = $post->post_parent ? 'post:' . $post->post_parent : '';
            }
        }
    }
    return $level;
}

function tncp_parent_id($row, $rows) {
    if (!$row['parent']) { return 0; }
    if (str_starts_with($row['parent'], 'post:')) { return (int) substr($row['parent'], 5); }
    foreach ($rows as $parent) {
        if ('row:' . $parent['id'] === $row['parent']) { return $parent['post_id'] ?: -1; }
    }
    return -1;
}

/** Hierarchical slugs are unique per parent; unresolved parents retain their row identity. */
function tncp_slug_scope($type, $row, $rows) {
    if (!is_post_type_hierarchical($type)) { return ''; }
    $parent = tncp_parent_id($row, $rows);
    return $parent < 0 ? $row['parent'] : 'post:' . $parent;
}
function tncp_slug_matches($type, $row, $post, $rows) {
    return '' !== $row['slug'] && $row['slug'] === $post['slug'] && (!is_post_type_hierarchical($type) || tncp_slug_scope($type, $row, $rows) === 'post:' . $post['parent']);
}

function tncp_validate_rows($input, $type, $old, $confirmation_ids = null) {
    if (!is_array($input) || count($input) > 2000) { return tncp_error(__('Use no more than 500 rows per post type.', 'tn-content-planner')); }
    $catalog = tncp_catalog($type);
    if (is_wp_error($catalog)) { return $catalog; }
    $posts = array_column($catalog, null, 'id');
    $old_rows = array_column($old['rows'], null, 'id');
    $rows = array(); $ids = array(); $mapped = array();
    foreach ($input as $raw) {
        if (!is_array($raw)) { return tncp_error(__('Invalid plan row.', 'tn-content-planner')); }
        foreach (array('id', 'title', 'slug', 'parent', 'template') as $field) {
            if (!isset($raw[$field]) || !is_string($raw[$field])) { return tncp_error(__('A required row field is missing.', 'tn-content-planner')); }
        }
        $id = $raw['id'];
        if (!preg_match('/^[a-zA-Z0-9_-]{1,80}$/', $id) || isset($ids[$id])) { return tncp_error(__('Row IDs must be unique.', 'tn-content-planner')); }
        $ids[$id] = true;
        $previous = $old_rows[$id] ?? null;
        $native_title = !empty($previous['post_id']) && $raw['title'] === $previous['baseline']['title'];
        $native_slug = !empty($previous['post_id']) && $raw['slug'] === $previous['baseline']['slug'];
        $title = $native_title ? $raw['title'] : tncp_title($raw['title']);
        $slug = sanitize_title(tncp_trim($raw['slug']));
        if ((!$native_title && (strlen($title) > 4000 || '' === trim(wp_strip_all_tags($title)))) || (!$native_slug && strlen($slug) > 200)) { return tncp_error(__('Every row needs a text title (up to 4,000 bytes). Optional slugs must be at most 200 characters.', 'tn-content-planner')); }
        if (!in_array($raw['template'], array('single', 'archive', 'custom'), true)) { return tncp_error(__('Choose Single, Archive or Custom.', 'tn-content-planner')); }
        if ($raw['parent'] && !preg_match('/^(row:[a-zA-Z0-9_-]{1,80}|post:[1-9][0-9]*)$/', $raw['parent'])) { return tncp_error(__('Invalid parent reference.', 'tn-content-planner')); }
        if (isset($raw['post_id']) && (!is_scalar($raw['post_id']) || !preg_match('/^[0-9]+$/', (string) $raw['post_id']))) { return tncp_error(__('Post ID must be a non-negative integer.', 'tn-content-planner')); }
        $post_id = absint($raw['post_id'] ?? 0);
        $previous = $old_rows[$id] ?? null;
        if ($previous && $previous['post_id'] && $previous['post_id'] !== $post_id) { return tncp_error(__('A linked row cannot be detached. Create a new plan item instead.', 'tn-content-planner')); }
        $baseline = null;
        $flags = array();
        foreach (array('local', 'related', 'children', 'siblings', 'parents') as $flag) { $flags[$flag] = !empty($raw['flags'][$flag]); }
        $confirmed = array();
        foreach (array('title', 'slug', 'parent') as $field) { $confirmed[$field] = !empty($raw['confirmed'][$field]); }
        $rows[] = array('id' => $id, 'title' => $title, 'slug' => $slug, 'parent' => $raw['parent'], 'template' => $raw['template'], 'flags' => $flags, 'post_id' => $post_id, 'baseline' => $baseline, 'confirmed' => $confirmed, 'scanned' => !empty($previous['scanned']));
    }
    // Resolve planned parents first, so input order does not affect slug mapping.
    $resolving = array(); $resolved = array();
    $resolve = function($index) use (&$resolve, &$rows, &$resolving, &$resolved, $catalog, $type) {
        if (isset($resolved[$index]) || isset($resolving[$index])) { return; }
        $resolving[$index] = true;
        $parent_index = array_search(substr($rows[$index]['parent'], 4), array_column($rows, 'id'), true);
        if (str_starts_with($rows[$index]['parent'], 'row:') && false !== $parent_index) { $resolve($parent_index); }
        if (!$rows[$index]['post_id']) {
            $matches = array_values(array_filter($catalog, static fn($post) => tncp_slug_matches($type, $rows[$index], $post, $rows)));
            if (1 === count($matches)) { $rows[$index]['post_id'] = $matches[0]['id']; }
        }
        $resolved[$index] = true;
    };
    foreach (array_keys($rows) as $index) { $resolve($index); }
    foreach ($rows as &$row) {
        $post_id = $row['post_id']; $previous = $old_rows[$row['id']] ?? null;
        if ($post_id && !isset($posts[$post_id])) { return tncp_error(__('A linked post is unavailable or cannot be edited.', 'tn-content-planner')); }
        if ($post_id && isset($mapped[$post_id])) { return tncp_error(__('A post can only be linked to one row in a post-type plan.', 'tn-content-planner')); }
        if ($post_id) { $mapped[$post_id] = true; }
        $row['baseline'] = $post_id ? array_intersect_key($posts[$post_id], array_flip(array('title', 'slug', 'parent'))) : null;
        if ($previous && $previous['post_id'] && $previous['baseline'] !== $row['baseline']) { return tncp_error(__('A linked post changed outside this plan. Reload the saved plan to refresh it before editing.', 'tn-content-planner'), 409); }
        $matches = array_filter($catalog, static fn($post) => $post['id'] !== $post_id && tncp_slug_matches($type, $row, $post, $rows));
        if ($matches && (!$post_id || $row['slug'] !== $row['baseline']['slug'] || tncp_parent_id($row, $rows) !== $row['baseline']['parent'])) { return tncp_error(__('That slug already exists under this parent.', 'tn-content-planner')); }
    }
    unset($row);
    $slugs = array();
    foreach ($rows as &$row) {
        $level = tncp_ancestry($row, $rows, $type);
        if (is_wp_error($level)) { return $level; }
        $row['pattern'] = $type . '-' . $level . '-' . $row['template'] . '-' . count(array_filter($row['flags']));
        $key = tncp_slug_scope($type, $row, $rows) . '|' . $row['slug'];
        if ($row['slug'] && isset($slugs[$key]) && !($row['post_id'] && $row['baseline']['slug'] === $row['slug'] && $slugs[$key]['post_id'] && $slugs[$key]['baseline']['slug'] === $row['slug'] && tncp_parent_id($row, $rows) === $row['baseline']['parent'] && tncp_parent_id($slugs[$key], $rows) === $slugs[$key]['baseline']['parent'])) { return tncp_error(__('Each planned slug must be unique under its parent (or across a non-hierarchical post type).', 'tn-content-planner')); }
        $slugs[$key] = $row;
        if ($row['baseline'] && (null === $confirmation_ids || in_array($row['id'], $confirmation_ids, true))) {
            $desired = array('title' => $row['title'], 'slug' => $row['slug'], 'parent' => tncp_parent_id($row, $rows));
            foreach ($desired as $field => $value) {
                if ($value !== $row['baseline'][$field] && !$row['confirmed'][$field]) { return tncp_error(sprintf(__('Confirm the linked post %s change before saving.', 'tn-content-planner'), $field)); }
            }
        }
    }
    unset($row);
    return $rows;
}

function tncp_plan_counts($type) {
    $plan = tncp_plan($type);
    $ids = array_values(array_filter(array_unique(array_column($plan['rows'], 'post_id'))));
    $mapped = 0;
    if ($ids) {
        $posts = get_posts(array('post_type' => $type, 'post__in' => $ids, 'post_status' => array('publish', 'draft', 'pending', 'private', 'future'), 'numberposts' => count($ids), 'suppress_filters' => false));
        foreach ($posts as $post) { if (current_user_can('edit_post', $post->ID)) { ++$mapped; } }
    }
    return array('mapped' => $mapped, 'planned' => count($plan['rows']));
}

/** Only untouched scan rows may be absorbed when reviewing a separate planned item. */
function tncp_scan_row_unchanged($row, $post, $rows) {
    return !empty($row['scanned']) && $row['title'] === $post['title'] && $row['slug'] === $post['slug']
        && tncp_parent_id($row, $rows) === $post['parent'] && $row['template'] === $post['planning']['template']
        && $row['flags'] == $post['planning']['flags'];
}

function tncp_scan_rows($plan, $type) {
    $catalog = tncp_catalog($type);
    if (is_wp_error($catalog)) { return $catalog; }
    $mapped = array_fill_keys(array_filter(array_column($plan['rows'], 'post_id')), true);
    usort($catalog, static fn($a, $b) => count(get_post_ancestors($a['id'])) <=> count(get_post_ancestors($b['id'])));
    foreach ($catalog as $post) {
        if (isset($mapped[$post['id']])) { continue; }
        // A unique slug already in the plan is the same item, not a second row.
        $matches = array_keys(array_filter($plan['rows'], static fn($row) => !$row['post_id'] && $row['slug'] && tncp_slug_matches($type, $row, $post, $plan['rows'])));
        $post_matches = array_filter($catalog, static fn($candidate) => $candidate['slug'] === $post['slug'] && (!is_post_type_hierarchical($type) || $candidate['parent'] === $post['parent']));
        $baseline = array_intersect_key($post, array_flip(array('title', 'slug', 'parent')));
        if (1 === count($matches) && 1 === count($post_matches)) {
            $index = $matches[0];
            $plan['rows'][$index]['post_id'] = $post['id'];
            $plan['rows'][$index]['baseline'] = $baseline;
            $plan['rows'][$index]['confirmed'] = array('title' => false, 'slug' => false, 'parent' => false);
        } else {
            $plan['rows'][] = array('id' => 'scan_' . wp_generate_uuid4(), 'title' => $post['title'], 'slug' => $post['slug'],
                'parent' => $post['parent'] ? 'post:' . $post['parent'] : '', 'template' => $post['planning']['template'],
                'flags' => $post['planning']['flags'], 'post_id' => $post['id'], 'baseline' => $baseline,
                'confirmed' => array('title' => false, 'slug' => false, 'parent' => false), 'scanned' => true);
        }
        $mapped[$post['id']] = true;
    }
    if (count($plan['rows']) > 2000) { return tncp_error(__('The complete scan exceeds the 2,000-row plan limit. No scan changes were saved.', 'tn-content-planner')); }
    return $plan;
}
