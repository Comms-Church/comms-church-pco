<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * CCPCO_Renderer
 * Central HTML factory. Both blocks and shortcodes call these methods.
 * This means a single place to update markup for the whole plugin.
 */
class CCPCO_Renderer {

    private $api;

    public function __construct() {
        $this->api = new CCPCO_API();
    }

    // =========================================================================
    // Signup grid / list
    // =========================================================================
    public function signup_list( $args = array() ) {
        if ( ! $this->api->is_configured() ) {
            return $this->admin_notice( __( 'Comms.Church PCO: API credentials not configured. Visit Settings → PCO Registrations.', 'comms-church-pco' ) );
        }

        $args = wp_parse_args( $args, array(
            'limit'         => 9,
            'display'       => 'tiles',
            'columns'       => 3,
            'filter'        => 'unarchived',
            'category'      => '',
            'show_closed'   => false,
            'show_date'     => true,
            'show_location' => true,
            'show_price'    => true,
            'show_calendar' => true,
            'show_desc'     => true,
            'image_shape'   => 'cinematic',
            'corner_radius' => 8,
            'brand_color'   => '',
            'button_label'  => __( 'Register', 'comms-church-pco' ),
            'capacity'      => 0,
        ) );

        // Resolve brand color: arg → global setting → default
        $brand_color = $this->resolve_color( $args['brand_color'] );

        $cache_key = 'list_' . md5( serialize( $args ) );
        $data = CCPCO_Cache::remember( $cache_key, function() use ( $args ) {
            return $this->api->get_signups( array(
                'per_page' => min( 100, intval( $args['limit'] ) * 3 ),
                'filter'   => sanitize_text_field( $args['filter'] ),
                'include'  => 'next_signup_time,signup_location,categories',
            ) );
        } );

        if ( is_wp_error( $data ) ) return $this->admin_notice( $data->get_error_message() );

        $signups  = $data['data']     ?? array();
        $included = $data['included'] ?? array();

        $times_map    = $this->build_map( $included, 'SignupTime' );
        $location_map = $this->build_map( $included, 'SignupLocation' );
        $cat_map      = $this->build_map( $included, 'Category' );

        $cat_filter  = strtolower( trim( $args['category'] ) );
        $show_closed = (bool) $args['show_closed'];
        $limit       = intval( $args['limit'] );
        $filtered    = array();

        foreach ( $signups as $signup ) {
            $a = $signup['attributes'] ?? array();
            if ( ! $show_closed && ( $a['closed'] ?? false ) ) continue;
            if ( $cat_filter ) {
                $match = false;
                foreach ( $signup['relationships']['categories']['data'] ?? array() as $rel ) {
                    $cat = $cat_map[ $rel['id'] ] ?? null;
                    if ( $cat && str_contains( strtolower( $cat['attributes']['name'] ?? '' ), $cat_filter ) ) {
                        $match = true; break;
                    }
                }
                if ( ! $match ) continue;
            }
            $filtered[] = $signup;
            if ( count( $filtered ) >= $limit ) break;
        }

        if ( empty( $filtered ) ) {
            return '<p class="ccpco-no-results">' . esc_html__( 'No upcoming events found.', 'comms-church-pco' ) . '</p>';
        }

        $cols    = intval( $args['columns'] );
        $radius  = intval( $args['corner_radius'] );
        $display = in_array( $args['display'], array( 'tiles', 'list' ) ) ? $args['display'] : 'tiles';

        ob_start(); ?>
        <div class="ccpco-signup-list ccpco-display-<?php echo esc_attr( $display ); ?> ccpco-cols-<?php echo esc_attr( $cols ); ?>"
             style="--ccpco-brand:<?php echo esc_attr( $brand_color ); ?>;--ccpco-radius:<?php echo esc_attr( $radius ); ?>px">
        <?php foreach ( $filtered as $signup ) :
            echo $this->signup_card_html( $signup, $args, $times_map, $location_map, $brand_color );
        endforeach; ?>
        </div>
        <?php return ob_get_clean();
    }

