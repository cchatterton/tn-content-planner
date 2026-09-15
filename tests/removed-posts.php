<?php
if (!defined('WP_CLI') || !WP_CLI) { exit; }
wp_set_current_user(1);
register_post_type('tncp_removed_test', array('public' => true, 'hierarchical' => true));
$ids = array(); $checks = 0;
$call = function($revision) { $request = new WP_REST_Request('POST', '/tncp/v1/refresh/tncp_removed_test'); foreach (array('revision' => $revision, 'scan' => true, 'preserve_pending' => true, 'apply_approved' => true) as $key => $value) { $request->set_param($key, $value); } return rest_do_request($request); };
$check = function($ok, $message) use (&$checks) { if (!$ok) { throw new RuntimeException($message); } ++$checks; };
try {
    foreach (array('Grandparent', 'Parent', 'Child', 'Deleted', 'Other') as $title) { $ids[] = wp_insert_post(array('post_type' => 'tncp_removed_test', 'post_title' => $title, 'post_status' => 'publish')); }
    wp_update_post(array('ID' => $ids[1], 'post_parent' => $ids[0]));
    wp_update_post(array('ID' => $ids[2], 'post_parent' => $ids[1]));
    $result = $call(0); $check(200 === $result->get_status(), 'Initial scan'); $plan = $result->get_data();
    $parent = array_values(array_filter($plan['rows'], static fn($row) => $row['post_id'] === $ids[1]))[0];
    $plan['rows'][] = array('id' => 'planned-child', 'title' => 'Planned child', 'slug' => '', 'parent' => 'row:' . $parent['id'], 'template' => 'single', 'flags' => array(), 'post_id' => 0, 'baseline' => null, 'confirmed' => array());
    update_option('tncp_plan_tncp_removed_test', $plan, false);
    wp_trash_post($ids[1]); wp_delete_post($ids[3], true);
    $child_before = tncp_snapshot(get_post($ids[2]));
    wp_update_post(array('ID' => $ids[4], 'post_title' => 'Other updated'));
    $result = $call($plan['revision']); $check(200 === $result->get_status(), 'Refresh continues after external trash and deletion'); $plan = $result->get_data();
    $mapped = array_column($plan['rows'], null, 'post_id');
    $check(!isset($mapped[$ids[1]]) && !isset($mapped[$ids[3]]), 'Removed posts absent from plan');
    $check('trash' === get_post_status($ids[1]) && !get_post($ids[3]), 'No restore or recreation');
    $check('Other updated' === $mapped[$ids[4]]['title'], 'Other linked rows refresh');
    $check($child_before === tncp_snapshot(get_post($ids[2])), 'Surviving WordPress child is unchanged');
    $check('post:' . $ids[0] === $mapped[$ids[2]]['parent'], 'Mapped child plan uses surviving grandparent');
    $unmapped = array_values(array_filter($plan['rows'], static fn($row) => $row['id'] === 'planned-child'))[0];
    $check('post:' . $ids[0] === $unmapped['parent'], 'Uncreated child retained and reparented in plan');
    $check(200 === $call($plan['revision'])->get_status(), 'Repeated refresh succeeds');
    $plan = tncp_plan('tncp_removed_test'); wp_untrash_post($ids[1]);
    $restored = $call($plan['revision']); $check(200 === $restored->get_status() && in_array($ids[1], array_column($restored->get_data()['rows'], 'post_id'), true), 'A post restored in WordPress is picked up by the next scan');
    echo "PASS: $checks externally removed post checks\n";
} finally { foreach (array_reverse($ids) as $id) { wp_delete_post($id, true); } delete_option('tncp_plan_tncp_removed_test'); unregister_post_type('tncp_removed_test'); }
