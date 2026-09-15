<?php
if (!defined('WP_CLI') || !WP_CLI) { exit; }
wp_set_current_user(1);
register_post_type('tncp_defaults_test', array('public' => true, 'hierarchical' => true));
$ids = array(); $checks = 0;
$check = function($ok, $message) use (&$checks) { if (!$ok) { throw new RuntimeException($message); } ++$checks; };
$call = function($action, $data) { $r = new WP_REST_Request('POST', '/tncp/v1/' . $action . '/tncp_defaults_test'); foreach ($data as $k => $v) { $r->set_param($k, $v); } return rest_do_request($r); };
try {
    $defaults = array(1 => array('local' => true), 2 => array('related' => true), 3 => array('children' => true));
    $saved = $call('save', array('revision' => 0, 'rows' => array(), 'defaults' => $defaults)); $check(200 === $saved->get_status(), 'Defaults save with empty plan'); $plan = $saved->get_data();
    for ($depth = 0; $depth < 4; $depth++) { $ids[] = wp_insert_post(array('post_type' => 'tncp_defaults_test', 'post_title' => 'Level ' . ($depth + 1), 'post_status' => 'publish', 'post_parent' => $depth ? $ids[$depth - 1] : 0)); }
    $ids[] = wp_insert_post(array('post_type' => 'tncp_defaults_test', 'post_title' => 'Existing flags', 'post_status' => 'publish'));
    update_post_meta($ids[4], '_tncp_flags', array('parents' => true));
    $scan = $call('refresh', array('revision' => $plan['revision'], 'scan' => true)); $check(200 === $scan->get_status(), 'Discovery succeeds'); $plan = $scan->get_data();
    foreach (array('local', 'related', 'children') as $i => $flag) { $check(tncp_post_planning($ids[$i])['flags'][$flag], 'Discovery applies level ' . ($i + 1)); }
    $check(!array_filter(tncp_post_planning($ids[3])['flags']), 'Level 4 has no defaults');
    $check(tncp_post_planning($ids[4])['flags']['parents'] && !tncp_post_planning($ids[4])['flags']['local'], 'Discovery keeps nonempty flags');
    $move = function($id, $parent, $action) use ($call, &$plan) {
        $row = array_values(array_filter($plan['rows'], static fn($r) => $r['post_id'] === $id))[0];
        $r = $call('change', array('revision' => $plan['revision'], 'row_id' => $row['id'], 'post_id' => $id, 'baseline' => tncp_snapshot(get_post($id)), 'planning' => tncp_post_planning($id), 'field' => 'parent', 'value' => $parent, 'confirmed' => true, 'defaults_action' => $action));
        if (200 === $r->get_status()) { $plan = $r->get_data()['plan']; } return $r;
    };
    $check(200 === $move($ids[3], 0, 'keep')->get_status(), 'Move empty flags');
    $check(tncp_post_planning($ids[3])['flags']['local'], 'Empty moved post receives destination defaults');
    $check(200 === $move($ids[4], $ids[0], 'keep')->get_status(), 'Move keeping flags');
    $check(tncp_post_planning($ids[4])['flags']['parents'] && !tncp_post_planning($ids[4])['flags']['related'], 'Existing flags kept');
    $check(200 === $move($ids[4], $ids[1], 'replace')->get_status(), 'Move replacing flags');
    $check(tncp_post_planning($ids[4])['flags']['children'] && !tncp_post_planning($ids[4])['flags']['parents'], 'Replace uses level 3');
    $check('tncp_defaults_test-2-single-1' === get_post_meta($ids[4], '_tncp_pattern', true), 'XP pattern updated after default replacement');
    $rows = $plan['rows']; $rows[] = array('id' => 'new-default', 'title' => 'New default', 'slug' => 'new-default', 'parent' => 'post:' . $ids[0], 'template' => 'single', 'flags' => array(), 'post_id' => 0, 'confirmed' => array());
    $plan = $call('save', array('revision' => $plan['revision'], 'rows' => $rows))->get_data();
    $created = $call('apply', array('revision' => $plan['revision'], 'selected' => array('new-default'))); $check(200 === $created->get_status() && !$created->get_data()['errors'], 'Create with level defaults');
    $plan = $created->get_data()['plan']; $new = array_values(array_filter($plan['rows'], static fn($r) => $r['id'] === 'new-default'))[0]; $ids[] = $new['post_id'];
    $check($new['flags']['related'] && tncp_post_planning($new['post_id'])['flags']['related'], 'Creation stores defaults in plan and metadata');
    $bad = $call('save', array('revision' => $plan['revision'], 'rows' => $plan['rows'], 'defaults' => array(1 => array('local' => 'yes')))); $check(400 === $bad->get_status(), 'Invalid defaults rejected');
    $check(409 === $call('save', array('revision' => 0, 'rows' => $plan['rows'], 'defaults' => $defaults))->get_status(), 'Default updates obey revision checks');
    echo "PASS: $checks level-default checks\n";
} finally { foreach (array_reverse($ids) as $id) { wp_delete_post($id, true); } delete_option('tncp_plan_tncp_defaults_test'); unregister_post_type('tncp_defaults_test'); }
