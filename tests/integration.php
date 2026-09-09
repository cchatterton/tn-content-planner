<?php
/** Run only against a disposable WordPress site: wp eval-file tests/integration.php. */
if (!defined('WP_CLI') || !WP_CLI) { exit; }
wp_set_current_user(1);
global $checks;
$checks = 0;
function tncp_test($condition, $message) {
    global $checks;
    if (!$condition) { throw new RuntimeException($message); }
    ++$checks;
}
function tncp_test_request($action, $params = array(), $type = 'page', $method = 'POST') {
    $request = new WP_REST_Request($method, '/tncp/v1/' . $action . '/' . $type);
    foreach ($params as $key => $value) { $request->set_param($key, $value); }
    return rest_do_request($request);
}
function tncp_test_row($id, $title, $parent = '') {
    return array('id' => $id, 'title' => $title, 'slug' => strtolower($id), 'parent' => $parent, 'template' => 'single', 'post_id' => 0, 'flags' => array('local' => true, 'related' => false, 'children' => true, 'siblings' => false, 'parents' => false), 'confirmed' => array());
}
$run = 'test-' . wp_generate_password(8, false, false);
$run = strtolower($run);
$original = get_option('tncp_plan_page', null);
$created = array();
try {
    register_post_type('tncp_public_test', array('public' => true, 'show_ui' => true));
    register_post_type('tncp_hidden_test', array('public' => true, 'show_ui' => false, 'capabilities' => array('publish_posts' => 'tncp_test_publish')));
    register_post_type('tncp_private_test', array('public' => false, 'show_ui' => true));
    register_post_type('tncp_cap_test', array('public' => true, 'capability_type' => 'tncp_restricted', 'map_meta_cap' => true));
    $types = tncp_types();
    tncp_test(isset($types['post'], $types['page']), 'Public built-in content types included');
    tncp_test(isset($types['tncp_public_test']), 'Public custom post type included');
    tncp_test(isset($types['tncp_hidden_test']), 'Public custom type with hidden admin UI included');
    tncp_test(!isset($types['tncp_private_test']), 'Non-public admin-visible type excluded');
    tncp_test(!isset($types['attachment']), 'Media attachments excluded');
    tncp_test(!isset($types['tncp_cap_test']), 'Public type without edit capability excluded');
    tncp_test(200 === tncp_test_request('plan', array(), 'tncp_hidden_test', 'GET')->get_status(), 'REST permits public type with hidden admin UI');
    tncp_test(403 === tncp_test_request('plan', array(), 'tncp_private_test', 'GET')->get_status(), 'REST rejects non-public type');
    tncp_test(403 === tncp_test_request('plan', array(), 'tncp_cap_test', 'GET')->get_status(), 'REST rejects public type without capability');
    $custom = tncp_test_row($run . '-custom', 'Public custom content');
    $saved_custom = tncp_test_request('save', array('revision' => 0, 'rows' => array($custom)), 'tncp_hidden_test')->get_data();
    tncp_test(isset($saved_custom['revision']), 'Public custom type plan saves');
    tncp_test(403 === tncp_test_request('apply', array('revision' => $saved_custom['revision'], 'selected' => array($custom['id'])), 'tncp_hidden_test')->get_status(), 'Default publishing requires publish capability');
    $applied_custom = tncp_test_request('apply', array('revision' => $saved_custom['revision'], 'selected' => array($custom['id']), 'creation_status' => 'draft'), 'tncp_hidden_test')->get_data();
    tncp_test(isset($applied_custom['plan']), 'Public custom type creates draft');
    $custom_id = $applied_custom['plan']['rows'][0]['post_id'];
    tncp_test('tncp_hidden_test' === get_post_type($custom_id) && 'draft' === get_post_status($custom_id), 'Created draft belongs to public custom type');
    wp_delete_post($custom_id, true);
    delete_option('tncp_plan_tncp_hidden_test');
    $published = tncp_test_request('save', array('revision' => 0, 'rows' => array($custom)), 'tncp_public_test')->get_data();
    tncp_test(400 === tncp_test_request('apply', array('revision' => $published['revision'], 'selected' => array($custom['id']), 'creation_status' => 'private'), 'tncp_public_test')->get_status(), 'Unsupported creation status rejected');
    $published = tncp_test_request('apply', array('revision' => $published['revision'], 'selected' => array($custom['id'])), 'tncp_public_test')->get_data();
    $published_id = $published['plan']['rows'][0]['post_id'];
    tncp_test('publish' === get_post_status($published_id), 'New posts default to published');
    wp_delete_post($published_id, true);
    delete_option('tncp_plan_tncp_public_test');
    delete_option('tncp_plan_page');
    $root = tncp_test_row($run . '-root', '<i class="fa-solid fa-house" aria-hidden="true"></i> Home');
    $child = tncp_test_row($run . '-child', 'Child', 'row:' . $root['id']);
    $response = tncp_test_request('save', array('revision' => 0, 'rows' => array($child, $root)));
    tncp_test(200 === $response->get_status(), 'Initial save: ' . wp_json_encode($response->get_data()));
    $plan = $response->get_data();
    tncp_test('page-1-single-2' === $plan['rows'][0]['pattern'], 'Child XP pattern');
    tncp_test('page-0-single-2' === $plan['rows'][1]['pattern'], 'Root XP pattern');
    tncp_test(str_contains($plan['rows'][1]['title'], 'fa-house'), 'Allowed icon HTML retained');
    tncp_test(0 === $plan['rows'][1]['post_id'], 'Saving does not create a post');
    $bad = tncp_test_request('save', array('revision' => 0, 'rows' => $plan['rows']));
    tncp_test(409 === $bad->get_status(), 'Reject stale plan revision');
    $bad = tncp_test_request('apply', array('revision' => $plan['revision'], 'selected' => array($child['id'])));
    tncp_test(400 === $bad->get_status(), 'Require selected new parent');
    $result = tncp_test_request('apply', array('revision' => $plan['revision'], 'selected' => array($child['id'], $root['id']), 'creation_status' => 'draft'))->get_data();
    tncp_test(isset($result['plan']), 'Apply succeeded: ' . wp_json_encode($result));
    $plan = $result['plan'];
    $created = array_column($plan['rows'], 'post_id');
    $child_id = $plan['rows'][0]['post_id']; $root_id = $plan['rows'][1]['post_id'];
    tncp_test(2 === count($result['completed']), 'Both rows created');
    tncp_test($root_id === (int) get_post($child_id)->post_parent, 'Create parent before child');
    tncp_test('draft' === get_post_status($root_id), 'New post is a draft');
    tncp_test('page-1-single-2' === get_post_meta($child_id, '_tncp_pattern', true), 'Pattern metadata');
    $again = tncp_test_request('apply', array('revision' => $plan['revision'], 'selected' => array($child['id'], $root['id'])))->get_data();
    tncp_test($created === array_column($again['plan']['rows'], 'post_id'), 'Retries retain IDs');
    $plan = $again['plan'];
    $rows = $plan['rows']; $rows[0]['title'] = 'Renamed child';
    tncp_test(400 === tncp_test_request('save', array('revision' => $plan['revision'], 'rows' => $rows))->get_status(), 'Reject title change without confirmation');
    $rows[0]['confirmed']['title'] = true;
    $plan = tncp_test_request('save', array('revision' => $plan['revision'], 'rows' => $rows))->get_data();
    tncp_test('Child' === get_the_title($child_id), 'Save stages confirmed edit without mutating post');
    wp_update_post(array('ID' => $child_id, 'post_content' => 'Keep this content', 'post_status' => 'publish'));
    $result = tncp_test_request('apply', array('revision' => $plan['revision'], 'selected' => array($child['id'])))->get_data();
    tncp_test(isset($result['plan']), 'Apply title edit');
    $plan = $result['plan'];
    tncp_test('Renamed child' === get_the_title($child_id), 'Linked title updated');
    tncp_test('Keep this content' === get_post($child_id)->post_content && 'publish' === get_post_status($child_id), 'Preserve content and publication status');
    $rows = $plan['rows']; $rows[0]['parent'] = ''; $rows[0]['confirmed']['parent'] = true;
    $plan = tncp_test_request('save', array('revision' => $plan['revision'], 'rows' => $rows))->get_data();
    $result = tncp_test_request('apply', array('revision' => $plan['revision'], 'selected' => array($child['id'])))->get_data(); $plan = $result['plan'];
    tncp_test(0 === (int) get_post($child_id)->post_parent, 'Confirmed parent move applied');
    $rows = $plan['rows']; $rows[0]['slug'] .= '-renamed'; $rows[0]['confirmed']['slug'] = true;
    $plan = tncp_test_request('save', array('revision' => $plan['revision'], 'rows' => $rows))->get_data();
    $result = tncp_test_request('apply', array('revision' => $plan['revision'], 'selected' => array($child['id'])))->get_data(); $plan = $result['plan'];
    tncp_test(str_ends_with(get_post($child_id)->post_name, '-renamed'), 'Confirmed slug change applied');
    $rows = $plan['rows']; $rows[1]['parent'] = 'row:' . $child['id']; $rows[0]['parent'] = 'post:' . $root_id;
    tncp_test(400 === tncp_test_request('save', array('revision' => $plan['revision'], 'rows' => $rows))->get_status(), 'Reject mixed row/post cycle');
    $rows = $plan['rows']; $rows[0]['slug'] = $rows[1]['slug']; $rows[0]['confirmed']['slug'] = true;
    tncp_test(400 === tncp_test_request('save', array('revision' => $plan['revision'], 'rows' => $rows))->get_status(), 'Reject slug belonging to another post');
    $rows = $plan['rows']; $rows[0]['post_id'] = 0;
    tncp_test(400 === tncp_test_request('save', array('revision' => $plan['revision'], 'rows' => $rows))->get_status(), 'Reject detaching mapped row');
    wp_update_post(array('ID' => $child_id, 'post_title' => 'Edited elsewhere'));
    tncp_test(409 === tncp_test_request('apply', array('revision' => $plan['revision'], 'selected' => array($child['id'])))->get_status(), 'Reject external post conflicts');
    $response = tncp_test_request('refresh', array('revision' => $plan['revision']));
    tncp_test(200 === $response->get_status(), 'Refresh conflicts'); $plan = $response->get_data();
    tncp_test('Edited elsewhere' === $plan['rows'][0]['title'], 'Refresh reads linked title');
    // Auto-map unique existing slug from a fresh plan.
    delete_option('tncp_plan_page');
    $mapping = tncp_test_row($run . '-mapping', get_post($root_id)->post_title);
    $mapping['slug'] = get_post($root_id)->post_name;
    $mapped = tncp_test_request('save', array('revision' => 0, 'rows' => array($mapping)))->get_data();
    tncp_test($root_id === $mapped['rows'][0]['post_id'], 'Unique slug maps post ID');
    $duplicate_id = wp_insert_post(array('post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Duplicate draft', 'post_name' => $mapping['slug']));
    $created[] = $duplicate_id;
    delete_option('tncp_plan_page');
    tncp_test(400 === tncp_test_request('save', array('revision' => 0, 'rows' => array($mapping)))->get_status(), 'Ambiguous existing slug rejected');
    $mapping['post_id'] = $root_id;
    $mapped = tncp_test_request('save', array('revision' => 0, 'rows' => array($mapping)))->get_data();
    tncp_test($root_id === $mapped['rows'][0]['post_id'], 'Explicit Post ID resolves unchanged ambiguous slug');
    $clean = tncp_title('<i class="fa-solid fa-house" onclick="alert(1)"></i><img src=x onerror=alert(1)><strong>Safe</strong>');
    tncp_test(!str_contains($clean, 'onclick') && !str_contains($clean, '<img') && str_contains($clean, '<strong>'), 'HTML allowlist strips executable markup');
    tncp_test(400 === tncp_test_request('save', array('revision' => $mapped['revision'], 'rows' => array(array('id' => array()))))->get_status(), 'Malformed row rejected');
    // Effective selected batch cycle: moving B under A requires first moving A out of B.
    wp_update_post(array('ID' => $child_id, 'post_parent' => $root_id));
    delete_option('tncp_plan_page');
    $a = tncp_test_row($run . '-a', get_post($child_id)->post_title); $a['post_id'] = $child_id; $a['slug'] = get_post($child_id)->post_name; $a['confirmed']['parent'] = true;
    $b = tncp_test_row($run . '-b', get_post($root_id)->post_title); $b['post_id'] = $root_id; $b['slug'] = get_post($root_id)->post_name; $b['parent'] = 'row:' . $a['id']; $b['confirmed']['parent'] = true;
    $plan = tncp_test_request('save', array('revision' => 0, 'rows' => array($a, $b)))->get_data();
    tncp_test(isset($plan['revision']), 'Future reversed hierarchy can be saved');
    tncp_test(400 === tncp_test_request('apply', array('revision' => $plan['revision'], 'selected' => array($b['id'])))->get_status(), 'Selected batch cannot depend on unselected pending move');
    $result = tncp_test_request('apply', array('revision' => $plan['revision'], 'selected' => array($b['id'], $a['id'])))->get_data();
    tncp_test(2 === count($result['completed']), 'Selected reversed hierarchy applies in safe order');
    wp_set_current_user(0);
    tncp_test(401 === tncp_test_request('plan', array(), 'page', 'GET')->get_status(), 'Anonymous REST blocked');
    wp_set_current_user(1);
    $subscriber = wp_create_user($run, wp_generate_password(), $run . '@example.test');
    wp_set_current_user($subscriber);
    tncp_test(403 === tncp_test_request('save', array('revision' => 0, 'rows' => array()))->get_status(), 'Subscriber REST blocked');
    wp_set_current_user(1);
    require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user($subscriber);
    // Updater contracts with isolated mocked network responses.
    $calls = array();
    $mock = static function($pre, $args, $url) use (&$calls) {
        $calls[] = $url;
        return array('headers' => array(), 'body' => wp_json_encode(array('version' => '0.2.0', 'body' => 'Test release')), 'response' => array('code' => 200, 'message' => 'OK'), 'cookies' => array());
    };
    add_filter('pre_http_request', $mock, 10, 3); tncp_clear_update_cache();
    $update = tncp_inject_update((object) array('response' => 'invalid', 'no_update' => 'invalid'));
    tncp_test(1 === count($calls) && str_contains($calls[0], 'update.json'), 'Valid manifest avoids GitHub API');
    tncp_test('0.2.0' === $update->response[plugin_basename(TNCP_PLUGIN_FILE)]->new_version, 'Native update injected');
    tncp_test(!isset($update->no_update[plugin_basename(TNCP_PLUGIN_FILE)]), 'No stale no_update');
    tncp_release_lookup(); tncp_test(1 === count($calls), 'Successful lookup cached');
    remove_filter('pre_http_request', $mock, 10);
    tncp_clear_update_cache(); $calls = array();
    $failed = static function($pre, $args, $url) use (&$calls) { $calls[] = $url; return array('headers' => array(), 'body' => 'rate limited', 'response' => array('code' => 429, 'message' => 'Too Many Requests'), 'cookies' => array()); };
    add_filter('pre_http_request', $failed, 10, 3);
    tncp_test(false === tncp_release_lookup(), 'Failed lookup returns false');
    tncp_test(false === get_site_transient('tncp_release'), 'Failure is not cached as release');
    tncp_release_lookup(); tncp_test(1 === count($calls), 'Rate limiting stops fallback and activates backoff');
    remove_filter('pre_http_request', $failed, 10); tncp_clear_update_cache();
    $calls = array();
    $fallback = static function($pre, $args, $url) use (&$calls) {
        $calls[] = $url;
        if (str_contains($url, 'update.json')) { return new WP_Error('offline', 'Unavailable'); }
        return array('headers' => array('location' => tncp_update_repository() . '/releases/tag/v0.3.0'), 'body' => '', 'response' => array('code' => 302, 'message' => 'Found'), 'cookies' => array());
    };
    add_filter('pre_http_request', $fallback, 10, 3);
    $release = tncp_release_lookup();
    tncp_test('0.3.0' === $release['version'] && 2 === count($calls), 'Public redirect fallback avoids API');
    remove_filter('pre_http_request', $fallback, 10); tncp_clear_update_cache();
    $equal = tncp_release_data(TNCP_VERSION, 'Current'); set_site_transient('tncp_release', $equal, 300);
    $file = plugin_basename(TNCP_PLUGIN_FILE);
    $transient = tncp_inject_update((object) array('response' => array($file => (object) array('new_version' => '0.0.1')), 'no_update' => array($file => new stdClass())));
    tncp_test(!isset($transient->response[$file]) && !isset($transient->no_update[$file]), 'Equal version removes stale update entries');
    $details = tncp_plugin_information(false, 'plugin_information', (object) array('slug' => 'tn-content-planner'));
    tncp_test('TN Content Planner' === $details->name && str_contains($details->sections['changelog'], 'Current'), 'Native plugin details include changelog');
    tncp_clear_update_cache();
    tncp_test(false === tncp_release_data('https://evil.test/zip'), 'Reject invalid release versions');
    add_option('tncp_lock_page', time(), '', false);
    $locked = tncp_test_request('save', array('revision' => 0, 'rows' => array()));
    tncp_test(409 === $locked->get_status(), 'Concurrent plan mutation lock');
    delete_option('tncp_lock_page');
    echo "PASS: $checks WordPress integration checks\n";
} finally {
    wp_set_current_user(1);
    foreach ($created as $id) { wp_delete_post($id, true); }
    if (null === $original) { delete_option('tncp_plan_page'); } else { update_option('tncp_plan_page', $original, false); }
    tncp_clear_update_cache();
    delete_option('tncp_plan_tncp_hidden_test');
    delete_option('tncp_plan_tncp_public_test');
    foreach (array('tncp_public_test', 'tncp_hidden_test', 'tncp_private_test', 'tncp_cap_test') as $fixture) { unregister_post_type($fixture); }
}
