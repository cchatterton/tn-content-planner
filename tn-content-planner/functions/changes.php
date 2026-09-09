<?php
if (!defined('ABSPATH')) { exit; }

function tncp_change_request($request) { return tncp_mutate($request, 'tncp_change_field'); }

/** Apply only the field explicitly approved, preserving other planned changes. */
function tncp_change_field($request, $plan) {
    $field = $request['field'];
    if (true !== $request['confirmed'] || !in_array($field, array('title', 'slug', 'parent', 'template', 'local', 'related', 'children', 'siblings', 'parents'), true) || !is_scalar($request['post_id']) || !ctype_digit((string) $request['post_id'])) {
        return tncp_error(__('Confirm a valid linked-post field change.', 'tn-content-planner'));
    }
    $post = get_post((int) $request['post_id']);
    if (!$post || $post->post_type !== $request['type'] || in_array($post->post_status, array('trash', 'auto-draft'), true) || !current_user_can('edit_post', $post->ID)) { return tncp_error(__('This linked post cannot be edited.', 'tn-content-planner'), 403); }
    if (!is_array($request['baseline']) || $request['baseline'] != tncp_snapshot($post)) { return tncp_error(__('The linked post changed elsewhere. Reload before approving this change.', 'tn-content-planner'), 409); }
    $index = array_search($request['row_id'], array_column($plan['rows'], 'id'), true);
    if (false !== $index && $plan['rows'][$index]['post_id'] !== $post->ID) { return tncp_error(__('This row is linked to a different post. Reload the plan.', 'tn-content-planner'), 409); }
    $value = $request['value'];
    $metadata = !in_array($field, array('title', 'slug', 'parent'), true);
    $planning = tncp_post_planning($post->ID);
    if ($metadata && (!is_array($request['planning']) || $request['planning'] != $planning)) { return tncp_error(__('The post planning metadata changed elsewhere. Reload before changing it.', 'tn-content-planner'), 409); }
    if ($metadata) {
        if ('template' === $field) {
            if (!in_array($value, array('single', 'archive', 'custom'), true)) { return tncp_error(__('Choose Single, Archive or Custom.', 'tn-content-planner')); }
            $planning['template'] = $value;
        } else {
            if (!is_bool($value)) { return tncp_error(__('Choose a checked or unchecked relationship.', 'tn-content-planner')); }
            $planning['flags'][$field] = $value;
        }
    } elseif ('parent' === $field) {
        if (!is_scalar($value) || !ctype_digit((string) $value)) { return tncp_error(__('Create the parent before moving this post under it.', 'tn-content-planner')); }
        $value = (int) $value;
        $parent = $value ? get_post($value) : null;
        if ($value && (!$parent || $parent->post_type !== $post->post_type || in_array($parent->post_status, array('trash', 'auto-draft'), true) || !current_user_can('edit_post', $value))) { return tncp_error(__('Choose an available parent in this post type.', 'tn-content-planner')); }
        $seen = array($post->ID => true); $ancestor = $value;
        while ($ancestor) {
            if (isset($seen[$ancestor]) || count($seen) > 100) { return tncp_error(__('Choose a parent that does not create a circular hierarchy.', 'tn-content-planner')); }
            $seen[$ancestor] = true; $ancestor = (int) get_post_field('post_parent', $ancestor);
        }
    } else {
        if (!is_string($value)) { return tncp_error(__('Enter a valid title or slug.', 'tn-content-planner')); }
        $value = 'title' === $field ? tncp_title($value) : sanitize_title(tncp_trim($value));
        if ('' === trim(wp_strip_all_tags($value)) || strlen($value) > ('title' === $field ? 4000 : 200)) { return tncp_error(__('Enter a nonempty title or slug within the field limit.', 'tn-content-planner')); }
    }
    if (in_array($field, array('slug', 'parent'), true)) {
        $desired = array('slug' => 'slug' === $field ? $value : $post->post_name, 'parent' => 'post:' . ('parent' === $field ? $value : $post->post_parent));
        $args = array('post_type' => $post->post_type, 'name' => $desired['slug'], 'post_status' => array('publish', 'draft', 'pending', 'private', 'future'), 'post__not_in' => array($post->ID), 'numberposts' => 1);
        if (is_post_type_hierarchical($post->post_type)) { $args['post_parent'] = 'parent' === $field ? $value : (int) $post->post_parent; }
        $scope = tncp_slug_scope($post->post_type, $desired, $plan['rows']);
        if (get_posts($args) || array_filter($plan['rows'], static fn($row) => $row['post_id'] !== $post->ID && $row['slug'] === $desired['slug'] && tncp_slug_scope($post->post_type, $row, $plan['rows']) === $scope)) { return tncp_error(__('That slug already belongs to another post or plan item under this parent.', 'tn-content-planner')); }
    }
    if (false !== $index) {
        if ($metadata && 'template' !== $field) { $plan['rows'][$index]['flags'][$field] = $value; }
        else { $plan['rows'][$index][$field] = 'parent' === $field ? ($value ? 'post:' . $value : '') : $value; }
        foreach ($plan['rows'] as $row) { $level = tncp_ancestry($row, $plan['rows'], $request['type']); if (is_wp_error($level)) { return $level; } }
    }
    if ($metadata) {
        $meta_key = 'template' === $field ? '_tncp_template' : '_tncp_flags';
        $meta_value = 'template' === $field ? $planning['template'] : $planning['flags'];
        update_post_meta($post->ID, $meta_key, $meta_value);
        if (get_post_meta($post->ID, $meta_key, true) != $meta_value) { return tncp_error(__('WordPress could not save this planning setting.', 'tn-content-planner')); }
    } else {
        $column = array('title' => 'post_title', 'slug' => 'post_name', 'parent' => 'post_parent')[$field];
        $result = wp_update_post(wp_slash(array('ID' => $post->ID, $column => $value)), true);
        if (is_wp_error($result)) { return tncp_error($result->get_error_message()); }
        if (!$result) { return tncp_error(__('WordPress could not apply the approved change.', 'tn-content-planner')); }
    }
    $post = get_post($post->ID);
    update_post_meta($post->ID, '_tncp_pattern', $post->post_type . '-' . count(get_post_ancestors($post)) . '-' . $planning['template'] . '-' . count(array_filter($planning['flags'])));
    $snapshot = tncp_snapshot($post);
    if (false !== $index) {
        if (!$metadata) { $plan['rows'][$index][$field] = 'parent' === $field ? ($snapshot['parent'] ? 'post:' . $snapshot['parent'] : '') : $snapshot[$field]; }
        $plan['rows'][$index]['baseline'] = $snapshot;
        $plan['rows'][$index]['confirmed'][$field] = false;
        $plan['rows'][$index]['scanned'] = false;
        foreach ($plan['rows'] as &$row) {
            $level = tncp_ancestry($row, $plan['rows'], $request['type']);
            if (is_wp_error($level)) { return tncp_error(__('WordPress changed the hierarchy unexpectedly. Reload the plan to inspect the result.', 'tn-content-planner'), 409); }
            $row['pattern'] = $request['type'] . '-' . $level . '-' . $row['template'] . '-' . count(array_filter($row['flags']));
        }
        unset($row);
        $plan = tncp_store($request['type'], $plan);
        if (is_wp_error($plan)) { return tncp_error(__('The post changed, but the plan could not be saved. Reload to reconcile the saved post.', 'tn-content-planner'), 500); }
    }
    return array('plan' => $plan, 'snapshot' => $snapshot, 'planning' => tncp_post_planning($post->ID), 'field' => $field, 'post_id' => $post->ID);
}

