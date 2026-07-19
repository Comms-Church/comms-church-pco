<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CCPCO_Bricks_Register_Button extends \Bricks\Element {

    public $category     = 'Planning Center';
    public $name         = 'ccpco-register-button';
    public $icon         = 'ti-hand-point-right';
    public $css_selector = '.ccpco-btn';

    public function get_label() { return 'PCO Register Button'; }

    public function enqueue_scripts() {
        CCPCO_Renderer::ensure_assets();
    }

    public function set_controls() {
        $this->controls['signup_id'] = array(
            'tab' => 'content', 'label' => 'Signup ID', 'type' => 'text', 'placeholder' => 'e.g. 12345',
        );
        $this->controls['label'] = array(
            'tab' => 'content', 'label' => 'Button Label', 'type' => 'text', 'default' => 'Register',
        );
        $this->controls['extra_class'] = array(
            'tab' => 'style', 'label' => 'Extra CSS Class', 'type' => 'text',
        );
        $this->controls['brand_color'] = array(
            'tab' => 'style', 'label' => 'Button Color', 'type' => 'color',
            'description' => 'Leave unset to use the global brand color.',
        );
    }

    public function render() {
        $signup_id = intval( $this->settings['signup_id'] ?? 0 );

        echo "<div {$this->render_attributes( '_root' )}>";

        if ( ! $signup_id ) {
            echo $this->render_element_placeholder( array(
                'title' => 'PCO Register Button: Enter a Signup ID.',
            ) );
            echo '</div>';
            return;
        }

        $s        = $this->settings;
        $renderer = new CCPCO_Renderer();

        echo $renderer->register_button( array(
            'id'          => $signup_id,
            'label'       => sanitize_text_field( $s['label'] ?? 'Register' ),
            'brand_color' => is_array( $s['brand_color'] ?? null )
                ? ( sanitize_hex_color( $s['brand_color']['hex'] ?? '' ) ?: '' )
                : '',
            'class'       => sanitize_html_class( $s['extra_class'] ?? '' ),
        ) );

        echo '</div>';
    }
}
