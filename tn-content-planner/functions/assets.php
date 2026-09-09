<?php
if (!defined('ABSPATH')) { exit; }
add_action('admin_enqueue_scripts', 'tncp_assets');
function tncp_assets($hook) {
    if ('toplevel_page_tn-content-planner' !== $hook) { return; }
    wp_enqueue_style('tncp-icons', TNCP_PLUGIN_URL . 'assets/fontawesome/css/all.min.css', array(), '6.7.2');
    wp_enqueue_style('tncp-admin', TNCP_PLUGIN_URL . 'styles/tn-content-planner.css', array(), TNCP_VERSION);
    wp_enqueue_script('tncp-admin', TNCP_PLUGIN_URL . 'scripts/tn-content-planner.js', array('wp-i18n'), TNCP_VERSION, true);
    wp_set_script_translations('tncp-admin', 'tn-content-planner');
    $types = array();
    foreach (tncp_types() as $type) { $types[] = array('name' => $type->name, 'label' => $type->label, 'hierarchical' => $type->hierarchical); }
    wp_localize_script('tncp-admin', 'TNCP', array('api' => rest_url('tncp/v1/'), 'nonce' => wp_create_nonce('wp_rest'), 'types' => $types));
}
