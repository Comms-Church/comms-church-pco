<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CCPCO_Webhook {

    const NS   = 'ccpco/v1';
    const PATH = '/webhook';

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_route' ) );
    }

    public function register_route() {
        register_rest_route( self::NS, self::PATH, array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle' ),
            'permission_callback' => '__return_true',
        ) );
    }

    public function handle( WP_REST_Request $request ) {
        $secret = get_option( 'ccpco_webhook_secret', '' );
        if ( empty( $secret ) ) {
            return new WP_REST_Response( array( 'error' => 'Webhook not configured.' ), 400 );
        }

        $sig  = $request->get_header( 'X-PCO-Webhooks-Authenticity' );
        $body = $request->get_body();

        if ( ! $this->verify( $body, $sig, $secret ) ) {
            $this->log( 'Signature verification failed — unauthorized request.' );
            return new WP_REST_Response( array( 'error' => 'Unauthorized.' ), 401 );
        }

        $payload = json_decode( $body, true );
        if ( ! $payload ) return new WP_REST_Response( array( 'error' => 'Invalid payload.' ), 400 );

        $event = $payload['name'] ?? '';
        $data  = $payload['data'] ?? array();
        $this->log( 'Received: ' . $event );

        switch ( $event ) {
            case 'registration.signup.updated':
            case 'registration.signup.created':
                $id = $data['id'] ?? null;
                if ( $id ) { CCPCO_Cache::flush_signup( $id ); $this->log( 'Flushed signup: ' . $id ); }
                break;

            case 'registration.attendee.created':
            case 'registration.attendee.updated':
            case 'registration.attendee.deleted':
            case 'registration.registration.created':
            case 'registration.registration.updated':
                $id = $data['relationships']['signup']['data']['id'] ?? null;
                if ( $id ) { CCPCO_Cache::flush_signup( $id ); $this->log( 'Flushed signup (from event): ' . $id ); }
                break;

            default:
                CCPCO_Cache::flush_all();
                $this->log( 'Unknown event, flushed all cache: ' . $event );
                break;
        }

        return new WP_REST_Response( array( 'ok' => true ), 200 );
    }

    private function verify( $body, $header, $secret ) {
        if ( ! $header ) return false;
        $parts = explode( '=', $header, 2 );
        if ( ( $parts[0] ?? '' ) !== 'sha256' ) return false;
        return hash_equals( hash_hmac( 'sha256', $body, $secret ), $parts[1] ?? '' );
    }

    private function log( $msg ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) error_log( '[CCPCO Webhook] ' . $msg );
        $log = get_option( 'ccpco_webhook_log', array() );
        array_unshift( $log, array( 'time' => current_time( 'mysql' ), 'message' => $msg ) );
        update_option( 'ccpco_webhook_log', array_slice( $log, 0, 20 ), false );
    }

    public static function endpoint_url() {
        return rest_url( self::NS . self::PATH );
    }

    public static function get_secret() {
        $s = get_option( 'ccpco_webhook_secret', '' );
        if ( ! $s ) { $s = wp_generate_password( 40, false ); update_option( 'ccpco_webhook_secret', $s ); }
        return $s;
    }
}
