<?php
if (!defined('ABSPATH')) { exit; }

function tncp_error($message, $status = 400) {
    return new WP_Error('tncp_error', $message, array('status' => $status));
}

function tncp_types() {
    $types = get_post_types(array('public' => true), 'objects');
    foreach ($types as $name => $type) {
        if ('attachment' === $name || !current_user_can($type->cap->edit_posts)) { unset($types[$name]); }
    }
    return $types;
}

function tncp_title($title) {
    return wp_kses($title, array(
        'i' => array('class' => true, 'aria-hidden' => true),
        'span' => array('class' => true, 'aria-hidden' => true),
        'strong' => array(), 'em' => array(), 'b' => array(), 'br' => array(),
    ));
}

function tncp_snapshot($post) {
    return array('title' => $post->post_title, 'slug' => $post->post_name, 'parent' => (int) $post->post_parent);
}

function tncp_catalog($type) {
    $posts = get_posts(array('post_type' => $type, 'post_status' => array('publish', 'draft', 'pending', 'private', 'future'), 'numberposts' => 2001, 'orderby' => 'ID', 'order' => 'ASC', 'suppress_filters' => false));
    if (count($posts) > 2000) { return tncp_error(__('This post type exceeds the 2,000-post catalog limit. Narrow the post type before planning.', 'tn-content-planner')); }
    $catalog = array();
    foreach ($posts as $post) {
        if (current_user_can('edit_post', $post->ID)) {
            $catalog[] = array_merge(array('id' => $post->ID), tncp_snapshot($post));
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

function tncp_validate_rows($input, $type, $old) {
    if (!is_array($input) || count($input) > 500) { return tncp_error(__('Use no more than 500 rows per post type.', 'tn-content-planner')); }
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
        $title = tncp_title($raw['title']);
        $slug = sanitize_title($raw['slug']);
        if (strlen($title) > 4000 || '' === trim(wp_strip_all_tags($title)) || !$slug || strlen($slug) > 200) { return tncp_error(__('Every row needs a text title (up to 4,000 bytes) and a slug of at most 200 characters.', 'tn-content-planner')); }
        if (!in_array($raw['template'], array('single', 'archive', 'custom'), true)) { return tncp_error(__('Choose Single, Archive or Custom.', 'tn-content-planner')); }
        if ($raw['parent'] && !preg_match('/^(row:[a-zA-Z0-9_-]{1,80}|post:[1-9][0-9]*)$/', $raw['parent'])) { return tncp_error(__('Invalid parent reference.', 'tn-content-planner')); }
        if (isset($raw['post_id']) && (!is_scalar($raw['post_id']) || !preg_match('/^[0-9]+$/', (string) $raw['post_id']))) { return tncp_error(__('Post ID must be a non-negative integer.', 'tn-content-planner')); }
        $post_id = absint($raw['post_id'] ?? 0);
        $previous = $old_rows[$id] ?? null;
        if ($previous && $previous['post_id'] && $previous['post_id'] !== $post_id) { return tncp_error(__('A linked row cannot be detached. Create a new plan item instead.', 'tn-content-planner')); }
        $matches = array_values(array_filter($catalog, static fn($post) => $post['slug'] === $slug));
        if (!$post_id && count($matches) > 1) { return tncp_error(sprintf(__('Slug "%s" matches multiple posts. Use a unique slug.', 'tn-content-planner'), $slug)); }
        if (!$post_id && count($matches) === 1) { $post_id = $matches[0]['id']; }
        if ($post_id && isset($posts[$post_id]) && $posts[$post_id]['slug'] !== $slug && array_filter($matches, static fn($match) => $match['id'] !== $post_id)) { return tncp_error(__('This slug already belongs to another post in this post type.', 'tn-content-planner')); }
        if ($post_id && !isset($posts[$post_id])) { return tncp_error(__('A linked post is unavailable or cannot be edited.', 'tn-content-planner')); }
        if ($post_id && isset($mapped[$post_id])) { return tncp_error(__('A post can only be linked to one row in a post-type plan.', 'tn-content-planner')); }
        if ($post_id) { $mapped[$post_id] = true; }
        $baseline = $post_id ? array_intersect_key($posts[$post_id], array_flip(array('title', 'slug', 'parent'))) : null;
        if ($previous && $previous['post_id'] && $previous['baseline'] !== $baseline) { return tncp_error(__('A linked post changed outside this plan. Reload the saved plan to refresh it before editing.', 'tn-content-planner'), 409); }
        $flags = array();
        foreach (array('local', 'related', 'children', 'siblings', 'parents') as $flag) { $flags[$flag] = !empty($raw['flags'][$flag]); }
        $confirmed = array();
        foreach (array('title', 'slug', 'parent') as $field) { $confirmed[$field] = !empty($raw['confirmed'][$field]); }
        $rows[] = array('id' => $id, 'title' => $title, 'slug' => $slug, 'parent' => $raw['parent'], 'template' => $raw['template'], 'flags' => $flags, 'post_id' => $post_id, 'baseline' => $baseline, 'confirmed' => $confirmed);
    }
    $slugs = array();
    foreach ($rows as &$row) {
        $level = tncp_ancestry($row, $rows, $type);
        if (is_wp_error($level)) { return $level; }
        $row['pattern'] = $type . '-' . $level . '-' . $row['template'] . '-' . count(array_filter($row['flags']));
        $key = $row['slug'];
        if (isset($slugs[$key])) { return tncp_error(__('Each planned slug must be unique within its post type.', 'tn-content-planner')); }
        $slugs[$key] = true;
        if ($row['baseline']) {
            $desired = array('title' => $row['title'], 'slug' => $row['slug'], 'parent' => tncp_parent_id($row, $rows));
            foreach ($desired as $field => $value) {
                if ($value !== $row['baseline'][$field] && !$row['confirmed'][$field]) { return tncp_error(sprintf(__('Confirm the linked post %s change before saving.', 'tn-content-planner'), $field)); }
            }
        }
    }
    unset($row);
    return $rows;
}
