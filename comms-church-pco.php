<?php
/**
 * Plugin Name:       Comms.Church — Planning Center Registrations
 * Plugin URI:        https://github.com/Comms-Church/comms-church-pco
 * Description:       Display Planning Center Registrations events on any WordPress site via Gutenberg blocks and shortcodes. API credentials stored securely server-side.
 * Version:           1.3.0
 * Author:            Comms.Church
 * Author URI:        https://comms.church
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       comms-church-pco
 * Requires at least: 5.8
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/*
 * Graceful guard for hosts running PHP older than 7.4.
 * Deactivates cleanly with an admin notice instead of a white-screen fatal.
 */
if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
    add_action( 'admin_notices', function () {
        echo '<div class="notice notice-error"><p><strong>Comms.Church PCO:</strong> This plugin requires PHP 7.4 or higher. Your server is running PHP ' . esc_html( PHP_VERSION ) . '. Please ask your host to upgrade.</p></div>';
    } );
    add_action( 'admin_init', function () {
        deactivate_plugins( plugin_basename( __FILE__ ) );
    } );
    return;
}

define( 'CCPCO_VERSION',     '1.3.0' );
define( 'CCPCO_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'CCPCO_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'CCPCO_PLUGIN_FILE', __FILE__ );

require_once CCPCO_PLUGIN_DIR . 'includes/polyfills.php';
require_once CCPCO_PLUGIN_DIR . 'includes/class-ccpco-api.php';
require_once CCPCO_PLUGIN_DIR . 'includes/class-ccpco-cache.php';
require_once CCPCO_PLUGIN_DIR . 'includes/class-ccpco-calendar.php';
require_once CCPCO_PLUGIN_DIR . 'includes/class-ccpco-renderer.php';
require_once CCPCO_PLUGIN_DIR . 'includes/class-ccpco-shortcodes.php';
require_once CCPCO_PLUGIN_DIR . 'includes/class-ccpco-blocks.php';
require_once CCPCO_PLUGIN_DIR . 'includes/class-ccpco-bricks.php';
require_once CCPCO_PLUGIN_DIR . 'includes/class-ccpco-webhook.php';
require_once CCPCO_PLUGIN_DIR . 'includes/class-ccpco-admin.php';

if ( is_admin() ) {
    require_once CCPCO_PLUGIN_DIR . 'includes/class-ccpco-updater.php';
}

register_activation_hook( __FILE__, array( 'CCPCO', 'activate' ) );

add_action( 'plugins_loaded', array( 'CCPCO', 'init' ) );

class CCPCO {

    public static function init() {
        new CCPCO_Admin();
        new CCPCO_Shortcodes();
        new CCPCO_Blocks();
        new CCPCO_Bricks();
        new CCPCO_Webhook();

        if ( is_admin() && class_exists( 'Comms_Church_PCO_Updater' ) ) {
            new Comms_Church_PCO_Updater( CCPCO_PLUGIN_FILE, CCPCO_VERSION );
        }
    }

    public static function activate() {
        // Generate webhook secret on activation
        if ( ! get_option( 'ccpco_webhook_secret' ) ) {
            update_option( 'ccpco_webhook_secret', wp_generate_password( 40, false ) );
        }
        // Set default brand settings
        if ( ! get_option( 'ccpco_brand_color' ) ) {
            update_option( 'ccpco_brand_color', '#1a4a8a' );
        }
    }
}
