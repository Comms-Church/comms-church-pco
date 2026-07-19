<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CCPCO_Bricks_Signup_List extends \Bricks\Element {

    public $category     = 'Planning Center';
    public $name         = 'ccpco-signup-list';
    public $icon         = 'ti-layout-grid2';
    public $css_selector = '.ccpco-signup-list';

    public function get_label() { return 'PCO Signup List'; }

    /** Bricks calls this when the element is on the page — proper asset loading. */
    public function enqueue_scripts() {
        CCPCO_Renderer::ensure_assets();
    }

    public function set_controls() {
        $this->controls['category'] = array(
            'tab' => 'content', 'label' => 'Category Filter', 'type' => 'text',
            'placeholder' => "e.g. Women's Ministry",
            'description' => 'Show only signups in this category. Leave blank for all.',
        );
        $this->controls['limit'] = array(
            'tab' => 'content', 'label' => 'Max Signups', 'type' => 'number',
            'min' => 1, 'max' => 50, 'default' => 9,
        );
        $this->controls['filter'] = array(
            'tab' => 'content', 'label' => 'Status', 'type' => 'select',
            'options' => array( 'unarchived' => 'Active', 'archived' => 'Archived' ),
            'default' => 'unarchived',
        );
        $this->controls['show_closed'] = array(
            'tab' => 'content', 'label' => 'Show Closed Signups', 'type' => 'checkbox', 'default' => false,
        );
        $this->controls['display'] = array(
            'tab' => 'content', 'label' => 'Display', 'type' => 'select',
            'options' => array( 'tiles' => 'Tiles (grid)', 'list' => 'List' ),
            'default' => 'tiles',
        );
        $this->controls['columns'] = array(
            'tab' => 'content', 'label' => 'Columns', 'type' => 'number',
            'min' => 1, 'max' => 4, 'default' => 3,
        );
        $this->controls['image_shape'] = array(
            'tab' => 'content', 'label' => 'Image Shape', 'type' => 'select',
            'options' => array( 'cinematic' => 'Cinematic (16:9)', 'square' => 'Square', 'portrait' => 'Portrait' ),
            'default' => 'cinematic',
        );
        $this->controls['corner_radius'] = array(
            'tab' => 'content', 'label' => 'Corner Radius (px)', 'type' => 'number',
            'min' => 0, 'max' => 40, 'default' => 8,
        );
        $this->controls['show_date']     = array( 'tab' => 'content', 'label' => 'Show Date',        'type' => 'checkbox', 'default' => true );
        $this->controls['show_location'] = array( 'tab' => 'content', 'label' => 'Show Location',    'type' => 'checkbox', 'default' => true );
        $this->controls['show_price']    = array( 'tab' => 'content', 'label' => 'Show Price',        'type' => 'checkbox', 'default' => true );
        $this->controls['show_calendar'] = array( 'tab' => 'content', 'label' => 'Add to Calendar',  'type' => 'checkbox', 'default' => true );
        $this->controls['show_desc']     = array( 'tab' => 'content', 'label' => 'Show Description', 'type' => 'checkbox', 'default' => true );
        $this->controls['button_label']  = array( 'tab' => 'content', 'label' => 'Button Label',     'type' => 'text',     'default' => 'Register' );
        $this->controls['capacity']      = array(
            'tab' => 'content', 'label' => 'Capacity (progress bar)', 'type' => 'number',
            'min' => 0, 'default' => 0, 'description' => 'Set above 0 to show a progress bar.',
        );
        $this->controls['brand_color'] = array(
            'tab' => 'style', 'label' => 'Brand Color', 'type' => 'color',
            'description' => 'Leave unset to use the global brand color from Settings → PCO Registrations.',
        );
    }

    public function render() {
        $s    = $this->settings;
        $renderer = new CCPCO_Renderer();

        echo "<div {$this->render_attributes( '_root' )}>";
        echo $renderer->signup_list( array(
            'limit'         => intval( $s['limit']        ?? 9 ),
            'display'       => sanitize_text_field( $s['display']    ?? 'tiles' ),
            'columns'       => intval( $s['columns']      ?? 3 ),
            'filter'        => sanitize_text_field( $s['filter']     ?? 'unarchived' ),
            'category'      => sanitize_text_field( $s['category']   ?? '' ),
            'show_closed'   => ! empty( $s['show_closed'] ),
            'show_date'     => isset( $s['show_date'] )     ? ! empty( $s['show_date'] )     : true,
            'show_location' => isset( $s['show_location'] ) ? ! empty( $s['show_location'] ) : true,
            'show_price'    => isset( $s['show_price'] )    ? ! empty( $s['show_price'] )    : true,
            'show_calendar' => isset( $s['show_calendar'] ) ? ! empty( $s['show_calendar'] ) : true,
            'show_desc'     => isset( $s['show_desc'] )     ? ! empty( $s['show_desc'] )     : true,
            'image_shape'   => sanitize_text_field( $s['image_shape'] ?? 'cinematic' ),
            'corner_radius' => intval( $s['corner_radius'] ?? 8 ),
            'brand_color'   => $this->color_value( $s['brand_color'] ?? null ),
            'button_label'  => sanitize_text_field( $s['button_label'] ?? 'Register' ),
            'capacity'      => intval( $s['capacity'] ?? 0 ),
        ) );
        echo '</div>';
    }

    /** Bricks color controls return an array with hex/rgb/raw keys. */
    private function color_value( $color ) {
        if ( is_array( $color ) ) {
            return sanitize_hex_color( $color['hex'] ?? '' ) ?: '';
        }
        return '';
    }
}