    // =========================================================================
    // Single signup detail
    // =========================================================================
    public function signup_detail( $args = array() ) {
        if ( ! $this->api->is_configured() ) {
            return $this->admin_notice( __( 'Comms.Church PCO: API credentials not configured.', 'comms-church-pco' ) );
        }

        $args = wp_parse_args( $args, array(
            'id'            => 0,
            'show_desc'     => true,
            'show_times'    => true,
            'show_location' => true,
            'show_tickets'  => true,
            'show_calendar' => true,
            'brand_color'   => '',
            'button_label'  => __( 'Register', 'comms-church-pco' ),
        ) );

        $signup_id   = intval( $args['id'] );
        if ( ! $signup_id ) return $this->admin_notice( __( 'No Signup ID specified.', 'comms-church-pco' ) );

        $brand_color = $this->resolve_color( $args['brand_color'] );

        $data = CCPCO_Cache::remember( 'detail_' . $signup_id, function() use ( $signup_id ) {
            return $this->api->get_signup( $signup_id, array( 'signup_times', 'signup_location' ) );
        } );

        if ( is_wp_error( $data ) ) return $this->admin_notice( $data->get_error_message() );

        $signup   = $data['data']     ?? array();
        $included = $data['included'] ?? array();
        $a        = $signup['attributes'] ?? array();

        $times    = array_values( array_filter( $included, fn( $i ) => $i['type'] === 'SignupTime' ) );
        $locs     = array_values( array_filter( $included, fn( $i ) => $i['type'] === 'SignupLocation' ) );
        $location = $locs[0] ?? null;

        // Tickets
        $tickets = array();
        if ( $args['show_tickets'] ) {
            $t = CCPCO_Cache::remember( 'tickets_' . $signup_id, function() use ( $signup_id ) {
                return $this->api->get_selection_types( $signup_id );
            } );
            if ( ! is_wp_error( $t ) ) $tickets = $t['data'] ?? array();
        }

        // Calendar
        $cal_html = '';
        if ( $args['show_calendar'] && ! empty( $times ) ) {
            $loc_str = $this->location_string( $location );
            $cal_html = CCPCO_Calendar::dropdown( $a, $times[0]['attributes'], $loc_str );
        }

        ob_start(); ?>
        <div class="ccpco-signup-detail" style="--ccpco-brand:<?php echo esc_attr( $brand_color ); ?>">

            <?php if ( ! empty( $a['logo_url'] ) ) : ?>
            <img class="ccpco-detail-hero" src="<?php echo esc_url( $a['logo_url'] ); ?>" alt="<?php echo esc_attr( $a['name'] ?? '' ); ?>">
            <?php endif; ?>

            <div class="ccpco-detail-body">
                <h2 class="ccpco-detail-title"><?php echo esc_html( $a['name'] ?? '' ); ?></h2>

                <?php if ( $args['show_desc'] && ! empty( $a['description'] ) ) : ?>
                <div class="ccpco-detail-desc"><?php echo wp_kses_post( $a['description'] ); ?></div>
                <?php endif; ?>

                <div class="ccpco-detail-meta">

                    <?php if ( $args['show_times'] && ! empty( $times ) ) : ?>
                    <div class="ccpco-meta-block">
                        <h3><?php esc_html_e( 'Event Times', 'comms-church-pco' ); ?></h3>
                        <ul class="ccpco-times-list">
                        <?php foreach ( $times as $t ) :
                            $ta = $t['attributes']; ?>
                            <li><?php echo esc_html( $this->format_date( $ta['starts_at'], $ta['ends_at'], $ta['all_day'] ) ); ?></li>
                        <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <?php if ( $args['show_location'] && $location ) :
                        $la = $location['attributes']; ?>
                    <div class="ccpco-meta-block">
                        <h3><?php esc_html_e( 'Location', 'comms-church-pco' ); ?></h3>
                        <?php if ( ( $la['location_type'] ?? '' ) === 'online' ) : ?>
                            <p><?php esc_html_e( 'Online Event', 'comms-church-pco' ); ?>
                            <?php if ( ! empty( $la['url'] ) ) : ?>
                                &mdash; <a href="<?php echo esc_url( $la['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Join Here', 'comms-church-pco' ); ?></a>
                            <?php endif; ?></p>
                        <?php else : ?>
                            <?php if ( ! empty( $la['name'] ) ) : ?><p class="ccpco-loc-name"><?php echo esc_html( $la['name'] ); ?></p><?php endif; ?>
                            <?php if ( ! empty( $la['full_formatted_address'] ) ) : ?><p class="ccpco-loc-addr"><?php echo esc_html( $la['full_formatted_address'] ); ?></p><?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ( ! empty( $tickets ) ) : ?>
                    <div class="ccpco-meta-block">
                        <h3><?php esc_html_e( 'Registration Options', 'comms-church-pco' ); ?></h3>
                        <ul class="ccpco-tickets-list">
                        <?php foreach ( $tickets as $ticket ) :
                            $ta    = $ticket['attributes'];
                            $price = intval( $ta['price_cents'] ?? 0 ); ?>
                            <li>
                                <span class="ccpco-ticket-name"><?php echo esc_html( $ta['name'] ); ?></span>
                                <span class="ccpco-ticket-price"><?php echo $price === 0 ? esc_html__( 'Free', 'comms-church-pco' ) : '$' . esc_html( number_format( $price / 100, 2 ) ); ?></span>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                </div>

                <div class="ccpco-detail-actions">
                    <?php if ( ! ( $a['closed'] ?? true ) && ! empty( $a['new_registration_url'] ) ) : ?>
                    <a href="<?php echo esc_url( $a['new_registration_url'] ); ?>" class="ccpco-btn ccpco-btn-primary" target="_blank" rel="noopener noreferrer">
                        <?php echo esc_html( $args['button_label'] ); ?>
                    </a>
                    <?php endif; ?>
                    <?php echo $cal_html; ?>
                </div>

            </div>
        </div>
        <?php return ob_get_clean();
    }

