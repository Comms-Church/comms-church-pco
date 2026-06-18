<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CCPCO_Admin {

    public function __construct() {
        add_action( 'admin_menu',            array( $this, 'add_menus' ) );
        add_action( 'admin_init',            array( $this, 'register_settings' ) );
        add_action( 'admin_notices',         array( $this, 'maybe_notice' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
    }

    public function add_menus() {
        add_options_page( 'PCO Registrations', 'PCO Registrations', 'manage_options', 'ccpco-settings', array( $this, 'page_settings' ) );
        add_submenu_page( 'options-general.php', 'PCO Shortcode Generator', '↳ Shortcode Generator', 'manage_options', 'ccpco-generator', array( $this, 'page_generator' ) );
    }

    public function register_settings() {
        foreach ( array( 'ccpco_app_id', 'ccpco_secret', 'ccpco_cache_ttl', 'ccpco_brand_color', 'ccpco_button_color', 'ccpco_corner_radius', 'ccpco_font_size_base' ) as $opt ) {
            register_setting( 'ccpco_settings', $opt, array( 'sanitize_callback' => 'sanitize_text_field' ) );
        }
        add_action( 'update_option_ccpco_app_id', array( 'CCPCO_Cache', 'flush_all' ) );
        add_action( 'update_option_ccpco_secret',  array( 'CCPCO_Cache', 'flush_all' ) );
    }

    public function maybe_notice() {
        $screen = get_current_screen();
        if ( $screen && in_array( $screen->id, array( 'settings_page_ccpco-settings', 'settings_page_ccpco-generator' ) ) ) return;
        if ( ! get_option( 'ccpco_app_id' ) || ! get_option( 'ccpco_secret' ) ) {
            echo '<div class="notice notice-warning"><p>'
                . '<strong>Comms.Church PCO:</strong> '
                . esc_html__( 'API credentials not configured.', 'comms-church-pco' ) . ' '
                . '<a href="' . esc_url( admin_url( 'options-general.php?page=ccpco-settings' ) ) . '">'
                . esc_html__( 'Configure now →', 'comms-church-pco' )
                . '</a></p></div>';
        }
    }

    public function enqueue( $hook ) {
        $pages = array( 'settings_page_ccpco-settings', 'settings_page_ccpco-generator' );
        if ( ! in_array( $hook, $pages ) ) return;
        wp_enqueue_style(  'ccpco-admin', CCPCO_PLUGIN_URL . 'assets/admin.css', array(), CCPCO_VERSION );
        wp_enqueue_script( 'ccpco-admin', CCPCO_PLUGIN_URL . 'assets/admin.js',  array(), CCPCO_VERSION, true );

        $api     = new CCPCO_API();
        $signups = array();
        if ( $api->is_configured() ) {
            $r = $api->get_signups( array( 'per_page' => 100, 'filter' => 'unarchived' ) );
            if ( ! is_wp_error( $r ) ) {
                foreach ( $r['data'] ?? array() as $s ) {
                    $signups[] = array( 'id' => $s['id'], 'name' => $s['attributes']['name'] ?? 'Signup ' . $s['id'] );
                }
            }
        }
        wp_localize_script( 'ccpco-admin', 'CCPCOAdmin', array(
            'ajax_url'    => admin_url( 'admin-ajax.php' ),
            'nonce'       => wp_create_nonce( 'ccpco_preview' ),
            'signups'     => $signups,
            'brand_color' => get_option( 'ccpco_brand_color', '#1a4a8a' ),
        ) );
        // Also enqueue front CSS so preview looks right
        wp_enqueue_style( 'ccpco-front', CCPCO_PLUGIN_URL . 'assets/front.css', array(), CCPCO_VERSION );
    }

    // =========================================================================
    // Settings page
    // =========================================================================
    public function page_settings() {
        $api     = new CCPCO_API();
        $wh_url  = CCPCO_Webhook::endpoint_url();
        $wh_sec  = CCPCO_Webhook::get_secret();
        $wh_log  = get_option( 'ccpco_webhook_log', array() );

        // Test connection
        $test = null;
        if ( isset( $_POST['ccpco_test'] ) && check_admin_referer( 'ccpco_test' ) ) {
            $r    = $api->get_signups( array( 'per_page' => 1 ) );
            $test = is_wp_error( $r )
                ? array( 'ok' => false, 'msg' => $r->get_error_message() )
                : array( 'ok' => true,  'msg' => sprintf( __( 'Connected! Found %d total signups.', 'comms-church-pco' ), $r['meta']['total_count'] ?? 0 ) );
        }

        // Flush cache
        if ( isset( $_POST['ccpco_flush'] ) && check_admin_referer( 'ccpco_flush' ) ) {
            CCPCO_Cache::flush_all();
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Cache cleared.', 'comms-church-pco' ) . '</p></div>';
        }
        ?>
        <div class="wrap ccpco-wrap">
            <div class="ccpco-header">
                <h1><?php esc_html_e( 'Planning Center Registrations', 'comms-church-pco' ); ?></h1>
                <p class="ccpco-tagline"><?php esc_html_e( 'by Comms.Church', 'comms-church-pco' ); ?></p>
            </div>

            <?php if ( $test ) : ?>
            <div class="notice <?php echo $test['ok'] ? 'notice-success' : 'notice-error'; ?> is-dismissible">
                <p><?php echo esc_html( $test['msg'] ); ?></p>
            </div>
            <?php endif; ?>

            <div class="ccpco-settings-grid">

                <!-- ---- API Credentials ---- -->
                <div class="ccpco-card">
                    <h2><?php esc_html_e( 'API Credentials', 'comms-church-pco' ); ?></h2>
                    <p><?php echo wp_kses_post( sprintf(
                        __( 'Create a Personal Access Token at <a href="%s" target="_blank" rel="noopener noreferrer">api.planningcenteronline.com/oauth/applications</a>. Your credentials are stored server-side and never exposed to visitors.', 'comms-church-pco' ),
                        'https://api.planningcenteronline.com/oauth/applications'
                    ) ); ?></p>

                    <form method="post" action="options.php">
                        <?php settings_fields( 'ccpco_settings' ); ?>
                        <table class="form-table">
                            <tr>
                                <th><label for="ccpco_app_id"><?php esc_html_e( 'Application ID', 'comms-church-pco' ); ?></label></th>
                                <td><input type="text" id="ccpco_app_id" name="ccpco_app_id" value="<?php echo esc_attr( get_option( 'ccpco_app_id' ) ); ?>" class="regular-text" autocomplete="off"></td>
                            </tr>
                            <tr>
                                <th><label for="ccpco_secret"><?php esc_html_e( 'Secret', 'comms-church-pco' ); ?></label></th>
                                <td><input type="password" id="ccpco_secret" name="ccpco_secret" value="<?php echo esc_attr( get_option( 'ccpco_secret' ) ); ?>" class="regular-text" autocomplete="new-password"></td>
                            </tr>
                            <tr>
                                <th><label for="ccpco_cache_ttl"><?php esc_html_e( 'Cache Duration', 'comms-church-pco' ); ?></label></th>
                                <td>
                                    <select id="ccpco_cache_ttl" name="ccpco_cache_ttl">
                                        <?php foreach ( array( 60 => '1 minute', 300 => '5 minutes (recommended)', 600 => '10 minutes', 1800 => '30 minutes', 3600 => '1 hour' ) as $val => $label ) : ?>
                                        <option value="<?php echo $val; ?>" <?php selected( get_option( 'ccpco_cache_ttl', 300 ), $val ); ?>><?php echo esc_html( $label ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        <?php submit_button( __( 'Save Credentials', 'comms-church-pco' ) ); ?>
                    </form>

                    <hr>
                    <form method="post">
                        <?php wp_nonce_field( 'ccpco_test' ); ?>
                        <input type="hidden" name="ccpco_test" value="1">
                        <?php submit_button( __( 'Test Connection', 'comms-church-pco' ), 'secondary', 'submit', false ); ?>
                    </form>
                    <form method="post" style="margin-top:.75rem">
                        <?php wp_nonce_field( 'ccpco_flush' ); ?>
                        <input type="hidden" name="ccpco_flush" value="1">
                        <?php submit_button( __( 'Clear Cache', 'comms-church-pco' ), 'delete', 'submit', false ); ?>
                    </form>
                </div>

                <!-- ---- Brand Settings ---- -->
                <div class="ccpco-card">
                    <h2><?php esc_html_e( 'Brand Settings', 'comms-church-pco' ); ?></h2>
                    <p><?php esc_html_e( 'These become global CSS defaults for all blocks and shortcodes. Individual blocks can override locally.', 'comms-church-pco' ); ?></p>

                    <form method="post" action="options.php">
                        <?php settings_fields( 'ccpco_settings' ); ?>
                        <table class="form-table">
                            <tr>
                                <th><label for="ccpco_brand_color"><?php esc_html_e( 'Brand Color', 'comms-church-pco' ); ?></label></th>
                                <td>
                                    <input type="color" id="ccpco_brand_color" name="ccpco_brand_color" value="<?php echo esc_attr( get_option( 'ccpco_brand_color', '#1a4a8a' ) ); ?>">
                                    <input type="text" class="ccpco-color-text" value="<?php echo esc_attr( get_option( 'ccpco_brand_color', '#1a4a8a' ) ); ?>" maxlength="7" style="width:90px;margin-left:.5rem">
                                    <p class="description"><?php esc_html_e( 'Used for buttons, accents, and links.', 'comms-church-pco' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="ccpco_corner_radius"><?php esc_html_e( 'Corner Radius', 'comms-church-pco' ); ?></label></th>
                                <td>
                                    <input type="number" id="ccpco_corner_radius" name="ccpco_corner_radius" value="<?php echo esc_attr( get_option( 'ccpco_corner_radius', 8 ) ); ?>" min="0" max="40" style="width:70px"> px
                                    <p class="description"><?php esc_html_e( 'Card and button corner radius.', 'comms-church-pco' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="ccpco_font_size_base"><?php esc_html_e( 'Base Font Size', 'comms-church-pco' ); ?></label></th>
                                <td>
                                    <select id="ccpco_font_size_base" name="ccpco_font_size_base">
                                        <?php foreach ( array( 'small' => 'Small (14px)', 'medium' => 'Medium (16px — default)', 'large' => 'Large (18px)' ) as $val => $lbl ) : ?>
                                        <option value="<?php echo $val; ?>" <?php selected( get_option( 'ccpco_font_size_base', 'medium' ), $val ); ?>><?php echo esc_html( $lbl ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        <?php submit_button( __( 'Save Brand Settings', 'comms-church-pco' ) ); ?>
                    </form>
                </div>

                <!-- ---- Webhooks ---- -->
                <div class="ccpco-card ccpco-full-width">
                    <h2><?php esc_html_e( 'Webhooks', 'comms-church-pco' ); ?> <span class="ccpco-badge" style="background:#fef3c7;color:#92400e"><?php esc_html_e( 'Coming Soon from PCO', 'comms-church-pco' ); ?></span></h2>
                    <p>
                        <?php esc_html_e( 'Planning Center does not yet support webhooks for the Registrations API. They currently offer webhooks for People and Giving only.', 'comms-church-pco' ); ?>
                    </p>
                    <p>
                        <?php esc_html_e( 'This plugin is already built to receive webhook notifications the moment PCO adds support — no update needed on your end. When that happens, the endpoint URL and secret below will be ready to paste into Planning Center.', 'comms-church-pco' ); ?>
                    </p>

                    <details style="margin-top:1rem;opacity:.6">
                        <summary style="cursor:pointer;font-weight:600"><?php esc_html_e( 'Ready for when PCO enables webhooks — expand to see setup details', 'comms-church-pco' ); ?></summary>
                        <div style="margin-top:1rem">
                        <table class="form-table">
                            <tr>
                                <th><?php esc_html_e( 'Endpoint URL', 'comms-church-pco' ); ?></th>
                                <td><div class="ccpco-copy-row"><input type="text" value="<?php echo esc_attr( $wh_url ); ?>" readonly class="regular-text"><button type="button" class="button ccpco-copy"><?php esc_html_e( 'Copy', 'comms-church-pco' ); ?></button></div></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Secret Token', 'comms-church-pco' ); ?></th>
                                <td><div class="ccpco-copy-row"><input type="text" value="<?php echo esc_attr( $wh_sec ); ?>" readonly class="regular-text"><button type="button" class="button ccpco-copy"><?php esc_html_e( 'Copy', 'comms-church-pco' ); ?></button></div></td>
                            </tr>
                        </table>
                        <p style="font-size:.875rem;margin-top:.5rem"><?php esc_html_e( 'Subscribe to these events once available:', 'comms-church-pco' ); ?></p>
                        <ul style="margin:.5rem 0 0 1.5rem;list-style:disc;font-size:.875rem">
                            <?php foreach ( array( 'registration.signup.updated', 'registration.attendee.created', 'registration.attendee.updated', 'registration.attendee.deleted', 'registration.registration.created', 'registration.registration.updated' ) as $e ) : ?>
                            <li><code><?php echo esc_html( $e ); ?></code></li>
                            <?php endforeach; ?>
                        </ul>
                        </div>
                    </details>

                    <p style="margin-top:1rem;color:#64748b;font-size:.875rem">
                        <?php esc_html_e( 'In the meantime, use Clear Cache above after making changes in PCO, or set a shorter cache duration if your events update frequently.', 'comms-church-pco' ); ?>
                    </p>

                    <?php if ( ! empty( $wh_log ) ) : ?>
                    <h3 style="margin-top:1.5rem"><?php esc_html_e( 'Recent Webhook Events', 'comms-church-pco' ); ?></h3>
                    <table class="widefat striped" style="font-size:.8rem">
                        <thead><tr><th><?php esc_html_e( 'Time', 'comms-church-pco' ); ?></th><th><?php esc_html_e( 'Event', 'comms-church-pco' ); ?></th></tr></thead>
                        <tbody>
                        <?php foreach ( $wh_log as $entry ) : ?>
                        <tr><td><?php echo esc_html( $entry['time'] ); ?></td><td><?php echo esc_html( $entry['message'] ); ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>

            </div>
        </div>
        <?php
    }

    // =========================================================================
    // Shortcode Generator page
    // =========================================================================
    public function page_generator() {
        $api        = new CCPCO_API();
        $configured = $api->is_configured();
        ?>
        <div class="wrap ccpco-wrap">
            <div class="ccpco-header">
                <h1><?php esc_html_e( 'Shortcode Generator', 'comms-church-pco' ); ?></h1>
                <p class="ccpco-tagline"><?php esc_html_e( 'by Comms.Church', 'comms-church-pco' ); ?></p>
            </div>
            <p><?php esc_html_e( 'Build a shortcode visually, then copy it into any page, post, or widget.', 'comms-church-pco' ); ?></p>

            <?php if ( ! $configured ) : ?>
            <div class="notice notice-warning"><p><?php echo wp_kses_post( sprintf( __( '<strong>API not configured.</strong> The signup picker requires credentials. <a href="%s">Set them up →</a>', 'comms-church-pco' ), esc_url( admin_url( 'options-general.php?page=ccpco-settings' ) ) ) ); ?></p></div>
            <?php endif; ?>

            <div class="ccpco-gen-layout">

                <!-- Controls -->
                <div class="ccpco-card ccpco-gen-controls">
                    <div class="ccpco-gen-tabs">
                        <button class="ccpco-tab active" data-tab="signups"><?php esc_html_e( 'Signup List', 'comms-church-pco' ); ?></button>
                        <button class="ccpco-tab" data-tab="signup"><?php esc_html_e( 'Single Signup', 'comms-church-pco' ); ?></button>
                        <button class="ccpco-tab" data-tab="register_button"><?php esc_html_e( 'Register Button', 'comms-church-pco' ); ?></button>
                        <button class="ccpco-tab" data-tab="attendee_count"><?php esc_html_e( 'Attendee Count', 'comms-church-pco' ); ?></button>
                        <button class="ccpco-tab" data-tab="signup_times"><?php esc_html_e( 'Signup Times', 'comms-church-pco' ); ?></button>
                        <button class="ccpco-tab" data-tab="ticket_types"><?php esc_html_e( 'Ticket Types', 'comms-church-pco' ); ?></button>
                    </div>

                    <!-- pco_signups -->
                    <div class="ccpco-tab-panel active" id="tab-signups">
                        <p class="ccpco-tab-desc"><?php esc_html_e( 'Grid or list of signups from your organization.', 'comms-church-pco' ); ?></p>
                        <table class="form-table ccpco-gen-form">
                            <tr><th><?php esc_html_e('Display Style','comms-church-pco');?></th><td><select data-attr="display"><option value="tiles"><?php esc_html_e('Tiles','comms-church-pco');?></option><option value="list"><?php esc_html_e('List','comms-church-pco');?></option></select></td></tr>
                            <tr class="row-tiles-only"><th><?php esc_html_e('Columns','comms-church-pco');?></th><td><select data-attr="columns" data-default="3"><option value="1">1</option><option value="2">2</option><option value="3" selected>3</option><option value="4">4</option></select></td></tr>
                            <tr><th><?php esc_html_e('Limit','comms-church-pco');?></th><td><input type="number" min="1" max="100" value="9" data-attr="limit" data-default="9"></td></tr>
                            <tr><th><?php esc_html_e('Status Filter','comms-church-pco');?></th><td><select data-attr="filter" data-default="unarchived"><option value="unarchived"><?php esc_html_e('Active','comms-church-pco');?></option><option value="archived"><?php esc_html_e('Archived','comms-church-pco');?></option></select></td></tr>
                            <tr><th><?php esc_html_e('Category','comms-church-pco');?></th><td><input type="text" data-attr="category" placeholder="<?php esc_attr_e("e.g. Women's Ministry",'comms-church-pco');?>"><p class="description"><?php esc_html_e('Partial match, case-insensitive. Leave blank for all.','comms-church-pco');?></p></td></tr>
                            <tr><th><?php esc_html_e('Image Shape','comms-church-pco');?></th><td><select data-attr="image_shape" data-default="cinematic"><option value="cinematic"><?php esc_html_e('Cinematic (16:9)','comms-church-pco');?></option><option value="square"><?php esc_html_e('Square','comms-church-pco');?></option><option value="portrait"><?php esc_html_e('Portrait','comms-church-pco');?></option></select></td></tr>
                            <tr><th><?php esc_html_e('Corner Radius','comms-church-pco');?></th><td><input type="number" min="0" max="40" value="8" data-attr="corner_radius" data-default="8"> px</td></tr>
                            <tr><th><?php esc_html_e('Brand Color','comms-church-pco');?></th><td><input type="color" data-attr="brand_color" data-default="" id="gen-color-signups"><input type="text" class="ccpco-color-text" maxlength="7" style="width:90px;margin-left:.5rem"><p class="description"><?php esc_html_e('Leave blank to use global brand color.','comms-church-pco');?></p></td></tr>
                            <tr><th><?php esc_html_e('Show Closed','comms-church-pco');?></th><td><label><input type="checkbox" data-attr="show_closed" data-default="false"> <?php esc_html_e('Include closed signups','comms-church-pco');?></label></td></tr>
                            <tr><th><?php esc_html_e('Show Date','comms-church-pco');?></th><td><label><input type="checkbox" data-attr="show_date" data-default="true" checked> <?php esc_html_e('Next event date','comms-church-pco');?></label></td></tr>
                            <tr><th><?php esc_html_e('Show Location','comms-church-pco');?></th><td><label><input type="checkbox" data-attr="show_location" data-default="true" checked></label></td></tr>
                            <tr><th><?php esc_html_e('Show Price','comms-church-pco');?></th><td><label><input type="checkbox" data-attr="show_price" data-default="true" checked></label></td></tr>
                            <tr><th><?php esc_html_e('Add to Calendar','comms-church-pco');?></th><td><label><input type="checkbox" data-attr="show_calendar" data-default="true" checked></label></td></tr>
                            <tr><th><?php esc_html_e('Show Description','comms-church-pco');?></th><td><label><input type="checkbox" data-attr="show_desc" data-default="true" checked></label></td></tr>
                            <tr><th><?php esc_html_e('Button Label','comms-church-pco');?></th><td><input type="text" value="" data-attr="button_label" data-default="" placeholder="<?php esc_attr_e('Register','comms-church-pco');?>"></td></tr>
                            <tr><th><?php esc_html_e('Capacity','comms-church-pco');?></th><td><input type="number" min="0" value="0" data-attr="capacity" data-default="0"><p class="description"><?php esc_html_e('Progress bar shown when above 0.','comms-church-pco');?></p></td></tr>
                        </table>
                    </div>

                    <!-- pco_signup -->
                    <div class="ccpco-tab-panel" id="tab-signup">
                        <p class="ccpco-tab-desc"><?php esc_html_e('Full detail card for one signup.','comms-church-pco');?></p>
                        <table class="form-table ccpco-gen-form">
                            <tr><th><?php esc_html_e('Signup','comms-church-pco');?></th><td><select data-attr="id" class="ccpco-signup-picker"><option value=""><?php esc_html_e('— select —','comms-church-pco');?></option></select><p class="description"><?php esc_html_e('Or type ID:','comms-church-pco');?></p><input type="number" data-attr="id" class="ccpco-id-manual" style="margin-top:4px"></td></tr>
                            <tr><th><?php esc_html_e('Show Description','comms-church-pco');?></th><td><label><input type="checkbox" data-attr="show_desc" data-default="true" checked></label></td></tr>
                            <tr><th><?php esc_html_e('Show Times','comms-church-pco');?></th><td><label><input type="checkbox" data-attr="show_times" data-default="true" checked></label></td></tr>
                            <tr><th><?php esc_html_e('Show Location','comms-church-pco');?></th><td><label><input type="checkbox" data-attr="show_location" data-default="true" checked></label></td></tr>
                            <tr><th><?php esc_html_e('Show Tickets','comms-church-pco');?></th><td><label><input type="checkbox" data-attr="show_tickets" data-default="true" checked></label></td></tr>
                            <tr><th><?php esc_html_e('Add to Calendar','comms-church-pco');?></th><td><label><input type="checkbox" data-attr="show_calendar" data-default="true" checked></label></td></tr>
                            <tr><th><?php esc_html_e('Brand Color','comms-church-pco');?></th><td><input type="color" data-attr="brand_color" data-default=""><input type="text" class="ccpco-color-text" maxlength="7" style="width:90px;margin-left:.5rem"></td></tr>
                            <tr><th><?php esc_html_e('Button Label','comms-church-pco');?></th><td><input type="text" data-attr="button_label" data-default="" placeholder="<?php esc_attr_e('Register','comms-church-pco');?>"></td></tr>
                        </table>
                    </div>

                    <!-- pco_register_button -->
                    <div class="ccpco-tab-panel" id="tab-register_button">
                        <p class="ccpco-tab-desc"><?php esc_html_e('Standalone button — auto-disables when signup closes.','comms-church-pco');?></p>
                        <table class="form-table ccpco-gen-form">
                            <tr><th><?php esc_html_e('Signup','comms-church-pco');?></th><td><select data-attr="id" class="ccpco-signup-picker"><option value=""><?php esc_html_e('— select —','comms-church-pco');?></option></select><p class="description"><?php esc_html_e('Or type ID:','comms-church-pco');?></p><input type="number" data-attr="id" class="ccpco-id-manual" style="margin-top:4px"></td></tr>
                            <tr><th><?php esc_html_e('Label','comms-church-pco');?></th><td><input type="text" data-attr="label" data-default="" placeholder="<?php esc_attr_e('Register','comms-church-pco');?>"></td></tr>
                            <tr><th><?php esc_html_e('Brand Color','comms-church-pco');?></th><td><input type="color" data-attr="brand_color" data-default=""><input type="text" class="ccpco-color-text" maxlength="7" style="width:90px;margin-left:.5rem"></td></tr>
                            <tr><th><?php esc_html_e('Extra CSS Class','comms-church-pco');?></th><td><input type="text" data-attr="class" placeholder="optional"></td></tr>
                        </table>
                    </div>

                    <!-- pco_attendee_count -->
                    <div class="ccpco-tab-panel" id="tab-attendee_count">
                        <p class="ccpco-tab-desc"><?php esc_html_e('Live count — outputs e.g. "47 registered".','comms-church-pco');?></p>
                        <table class="form-table ccpco-gen-form">
                            <tr><th><?php esc_html_e('Signup','comms-church-pco');?></th><td><select data-attr="id" class="ccpco-signup-picker"><option value=""><?php esc_html_e('— select —','comms-church-pco');?></option></select><input type="number" data-attr="id" class="ccpco-id-manual" style="margin-top:4px"></td></tr>
                            <tr><th><?php esc_html_e('Label','comms-church-pco');?></th><td><input type="text" value="registered" data-attr="label" data-default="registered"></td></tr>
                        </table>
                    </div>

                    <!-- pco_signup_times -->
                    <div class="ccpco-tab-panel" id="tab-signup_times">
                        <p class="ccpco-tab-desc"><?php esc_html_e('List all event times for a signup.','comms-church-pco');?></p>
                        <table class="form-table ccpco-gen-form">
                            <tr><th><?php esc_html_e('Signup','comms-church-pco');?></th><td><select data-attr="id" class="ccpco-signup-picker"><option value=""><?php esc_html_e('— select —','comms-church-pco');?></option></select><input type="number" data-attr="id" class="ccpco-id-manual" style="margin-top:4px"></td></tr>
                            <tr><th><?php esc_html_e('Filter','comms-church-pco');?></th><td><select data-attr="filter" data-default="future"><option value="future"><?php esc_html_e('Future','comms-church-pco');?></option><option value="past"><?php esc_html_e('Past','comms-church-pco');?></option><option value=""><?php esc_html_e('All','comms-church-pco');?></option></select></td></tr>
                            <tr><th><?php esc_html_e('Date Format','comms-church-pco');?></th><td><input type="text" value="F j, Y g:i a" data-attr="format" data-default="F j, Y g:i a"><p class="description">PHP date format</p></td></tr>
                        </table>
                    </div>

                    <!-- pco_ticket_types -->
                    <div class="ccpco-tab-panel" id="tab-ticket_types">
                        <p class="ccpco-tab-desc"><?php esc_html_e('List ticket/registration options with prices.','comms-church-pco');?></p>
                        <table class="form-table ccpco-gen-form">
                            <tr><th><?php esc_html_e('Signup','comms-church-pco');?></th><td><select data-attr="id" class="ccpco-signup-picker"><option value=""><?php esc_html_e('— select —','comms-church-pco');?></option></select><input type="number" data-attr="id" class="ccpco-id-manual" style="margin-top:4px"></td></tr>
                            <tr><th><?php esc_html_e('Public Only','comms-church-pco');?></th><td><label><input type="checkbox" data-attr="public_only" data-default="true" checked></label></td></tr>
                            <tr><th><?php esc_html_e('Show Free','comms-church-pco');?></th><td><label><input type="checkbox" data-attr="show_free" data-default="true" checked></label></td></tr>
                        </table>
                    </div>
                </div>

                <!-- Output + Preview -->
                <div class="ccpco-gen-sidebar">
                    <div class="ccpco-card">
                        <h2><?php esc_html_e('Generated Shortcode','comms-church-pco');?></h2>
                        <div class="ccpco-gen-output-wrap"><code id="ccpco-gen-output" class="ccpco-gen-output">[pco_signups]</code></div>
                        <button id="ccpco-copy-btn" class="button button-primary"><?php esc_html_e('Copy to Clipboard','comms-church-pco');?></button>
                        <span id="ccpco-copy-msg" style="display:none;margin-left:.75rem;color:#16a34a;font-weight:600">&#10003; <?php esc_html_e('Copied!','comms-church-pco');?></span>
                    </div>

                    <div class="ccpco-card" style="margin-top:1.25rem">
                        <h2><?php esc_html_e('Live Preview','comms-church-pco');?></h2>
                        <p style="font-size:.8rem;color:#64748b;margin-top:0"><?php esc_html_e('Renders the actual output using your live PCO data.','comms-church-pco');?></p>
                        <div id="ccpco-gen-preview" class="ccpco-gen-preview">
                            <p style="color:#94a3b8;font-style:italic"><?php esc_html_e('Adjust settings to see a preview.','comms-church-pco');?></p>
                        </div>
                    </div>

                    <div class="ccpco-card ccpco-tips" style="margin-top:1.25rem">
                        <h3><?php esc_html_e('How to use','comms-church-pco');?></h3>
                        <ul>
                            <li><?php esc_html_e('Copy the shortcode and paste it into any page, post, or Classic Widget.','comms-church-pco');?></li>
                            <li><?php esc_html_e('In the Block Editor, use a Shortcode block and paste it there.','comms-church-pco');?></li>
                            <li><?php esc_html_e('Or search for "PCO" in the block inserter to add a native block directly.','comms-church-pco');?></li>
                        </ul>
                        <h3 style="margin-top:1rem"><?php esc_html_e('Finding a Signup ID','comms-church-pco');?></h3>
                        <p><?php esc_html_e('Open a signup in PCO and read the number from the URL:','comms-church-pco');?><br>
                        <code>registrations.planningcenteronline.com/signups/<strong>12345</strong></code></p>
                    </div>
                </div>

            </div>
        </div>
        <?php
    }
}
