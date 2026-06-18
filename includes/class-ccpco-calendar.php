<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CCPCO_Calendar {

    public static function dropdown( $signup_attrs, $time_attrs = array(), $location = '' ) {
        $title   = $signup_attrs['name']        ?? '';
        $desc    = $signup_attrs['description'] ?? '';
        $starts  = $time_attrs['starts_at']     ?? '';
        $ends    = $time_attrs['ends_at']        ?? '';
        $all_day = $time_attrs['all_day']        ?? false;

        if ( ! $starts ) return '';

        $google  = self::google_url(  $title, $desc, $starts, $ends, $all_day, $location );
        $ical    = self::ical_url(    $title, $desc, $starts, $ends, $all_day, $location );
        $outlook = self::outlook_url( $title, $desc, $starts, $ends, $all_day, $location );

        ob_start(); ?>
        <div class="ccpco-cal-wrap">
            <button class="ccpco-cal-trigger" type="button" aria-haspopup="true" aria-expanded="false">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/></svg>
                <?php esc_html_e( 'Add to Calendar', 'comms-church-pco' ); ?>
                <svg class="ccpco-cal-chevron" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
            </button>
            <div class="ccpco-cal-dropdown" role="menu">
                <a href="<?php echo esc_url( $google ); ?>" target="_blank" rel="noopener noreferrer" class="ccpco-cal-option" role="menuitem">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21.35 11.1h-9.17v2.73h6.51c-.33 3.81-3.5 5.44-6.5 5.44C8.36 19.27 5 16.25 5 12c0-4.1 3.2-7.27 7.2-7.27 3.09 0 4.9 1.97 4.9 1.97L19 4.72S16.56 2 12.1 2C6.42 2 2.03 6.8 2.03 12c0 5.05 4.13 10 10.22 10 5.35 0 9.25-3.67 9.25-9.09 0-1.15-.15-1.81-.15-1.81z" fill="#4285F4"/></svg>
                    <?php esc_html_e( 'Google Calendar', 'comms-church-pco' ); ?>
                </a>
                <a href="<?php echo esc_url( $ical ); ?>" class="ccpco-cal-option" role="menuitem">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 3h-1V1h-2v2H9V1H7v2H6C4.9 3 4 3.9 4 5v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 18H6V8h12v13z" fill="#333"/></svg>
                    <?php esc_html_e( 'Apple Calendar (.ics)', 'comms-church-pco' ); ?>
                </a>
                <a href="<?php echo esc_url( $outlook ); ?>" target="_blank" rel="noopener noreferrer" class="ccpco-cal-option" role="menuitem">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 3c1.93 0 3.5 1.57 3.5 3.5S13.93 13 12 13s-3.5-1.57-3.5-3.5S10.07 6 12 6zm7 13H5v-.23c0-.62.28-1.2.76-1.58C7.47 15.82 9.64 15 12 15s4.53.82 6.24 2.19c.48.38.76.97.76 1.58V19z" fill="#0078D4"/></svg>
                    <?php esc_html_e( 'Outlook / Office 365', 'comms-church-pco' ); ?>
                </a>
            </div>
        </div>
        <?php return ob_get_clean();
    }

    private static function google_url( $title, $desc, $starts, $ends, $all_day, $location ) {
        $fmt   = $all_day ? 'Ymd' : 'Ymd\THis\Z';
        $start = gmdate( $fmt, strtotime( $starts ) );
        $end   = $ends ? gmdate( $fmt, strtotime( $ends ) ) : gmdate( $fmt, strtotime( $starts ) + 3600 );
        return add_query_arg( array(
            'action'   => 'TEMPLATE',
            'text'     => urlencode( $title ),
            'dates'    => $start . '/' . $end,
            'details'  => urlencode( wp_strip_all_tags( $desc ) ),
            'location' => urlencode( $location ),
        ), 'https://calendar.google.com/calendar/render' );
    }

    private static function ical_url( $title, $desc, $starts, $ends, $all_day, $location ) {
        return add_query_arg( array(
            'ccpco_ical'  => '1',
            'title'       => urlencode( $title ),
            'starts'      => urlencode( $starts ),
            'ends'        => urlencode( $ends ?: '' ),
            'all_day'     => $all_day ? '1' : '0',
            'location'    => urlencode( $location ),
            'description' => urlencode( wp_strip_all_tags( $desc ) ),
        ), home_url( '/' ) );
    }

    private static function outlook_url( $title, $desc, $starts, $ends, $all_day, $location ) {
        $fmt   = $all_day ? 'Y-m-d' : 'Y-m-d\TH:i:s';
        $start = gmdate( $fmt, strtotime( $starts ) );
        $end   = $ends ? gmdate( $fmt, strtotime( $ends ) ) : gmdate( $fmt, strtotime( $starts ) + 3600 );
        return add_query_arg( array(
            'rru'      => 'addevent',
            'subject'  => urlencode( $title ),
            'startdt'  => $start,
            'enddt'    => $end,
            'body'     => urlencode( wp_strip_all_tags( $desc ) ),
            'location' => urlencode( $location ),
        ), 'https://outlook.live.com/calendar/0/deeplink/compose' );
    }

    public static function maybe_serve_ical() {
        if ( empty( $_GET['ccpco_ical'] ) ) return;

        $title    = sanitize_text_field( urldecode( $_GET['title']       ?? '' ) );
        $starts   = sanitize_text_field( urldecode( $_GET['starts']      ?? '' ) );
        $ends     = sanitize_text_field( urldecode( $_GET['ends']        ?? '' ) );
        $all_day  = ! empty( $_GET['all_day'] ) && $_GET['all_day'] === '1';
        $location = sanitize_text_field( urldecode( $_GET['location']    ?? '' ) );
        $desc     = sanitize_text_field( urldecode( $_GET['description'] ?? '' ) );

        if ( ! $starts ) wp_die( esc_html__( 'Invalid request.', 'comms-church-pco' ) );

        $start_ts = strtotime( $starts );
        $end_ts   = $ends ? strtotime( $ends ) : $start_ts + 3600;
        $uid      = md5( $title . $starts ) . '@' . parse_url( home_url(), PHP_URL_HOST );

        $ics  = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Comms.Church PCO//EN\r\n";
        $ics .= "BEGIN:VEVENT\r\n";
        $ics .= "UID:{$uid}\r\n";
        $ics .= "SUMMARY:"  . self::ical_escape( $title )    . "\r\n";
        $ics .= "DESCRIPTION:" . self::ical_escape( $desc )  . "\r\n";
        $ics .= "LOCATION:" . self::ical_escape( $location ) . "\r\n";
        if ( $all_day ) {
            $ics .= "DTSTART;VALUE=DATE:" . gmdate( 'Ymd', $start_ts ) . "\r\n";
            $ics .= "DTEND;VALUE=DATE:"   . gmdate( 'Ymd', $end_ts )   . "\r\n";
        } else {
            $ics .= "DTSTART:" . gmdate( 'Ymd\THis\Z', $start_ts ) . "\r\n";
            $ics .= "DTEND:"   . gmdate( 'Ymd\THis\Z', $end_ts )   . "\r\n";
        }
        $ics .= "DTSTAMP:" . gmdate( 'Ymd\THis\Z' ) . "\r\n";
        $ics .= "END:VEVENT\r\nEND:VCALENDAR\r\n";

        header( 'Content-Type: text/calendar; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . sanitize_title( $title ) . '.ics"' );
        header( 'Cache-Control: no-cache, must-revalidate' );
        echo $ics;
        exit;
    }

    private static function ical_escape( $str ) {
        return wordwrap(
            str_replace( array( '\\', ';', ',', "\n" ), array( '\\\\', '\;', '\,', '\n' ), $str ),
            73, "\r\n ", true
        );
    }
}

add_action( 'template_redirect', array( 'CCPCO_Calendar', 'maybe_serve_ical' ) );
