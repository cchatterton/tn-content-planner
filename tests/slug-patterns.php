<?php
/** Run against a disposable WordPress site with WP CLI. */
if (!defined('WP_CLI') || !WP_CLI) { exit; }
wp_set_current_user(1);
global $checks;
$checks = 0; $created = array();
function scope_check($ok, $message) { global $checks; if (!$ok) { throw new RuntimeException($message); } ++$checks; }
function scope_row($id, $slug, $parent = '') { return array('id' => $id, 'title' => $id, 'slug' => $slug, 'parent' => $parent, 'post_id' => 0, 'template' => 'single', 'flags' => array(), 'confirmed' => array('title' => true, 'slug' => true, 'parent' => true)); }
function scope_request($action, $params) { $r = new WP_REST_Request('POST', '/tncp/v1/' . $action . '/tncp_scope_test'); foreach ($params as $k => $v) { $r->set_param($k, $v); } return rest_do_request($r); }
$old = get_option('tncp_plan_tncp_scope_test', null); $old_patterns = get_option('tncp_patterns', null);
register_post_type('tncp_scope_test', array('public' => true, 'hierarchical' => true));
register_post_type('tncp_flat_test', array('public' => true));
try {
    delete_option('tncp_plan_tncp_scope_test');
    $empty = array('revision' => 0, 'rows' => array());
    $a = scope_row('alpha', 'alpha'); $b = scope_row('beta', 'beta');
    $one = scope_row('one', 'shared', 'row:alpha'); $two = scope_row('two', 'shared', 'row:beta');
    // Children deliberately precede parents in the input.
    $rows = array($one, $two, $b, $a);
    scope_check(!is_wp_error(tncp_validate_rows($rows, 'tncp_scope_test', $empty)), 'Different uncreated parents allow the same slug');
    scope_check(is_wp_error(tncp_validate_rows($rows, 'tncp_flat_test', $empty)), 'Flat post types retain WordPress global slug uniqueness');
    $duplicate = $rows; $duplicate[1]['parent'] = 'row:alpha';
    scope_check(is_wp_error(tncp_validate_rows($duplicate, 'tncp_scope_test', $empty)), 'Same-parent duplicate rejected');
    $blank = scope_row('blank', ''); $blank2 = scope_row('blank-two', '');
    $saved = scope_request('save', array('revision' => 0, 'rows' => array_merge($rows, array($blank, $blank2))));
    scope_check(200 === $saved->get_status(), 'Save multiple blank-slug rows'); $plan = $saved->get_data();
    $blocked = scope_request('apply', array('revision' => $plan['revision'], 'selected' => array('blank'), 'creation_status' => 'publish'));
    scope_check(400 === $blocked->get_status(), 'Apply rejects blank slug');
    $blocked = scope_request('resolve', array('revision' => $plan['revision'], 'row_id' => 'blank', 'decision' => 'new', 'new_slug' => 'override', 'creation_status' => 'publish'));
    scope_check(400 === $blocked->get_status(), 'Review cannot bypass blank-slug selection rule');
    $applied = scope_request('apply', array('revision' => $plan['revision'], 'selected' => array('one', 'two', 'alpha', 'beta'), 'creation_status' => 'publish'));
    scope_check(200 === $applied->get_status() && !$applied->get_data()['errors'], 'Create same slug under separate parents');
    $plan = $applied->get_data()['plan']; $mapped = array_column($plan['rows'], null, 'id');
    $created = array_filter(array_column($plan['rows'], 'post_id'));
    scope_check('shared' === get_post($mapped['one']['post_id'])->post_name && 'shared' === get_post($mapped['two']['post_id'])->post_name, 'WordPress preserves both shared slugs');
    scope_check(get_post($mapped['one']['post_id'])->post_parent !== get_post($mapped['two']['post_id'])->post_parent, 'Created hierarchy is correct');
    $remapped = tncp_validate_rows($rows, 'tncp_scope_test', $empty);
    scope_check(!is_wp_error($remapped), 'Parent-first resolution accepts children-first mapping');
    $remapped = array_column($remapped, null, 'id');
    scope_check($remapped['one']['post_id'] === $mapped['one']['post_id'] && $remapped['two']['post_id'] === $mapped['two']['post_id'], 'Slug mapping selects the correct parent branch');
    $scan = tncp_scan_rows(array('revision' => 0, 'rows' => $rows), 'tncp_scope_test');
    scope_check(4 === count($scan['rows']), 'Scan maps branch-specific slugs without extra rows');
    $collision = scope_request('change', array('revision' => $plan['revision'], 'row_id' => 'one', 'post_id' => $mapped['one']['post_id'], 'baseline' => $mapped['one']['baseline'], 'field' => 'parent', 'value' => $mapped['beta']['post_id'], 'confirmed' => true));
    scope_check(400 === $collision->get_status(), 'Moving a shared slug into occupied parent is rejected before mutation');
    scope_check((int) get_post($mapped['one']['post_id'])->post_parent === $mapped['alpha']['post_id'], 'Rejected move preserves parent');
    $renamed = scope_request('change', array('revision' => $plan['revision'], 'row_id' => 'one', 'post_id' => $mapped['one']['post_id'], 'baseline' => $mapped['one']['baseline'], 'field' => 'slug', 'value' => 'beta', 'confirmed' => true));
    scope_check(200 === $renamed->get_status() && 'beta' === get_post($mapped['one']['post_id'])->post_name, 'Immediate slug change allows same slug as root post');
    $plan = $renamed->get_data()['plan'];
    $patterns = tncp_patterns_data(); $entries = array_column($patterns['rows'], null, 'key');
    $root = $entries['tncp_scope_test-0-single-0']; $child = $entries['tncp_scope_test-1-single-0'];
    scope_check(2 === $root['mapped_count'] && 4 === $root['count'], 'Pattern counts mapped / total including blank-slug plans');
    scope_check(2 === $child['mapped_count'] && 2 === $child['count'], 'Child pattern mapped count');
    scope_check(in_array($mapped['one']['post_id'], $child['example_ids'], true) && !in_array($mapped['alpha']['post_id'], $child['example_ids'], true), 'Examples only contain mapped members of that pattern');
    $request = new WP_REST_Request('POST', '/tncp/v1/patterns'); $request->set_param('revision', $patterns['revision']);
    $child['post_id'] = $mapped['alpha']['post_id']; $request->set_param('rows', array($child));
    scope_check(400 === rest_do_request($request)->get_status(), 'Same-type example with wrong pattern rejected');
    $child['post_id'] = $mapped['one']['post_id']; $child['status'] = 'done'; $request->set_param('rows', array($child));
    scope_check(200 === rest_do_request($request)->get_status(), 'Matching example accepted');
    scope_check(tncp_pattern_examples()[$child['key']] === $mapped['one']['post_id'], 'Content tab example link is supplied');
    $before = tncp_pattern_counts()['done'];
    $index = array_search('one', array_column($plan['rows'], 'id'), true); $plan['rows'][$index]['template'] = 'archive'; update_option('tncp_plan_tncp_scope_test', $plan, false);
    scope_check(!isset(tncp_pattern_examples()[$child['key']]), 'Example link disappears when its item moves to another pattern');
    scope_check($before - 1 === tncp_pattern_counts()['done'], 'Wrong-pattern example no longer counts as done');
    echo "PASS: $checks slug and pattern checks\n";
} finally {
    foreach (array_reverse($created) as $id) { wp_delete_post($id, true); }
    if (null === $old) { delete_option('tncp_plan_tncp_scope_test'); } else { update_option('tncp_plan_tncp_scope_test', $old, false); }
    if (null === $old_patterns) { delete_option('tncp_patterns'); } else { update_option('tncp_patterns', $old_patterns, false); }
    unregister_post_type('tncp_scope_test'); unregister_post_type('tncp_flat_test');
}
