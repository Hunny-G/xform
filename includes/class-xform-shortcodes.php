<?php
defined( 'ABSPATH' ) || exit;
final class XForm_Shortcodes {
    public static function init(): void {
        add_shortcode( 'xform', array( __CLASS__, 'render' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
    }
    public static function register_assets(): void {
        wp_register_script( 'xform-public', XFORM_URL . 'public/xform.js', array(), XFORM_VERSION, true );
        wp_register_style( 'xform-public', XFORM_URL . 'public/xform.css', array(), XFORM_VERSION );
    }
    public static function render( $atts ): string {
        $atts = shortcode_atts( array( 'id' => '', 'name' => '' ), $atts, 'xform' );
        $id = sanitize_title( $atts['id'] ?: $atts['name'] );
        $form = XForm_Forms::get( $id );
        if ( ! $form ) {
            return '<p class="xform-message xform-error">' . esc_html__( 'This form is unavailable.', 'xform' ) . '</p>';
        }
        wp_enqueue_script( 'xform-public' );
        wp_enqueue_style( 'xform-public' );
        wp_localize_script( 'xform-public', 'XFormConfig', array(
            'endpoint' => esc_url_raw( rest_url( 'xform/v1/submit' ) ),
            'nonce' => wp_create_nonce( 'xform_submit' ),
        ) );
        $html = $form['html'];
        // The plugin owns the form element and its submission metadata.
        $html = preg_replace( '#<form\b[^>]*>#i', '', $html );
        $html = preg_replace( '#</form\s*>#i', '', $html );
        // Convert authored submit controls into ordinary submit buttons while retaining their attributes.
        $html = preg_replace_callback( '#<button\b([^>]*)>#i', static function ( $m ) {
            $attrs = preg_replace( '/\s+type\s*=\s*(["\']).*?\1/i', '', $m[1] );
            return '<button type="submit"' . $attrs . '>';
        }, $html );
        $html = preg_replace_callback( '#<input\b([^>]*)>#i', static function ( $m ) {
            if ( preg_match( '/\btype\s*=\s*(["\'])submit\1/i', $m[1] ) ) {
                return '<input' . preg_replace( '/\btype\s*=\s*(["\'])submit\1/i', 'type="submit"', $m[1] ) . '>';
            }
            return $m[0];
        }, $html );
        $nonce = wp_nonce_field( 'xform_submit', 'xform_nonce', true, false );
        $out = '<div class="xform-wrap" data-xform-wrap data-xform-id="' . esc_attr( $id ) . '">';
        $out .= '<form class="xform-form" method="post" data-xform-form>';
        $out .= '<input type="hidden" name="xform_id" value="' . esc_attr( $id ) . '">' . $nonce;
        $out .= '<div class="xform-status" data-xform-status role="status" aria-live="polite"></div>';
        $out .= $html;
        $out .= '<div class="xform-honeypot" aria-hidden="true"><label>Leave this field empty<input type="text" name="xform_website" tabindex="-1" autocomplete="off"></label></div>';
        $out .= '</form></div>';
        if ( '' !== trim( $form['css'] ) ) {
            $out .= '<style>' . $form['css'] . '</style>';
        }
        return $out;
    }
}
