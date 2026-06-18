<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CCPCO_Cache {

    const DEFAULT_TTL       = 300;
    const CIRCUIT_BREAK_TTL = 60;
    const STALE_TTL         = 86400;

    public static function remember( $cache_key, $callback, $ttl = null ) {
        $ttl       = $ttl ?? intval( get_option( 'ccpco_cache_ttl', self::DEFAULT_TTL ) );
        $key       = 'ccpco_' . md5( $cache_key );
        $stale_key = 'ccpco_s_' . md5( $cache_key );
        $break_key = 'ccpco_b_' . md5( $cache_key );

        $cached = get_transient( $key );
        if ( false !== $cached ) return $cached;

        if ( get_transient( $break_key ) ) {
            $stale = get_transient( $stale_key );
            if ( false !== $stale ) return $stale;
        }

        $fresh = call_user_func( $callback );

        if ( is_wp_error( $fresh ) ) {
            $data = $fresh->get_error_data();
            if ( isset( $data['status'] ) && $data['status'] === 429 ) {
                set_transient( $break_key, 1, self::CIRCUIT_BREAK_TTL );
                $stale = get_transient( $stale_key );
                if ( false !== $stale ) return $stale;
            }
            return $fresh;
        }

        set_transient( $key,       $fresh, $ttl              );
        set_transient( $stale_key, $fresh, self::STALE_TTL   );

        return $fresh;
    }

    public static function flush( $cache_key ) {
        delete_transient( 'ccpco_'   . md5( $cache_key ) );
        delete_transient( 'ccpco_b_' . md5( $cache_key ) );
    }

    public static function flush_signup( $signup_id ) {
        $patterns = array(
            'signup_detail_' . $signup_id,
            'signup_btn_'    . $signup_id,
            'tickets_'       . $signup_id . '_1',
            'tickets_'       . $signup_id . '_0',
            'min_price_'     . $signup_id,
            'attendees_count_' . $signup_id,
            'times_'         . $signup_id . '_future',
            'times_'         . $signup_id . '_past',
            'times_'         . $signup_id . '_',
        );
        foreach ( $patterns as $k ) self::flush( $k );
        self::flush_lists();
    }

    public static function flush_lists() {
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->options}
            WHERE option_name LIKE '_transient_ccpco_%'
               OR option_name LIKE '_transient_timeout_ccpco_%'" );
    }

    public static function flush_all() {
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->options}
            WHERE option_name LIKE '_transient_ccpco%'
               OR option_name LIKE '_transient_timeout_ccpco%'" );
    }
}
