<?php
/**
 * Plugin Name: TN Content Planner
 * Description: Plan content by post type, arrange a WBS, and create or update selected WordPress content.
 * Version: 0.3.18
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: Techn
 * Author URI: https://techn.com.au
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tn-content-planner
 * Update URI: https://github.com/cchatterton/tn-content-planner
 */
if (!defined('ABSPATH')) { exit; }
define('TNCP_VERSION', '0.3.18');
define('TNCP_PLUGIN_FILE', __FILE__);
define('TNCP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TNCP_PLUGIN_URL', plugin_dir_url(__FILE__));
foreach (array('helpers', 'admin', 'assets', 'rest', 'updater') as $tncp_file) {
    require_once TNCP_PLUGIN_DIR . 'functions/' . $tncp_file . '.php';
}
unset($tncp_file);

require_once TNCP_PLUGIN_DIR . 'functions/patterns.php';

require_once TNCP_PLUGIN_DIR . 'functions/changes.php';