    // =========================================================================
    // Register button
    // =========================================================================
    public function register_button( $args = array() ) {
        $args = wp_parse_args( $args, array(
            'id'          => 0,
            'label'       => __( 'Register', 'comms-church-pco' ),
            'brand_color' => '',
            'class'       => '',
        ) );

        $signup_id   = intval( $args['id'] );
        if ( ! $signup_id ) return '';
        $brand_color = $this->resolve_color( $args['brand_color'] );

        $data = CCPCO_Cache::remember( 'btn_' . $signup_id, function() use ( $signup_id ) {
            return $this->api->get_signup( $signup_id );
        } );

        if ( is_wp_error( $data ) ) return '';

        $a       = $data['data']['attributes'] ?? array();
        $closed  = $a['closed'] ?? true;
        $reg_url = $a['new_registration_url'] ?? '';
        $extra   = sanitize_html_class( $args['class'] );

        if ( $closed || ! $reg_url ) {
            return '<span class="ccpco-btn ccpco-btn-disabled ' . $extra . '">' . esc_html__( 'Registration Closed', 'comms-church-pco' ) . '</span>';
        }

        return '<a href="' . esc_url( $reg_url ) . '" '
            . 'class="ccpco-btn ccpco-btn-primary ' . $extra . '" '
            . 'style="background-color:' . esc_attr( $brand_color ) . '" '
            . 'target="_blank" rel="noopener noreferrer">'
            . esc_html( $args['label'] )
            . '</a>';
    }

    // =========================================================================
    // Attendee count
    // =========================================================================
    public function attendee_count( $signup_id, $label = 'registered' ) {
        $data = CCPCO_Cache::remember( 'count_' . $signup_id, function() use ( $signup_id ) {
            return $this->api->get_attendees( $signup_id, array( 'filter' => 'active', 'per_page' => 1 ) );
        }, 120 );

        if ( is_wp_error( $data ) ) return '';
        $total = $data['meta']['total_count'] ?? 0;
        return '<span class="ccpco-count">' . esc_html( $total . ' ' . $label ) . '</span>';
    }

