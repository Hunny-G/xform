<?php
defined( 'ABSPATH' ) || exit;
final class XForm_Admin {
    public static function init(): void {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
        add_action( 'admin_post_xform_save', array( __CLASS__, 'save' ) );
        add_action( 'admin_post_xform_export', array( __CLASS__, 'export' ) );
        add_action( 'admin_post_xform_delete', array( __CLASS__, 'delete' ) );
        add_action( 'admin_post_xform_crm_settings', array( __CLASS__, 'save_crm_settings' ) );
    }
    public static function menu(): void {
        add_menu_page( 'XForm', 'XForm', 'manage_options', 'xform', array( __CLASS__, 'forms_page' ), 'dashicons-feedback', 57 );
        add_submenu_page( 'xform', 'Forms', 'Forms', 'manage_options', 'xform', array( __CLASS__, 'forms_page' ) );
        add_submenu_page( 'xform', 'Submissions', 'Submissions', 'manage_options', 'xform-submissions', array( __CLASS__, 'submissions_page' ) );
        add_submenu_page( 'xform', 'CRM Integration', 'CRM Integration', 'manage_options', 'xform-crm', array( __CLASS__, 'crm_page' ) );
    }
    public static function assets( string $hook ): void {
        if ( false !== strpos( $hook, 'xform' ) ) {
            wp_enqueue_style( 'xform-admin', XFORM_URL . 'assets/css/admin.css', array(), XFORM_VERSION );
        }
    }
    private static function header( string $title ): void {
        echo '<div class="wrap xform-admin"><div class="xform-hero"><span class="xform-kicker">WORDPRESS FORMS</span><h1>' . esc_html( $title ) . '</h1><p>Build once. Embed anywhere. Route every submission.</p></div>';
    }
    public static function forms_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $forms = XForm_Forms::all();
        $edit_id = isset( $_GET['edit'] ) ? sanitize_title( wp_unslash( $_GET['edit'] ) ) : '';
        $edit = $edit_id && isset( $forms[ $edit_id ] ) ? $forms[ $edit_id ] : array();
        self::header( 'Forms' );
        echo '<div class="xform-grid"><section class="xform-card"><h2>' . ( $edit ? 'Edit form' : 'Create a form' ) . '</h2>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'xform_save' );
        echo '<input type="hidden" name="action" value="xform_save">';
        echo '<label>Form name<input required name="name" value="' . esc_attr( $edit['name'] ?? '' ) . '"></label>';
        echo '<label>Form ID / shortcode slug<input required name="id" pattern="[A-Za-z0-9_-]+" value="' . esc_attr( $edit['id'] ?? '' ) . '" placeholder="contact"></label>';
        echo '<label>HTML markup<textarea required class="xform-code" name="html" rows="15" spellcheck="false">' . esc_textarea( $edit['html'] ?? '<div class="my-form"><label for="x-name">Name</label><input id="x-name" name="name" type="text" required><label for="x-email">Email</label><input id="x-email" name="email" type="email" required><button type="submit">Send message</button></div>' ) . '</textarea></label>';
        echo '<label>CSS (optional)<textarea class="xform-code" name="css" rows="7" spellcheck="false">' . esc_textarea( $edit['css'] ?? '' ) . '</textarea></label>';
        echo '<h3>Delivery settings</h3><label>Destination type<select name="destination">';
        $dest = $edit['destination'] ?? 'email';
        foreach ( array( 'email' => 'Email', 'webhook' => 'Webhook', 'both' => 'Both', 'none' => 'Log only' ) as $value => $label ) {
            echo '<option value="' . esc_attr( $value ) . '" ' . selected( $dest, $value, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></label>';
        echo '<label>Recipient email<input type="email" name="email" value="' . esc_attr( $edit['email'] ?? get_option( 'admin_email' ) ) . '"></label>';
        echo '<label>Webhook URL (HTTPS)<input type="url" name="webhook" value="' . esc_attr( $edit['webhook'] ?? '' ) . '" placeholder="https://hooks.example.com/..."></label>';
        echo '<label>Success message<input name="success_message" value="' . esc_attr( $edit['success_message'] ?? 'Thank you. Your submission has been received.' ) . '"></label>';
        submit_button( $edit ? 'Update form' : 'Save form', 'primary' );
        echo '</form></section><section class="xform-card"><h2>Your forms</h2>';
        if ( ! $forms ) { echo '<p>No forms yet. Create your first form to get a shortcode.</p>'; }
        foreach ( $forms as $form ) {
            $shortcode = '[xform id="' . $form['id'] . '"]';
            echo '<div class="xform-form-row"><strong>' . esc_html( $form['name'] ) . '</strong><code>' . esc_html( $shortcode ) . '</code><p><a class="button" href="' . esc_url( add_query_arg( array( 'page' => 'xform', 'edit' => $form['id'] ), admin_url( 'admin.php' ) ) ) . '">Edit</a> <button type="button" class="button" onclick="navigator.clipboard.writeText(' . esc_attr( wp_json_encode( $shortcode ) ) . ')">Copy shortcode</button> <a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=xform_delete&id=' . rawurlencode( $form['id'] ) ), 'xform_delete_' . $form['id'] ) ) . '" onclick="return confirm(\'Delete this form?\')">Delete</a></p></div>';
        }
        echo '</section></div></div>';
    }
    public static function save(): void {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); }
        check_admin_referer( 'xform_save' );
        $input = array();
        foreach ( array( 'name', 'id', 'html', 'css', 'destination', 'email', 'webhook', 'success_message' ) as $key ) {
            $input[ $key ] = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
        }
        $saved = XForm_Forms::save( $input );
        $url = admin_url( 'admin.php?page=xform' );
        if ( is_wp_error( $saved ) ) {
            $url = add_query_arg( 'xform_error', rawurlencode( $saved->get_error_message() ), $url );
        } else {
            $url = add_query_arg( 'xform_saved', '1', $url );
        }
        wp_safe_redirect( $url ); exit;
    }
    public static function delete(): void {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); }
        $id = isset( $_GET['id'] ) ? sanitize_title( wp_unslash( $_GET['id'] ) ) : '';
        check_admin_referer( 'xform_delete_' . $id );
        $forms = XForm_Forms::all();
        unset( $forms[ $id ] );
        update_option( 'xform_forms', $forms, false );
        wp_safe_redirect( admin_url( 'admin.php?page=xform' ) ); exit;
    }
    public static function crm_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $token = (string) get_option( 'xform_crm_token', '' );
        self::header( 'CRM Integration' );
        echo '<section class="xform-card"><h2>Secure connection to Divit CRM</h2><p>Generate a long random secret here, then place the same value in the local CRM <code>.env</code> file as <code>WORDPRESS_SYNC_TOKEN</code>. Keep it private.</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'xform_crm_settings' );
        echo '<input type="hidden" name="action" value="xform_crm_settings">';
        echo '<label>CRM shared secret<input type="password" name="crm_token" value="" autocomplete="new-password" placeholder="Leave blank to keep the existing secret"></label>';
        echo '<p class="description">' . ( strlen( $token ) >= 32 ? 'A secret is configured. For safety, its value is never displayed.' : 'No valid secret is configured yet.' ) . '</p>';
        submit_button( 'Save CRM secret' );
        echo '</form><p><strong>Queue endpoint:</strong> <code>' . esc_html( rest_url( 'xform/v1/crm/queue' ) ) . '</code></p><p><strong>Acknowledgement endpoint:</strong> <code>' . esc_html( rest_url( 'xform/v1/crm/ack' ) ) . '</code></p><p>Only use this integration over HTTPS. The CRM should poll this site outbound; do not expose the local CRM server to the internet.</p></section></div>';
    }
    public static function save_crm_settings(): void {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); }
        check_admin_referer( 'xform_crm_settings' );
        $token = isset( $_POST['crm_token'] ) ? trim( (string) wp_unslash( $_POST['crm_token'] ) ) : '';
        if ( '' !== $token ) {
            if ( strlen( $token ) < 32 || strlen( $token ) > 200 ) { wp_die( 'Secret must be between 32 and 200 characters.', 400 ); }
            update_option( 'xform_crm_token', $token, false );
        }
        wp_safe_redirect( admin_url( 'admin.php?page=xform-crm&saved=1' ) ); exit;
    }
    public static function submissions_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        global $wpdb;
        $table = XForm_DB::table();
        $rows = $wpdb->get_results( "SELECT id, form_id, delivery_status, crm_status, created_at FROM {$table} ORDER BY id DESC LIMIT 100", ARRAY_A );
        self::header( 'Submissions' );
        echo '<section class="xform-card"><p>Latest 100 submissions. Export downloads all records as UTF-8 CSV.</p><a class="button button-primary" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=xform_export' ), 'xform_export' ) ) . '">Export all CSV</a><table class="widefat striped"><thead><tr><th>ID</th><th>Form</th><th>Email / Webhook</th><th>CRM Sync</th><th>Created (UTC)</th></tr></thead><tbody>';
        foreach ( $rows as $row ) {
            echo '<tr><td>' . esc_html( $row['id'] ) . '</td><td>' . esc_html( $row['form_id'] ) . '</td><td>' . esc_html( $row['delivery_status'] ) . '</td><td>' . esc_html( $row['crm_status'] ) . '</td><td>' . esc_html( $row['created_at'] ) . '</td></tr>';
        }
        if ( ! $rows ) { echo '<tr><td colspan="5">No submissions recorded yet.</td></tr>'; }
        echo '</tbody></table></section></div>';
    }
    public static function export(): void {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); }
        check_admin_referer( 'xform_export' );
        global $wpdb;
        $rows = $wpdb->get_results( 'SELECT id, form_id, form_data, ip_address, delivery_status, created_at FROM ' . XForm_DB::table() . ' ORDER BY id ASC', ARRAY_A );
        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=xform-submissions-' . gmdate( 'Y-m-d' ) . '.csv' );
        $out = fopen( 'php://output', 'w' );
        // UTF-8 BOM helps Excel identify UTF-8 text.
        fwrite( $out, "\xEF\xBB\xBF" );
        fputcsv( $out, array( 'ID', 'Form ID', 'Form Data JSON', 'IP Address', 'Delivery Status', 'Created At UTC' ) );
        foreach ( $rows as $row ) {
            fputcsv( $out, array_map( static function ( $value ) {
                $value = (string) $value;
                // Mitigate spreadsheet formula injection.
                return preg_match( '/^[\s]*[=+\-@]/', $value ) ? "'" . $value : $value;
            }, array_values( $row ) ) );
        }
        fclose( $out ); exit;
    }
}
