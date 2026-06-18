<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CCPCO_Shortcodes {

    private $renderer;

    public function __construct() {
        $this->renderer = new CCPCO_Renderer();
        add_shortcode( 'pco_signups',         array( $this, 'sc_signups' ) );
        add_shortcode( 'pco_signup',          array( $this, 'sc_signup' ) );
        add_shortcode( 'pco_register_button', array( $this, 'sc_register_button' ) );
        add_shortcode( 'pco_attendee_count',  array( $this, 'sc_attendee_count' ) );
        add_shortcode( 'pco_signup_times',    array( $this, 'sc_signup_times' ) );
        add_shortcode( 'pco_ticket_types',    array( $this, 'sc_ticket_types' ) );
        add_action( 'wp_enqueue_scripts',     array( $this, 'enqueue' ) );
        add_action( 'wp_ajax_ccpco_preview',  array( $this, 'ajax_preview' ) );
    }

    public function enqueue() {
        wp_enqueue_style(  'ccpco-front', CCPCO_PLUGIN_URL . 'assets/front.css', array(), CCPCO_VERSION );
        wp_enqueue_script( 'ccpco-front', CCPCO_PLUGIN_URL . 'assets/front.js',  array(), CCPCO_VERSION, true );
    }

    // ---- [pco_signups] -------------------------------------------------------
    public function sc_signups( $atts ) {
        $atts = shortcode_atts( array(
            'limit'         => 9,
            'display'       => 'tiles',
            'columns'       => 3,
            'filter'        => 'unarchived',
            'category'      => '',
            'show_closed'   => 'false',
            'show_date'     => 'true',
            'show_location' => 'true',
            'show_price'    => 'true',
            'show_calendar' => 'true',
            'show_desc'     => 'true',
            'image_shape'   => 'cinematic',
            'corner_radius' => 8,
            'brand_color'   => '',
            'button_label'  => '',
            'capacity'      => 0,
        ), $atts, 'pco_signups' );

        return $this->renderer->signup_list( array(
            'limit'         => intval( $atts['limit'] ),
            'display'       => sanitize_text_field( $atts['display'] ),
            'columns'       => intval( $atts['columns'] ),
            'filter'        => sanitize_text_field( $atts['filter'] ),
            'category'      => sanitize_text_field( $atts['category'] ),
            'show_closed'   => filter_var( $atts['show_closed'],   FILTER_VALIDATE_BOOLEAN ),
            'show_date'     => filter_var( $atts['show_date'],     FILTER_VALIDATE_BOOLEAN ),
            'show_location' => filter_var( $atts['show_location'], FILTER_VALIDATE_BOOLEAN ),
            'show_price'    => filter_var( $atts['show_price'],    FILTER_VALIDATE_BOOLEAN ),
            'show_calendar' => filter_var( $atts['show_calendar'], FILTER_VALIDATE_BOOLEAN ),
            'show_desc'     => filter_var( $atts['show_desc'],     FILTER_VALIDATE_BOOLEAN ),
            'image_shape'   => sanitize_text_field( $atts['image_shape'] ),
            'corner_radius' => intval( $atts['corner_radius'] ),
            'brand_color'   => sanitize_hex_color( $atts['brand_color'] ),
            'button_label'  => sanitize_text_field( $atts['button_label'] ) ?: __( 'Register', 'comms-church-pco' ),
            'capacity'      => intval( $atts['capacity'] ),
        ) );
    }

    // ---- [pco_signup id="123"] -----------------------------------------------
    public function sc_signup( $atts ) {
        $atts = shortcode_atts( array(
            'id'            => '',
            'show_desc'     => 'true',
            'show_times'    => 'true',
            'show_location' => 'true',
            'show_tickets'  => 'true',
            'show_calendar' => 'true',
            'brand_color'   => '',
            'button_label'  => '',
        ), $atts, 'pco_signup' );

        return $this->renderer->signup_detail( array(
            'id'            => intval( $atts['id'] ),
            'show_desc'     => filter_var( $atts['show_desc'],     FILTER_VALIDATE_BOOLEAN ),
            'show_times'    => filter_var( $atts['show_times'],    FILTER_VALIDATE_BOOLEAN ),
            'show_location' => filter_var( $atts['show_location'], FILTER_VALIDATE_BOOLEAN ),
            'show_tickets'  => filter_var( $atts['show_tickets'],  FILTER_VALIDATE_BOOLEAN ),
            'show_calendar' => filter_var( $atts['show_calendar'], FILTER_VALIDATE_BOOLEAN ),
            'brand_color'   => sanitize_hex_color( $atts['brand_color'] ),
            'button_label'  => sanitize_text_field( $atts['button_label'] ) ?: __( 'Register', 'comms-church-pco' ),
        ) );
    }

    // ---- [pco_register_button id="123"] -------------------------------------
    public function sc_register_button( $atts ) {
        $atts = shortcode_atts( array(
            'id'          => '',
            'label'       => '',
            'brand_color' => '',
            'class'       => '',
        ), $atts, 'pco_register_button' );

        return $this->renderer->register_button( array(
            'id'          => intval( $atts['id'] ),
            'label'       => sanitize_text_field( $atts['label'] ) ?: __( 'Register', 'comms-church-pco' ),
            'brand_color' => sanitize_hex_color( $atts['brand_color'] ),
            'class'       => sanitize_html_class( $atts['class'] ),
        ) );
    }

    // ---- [pco_attendee_count id="123"] --------------------------------------
    public function sc_attendee_count( $atts ) {
        $atts = shortcode_atts( array( 'id' => '', 'label' => 'registered' ), $atts, 'pco_attendee_count' );
        if ( empty( $atts['id'] ) ) return '';
        return $this->renderer->attendee_count( intval( $atts['id'] ), sanitize_text_field( $atts['label'] ) );
    }

    // ---- [pco_signup_times id="123"] ----------------------------------------
    public function sc_signup_times( $atts ) {
        $atts = shortcode_atts( array( 'id' => '', 'filter' => 'future', 'format' => 'F j, Y g:i a' ), $atts, 'pco_signup_times' );
        if ( empty( $atts['id'] ) ) return '';

        $api  = new CCPCO_API();
        $data = CCPCO_Cache::remember( 'times_' . $atts['id'] . '_' . $atts['filter'], function() use ( $atts, $api ) {
            return $api->get_signup_times( intval( $atts['id'] ), sanitize_text_field( $atts['filter'] ) );
        } );

        if ( is_wp_error( $data ) || empty( $data['data'] ) ) return '';

        ob_start(); ?>
        <ul class="ccpco-times-list">
        <?php foreach ( $data['data'] as $t ) :
            $ta    = $t['attributes'];
            $start = $ta['starts_at'] ? wp_date( $atts['format'], strtotime( $ta['starts_at'] ) ) : '';
            $end   = $ta['ends_at']   ? wp_date( $atts['format'], strtotime( $ta['ends_at'] ) )   : ''; ?>
            <li>
            <?php if ( $ta['all_day'] ) : ?>
                <?php echo esc_html( wp_date( 'F j, Y', strtotime( $ta['starts_at'] ) ) ); ?> (<?php esc_html_e( 'All Day', 'comms-church-pco' ); ?>)
            <?php else : ?>
                <?php echo esc_html( $start ); ?><?php if ( $end ) echo ' &ndash; ' . esc_html( $end ); ?>
            <?php endif; ?>
            </li>
        <?php endforeach; ?>
        </ul>
        <?php return ob_get_clean();
    }

    // ---- [pco_ticket_types id="123"] ----------------------------------------
    public function sc_ticket_types( $atts ) {
        $atts = shortcode_atts( array( 'id' => '', 'show_free' => 'true', 'public_only' => 'true' ), $atts, 'pco_ticket_types' );
        if ( empty( $atts['id'] ) ) return '';

        $signup_id   = intval( $atts['id'] );
        $public_only = filter_var( $atts['public_only'], FILTER_VALIDATE_BOOLEAN );
        $api         = new CCPCO_API();

        $data = CCPCO_Cache::remember( 'tickets_' . $signup_id . ( $public_only ? '_1' : '_0' ), function() use ( $signup_id, $public_only, $api ) {
            return $api->get_selection_types( $signup_id, $public_only );
        } );

        if ( is_wp_error( $data ) || empty( $data['data'] ) ) return '';

        ob_start(); ?>
        <ul class="ccpco-tickets-list">
        <?php foreach ( $data['data'] as $ticket ) :
            $ta    = $ticket['attributes'];
            $price = intval( $ta['price_cents'] ?? 0 );
            if ( $price === 0 && ! filter_var( $atts['show_free'], FILTER_VALIDATE_BOOLEAN ) ) continue; ?>
            <li>
                <span class="ccpco-ticket-name"><?php echo esc_html( $ta['name'] ); ?></span>
                <span class="ccpco-ticket-price"><?php echo $price === 0 ? esc_html__( 'Free', 'comms-church-pco' ) : '$' . esc_html( number_format( $price / 100, 2 ) ); ?></span>
            </li>
        <?php endforeach; ?>
        </ul>
        <?php return ob_get_clean();
    }

    // ---- AJAX preview (for generator page) ----------------------------------
    public function ajax_preview() {
        check_ajax_referer( 'ccpco_preview', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized', 403 );
        $sc   = sanitize_text_field( wp_unslash( $_POST['shortcode'] ?? '' ) );
        $html = '<div class="ccpco-preview-wrap">' . do_shortcode( $sc ) . '</div>';
        wp_send_json_success( $html );
    }
}

// Append font size class to body (called from main plugin init)
function ccpco_body_class( $classes ) {
    $size = get_option( 'ccpco_font_size_base', 'medium' );
    if ( $size !== 'medium' ) {
        $classes[] = 'ccpco-font-' . sanitize_html_class( $size );
    }
    return $classes;
}
add_filter( 'body_class', 'ccpco_body_class' );