    // =========================================================================
    // Shared card HTML (used by signup_list)
    // =========================================================================
    public function signup_card_html( $signup, $args, $times_map, $location_map, $brand_color ) {
        $id       = $signup['id'];
        $a        = $signup['attributes'] ?? array();
        $name     = $a['name']    ?? '';
        $desc     = $a['description'] ?? '';
        $logo     = $a['logo_url'] ?? '';
        $closed   = $a['closed']  ?? false;
        $reg_url  = $a['new_registration_url'] ?? '';

        // Next time
        $next_time = null;
        $time_rel  = $signup['relationships']['next_signup_time']['data'] ?? null;
        if ( $time_rel ) $next_time = $times_map[ $time_rel['id'] ] ?? null;

        // Location
        $location = null;
        $loc_rel  = $signup['relationships']['signup_location']['data'] ?? null;
        if ( $loc_rel ) $location = $location_map[ $loc_rel['id'] ] ?? null;

        // Price
        $price_html = '';
        if ( $args['show_price'] ?? true ) {
            $price_html = $this->min_price_html( $id );
        }

        // Progress bar
        $capacity  = intval( $args['capacity'] ?? 0 );
        $reg_count = 0;
        if ( $capacity > 0 ) {
            $cd = CCPCO_Cache::remember( 'count_' . $id, function() use ( $id ) {
                return $this->api->get_attendees( $id, array( 'filter' => 'active', 'per_page' => 1 ) );
            }, 120 );
            if ( ! is_wp_error( $cd ) ) $reg_count = intval( $cd['meta']['total_count'] ?? 0 );
        }

        // Calendar
        $cal_html = '';
        if ( $args['show_calendar'] ?? true ) {
            if ( $next_time ) {
                $loc_str  = $this->location_string( $location );
                $cal_html = CCPCO_Calendar::dropdown( $a, $next_time['attributes'], $loc_str );
            }
        }

        $shape = in_array( $args['image_shape'] ?? 'cinematic', array( 'cinematic', 'square', 'portrait' ) )
            ? $args['image_shape'] : 'cinematic';

        ob_start(); ?>
        <article class="ccpco-card <?php echo $closed ? 'ccpco-closed' : 'ccpco-open'; ?>">

            <div class="ccpco-card-image ccpco-shape-<?php echo esc_attr( $shape ); ?>">
                <?php if ( $logo ) : ?>
                <img src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $name ); ?>" loading="lazy">
                <?php else : ?>
                <div class="ccpco-image-placeholder" aria-hidden="true"><span><?php echo esc_html( $name ); ?></span></div>
                <?php endif; ?>
                <?php if ( $closed ) : ?>
                <span class="ccpco-closed-badge"><?php esc_html_e( 'Closed', 'comms-church-pco' ); ?></span>
                <?php endif; ?>
            </div>

            <div class="ccpco-card-body">
                <h3 class="ccpco-card-title"><?php echo esc_html( $name ); ?></h3>

                <div class="ccpco-card-meta">
                    <?php if ( ( $args['show_date'] ?? true ) && $next_time ) :
                        $ta = $next_time['attributes']; ?>
                    <p class="ccpco-meta-date">
                        <?php echo $this->icon_calendar(); ?>
                        <span><?php echo esc_html( $this->format_date( $ta['starts_at'], $ta['ends_at'], $ta['all_day'] ) ); ?></span>
                    </p>
                    <?php endif; ?>

                    <?php if ( ( $args['show_location'] ?? true ) && $location ) :
                        $la = $location['attributes'];
                        $loc_type = $la['location_type'] ?? ''; ?>
                    <p class="ccpco-meta-location">
                        <?php echo $this->icon_location(); ?>
                        <?php if ( $loc_type === 'online' && ! empty( $la['url'] ) ) : ?>
                            <a href="<?php echo esc_url( $la['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Online Event', 'comms-church-pco' ); ?></a>
                        <?php elseif ( $loc_type === 'online' ) : ?>
                            <span><?php esc_html_e( 'Online Event', 'comms-church-pco' ); ?></span>
                        <?php else : ?>
                            <span><?php echo esc_html( $la['name'] ?? $la['formatted_address'] ?? '' ); ?></span>
                        <?php endif; ?>
                    </p>
                    <?php endif; ?>

                    <?php if ( $price_html ) : ?>
                    <p class="ccpco-meta-price"><?php echo $price_html; ?></p>
                    <?php endif; ?>
                </div>

