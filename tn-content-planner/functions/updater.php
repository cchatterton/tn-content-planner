<?php
if (!defined('ABSPATH')) { exit; }

function tncp_update_repository() { return 'https://github.com/cchatterton/tn-content-planner'; }
function tncp_clear_update_cache() {
    delete_site_transient('tncp_release');
    delete_site_transient('tncp_release_error');
}
function tncp_forced_update() {
    if (!current_user_can('update_plugins')) { return false; }
    // These request values only bypass a cache; the manual mutation separately verifies its nonce.
    $action = isset($_REQUEST['action']) && is_string($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : '';
    return isset($_REQUEST['force-check']) || isset($_REQUEST['tncp_check_updates']) || in_array($action, array('update-selected', 'upgrade-plugin', 'do-plugin-upgrade'), true);
}
function tncp_release_data($version, $body = '') {
    $version = ltrim((string) $version, 'vV');
    if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) { return false; }
    $base = tncp_update_repository();
    return array('version' => $version, 'body' => wp_strip_all_tags((string) $body), 'url' => $base . '/releases/tag/v' . $version, 'package' => $base . '/releases/download/v' . $version . '/tn-content-planner.zip');
}
function tncp_release_lookup() {
    static $forced = false;
    if (!$forced && tncp_forced_update()) { tncp_clear_update_cache(); $forced = true; }
    $cached = get_site_transient('tncp_release');
    if (is_array($cached) && !empty($cached['version'])) { return $cached; }
    if (get_site_transient('tncp_release_error')) { return false; }
    $args = array('timeout' => 8, 'limit_response_size' => 262144, 'headers' => array('User-Agent' => 'TN-Content-Planner/' . TNCP_VERSION));
    $manifest = wp_remote_get('https://raw.githubusercontent.com/cchatterton/tn-content-planner/main/update.json', $args);
    $release = false;
    if (!is_wp_error($manifest) && 200 === wp_remote_retrieve_response_code($manifest)) {
        $data = json_decode(wp_remote_retrieve_body($manifest), true);
        if (is_array($data) && is_scalar($data['version'] ?? null) && is_string($data['body'] ?? '')) { $release = tncp_release_data($data['version'], $data['body'] ?? ''); }
    }
    $limited = !is_wp_error($manifest) && 429 === wp_remote_retrieve_response_code($manifest);
    if (!$release && !$limited) {
        $redirect = wp_remote_get(tncp_update_repository() . '/releases/latest', array_merge($args, array('redirection' => 0)));
        if (!is_wp_error($redirect)) {
            $limited = 429 === wp_remote_retrieve_response_code($redirect);
            $location = wp_remote_retrieve_header($redirect, 'location');
            $prefix = tncp_update_repository() . '/releases/tag/';
            if (in_array(wp_remote_retrieve_response_code($redirect), array(301, 302, 303, 307, 308), true) && is_string($location) && str_starts_with($location, $prefix)) {
                $tag = substr($location, strlen($prefix));
                $release = tncp_release_data($tag, __('See the GitHub release for the full changelog.', 'tn-content-planner'));
                if ($release) {
                    $release['url'] = $prefix . rawurlencode($tag);
                    $release['package'] = tncp_update_repository() . '/releases/download/' . rawurlencode($tag) . '/tn-content-planner.zip';
                }
            }
        }
    }
    if (!$release && !$limited) {
        $response = wp_remote_get('https://api.github.com/repos/cchatterton/tn-content-planner/releases/latest', $args);
        if (!is_wp_error($response) && 200 === wp_remote_retrieve_response_code($response)) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (is_array($data) && is_string($data['tag_name'] ?? null) && empty($data['prerelease']) && empty($data['draft'])) {
                foreach (($data['assets'] ?? array()) as $asset) {
                    if ('tn-content-planner.zip' === ($asset['name'] ?? '') && !empty($asset['browser_download_url'])) {
                        $release = tncp_release_data($data['tag_name'], is_string($data['body'] ?? null) ? $data['body'] : '');
                        if ($release) {
                            $release['url'] = tncp_update_repository() . '/releases/tag/' . rawurlencode($data['tag_name']);
                            $release['package'] = tncp_update_repository() . '/releases/download/' . rawurlencode($data['tag_name']) . '/tn-content-planner.zip';
                        }
                        break;
                    }
                }
            }
        }
    }
    if (!$release) {
        set_site_transient('tncp_release_error', array('checked_at' => time()), 10 * MINUTE_IN_SECONDS);
        return false;
    }
    delete_site_transient('tncp_release_error');
    set_site_transient('tncp_release', $release, version_compare($release['version'], TNCP_VERSION, '>') ? 6 * HOUR_IN_SECONDS : 5 * MINUTE_IN_SECONDS);
    return $release;
}
add_filter('pre_set_site_transient_update_plugins', 'tncp_inject_update');
add_filter('site_transient_update_plugins', 'tncp_inject_update');
function tncp_inject_update($transient) {
    $release = tncp_release_lookup();
    if (!$release) { return $transient; }
    if (!is_object($transient)) { $transient = new stdClass(); }
    $transient->response = isset($transient->response) && is_array($transient->response) ? $transient->response : array();
    $transient->no_update = isset($transient->no_update) && is_array($transient->no_update) ? $transient->no_update : array();
    $file = plugin_basename(TNCP_PLUGIN_FILE);
    unset($transient->response[$file], $transient->no_update[$file]);
    if (version_compare($release['version'], TNCP_VERSION, '>')) {
        $transient->response[$file] = (object) array('id' => tncp_update_repository(), 'slug' => 'tn-content-planner', 'plugin' => $file, 'new_version' => $release['version'], 'url' => $release['url'], 'package' => $release['package'], 'requires' => '6.0', 'requires_php' => '8.1');
    }
    return $transient;
}
add_filter('plugins_api', 'tncp_plugin_information', 10, 3);
function tncp_plugin_information($result, $action, $args) {
    if ('plugin_information' !== $action || 'tn-content-planner' !== ($args->slug ?? '')) { return $result; }
    $release = tncp_release_lookup();
    if (!$release) { return $result; }
    return (object) array('name' => 'TN Content Planner', 'slug' => 'tn-content-planner', 'version' => $release['version'], 'author' => 'Techn', 'homepage' => tncp_update_repository(), 'download_link' => $release['package'], 'requires' => '6.0', 'requires_php' => '8.1', 'sections' => array('description' => esc_html__('Plan a content WBS and create selected WordPress drafts.', 'tn-content-planner'), 'changelog' => nl2br(esc_html($release['body']))));
}
add_filter('plugin_row_meta', 'tncp_plugin_links', 10, 2);
function tncp_plugin_links($links, $file) {
    if (plugin_basename(TNCP_PLUGIN_FILE) !== $file) { return $links; }
    $links[] = '<a href="' . esc_url(tncp_update_repository()) . '">GitHub</a>';
    if (current_user_can('update_plugins')) {
        $url = is_network_admin() ? network_admin_url('plugins.php') : admin_url('plugins.php');
        $url = wp_nonce_url(add_query_arg('tncp_check_updates', '1', $url), 'tncp_check_updates');
        $links[] = '<a href="' . esc_url($url) . '">' . esc_html__('Check for updates', 'tn-content-planner') . '</a>';
    }
    return $links;
}
add_action('admin_init', 'tncp_manual_update_check');
function tncp_manual_update_check() {
    if (!isset($_GET['tncp_check_updates'])) { return; }
    if (!current_user_can('update_plugins')) { wp_die(esc_html__('You cannot update plugins.', 'tn-content-planner')); }
    check_admin_referer('tncp_check_updates');
    tncp_clear_update_cache();
    delete_site_transient('update_plugins');
    wp_update_plugins();
    $transient = tncp_inject_update(get_site_transient('update_plugins'));
    set_site_transient('update_plugins', $transient);
    $release = tncp_release_lookup();
    $result = !$release ? 'failed' : (version_compare($release['version'], TNCP_VERSION, '>') ? 'available' : 'current');
    $url = is_network_admin() ? network_admin_url('plugins.php') : admin_url('plugins.php');
    wp_safe_redirect(add_query_arg('tncp_update_result', $result, $url));
    exit;
}
add_action('admin_notices', 'tncp_update_notice');
add_action('network_admin_notices', 'tncp_update_notice');
function tncp_update_notice() {
    if (!current_user_can('update_plugins') || 'plugins' !== get_current_screen()->base || !isset($_GET['tncp_update_result']) || !is_string($_GET['tncp_update_result'])) { return; }
    $messages = array('failed' => __('TN Content Planner could not check for updates. Please try again later.', 'tn-content-planner'), 'available' => __('An update for TN Content Planner is available. Use the update now link below.', 'tn-content-planner'), 'current' => __('TN Content Planner is up to date.', 'tn-content-planner'));
    $result = sanitize_key(wp_unslash($_GET['tncp_update_result']));
    if (isset($messages[$result])) { echo '<div class="notice notice-info is-dismissible"><p>' . esc_html($messages[$result]) . '</p></div>'; }
}
add_action('upgrader_process_complete', 'tncp_upgrader_complete', 10, 2);
function tncp_upgrader_complete($upgrader, $options) {
    if ('plugin' === ($options['type'] ?? '') && 'update' === ($options['action'] ?? '') && !is_wp_error($upgrader->result) && in_array(plugin_basename(TNCP_PLUGIN_FILE), $options['plugins'] ?? array($options['plugin'] ?? ''), true)) { tncp_clear_update_cache(); }
}