/** Honour earlier saved dialog approvals without requiring a second review. */
function tncp_apply_saved_approvals($plan, $type) {
    foreach (array_column($plan['rows'], 'id') as $id) {
        foreach (array('title', 'slug', 'parent', 'template', 'local', 'related', 'children', 'siblings', 'parents') as $field) {
            $index = array_search($id, array_column($plan['rows'], 'id'), true);
            $row = $plan['rows'][$index];
            if (!$row['post_id'] || empty($row['baseline'])) { continue; }
            $post = get_post($row['post_id']);
            // Conflicts remain available in review; never overwrite an external edit during a scan.
            if (!$post || tncp_snapshot($post) != $row['baseline']) { continue; }
            $metadata = !in_array($field, array('title', 'slug', 'parent'), true);
            $planning = tncp_post_planning($row['post_id']);
            if (!$metadata && empty($row['confirmed'][$field])) { continue; }
            $value = $metadata ? ('template' === $field ? $row['template'] : (bool) $row['flags'][$field]) : ('parent' === $field ? tncp_parent_id($row, $plan['rows']) : $row[$field]);
            $actual = $metadata ? ('template' === $field ? $planning['template'] : $planning['flags'][$field]) : $row['baseline'][$field];
            if ($value === $actual || ('parent' === $field && $value < 0)) { continue; }
            $request = new WP_REST_Request('POST');
            foreach (array('type' => $type, 'field' => $field, 'row_id' => $id, 'post_id' => $row['post_id'], 'baseline' => $row['baseline'], 'planning' => $planning, 'value' => $value, 'confirmed' => true) as $key => $item) { $request->set_param($key, $item); }
            $result = tncp_change_field($request, $plan);
            if (is_wp_error($result)) { return $result; }
            $plan = $result['plan'];
        }
    }
    return $plan;
}