                <?php if ( $capacity > 0 ) :
                    $pct  = min( 100, round( ( $reg_count / $capacity ) * 100 ) );
                    $full = $reg_count >= $capacity; ?>
                <div class="ccpco-progress" title="<?php echo esc_attr( $reg_count . ' / ' . $capacity ); ?>">
                    <div class="ccpco-progress-bar">
                        <div class="ccpco-progress-fill <?php echo $full ? 'ccpco-full' : ''; ?>" style="width:<?php echo esc_attr( $pct ); ?>%"></div>
                    </div>
                    <p class="ccpco-progress-label">
                        <?php echo $full
                            ? esc_html__( 'Full', 'comms-church-pco' )
                            : esc_html( $reg_count . ' / ' . $capacity . ' ' . __( 'registered', 'comms-church-pco' ) ); ?>
                    </p>
                </div>
                <?php endif; ?>

                <?php if ( ( $args['show_desc'] ?? true ) && $desc ) : ?>
                <p class="ccpco-card-desc"><?php echo esc_html( wp_trim_words( $desc, 18 ) ); ?></p>
                <?php endif; ?>

                <div class="ccpco-card-actions">
                    <?php if ( ! $closed && $reg_url ) : ?>
                    <a href="<?php echo esc_url( $reg_url ); ?>" class="ccpco-btn ccpco-btn-primary" target="_blank" rel="noopener noreferrer">
                        <?php echo esc_html( $args['button_label'] ?? __( 'Register', 'comms-church-pco' ) ); ?>
                    </a>
                    <?php endif; ?>
                    <?php echo $cal_html; ?>
                </div>
            </div>

        </article>
        <?php return ob_get_clean();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    public function min_price_html( $signup_id ) {
        $data = CCPCO_Cache::remember( 'price_' . $signup_id, function() use ( $signup_id ) {
            return $this->api->get_selection_types( $signup_id, true );
        }, 600 );
        if ( is_wp_error( $data ) || empty( $data['data'] ) ) return '';
        $prices = array_filter( array_map( fn( $t ) => intval( $t['attributes']['price_cents'] ?? 0 ), $data['data'] ) );
        if ( empty( $prices ) ) return '<span class="ccpco-price-free">' . esc_html__( 'Free', 'comms-church-pco' ) . '</span>';
        return '<span class="ccpco-price">' . esc_html( sprintf( __( 'From $%s', 'comms-church-pco' ), number_format( min( $prices ) / 100, 2 ) ) ) . '</span>';
    }

    public function format_date( $starts_at, $ends_at = '', $all_day = false ) {
        if ( ! $starts_at ) return '';
        $s = strtotime( $starts_at );
        $e = $ends_at ? strtotime( $ends_at ) : null;
        if ( $all_day ) {
            if ( $e && wp_date( 'Y-m-d', $s ) !== wp_date( 'Y-m-d', $e ) )
                return wp_date( 'F j', $s ) . ' – ' . wp_date( 'F j, Y', $e );
            return wp_date( 'F j, Y', $s );
        }
        $out = wp_date( 'F j, Y \a\t g:i a', $s );
        if ( $e ) {
            $out .= wp_date( 'Y-m-d', $s ) === wp_date( 'Y-m-d', $e )
                ? ' – ' . wp_date( 'g:i a', $e )
                : ' – ' . wp_date( 'F j, Y \a\t g:i a', $e );
        }
        return $out;
    }

    private function resolve_color( $color ) {
        if ( $color && preg_match( '/^#[0-9a-fA-F]{3,6}$/', $color ) ) return $color;
        $global = get_option( 'ccpco_brand_color', '#1a4a8a' );
        return $global ?: '#1a4a8a';
    }

    private function location_string( $location ) {
        if ( ! $location ) return '';
        $la = $location['attributes'];
        if ( ( $la['location_type'] ?? '' ) === 'online' ) return $la['url'] ?? 'Online';
        return $la['full_formatted_address'] ?? $la['formatted_address'] ?? $la['name'] ?? '';
    }

    public function build_map( $included, $type ) {
        $map = array();
        foreach ( $included as $item ) {
            if ( $item['type'] === $type ) $map[ $item['id'] ] = $item;
        }
        return $map;
    }

    private function icon_calendar() {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/></svg>';
    }

    private function icon_location() {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg>';
    }

    public function admin_notice( $message ) {
        if ( current_user_can( 'manage_options' ) ) {
            return '<div class="ccpco-admin-notice"><p>' . esc_html( $message ) . '</p></div>';
        }
        return '';
    }
}
