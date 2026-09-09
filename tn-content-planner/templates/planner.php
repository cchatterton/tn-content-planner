<?php if (!defined('ABSPATH')) { exit; } ?>
<div class="wrap tncp-wrap">
    <h1 class="screen-reader-text"><?php esc_html_e('TN Content Planner', 'tn-content-planner'); ?></h1>
    <div id="tncp-notice" class="notice" role="status" aria-live="polite" hidden><p></p></div>
    <div class="tncp-hero">
        <span class="tncp-eyebrow">TECHN · <?php esc_html_e('CONTENT PLANNING', 'tn-content-planner'); ?></span>
        <h2><?php esc_html_e('TN Content Planner', 'tn-content-planner'); ?></h2>
        <p><?php esc_html_e('Give every piece of content a place. Plan the structure, then create it in WordPress.', 'tn-content-planner'); ?></p>
    </div>
    <div id="tncp-app" aria-busy="true">
        <p><?php esc_html_e('Loading your content plan…', 'tn-content-planner'); ?></p>
    </div>
    <dialog id="tncp-dialog" class="tncp-dialog" aria-labelledby="tncp-dialog-title" aria-describedby="tncp-dialog-description">
        <form method="dialog">
            <h2 id="tncp-dialog-title"></h2>
            <p id="tncp-dialog-description"></p>
            <div id="tncp-dialog-actions" class="tncp-actions"></div>
        </form>
    </dialog>
</div>
