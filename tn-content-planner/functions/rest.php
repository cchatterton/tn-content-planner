<?php
if (!defined('ABSPATH')) { exit; }
add_action('rest_api_init', 'tncp_register_routes');
function tncp_register_routes() {
    foreach (array('plan' => 'GET', 'save' => 'POST', 'apply' => 'POST', 'refresh' => 'POST') as $action => $method) {
        register_rest_route('tncp/v1', '/' . $action . '/(?P<type>[a-z0-9_-]+)', array('methods' => $method, 'callback' => 'tncp_' . $action . '_request', 'permission_callback' => 'tncp_permissions'));
    }
}
function tncp_permissions($request) {
    return current_user_can('manage_options') && isset(tncp_types()[$request['type']]);
}
function tncp_plan_request($request) {
    $catalog = tncp_catalog($request['type']);
    if (is_wp_error($catalog)) { return $catalog; }
    return array('plan' => tncp_plan($request['type']), 'catalog' => $catalog);
}
/** Serialise mutations across browsers. Expired locks recover after an interrupted PHP request. */
function tncp_mutate($request, $action) {
    $type = $request['type'];
    $lock = 'tncp_lock_' . $type;
    $started = time();
    $existing = (int) get_option($lock, 0);
    if ($existing && $existing < $started - 600) {
        global $wpdb;
        // Compare-and-delete prevents one recovery request from deleting another request's new lock.
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $lock, (string) $existing));
        wp_cache_delete($lock, 'options');
    }
    if (!add_option($lock, $started, '', false)) { return tncp_error(__('Another change is running. Please try again shortly.', 'tn-content-planner'), 409); }
    try {
        $plan = tncp_plan($type);
        if (!is_numeric($request['revision']) || (int) $request['revision'] !== $plan['revision']) {
            return tncp_error(__('This plan was saved in another window. Reload it before making changes.', 'tn-content-planner'), 409);
        }
        return call_user_func($action, $request, $plan);
    } finally {
        delete_option($lock);
    }
}
function tncp_store($type, $plan) {
    ++$plan['revision'];
    if (!update_option('tncp_plan_' . $type, $plan, false)) { return tncp_error(__('The plan could not be saved. Please retry.', 'tn-content-planner'), 500); }
    return $plan;
}
function tncp_save_request($request) { return tncp_mutate($request, 'tncp_save_plan'); }
function tncp_save_plan($request, $plan) {
    $rows = tncp_validate_rows($request['rows'], $request['type'], $plan);
    if (is_wp_error($rows)) { return $rows; }
    $plan['rows'] = $rows;
    return tncp_store($request['type'], $plan);
}
function tncp_refresh_request($request) { return tncp_mutate($request, 'tncp_refresh_plan'); }
function tncp_refresh_plan($request, $plan) {
    foreach ($plan['rows'] as &$row) {
        if (!$row['post_id']) { continue; }
        $post = get_post($row['post_id']);
        if (!$post || $post->post_type !== $request['type'] || !current_user_can('edit_post', $post->ID) || in_array($post->post_status, array('trash', 'auto-draft'), true)) {
            return tncp_error(__('A linked post was removed or is unavailable. Restore it before refreshing this plan.', 'tn-content-planner'));
        }
        $row['baseline'] = tncp_snapshot($post);
        $row['title'] = $post->post_title;
        $row['slug'] = $post->post_name;
        $row['parent'] = $post->post_parent ? 'post:' . $post->post_parent : '';
        $row['confirmed'] = array('title' => false, 'slug' => false, 'parent' => false);
    }
    unset($row);
    foreach ($plan['rows'] as &$row) {
        $level = tncp_ancestry($row, $plan['rows'], $request['type']);
        if (is_wp_error($level)) { return $level; }
        $row['pattern'] = $request['type'] . '-' . $level . '-' . $row['template'] . '-' . count(array_filter($row['flags']));
    }
    unset($row);
    return tncp_store($request['type'], $plan);
}
function tncp_apply_request($request) { return tncp_mutate($request, 'tncp_apply_plan'); }
function tncp_apply_plan($request, $plan) {
    $selected = $request['selected'];
    if (!is_array($selected) || !$selected || count($selected) > 50 || array_filter($selected, static fn($id) => !is_string($id))) {
        return tncp_error(__('Select between 1 and 50 saved rows per batch.', 'tn-content-planner'));
    }
    $rows = tncp_validate_rows($plan['rows'], $request['type'], $plan);
    if (is_wp_error($rows)) { return $rows; }
    $by_id = array_column($rows, null, 'id');
    foreach ($selected as $id) {
        if (!isset($by_id[$id])) { return tncp_error(__('A selected row is no longer in this plan.', 'tn-content-planner')); }
        $row = $by_id[$id];
        $parent = $row['parent'];
        while (str_starts_with($parent, 'row:')) {
            $ancestor = $by_id[substr($parent, 4)];
            if (!$ancestor['post_id'] && !in_array($ancestor['id'], $selected, true)) {
                return tncp_error(__('Select the uncreated parent rows too, or create them first.', 'tn-content-planner'));
            }
            $parent = $ancestor['parent'];
        }
        $object = get_post_type_object($request['type']);
        if (!$row['post_id'] && !current_user_can($object->cap->create_posts)) { return tncp_error(__('You cannot create posts of this type.', 'tn-content-planner'), 403); }
    }
    // Validate the graph that this batch actually applies, not unselected future moves.
    $effective = $rows;
    foreach ($effective as &$item) {
        if ($item['baseline'] && !in_array($item['id'], $selected, true)) {
            $item['parent'] = $item['baseline']['parent'] ? 'post:' . $item['baseline']['parent'] : '';
        }
    }
    unset($item);
    foreach ($effective as $item) {
        $level = tncp_ancestry($item, $effective, $request['type']);
        if (is_wp_error($level)) { return $level; }
    }
    // Shallowest rows first; parent IDs are persisted after every successful write.
    usort($selected, static fn($left, $right) => tncp_ancestry($by_id[$left], $effective, $request['type']) <=> tncp_ancestry($by_id[$right], $effective, $request['type']));
    $completed = array(); $errors = array();
    foreach ($selected as $id) {
        $index = array_search($id, array_column($rows, 'id'), true);
        $row = $rows[$index];
        $parent_id = tncp_parent_id($row, $rows);
        if ($parent_id < 0) { $errors[] = __('A parent failed to create; dependent rows were skipped.', 'tn-content-planner'); break; }
        $data = array('post_title' => $row['title'], 'post_name' => $row['slug'], 'post_parent' => $parent_id, 'post_type' => $request['type']);
        if ($row['post_id']) {
            $current = get_post($row['post_id']);
            if (!$current || tncp_snapshot($current) !== $row['baseline']) { $errors[] = __('A post changed during this batch. Refresh linked posts and retry.', 'tn-content-planner'); break; }
            // Preserve status, content and all fields outside this plan.
            $data['ID'] = $row['post_id'];
            $desired = array('title' => $row['title'], 'slug' => $row['slug'], 'parent' => $parent_id);
            $post_id = $desired === $row['baseline'] ? $row['post_id'] : wp_update_post(wp_slash($data), true);
        } else {
            // Durable idempotency marker allows recovery if a request ends after insertion.
            $recovered = get_posts(array('post_type' => $request['type'], 'post_status' => 'any', 'numberposts' => 1, 'meta_key' => '_tncp_row_id', 'meta_value' => $id));
            if ($recovered) {
                $post_id = $recovered[0]->ID;
            } else {
                $data['post_status'] = 'draft';
                $data['meta_input'] = array('_tncp_row_id' => $id);
                $post_id = wp_insert_post(wp_slash($data), true);
            }
        }
        if (is_wp_error($post_id) || !$post_id) { $errors[] = __('WordPress could not save a selected post. Completed rows remain linked; retry the remaining rows.', 'tn-content-planner'); break; }
        $post = get_post($post_id);
        $rows[$index]['post_id'] = $post_id;
        $rows[$index]['baseline'] = tncp_snapshot($post);
        $rows[$index]['title'] = $post->post_title;
        $rows[$index]['slug'] = $post->post_name;
        $rows[$index]['confirmed'] = array('title' => false, 'slug' => false, 'parent' => false);
        update_post_meta($post_id, '_tncp_template', $row['template']);
        update_post_meta($post_id, '_tncp_flags', $row['flags']);
        update_post_meta($post_id, '_tncp_pattern', $row['pattern']);
        $plan['rows'] = $rows;
        $stored = tncp_store($request['type'], $plan);
        if (is_wp_error($stored)) { return $stored; }
        $plan = $stored;
        $completed[] = $id;
        if ($post->post_name !== $row['slug'] || $post->post_title !== $row['title'] || (int) $post->post_parent !== $parent_id) {
            $errors[] = __('WordPress adjusted a title, slug or parent. The saved plan now reflects the actual post; review it before continuing.', 'tn-content-planner');
            $rows[$index]['parent'] = $post->post_parent ? 'post:' . $post->post_parent : '';
            $plan['rows'] = $rows;
            $stored = tncp_store($request['type'], $plan);
            if (is_wp_error($stored)) { return $stored; }
            $plan = $stored;
            break;
        }
    }
    return array('plan' => $plan, 'completed' => $completed, 'errors' => $errors);
}
