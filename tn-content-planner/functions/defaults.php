<?php
if (!defined('ABSPATH')) { exit; }

function tncp_level_defaults($plan, $level) {
    $flags = array();
    foreach (array('local', 'related', 'children', 'siblings', 'parents') as $flag) {
        $flags[$flag] = $level >= 1 && $level <= 3 && !empty($plan['defaults'][$level][$flag]);
    }
    return $flags;
}
function tncp_validate_defaults($input) {
    if (!is_array($input)) { return tncp_error(__('Send valid level defaults.', 'tn-content-planner')); }
    $defaults = array();
    foreach (array(1, 2, 3) as $level) {
        foreach (array('local', 'related', 'children', 'siblings', 'parents') as $flag) {
            $value = $input[$level][$flag] ?? false;
            if (!is_bool($value)) { return tncp_error(__('Level defaults must be checked or unchecked.', 'tn-content-planner')); }
            $defaults[$level][$flag] = $value;
        }
    }
    return $defaults;
}
