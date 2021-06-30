<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wt-import-export-upsell-wrapper market-box table-box-main">
    <div class="ier-premium-upgrade">
        <div class="wt-ier-sidebar-wrapper wt-ier-p-5">
            <img src="<?php echo WT_P_IEW_PLUGIN_URL; ?>assets/images/upgrade/crown.svg" class="wt-ier-sidebar-img">
            <h3 class="wt-ier-sidebar-title wt-ier-center"><?php _e('Do not leave any data behind!<br>Upgrade to pro.');?></h3>
            <div class="wt-ier-row">
                <div class="wt-ier-col-12 wt-ier-col-md-6 wt-ier-border-md-right">
                    <div class="wt-ier-flex wt-ier-v-center ">
                        <img src="<?php echo WT_P_IEW_PLUGIN_URL; ?>assets/images/upgrade/money-back-badge.svg" class="wt-ier-icon">
                        <p class="wt-ier-sidebar-p"><?php _e('30 Day Money Back Guarantee');?></p>
                    </div>
                </div>
                <div class="wt-ier-col-12 wt-ier-col-md-6">
                    <div class="wt-ier-flex wt-ier-v-center">
                        <img src="<?php echo WT_P_IEW_PLUGIN_URL; ?>assets/images/upgrade/support.svg" class="wt-ier-icon">
                        <p class="wt-ier-sidebar-p"><?php _e('Fast and Superior Support');?></p>
                    </div>
                </div>
            </div>
            <div class="wt-ier-center wt-ier-pt-4">
                <a href="<?php echo admin_url( 'admin.php?page=wt_import_export_for_woo_basic#wt-pro-upgrade' );?>" target="_blank" class="wt-ier-green-btn"><?php _e('Upgrade to Premium');?></a>
            </div>
        </div>
    </div>
</div>