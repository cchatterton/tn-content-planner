<?php
if (!defined('ABSPATH')) { exit; }

function tncp_patterns_permission() { return current_user_can('manage_options'); }

/** Count unique saved patterns across all eligible types, independent of the Mine filter. */
function tncp_pattern_counts() {
    $saved = get_option('tncp_patterns', array('entries' => array()));
    $keys = array(); $done = 0;
    foreach (tncp_types() as $type) {
        $plan = tncp_plan($type->name);
        foreach ($plan['rows'] as $row) {
            $level = tncp_ancestry($row, $plan['rows'], $type->name);
            if (is_wp_error($level)) { return null; }
            $key = $type->name . '-' . $level . '-' . $row['template'] . '-' . count(array_filter($row['flags']));
            if (isset($keys[$key])) { continue; }
            $keys[$key] = true;
            $entry = $saved['entries'][$key] ?? array();
            if ('done' !== ($entry['status'] ?? '') || empty($entry['post_id'])) { continue; }
            $post = get_post($entry['post_id']);
            if ($post && $post->post_type === $type->name && in_array($post->post_status, array('publish', 'draft', 'pending', 'private', 'future'), true) && current_user_can('edit_post', $post->ID)) { ++$done; }
        }
    }
    return array('done' => $done, 'total' => count($keys));
}

function tncp_patterns_data() {
    $saved = get_option('tncp_patterns', array('revision' => 0, 'entries' => array()));
    $patterns = array(); $catalog = array();
    foreach (tncp_types() as $type) {
        $posts = tncp_catalog($type->name);
        if (is_wp_error($posts)) { return $posts; }
        $catalog[$type->name] = $posts;
        $plan = tncp_plan($type->name);
        foreach ($plan['rows'] as $row) {
            $level = tncp_ancestry($row, $plan['rows'], $type->name);
            if (is_wp_error($level)) { return $level; }
            $key = $type->name . '-' . $level . '-' . $row['template'] . '-' . count(array_filter($row['flags']));
            if (!isset($patterns[$key])) {
                $entry = $saved['entries'][$key] ?? array();
                $patterns[$key] = array('key' => $key, 'type' => $type->name, 'count' => 0, 'description' => $entry['description'] ?? '', 'status' => $entry['status'] ?? 'todo', 'post_id' => $entry['post_id'] ?? 0, 'user_id' => (int) ($entry['user_id'] ?? 0));
            }
            ++$patterns[$key]['count'];
        }
    }
    ksort($patterns, SORT_NATURAL);
    return array('pattern_counts' => tncp_pattern_counts(), 'revision' => $saved['revision'], 'rows' => array_values($patterns), 'catalog' => $catalog, 'current_user_id' => get_current_user_id(), 'users' => array_map(static fn($user) => array('id' => (int) $user->ID, 'name' => $user->display_name), get_users(array('blog_id' => get_current_blog_id(), 'orderby' => 'display_name', 'order' => 'ASC', 'fields' => array('ID', 'display_name')))));
}

function tncp_patterns_save($request) {
    $lock = 'tncp_lock_patterns';
    $started = time();
    $existing = (int) get_option($lock, 0);
    if ($existing && $existing < $started - 600) {
        global $wpdb;
        $wpdb->query($wpdb->prepare('DELETE FROM ' . $wpdb->options . ' WHERE option_name = %s AND option_value = %s', $lock, (string) $existing));
        wp_cache_delete($lock, 'options');
    }
    if (!add_option($lock, $started, '', false)) { return tncp_error(__('Another pattern change is running. Try again shortly.', 'tn-content-planner'), 409); }
    try {
        $saved = get_option('tncp_patterns', array('revision' => 0, 'entries' => array()));
        if (!is_numeric($request['revision']) || (int) $request['revision'] !== $saved['revision']) { return tncp_error(__('The patterns were saved in another window. Reload before saving.', 'tn-content-planner'), 409); }
        $data = tncp_patterns_data();
        if (is_wp_error($data)) { return $data; }
        $patterns = array_column($data['rows'], null, 'key');
        $rows = $request['rows'];
        if (!is_array($rows) || count($rows) > count($patterns)) { return tncp_error(__('Send valid pattern rows.', 'tn-content-planner')); }
        $seen = array();
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['key']) || !is_string($row['key']) || !isset($patterns[$row['key']]) || isset($seen[$row['key']])) { return tncp_error(__('The pattern list changed. Reload before saving.', 'tn-content-planner'), 409); }
            $key = $row['key']; $seen[$key] = true;
            if (!isset($row['description']) || !is_string($row['description']) || mb_strlen($row['description']) > 240 || !in_array($row['status'] ?? null, array('todo', 'in-progress', 'done'), true)) { return tncp_error(__('Use a description up to 240 characters and a valid status.', 'tn-content-planner')); }
            if (!isset($row['post_id']) || !is_scalar($row['post_id']) || !ctype_digit((string) $row['post_id'])) { return tncp_error(__('Choose a valid example post.', 'tn-content-planner')); }
            $post_id = (int) $row['post_id'];
            $eligible = array_column($data['catalog'][$patterns[$key]['type']], 'id');
            if ($post_id && !in_array($post_id, $eligible, true)) { return tncp_error(__('The example post is unavailable or belongs to another post type.', 'tn-content-planner')); }
            $user_id = $row['user_id'] ?? ($saved['entries'][$key]['user_id'] ?? 0);
            if (!is_scalar($user_id) || !ctype_digit((string) $user_id)) { return tncp_error(__('Choose a valid assigned user.', 'tn-content-planner')); }
            $user_id = (int) $user_id;
            $previous_user = (int) ($saved['entries'][$key]['user_id'] ?? 0);
            if ($user_id && $user_id !== $previous_user && !in_array($user_id, array_column($data['users'], 'id'), true)) { return tncp_error(__('Choose a user from this site.', 'tn-content-planner')); }
            $saved['entries'][$key] = array('description' => sanitize_text_field($row['description']), 'status' => $row['status'], 'post_id' => $post_id, 'user_id' => $user_id);
        }
        ++$saved['revision'];
        if (!update_option('tncp_patterns', $saved, false)) { return tncp_error(__('The patterns could not be saved. Please retry.', 'tn-content-planner'), 500); }
        return tncp_patterns_data();
    } finally { delete_option($lock); }
}
