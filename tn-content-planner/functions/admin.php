<?php
if (!defined('ABSPATH')) { exit; }
add_action('admin_menu', 'tncp_admin_menu');
function tncp_admin_menu() {
    add_menu_page(__('TN Content Planner', 'tn-content-planner'), __('Content Planner', 'tn-content-planner'), 'manage_options', 'tn-content-planner', 'tncp_admin_page', 'dashicons-networking', 58);
}
function tncp_admin_page() {
    if (!current_user_can('manage_options')) { return; }
    require TNCP_PLUGIN_DIR . 'templates/planner.php';
}
