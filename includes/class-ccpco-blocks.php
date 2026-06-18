<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Registers three Gutenberg blocks using server-side rendering.
 * No build step required — all rendering is PHP, editor UI is native WP block JSON.
 */
class CCPCO_Blocks {

    private $renderer;

    public function __construct() {
        $this->renderer = new CCPCO_Renderer();
        add_action( 'init', array( $this, 'register_blocks' ) );
        add_action( 'enqueue_block_editor_assets', array( $this, 'editor_assets' ) );
    }

    public function register_blocks() {
        if ( ! function_exists( 'register_block_type' ) ) return;

        // ---- PCO Signup List block ----------------------------------------
        register_block_type( 'comms-church-pco/signup-list', array(
            'title'           => __( 'PCO Signup List', 'comms-church-pco' ),
            'description'     => __( 'Display a grid or list of Planning Center signups.', 'comms-church-pco' ),
            'category'        => 'comms-church-pco',
            'icon'            => 'calendar-alt',
            'render_callback' => array( $this, 'render_signup_list' ),
            'attributes'      => array(
                'limit'         => array( 'type' => 'number',  'default' => 9 ),
                'display'       => array( 'type' => 'string',  'default' => 'tiles' ),
                'columns'       => array( 'type' => 'number',  'default' => 3 ),
                'filter'        => array( 'type' => 'string',  'default' => 'unarchived' ),
                'category'      => array( 'type' => 'string',  'default' => '' ),
                'showClosed'    => array( 'type' => 'boolean', 'default' => false ),
                'showDate'      => array( 'type' => 'boolean', 'default' => true ),
                'showLocation'  => array( 'type' => 'boolean', 'default' => true ),
                'showPrice'     => array( 'type' => 'boolean', 'default' => true ),
                'showCalendar'  => array( 'type' => 'boolean', 'default' => true ),
                'showDesc'      => array( 'type' => 'boolean', 'default' => true ),
                'imageShape'    => array( 'type' => 'string',  'default' => 'cinematic' ),
                'cornerRadius'  => array( 'type' => 'number',  'default' => 8 ),
                'brandColor'    => array( 'type' => 'string',  'default' => '' ),
                'buttonLabel'   => array( 'type' => 'string',  'default' => '' ),
                'capacity'      => array( 'type' => 'number',  'default' => 0 ),
            ),
            'supports' => array(
                'html'  => false,
                'align' => array( 'wide', 'full' ),
            ),
        ) );

        // ---- PCO Signup Card block ----------------------------------------
        register_block_type( 'comms-church-pco/signup-card', array(
            'title'           => __( 'PCO Signup Card', 'comms-church-pco' ),
            'description'     => __( 'Display a single Planning Center signup with full detail.', 'comms-church-pco' ),
            'category'        => 'comms-church-pco',
            'icon'            => 'tickets-alt',
            'render_callback' => array( $this, 'render_signup_card' ),
            'attributes'      => array(
                'signupId'      => array( 'type' => 'number',  'default' => 0 ),
                'showDesc'      => array( 'type' => 'boolean', 'default' => true ),
                'showTimes'     => array( 'type' => 'boolean', 'default' => true ),
                'showLocation'  => array( 'type' => 'boolean', 'default' => true ),
                'showTickets'   => array( 'type' => 'boolean', 'default' => true ),
                'showCalendar'  => array( 'type' => 'boolean', 'default' => true ),
                'brandColor'    => array( 'type' => 'string',  'default' => '' ),
                'buttonLabel'   => array( 'type' => 'string',  'default' => '' ),
            ),
            'supports' => array( 'html' => false ),
        ) );

        // ---- PCO Register Button block ------------------------------------
        register_block_type( 'comms-church-pco/register-button', array(
            'title'           => __( 'PCO Register Button', 'comms-church-pco' ),
            'description'     => __( 'A register button that links directly to a Planning Center signup.', 'comms-church-pco' ),
            'category'        => 'comms-church-pco',
            'icon'            => 'button',
            'render_callback' => array( $this, 'render_register_button' ),
            'attributes'      => array(
                'signupId'    => array( 'type' => 'number', 'default' => 0 ),
                'label'       => array( 'type' => 'string', 'default' => '' ),
                'brandColor'  => array( 'type' => 'string', 'default' => '' ),
                'extraClass'  => array( 'type' => 'string', 'default' => '' ),
            ),
            'supports' => array( 'html' => false, 'align' => true ),
        ) );

        // Register custom block category
        add_filter( 'block_categories_all', array( $this, 'register_category' ), 10, 2 );
    }

    public function register_category( $categories ) {
        return array_merge(
            array( array(
                'slug'  => 'comms-church-pco',
                'title' => __( 'Planning Center', 'comms-church-pco' ),
                'icon'  => 'calendar-alt',
            ) ),
            $categories
        );
    }

