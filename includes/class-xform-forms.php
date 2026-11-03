<?php
defined( 'ABSPATH' ) || exit;
final class XForm_Forms {
    public static function all(): array {
        $forms = get_option( 'xform_forms', array() );
        return is_array( $forms ) ? $forms : array();
    }
    public static function get( string $id ): ?array {
        $forms = self::all();
        return isset( $forms[ $id ] ) && is_array( $forms[ $id ] ) ? $forms[ $id ] : null;
    }
    public static function save( array $input ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'xform_forbidden', __( 'You are not allowed to manage forms.', 'xform' ) );
        }
        $name = sanitize_text_field( $input['name'] ?? '' );
        $id = sanitize_title( $input['id'] ?? $name );
        if ( '' === $name || '' === $id ) {
            return new WP_Error( 'xform_invalid', __( 'A form name and ID are required.', 'xform' ) );
        }
        $destination = sanitize_key( $input['destination'] ?? 'email' );
        if ( ! in_array( $destination, array( 'email', 'webhook', 'both', 'none' ), true ) ) {
            $destination = 'email';
        }
        $email = sanitize_email( $input['email'] ?? '' );
        if ( in_array( $destination, array( 'email', 'both' ), true ) && ! is_email( $email ) ) {
            return new WP_Error( 'xform_email', __( 'Enter a valid destination email address.', 'xform' ) );
        }
        $webhook = esc_url_raw( $input['webhook'] ?? '' );
        if ( in_array( $destination, array( 'webhook', 'both' ), true ) && ! self::is_valid_webhook_url( $webhook ) ) {
            return new WP_Error( 'xform_webhook', __( 'Webhook URL must be a valid HTTPS URL without embedded credentials.', 'xform' ) );
        }
        $html = wp_kses( (string) ( $input['html'] ?? '' ), self::allowed_html(), array( 'http', 'https', 'mailto' ) );
        $css = self::clean_css( (string) ( $input['css'] ?? '' ) );
        if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
            return new WP_Error( 'xform_empty', __( 'Add your form HTML before saving.', 'xform' ) );
        }
        $forms = self::all();
        $forms[ $id ] = array(
            'id' => $id, 'name' => $name, 'html' => $html, 'css' => $css,
            'destination' => $destination, 'email' => $email, 'webhook' => $webhook,
            'success_message' => sanitize_text_field( $input['success_message'] ?? __( 'Thank you. Your submission has been received.', 'xform' ) ),
            'updated' => current_time( 'mysql' ),
        );
        update_option( 'xform_forms', $forms, false );
        return $forms[ $id ];
    }
    /**
     * Validate the URL shape. The HTTP API performs a second unsafe-URL/IP check at request time.
     */
    public static function is_valid_webhook_url( string $url ): bool {
        $parts = wp_parse_url( $url );
        if ( ! is_array( $parts ) || empty( $parts['host'] ) || 'https' !== strtolower( $parts['scheme'] ?? '' ) ) {
            return false;
        }
        if ( isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['fragment'] ) ) {
            return false;
        }
        return (bool) filter_var( $parts['host'], FILTER_VALIDATE_IP ) || (bool) preg_match( '/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', $parts['host'] );
    }
    private static function allowed_html(): array {
        $allowed = wp_kses_allowed_html( 'post' );
        $allowed['form'] = array( 'class' => true, 'id' => true, 'aria-label' => true, 'novalidate' => true );
        foreach ( array( 'input', 'textarea', 'select', 'option', 'button', 'label', 'fieldset', 'legend' ) as $tag ) {
            $allowed[ $tag ] = array(
                'type' => true, 'name' => true, 'value' => true, 'class' => true, 'id' => true,
                'placeholder' => true, 'required' => true, 'checked' => true, 'selected' => true,
                'multiple' => true, 'min' => true, 'max' => true, 'step' => true, 'maxlength' => true,
                'autocomplete' => true, 'for' => true, 'disabled' => true, 'aria-label' => true,
                'aria-describedby' => true, 'rows' => true, 'cols' => true,
            );
        }
        return $allowed;
    }
    private static function clean_css( string $css ): string {
        $css = preg_replace( '#</?style[^>]*>#i', '', $css );
        $css = preg_replace( '/@import[^;]*;?/i', '', $css );
        $css = preg_replace( '/expression\s*\([^)]*\)/i', '', $css );
        $css = preg_replace( '/javascript\s*:/i', '', $css );
        $css = preg_replace( '/url\s*\(\s*[\'"]?\s*data:/i', 'url(', $css );
        return substr( $css, 0, 30000 );
    }
}
