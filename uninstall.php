<?php
/**
 * Uninstall cleanup for Comms.Church — Planning Center Registrations.
 * Runs only when the plugin is DELETED from the Plugins screen (not on
 * deactivation). Removes every option and transient the plugin created,
 * including the stored API credentials.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

$ccpco_options = array(
    'ccpco_app_id',
    'ccpco_secret',
    'ccpco_cache_ttl',
    'ccpco_brand_color',
    'ccpco_button_color',
    'ccpco_corner_radius',
    'ccpco_font_size_base',
    'ccpco_webhook_secret',
    'ccpco_webhook_log',
    'ccpco_cache_generation',
);

foreach ( $ccpco_options as $ccpco_option ) {
    delete_option( $ccpco_option );
}

// Remove all plugin transients (data cache, stale copies, breakers, updater).
global $wpdb;
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '\_transient\_ccpco%'
        OR option_name LIKE '\_transient\_timeout\_ccpco%'"
);