    // ---- Render callbacks ------------------------------------------------

    public function render_signup_list( $attrs ) {
        return $this->renderer->signup_list( array(
            'limit'         => intval( $attrs['limit']        ?? 9 ),
            'display'       => sanitize_text_field( $attrs['display']    ?? 'tiles' ),
            'columns'       => intval( $attrs['columns']      ?? 3 ),
            'filter'        => sanitize_text_field( $attrs['filter']     ?? 'unarchived' ),
            'category'      => sanitize_text_field( $attrs['category']   ?? '' ),
            'show_closed'   => (bool) ( $attrs['showClosed']   ?? false ),
            'show_date'     => (bool) ( $attrs['showDate']     ?? true  ),
            'show_location' => (bool) ( $attrs['showLocation'] ?? true  ),
            'show_price'    => (bool) ( $attrs['showPrice']    ?? true  ),
            'show_calendar' => (bool) ( $attrs['showCalendar'] ?? true  ),
            'show_desc'     => (bool) ( $attrs['showDesc']     ?? true  ),
            'image_shape'   => sanitize_text_field( $attrs['imageShape']   ?? 'cinematic' ),
            'corner_radius' => intval( $attrs['cornerRadius'] ?? 8 ),
            'brand_color'   => sanitize_hex_color( $attrs['brandColor']  ?? '' ),
            'button_label'  => sanitize_text_field( $attrs['buttonLabel'] ?? '' ) ?: __( 'Register', 'comms-church-pco' ),
            'capacity'      => intval( $attrs['capacity']     ?? 0 ),
        ) );
    }

    public function render_signup_card( $attrs ) {
        return $this->renderer->signup_detail( array(
            'id'            => intval( $attrs['signupId']     ?? 0 ),
            'show_desc'     => (bool) ( $attrs['showDesc']     ?? true ),
            'show_times'    => (bool) ( $attrs['showTimes']    ?? true ),
            'show_location' => (bool) ( $attrs['showLocation'] ?? true ),
            'show_tickets'  => (bool) ( $attrs['showTickets']  ?? true ),
            'show_calendar' => (bool) ( $attrs['showCalendar'] ?? true ),
            'brand_color'   => sanitize_hex_color( $attrs['brandColor']  ?? '' ),
            'button_label'  => sanitize_text_field( $attrs['buttonLabel'] ?? '' ) ?: __( 'Register', 'comms-church-pco' ),
        ) );
    }

    public function render_register_button( $attrs ) {
        return $this->renderer->register_button( array(
            'id'          => intval( $attrs['signupId']   ?? 0 ),
            'label'       => sanitize_text_field( $attrs['label']      ?? '' ) ?: __( 'Register', 'comms-church-pco' ),
            'brand_color' => sanitize_hex_color( $attrs['brandColor'] ?? '' ),
            'class'       => sanitize_html_class( $attrs['extraClass'] ?? '' ),
        ) );
    }

    // ---- Editor sidebar JS -----------------------------------------------
    public function editor_assets() {
        // Get live signups for the ID picker in the editor sidebar
        $api     = new CCPCO_API();
        $signups = array();
        if ( $api->is_configured() ) {
            $result = CCPCO_Cache::remember( 'editor_signups', function() use ( $api ) {
                return $api->get_signups( array( 'per_page' => 100, 'filter' => 'unarchived' ) );
            }, 300 );
            if ( ! is_wp_error( $result ) ) {
                foreach ( $result['data'] ?? array() as $s ) {
                    $signups[] = array(
                        'id'   => intval( $s['id'] ),
                        'name' => $s['attributes']['name'] ?? 'Signup ' . $s['id'],
                    );
                }
            }
        }

        wp_enqueue_script(
            'ccpco-blocks',
            CCPCO_PLUGIN_URL . 'assets/blocks.js',
            array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n', 'wp-server-side-render' ),
            CCPCO_VERSION,
            true
        );
        wp_localize_script( 'ccpco-blocks', 'CCPCOBlocks', array(
            'signups'     => $signups,
            'configured'  => $api->is_configured(),
            'settingsUrl' => admin_url( 'options-general.php?page=ccpco-settings' ),
            'brandColor'  => get_option( 'ccpco_brand_color', '#1a4a8a' ),
        ) );

        wp_enqueue_style( 'ccpco-blocks-editor', CCPCO_PLUGIN_URL . 'assets/editor.css', array( 'wp-edit-blocks' ), CCPCO_VERSION );
        // Also load front-end CSS in editor so preview looks right
        wp_enqueue_style( 'ccpco-front', CCPCO_PLUGIN_URL . 'assets/front.css', array(), CCPCO_VERSION );
    }
}
