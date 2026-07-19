<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * CCPCO_Bricks
 *
 * Registers native Bricks Builder elements when the Bricks theme is active.
 * On non-Bricks sites this class silently does nothing — the Gutenberg
 * blocks and shortcodes remain available everywhere.
 *
 * Mirrors the registration pattern proven in production on gccws.net:
 * \Bricks\Elements::register_element() is given the file path and includes
 * the element class itself.
 */
class CCPCO_Bricks {

    public function __construct() {
        add_action( 'init', array( $this, 'register' ), 20 );
    }

    public function register() {
        if ( ! class_exists( '\Bricks\Elements' ) ) return;

        \Bricks\Elements::register_element( CCPCO_PLUGIN_DIR . 'includes/bricks/element-signup-list.php' );
        \Bricks\Elements::register_element( CCPCO_PLUGIN_DIR . 'includes/bricks/element-signup-card.php' );
        \Bricks\Elements::register_element( CCPCO_PLUGIN_DIR . 'includes/bricks/element-register-button.php' );
    }

    /** True when Bricks (theme or builder context) is present. */
    public static function is_active() {
        return defined( 'BRICKS_VERSION' ) || class_exists( '\Bricks\Elements' );
    }
}
