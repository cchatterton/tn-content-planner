<?php if (!defined('ABSPATH')) { exit; } ?>
<div class="wrap tncp-wrap">
    <h1 class="screen-reader-text"><?php esc_html_e('Content Planner', 'tn-content-planner'); ?></h1>
    <div id="tncp-notice" class="notice" role="status" aria-live="polite" hidden><p></p></div>
    <div class="tncp-hero">
        <span class="tncp-version" aria-label="<?php echo esc_attr(sprintf(__('Plugin version %s', 'tn-content-planner'), TNCP_VERSION)); ?>">v<?php echo esc_html(TNCP_VERSION); ?></span>
        <div class="tncp-hero-copy">
        <span class="tncp-eyebrow"><?php esc_html_e('Plan. Organise. Publish.', 'tn-content-planner'); ?></span>
        <h2><?php esc_html_e('Content Planner', 'tn-content-planner'); ?></h2>
        <p><?php esc_html_e('Give every piece of content a place. Plan the structure, then create it in WordPress.', 'tn-content-planner'); ?></p>
        </div>
        <ul class="tncp-capabilities" aria-label="<?php esc_attr_e('Plugin features', 'tn-content-planner'); ?>">
            <li><?php esc_html_e('Visual hierarchy', 'tn-content-planner'); ?></li>
            <li><?php esc_html_e('CSV import', 'tn-content-planner'); ?></li>
            <li><?php esc_html_e('Publish or draft', 'tn-content-planner'); ?></li>
        </ul>
    </div>
    <div id="tncp-loading" class="tncp-loading" role="status" hidden><span class="spinner is-active" aria-hidden="true"></span><?php esc_html_e('Loading linked posts…', 'tn-content-planner'); ?></div>
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
