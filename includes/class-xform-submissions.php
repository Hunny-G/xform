<?php
defined( 'ABSPATH' ) || exit;
final class XForm_Submissions {
    public static function init(): void {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
        add_action( 'xform_deliver_submission', array( __CLASS__, 'deliver' ), 10, 1 );
        add_action( 'rest_api_init', array( __CLASS__, 'register_crm_routes' ) );
    }
    public static function register_routes(): void {
        register_rest_route( 'xform/v1', '/submit', array(
            'methods' => 'POST',
            'callback' => array( __CLASS__, 'submit' ),
            'permission_callback' => '__return_true',
        ) );
    }
    public static function submit( WP_REST_Request $request ): WP_REST_Response {
        $content_length = (int) $request->get_header( 'content-length' );
        if ( $content_length > 32768 || strlen( (string) wp_json_encode( $request->get_params() ) ) > 32768 ) {
            return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Submission is too large.', 'xform' ) ), 413 );
        }
        $nonce = sanitize_text_field( (string) $request->get_header( 'X-XForm-Nonce' ) );
        if ( ! wp_verify_nonce( $nonce, 'xform_submit' ) ) {
            return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Security check failed. Refresh and try again.', 'xform' ) ), 403 );
        }
        $params = $request->get_params();
        if ( ! is_array( $params ) || count( $params ) > 60 ) {
            return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Invalid submission.', 'xform' ) ), 400 );
        }
        $form_id = sanitize_title( (string) ( $params['xform_id'] ?? '' ) );
        $form = XForm_Forms::get( $form_id );
        if ( ! $form ) {
            return new WP_REST_Response( array( 'success' => false, 'message' => __( 'This form is unavailable.', 'xform' ) ), 404 );
        }
        if ( ! empty( $params['xform_website'] ) ) {
            return new WP_REST_Response( array( 'success' => true, 'message' => $form['success_message'] ), 200 );
        }
        $ip = self::client_ip();
        $rate_key = 'xform_rl_' . md5( $form_id . '|' . ( $ip ?: 'unknown' ) );
        $rate = (int) get_transient( $rate_key );
        if ( $rate >= 5 ) {
            return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Too many submissions. Please try again later.', 'xform' ) ), 429 );
        }
        set_transient( $rate_key, $rate + 1, 10 * MINUTE_IN_SECONDS );
        $data = array();
        foreach ( $params as $key => $value ) {
            if ( in_array( $key, array( '_wpnonce', 'xform_nonce', 'xform_id', 'xform_website', 'action' ), true ) || 0 === strpos( (string) $key, '_' ) ) {
                continue;
            }
            $safe_key = sanitize_key( (string) $key );
            if ( '' === $safe_key ) {
                continue;
            }
            if ( is_array( $value ) ) {
                $data[ $safe_key ] = array_map( static function ( $item ) {
                    return sanitize_text_field( is_scalar( $item ) ? substr( (string) $item, 0, 2000 ) : '' );
                }, array_slice( $value, 0, 20 ) );
            } elseif ( is_scalar( $value ) ) {
                $data[ $safe_key ] = sanitize_textarea_field( substr( (string) $value, 0, 5000 ) );
            }
        }
        if ( empty( $data ) ) {
            return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Please complete the form and try again.', 'xform' ) ), 400 );
        }
        global $wpdb;
        $inserted = $wpdb->insert(
            XForm_DB::table(),
            array(
                'form_id' => $form_id,
                'form_data' => wp_json_encode( $data ),
                'ip_address' => $ip,
                'delivery_status' => 'queued',
                'created_at' => current_time( 'mysql', true ),
            ),
            array( '%s', '%s', '%s', '%s', '%s' )
        );
        if ( ! $inserted ) {
            return new WP_REST_Response( array( 'success' => false, 'message' => __( 'We could not save your submission. Please try again.', 'xform' ) ), 500 );
        }
        $submission_id = (int) $wpdb->insert_id;
        if ( ! wp_next_scheduled( 'xform_deliver_submission', array( $submission_id ) ) ) {
            wp_schedule_single_event( time() + 2, 'xform_deliver_submission', array( $submission_id ) );
        }
        return new WP_REST_Response( array( 'success' => true, 'message' => $form['success_message'] ), 202 );
    }
    public static function register_crm_routes(): void {
        register_rest_route( 'xform/v1', '/crm/queue', array(
            'methods' => 'GET', 'callback' => array( __CLASS__, 'crm_queue' ),
            'permission_callback' => '__return_true',
        ) );
        register_rest_route( 'xform/v1', '/crm/ack', array(
            'methods' => 'POST', 'callback' => array( __CLASS__, 'crm_ack' ),
            'permission_callback' => '__return_true',
        ) );
    }
    private static function crm_authorized( WP_REST_Request $request ): bool {
        $saved = (string) get_option( 'xform_crm_token', '' );
        $provided = (string) $request->get_header( 'X-XForm-CRM-Token' );
        return strlen( $saved ) >= 32 && strlen( $provided ) >= 32 && hash_equals( $saved, $provided );
    }
    public static function crm_queue( WP_REST_Request $request ): WP_REST_Response {
        if ( ! self::crm_authorized( $request ) ) {
            return new WP_REST_Response( array( 'ok' => false ), 401 );
        }
        global $wpdb;
        $table = XForm_DB::table();
        $rows = $wpdb->get_results( "SELECT id, form_id, form_data, created_at FROM {$table} WHERE crm_status = 'pending' ORDER BY id ASC LIMIT 25", ARRAY_A );
        $items = array();
        foreach ( $rows as $row ) {
            $form = XForm_Forms::get( (string) $row['form_id'] );
            $items[] = array(
                'source_id' => 'xform-' . (int) $row['id'],
                'form_id' => (string) $row['form_id'],
                'form_name' => $form ? (string) $form['name'] : (string) $row['form_id'],
                'submitted_at' => (string) $row['created_at'],
                'data' => json_decode( (string) $row['form_data'], true ) ?: array(),
            );
        }
        return new WP_REST_Response( array( 'ok' => true, 'items' => $items ), 200 );
    }
    public static function crm_ack( WP_REST_Request $request ): WP_REST_Response {
        if ( ! self::crm_authorized( $request ) ) {
            return new WP_REST_Response( array( 'ok' => false ), 401 );
        }
        $id = absint( $request->get_param( 'id' ) );
        if ( ! $id ) {
            return new WP_REST_Response( array( 'ok' => false, 'message' => 'Invalid ID' ), 400 );
        }
        global $wpdb;
        $updated = $wpdb->update(
            XForm_DB::table(),
            array( 'crm_status' => 'delivered' ),
            array( 'id' => $id, 'crm_status' => 'pending' ),
            array( '%s' ),
            array( '%d', '%s' )
        );
        return new WP_REST_Response( array( 'ok' => 1 === $updated ), 200 );
    }
    private static function client_ip(): string {
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
        return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
    }
    public static function deliver( int $submission_id ): void {
        global $wpdb;
        $table = XForm_DB::table();
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $submission_id ), ARRAY_A );
        if ( ! $row || 'sent' === $row['delivery_status'] ) {
            return;
        }
        $form = XForm_Forms::get( (string) $row['form_id'] );
        if ( ! $form ) {
            self::set_status( $submission_id, 'failed' );
            return;
        }
        $data = json_decode( $row['form_data'], true );
        if ( ! is_array( $data ) ) {
            self::set_status( $submission_id, 'failed' );
            return;
        }
        $body = "New form submission: " . $form['name'] . "\n\n";
        foreach ( $data as $key => $value ) {
            $body .= sanitize_text_field( $key ) . ': ' . ( is_array( $value ) ? implode( ', ', $value ) : $value ) . "\n";
        }
        $ok = true;
        if ( in_array( $form['destination'], array( 'email', 'both' ), true ) ) {
            $ok = wp_mail( $form['email'], sprintf( __( 'New submission: %s', 'xform' ), $form['name'] ), $body ) && $ok;
        }
        if ( in_array( $form['destination'], array( 'webhook', 'both' ), true ) ) {
            if ( ! XForm_Forms::is_valid_webhook_url( (string) $form['webhook'] ) ) {
                self::set_status( $submission_id, 'failed' );
                return;
            }
            // wp_safe_remote_post rejects URLs that resolve to unsafe/private IP addresses.
            // Redirects are disabled so a trusted destination cannot redirect the request internally.
            $response = wp_safe_remote_post( $form['webhook'], array(
                'timeout' => 8, 'redirection' => 0,
                'headers' => array( 'Content-Type' => 'application/json' ),
                'body' => wp_json_encode( array( 'form_id' => $form['id'], 'form_name' => $form['name'], 'submission_id' => $submission_id, 'submitted_at' => $row['created_at'], 'data' => $data ) ),
            ) );
            $ok = ! is_wp_error( $response ) && (int) wp_remote_retrieve_response_code( $response ) >= 200 && (int) wp_remote_retrieve_response_code( $response ) < 300 && $ok;
        }
        self::set_status( $submission_id, $ok ? 'sent' : 'failed' );
    }
    private static function set_status( int $id, string $status ): void {
        global $wpdb;
        $wpdb->update( XForm_DB::table(), array( 'delivery_status' => $status ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );
    }
}
