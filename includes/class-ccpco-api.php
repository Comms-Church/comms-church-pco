<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CCPCO_API {

    const BASE_URL = 'https://api.planningcenteronline.com/registrations/v2';

    private $app_id;
    private $secret;

    public function __construct() {
        $this->app_id = get_option( 'ccpco_app_id', '' );
        $this->secret = get_option( 'ccpco_secret', '' );
    }

    public function is_configured() {
        return ! empty( $this->app_id ) && ! empty( $this->secret );
    }

    /**
     * Authenticated GET request.
     * Returns decoded array or WP_Error (with status code in error data on non-200).
     */
    public function get( $endpoint, $params = array() ) {
        if ( ! $this->is_configured() ) {
            return new WP_Error( 'ccpco_not_configured', __( 'Planning Center API credentials are not configured.', 'comms-church-pco' ) );
        }

        $url = add_query_arg( $params, self::BASE_URL . $endpoint );

        $response = wp_remote_get( $url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $this->app_id . ':' . $this->secret ),
                'Content-Type'  => 'application/json',
            ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) ) return $response;

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $msg = $body['errors'][0]['detail'] ?? __( 'Unknown API error.', 'comms-church-pco' );
            return new WP_Error( 'ccpco_api_error', $msg, array( 'status' => $code ) );
        }

        return $body;
    }

    // ---- Convenience wrappers ------------------------------------------------

    public function get_signups( $args = array() ) {
        return $this->get( '/signups', wp_parse_args( $args, array( 'per_page' => 25 ) ) );
    }

    public function get_signup( $id, $include = array() ) {
        $params = array();
        if ( $include ) $params['include'] = implode( ',', $include );
        return $this->get( '/signups/' . intval( $id ), $params );
    }

    public function get_attendees( $signup_id, $args = array() ) {
        return $this->get( '/signups/' . intval( $signup_id ) . '/attendees',
            wp_parse_args( $args, array( 'per_page' => 1, 'filter' => 'active' ) )
        );
    }

    public function get_selection_types( $signup_id, $public_only = true ) {
        $params = array( 'per_page' => 50 );
        if ( $public_only ) $params['filter'] = 'publicly_available';
        return $this->get( '/signups/' . intval( $signup_id ) . '/selection_types', $params );
    }

    public function get_signup_times( $signup_id, $filter = 'future' ) {
        $params = array( 'per_page' => 50 );
        if ( $filter ) $params['filter'] = $filter;
        return $this->get( '/signups/' . intval( $signup_id ) . '/signup_times', $params );
    }

    public function get_categories() {
        return $this->get( '/categories', array( 'per_page' => 100 ) );
    }
}
