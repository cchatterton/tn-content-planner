<?php
if (!defined('ABSPATH')) { exit; }
add_action('rest_api_init', 'tncp_register_routes');
function tncp_register_routes() {
    register_rest_route('tncp/v1', '/patterns', array(
        array('methods' => 'GET', 'callback' => 'tncp_patterns_data', 'permission_callback' => 'tncp_patterns_permission'),
        array('methods' => 'POST', 'callback' => 'tncp_patterns_save', 'permission_callback' => 'tncp_patterns_permission'),
    ));
    foreach (array('plan' => 'GET', 'save' => 'POST', 'apply' => 'POST', 'refresh' => 'POST', 'resolve' => 'POST', 'bin' => 'POST', 'change' => 'POST', 'lock' => 'POST') as $action => $method) {
        register_rest_route('tncp/v1', '/' . $action . '/(?P<type>[a-z0-9_-]+)', array('methods' => $method, 'callback' => 'tncp_' . $action . '_request', 'permission_callback' => 'tncp_permissions'));
    }
}
function tncp_permissions($request) {
    return current_user_can('manage_options') && isset(tncp_types()[$request['type']]);
}
function tncp_plan_request($request) {
    $catalog = tncp_catalog($request['type']);
    if (is_wp_error($catalog)) { return $catalog; }
    return array('plan' => tncp_plan($request['type']), 'catalog' => $catalog, 'settings' => tncp_type_settings($request['type']), 'pattern_statuses' => tncp_pattern_statuses(), 'pattern_examples' => tncp_pattern_examples(), 'pattern_counts' => tncp_pattern_counts());
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
        if ('tncp_set_lock' !== $action && get_option('tncp_locked_' . $type, false)) { return tncp_error(__('This post type is locked. Unlock it before making changes.', 'tn-content-planner'), 423); }
        $result = call_user_func($action, $request, $plan);
        if (is_array($result)) { $result['pattern_counts'] = tncp_pattern_counts(); $result['pattern_examples'] = tncp_pattern_examples(); $result['pattern_statuses'] = tncp_pattern_statuses(); }
        return $result;
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
    if (!empty($request['apply_approved'])) {
        $plan = tncp_apply_saved_approvals($plan, $request['type']);
        if (is_wp_error($plan)) { return $plan; }
    }
    foreach ($plan['rows'] as &$row) {
        if (!$row['post_id']) { continue; }
        $post = get_post($row['post_id']);
        if (!$post || $post->post_type !== $request['type'] || !current_user_can('edit_post', $post->ID) || in_array($post->post_status, array('trash', 'auto-draft'), true)) {
            return tncp_error(__('A linked post was removed or is unavailable. Restore it before refreshing this plan.', 'tn-content-planner'));
        }
        // Automatic tab refresh must not overwrite saved changes awaiting review.
        if (!empty($request['preserve_pending']) && !empty($row['baseline'])) {
            $baseline = $row['baseline'];
            $parent = tncp_parent_id($row, $plan['rows']);
            if ($row['title'] !== $baseline['title'] || $row['slug'] !== $baseline['slug'] || $parent !== (int) $baseline['parent']) { continue; }
        }
        $row['baseline'] = tncp_snapshot($post);
        $row['title'] = $post->post_title;
        $row['slug'] = $post->post_name;
        $row['parent'] = $post->post_parent ? 'post:' . $post->post_parent : '';
        $row['confirmed'] = array('title' => false, 'slug' => false, 'parent' => false);
    }
    unset($row);
    if (!empty($request['scan'])) {
        $plan = tncp_scan_rows($plan, $request['type']);
        if (is_wp_error($plan)) { return $plan; }
    }
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
    $creation_status = $request['creation_status'] ?? 'publish';
    if (!in_array($creation_status, array('publish', 'draft'), true)) { return tncp_error(__('Choose Published or Draft for new posts.', 'tn-content-planner')); }
    $selected = $request['selected'];
    if (!is_array($selected) || !$selected || count($selected) > 50 || array_filter($selected, static fn($id) => !is_string($id))) {
        return tncp_error(__('Select between 1 and 50 saved rows per batch.', 'tn-content-planner'));
    }
    $rows = tncp_validate_rows($plan['rows'], $request['type'], $plan, $selected);
    if (is_wp_error($rows)) { return $rows; }
    $by_id = array_column($rows, null, 'id');
    foreach ($selected as $id) {
        if (!isset($by_id[$id])) { return tncp_error(__('A selected row is no longer in this plan.', 'tn-content-planner')); }
        $row = $by_id[$id];
        if (!$row['slug']) { return tncp_error(__('Enter a slug before mapping this item.', 'tn-content-planner')); }
        $parent = $row['parent'];
        while (str_starts_with($parent, 'row:')) {
            $ancestor = $by_id[substr($parent, 4)];
            if (!$ancestor['post_id'] && !in_array($ancestor['id'], $selected, true)) {
                return tncp_error(__('Select the uncreated parent rows too, or create them first.', 'tn-content-planner'));
            }
            $parent = $ancestor['parent'];
        }
        $object = get_post_type_object($request['type']);
        if (!$row['post_id'] && 'publish' === $creation_status && !current_user_can($object->cap->publish_posts)) { return tncp_error(__('You cannot publish this post type. Choose Draft instead.', 'tn-content-planner'), 403); }
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
                $data['post_status'] = $creation_status;
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

function tncp_resolve_request($request) { return tncp_mutate($request, 'tncp_resolve_item'); }

/** Apply one explicit reconciliation decision while holding the normal plan lock. */
function tncp_resolve_item($request, $plan) {
    $index = array_search($request['row_id'], array_column($plan['rows'], 'id'), true);
    $decision = $request['decision'];
    if (false === $index || !in_array($decision, array('source', 'destination', 'new'), true)) {
        return tncp_error(__('Choose a saved item and a reconciliation decision.', 'tn-content-planner'));
    }
    $type = $request['type'];
    $row = $plan['rows'][$index];
    if (!$row['slug']) { return tncp_error(__('Enter a slug before mapping this item.', 'tn-content-planner')); }
    $original_id = $row['id'];
    $catalog = tncp_catalog($type);
    if (is_wp_error($catalog)) { return $catalog; }
    $target = null;
    if ('new' !== $decision) {
        if (!is_scalar($request['target_id']) || !ctype_digit((string) $request['target_id'])) { return tncp_error(__('Choose a matching WordPress post.', 'tn-content-planner')); }
        foreach ($catalog as $post) { if ($post['id'] === (int) $request['target_id']) { $target = $post; break; } }
        if (!$target) { return tncp_error(__('The matching post is unavailable or cannot be edited.', 'tn-content-planner'), 403); }
        foreach ($plan['rows'] as $candidate_index => $candidate) {
            if ($candidate['id'] === $original_id || $candidate['post_id'] !== $target['id']) { continue; }
            if (!tncp_scan_row_unchanged($candidate, $target, $plan['rows'])) { return tncp_error(__('This post has another edited plan row. Review that row instead.', 'tn-content-planner')); }
            foreach ($plan['rows'] as &$child) {
                if ('row:' . $candidate['id'] === $child['parent']) { $child['parent'] = 'post:' . $target['id']; }
            }
            unset($child);
            unset($plan['rows'][$candidate_index]);
            $plan['rows'] = array_values($plan['rows']);
            $index = array_search($original_id, array_column($plan['rows'], 'id'), true);
            break;
        }
        // Compare the values actually displayed to the reviewer, including planning metadata.
        $expected = $request['target_snapshot'];
        $actual = array_intersect_key($target, array_flip(array('title', 'slug', 'parent', 'planning')));
        if (!is_array($expected) || $expected != $actual) { return tncp_error(__('The destination changed while you reviewed it. Reload this item before applying.', 'tn-content-planner'), 409); }
        $row['post_id'] = $target['id'];
        $row['baseline'] = array_intersect_key($target, array_flip(array('title', 'slug', 'parent')));
        $row['confirmed'] = array('title' => true, 'slug' => true, 'parent' => true);
        if ('destination' === $decision) {
            $row['title'] = $target['title'];
            $row['slug'] = $target['slug'];
            $row['parent'] = $target['parent'] ? 'post:' . $target['parent'] : '';
            $row['template'] = $target['planning']['template'];
            $row['flags'] = $target['planning']['flags'];
        }
    } else {
        // A distinct row identity prevents recovery markers from reusing the old linked post.
        $row['id'] = 'r_' . hash('sha256', $original_id . ':' . $plan['revision']);
        $row['post_id'] = 0;
        $row['baseline'] = null;
        $row['confirmed'] = array();
        if (null !== $request['new_slug'] && !is_string($request['new_slug'])) { return tncp_error(__('Enter a valid slug.', 'tn-content-planner')); }
        $slug = sanitize_title(tncp_trim($request['new_slug'] ?? $row['slug']));
        if (!$slug) { return tncp_error(__('Enter a slug for the new post.', 'tn-content-planner')); }
        foreach ($catalog as $post) {
            if (tncp_slug_matches($type, array_merge($row, array('slug' => $slug)), $post, $plan['rows'])) { return tncp_error(__('That slug already exists. Choose a distinct slug to create a new post.', 'tn-content-planner')); }
        }
        $row['slug'] = $slug;
        foreach ($plan['rows'] as &$child) {
            if ('row:' . $original_id === $child['parent']) { $child['parent'] = 'row:' . $row['id']; }
        }
        unset($child);
    }
    $row['scanned'] = false;
    $plan['rows'][$index] = $row;
    // Reconciliation explicitly authorises this row's new mapping; normal Save cannot detach it.
    $rows = tncp_validate_rows($plan['rows'], $type, $plan, array($row['id']));
    if (is_wp_error($rows)) { return $rows; }
    $plan['rows'] = $rows;
    if ('destination' === $decision) {
        // Accept destination writes only the plan, never the existing post or its metadata.
        $plan['rows'][$index]['title'] = $target['title'];
        $plan['rows'][$index]['confirmed'] = array('title' => false, 'slug' => false, 'parent' => false);
        $stored = tncp_store($type, $plan);
        if (is_wp_error($stored)) { return $stored; }
        return array('plan' => $stored, 'completed' => array($original_id), 'errors' => array());
    }
    $request->set_param('selected', array($row['id']));
    $result = tncp_apply_plan($request, $plan);
    if (is_wp_error($result)) { return $result; }
    if ($result['completed']) { $result['completed'] = array($original_id); }
    return $result;
}

function tncp_bin_request($request) { return tncp_mutate($request, 'tncp_bin_linked_post'); }
function tncp_bin_linked_post($request, $plan) {
    if (true !== $request['confirmed']) { return tncp_error(__('Confirm moving the linked post to the bin.', 'tn-content-planner')); }
    $index = array_search($request['row_id'], array_column($plan['rows'], 'id'), true);
    if (false === $index || !$plan['rows'][$index]['post_id']) { return tncp_error(__('Save a linked row before moving its post to the bin.', 'tn-content-planner')); }
    $row = $plan['rows'][$index];
    $post = get_post($row['post_id']);
    if (!$post || $post->post_type !== $request['type'] || !current_user_can('delete_post', $post->ID)) { return tncp_error(__('You cannot move this linked post to the bin.', 'tn-content-planner'), 403); }
    if (!defined('EMPTY_TRASH_DAYS') || EMPTY_TRASH_DAYS <= 0) { return tncp_error(__('The WordPress bin is disabled. Remove the plan row only; permanent deletion is not supported here.', 'tn-content-planner')); }
    if (tncp_snapshot($post) !== $row['baseline']) { return tncp_error(__('The linked post changed. Click the post-type tab to reload linked posts before moving it to the bin.', 'tn-content-planner'), 409); }
    foreach ($plan['rows'] as $child) {
        if ($child['parent'] === 'row:' . $row['id'] || $child['parent'] === 'post:' . $post->ID) { return tncp_error(__('Move the child plan rows before removing their parent.', 'tn-content-planner')); }
    }
    if (!wp_trash_post($post->ID) || 'trash' !== get_post_status($post->ID)) { return tncp_error(__('WordPress could not move this post to the bin. The plan row has been kept.', 'tn-content-planner')); }
    array_splice($plan['rows'], $index, 1);
    $stored = tncp_store($request['type'], $plan);
    if (is_wp_error($stored)) { return tncp_error(__('The post was moved to the bin, but the plan could not be saved. Reload the plan and remove the row, or restore the post from the WordPress bin.', 'tn-content-planner'), 500); }
    return $stored;
}

/** Use the same per-type mutation lock so locking cannot race a save or post change. */
function tncp_lock_request($request) { return tncp_mutate($request, 'tncp_set_lock'); }
function tncp_set_lock($request, $plan) {
    if (!is_bool($request['locked'])) { return tncp_error(__('Choose a valid lock state.', 'tn-content-planner')); }
    $key = 'tncp_locked_' . $request['type'];
    update_option($key, $request['locked'], false);
    if ((bool) get_option($key, false) !== $request['locked']) { return tncp_error(__('The lock could not be saved. Please retry.', 'tn-content-planner'), 500); }
    return array('locked' => $request['locked']);
}
