<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CCPCO_Bricks_Signup_Card extends \Bricks\Element {

    public $category     = 'Planning Center';
    public $name         = 'ccpco-signup-card';
    public $icon         = 'ti-calendar';
    public $css_selector = '.ccpco-signup-detail';

    public function get_label() { return 'PCO Signup Card'; }

    public function enqueue_scripts() {
        CCPCO_Renderer::ensure_assets();
    }

    public function set_controls() {
        $this->controls['signup_id'] = array(
            'tab' => 'content', 'label' => 'Signup ID', 'type' => 'text',
            'placeholder' => 'e.g. 12345',
            'description' => 'Find this in the PCO URL: registrations.planningcenteronline.com/signups/[ID]',
        );
        $this->controls['show_desc']     = array( 'tab' => 'content', 'label' => 'Show Description', 'type' => 'checkbox', 'default' => true );
        $this->controls['show_times']    = array( 'tab' => 'content', 'label' => 'Show Times',        'type' => 'checkbox', 'default' => true );
        $this->controls['show_location'] = array( 'tab' => 'content', 'label' => 'Show Location',     'type' => 'checkbox', 'default' => true );
        $this->controls['show_tickets']  = array( 'tab' => 'content', 'label' => 'Show Ticket Types', 'type' => 'checkbox', 'default' => true );
        $this->controls['show_calendar'] = array( 'tab' => 'content', 'label' => 'Add to Calendar',   'type' => 'checkbox', 'default' => true );
        $this->controls['button_label']  = array( 'tab' => 'content', 'label' => 'Button Label',      'type' => 'text',     'default' => 'Register' );
        $this->controls['brand_color']   = array(
            'tab' => 'style', 'label' => 'Brand Color', 'type' => 'color',
            'description' => 'Leave unset to use the global brand color.',
        );
    }

    public function render() {
        $signup_id = intval( $this->settings['signup_id'] ?? 0 );

        echo "<div {$this->render_attributes( '_root' )}>";

        if ( ! $signup_id ) {
            echo $this->render_element_placeholder( array(
                'title' => 'PCO Signup Card: Enter a Signup ID in the element settings.',
            ) );
            echo '</div>';
            return;
        }

        $s        = $this->settings;
        $renderer = new CCPCO_Renderer();

        echo $renderer->signup_detail( array(
            'id'            => $signup_id,
            'show_desc'     => isset( $s['show_desc'] )     ? ! empty( $s['show_desc'] )     : true,
            'show_times'    => isset( $s['show_times'] )    ? ! empty( $s['show_times'] )    : true,
            'show_location' => isset( $s['show_location'] ) ? ! empty( $s['show_location'] ) : true,
            'show_tickets'  => isset( $s['show_tickets'] )  ? ! empty( $s['show_tickets'] )  : true,
            'show_calendar' => isset( $s['show_calendar'] ) ? ! empty( $s['show_calendar'] ) : true,
            'button_label'  => sanitize_text_field( $s['button_label'] ?? 'Register' ),
            'brand_color'   => is_array( $s['brand_color'] ?? null )
                ? ( sanitize_hex_color( $s['brand_color']['hex'] ?? '' ) ?: '' )
                : '',
        ) );

        echo '</div>';
    }
}
