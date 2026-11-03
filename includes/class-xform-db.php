<?php
defined( 'ABSPATH' ) || exit;
final class XForm_DB {
    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'xform_submissions';
    }
    public static function install(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table();
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            form_id varchar(120) NOT NULL,
            form_data longtext NOT NULL,
            ip_address varchar(45) NOT NULL DEFAULT '',
            delivery_status varchar(20) NOT NULL DEFAULT 'queued',
            crm_status varchar(20) NOT NULL DEFAULT 'pending',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY form_id (form_id),
            KEY created_at (created_at),
            KEY delivery_status (delivery_status),
            KEY crm_status (crm_status)
        ) {$charset};";
        dbDelta( $sql );
        update_option( 'xform_db_version', XFORM_VERSION );
    }
    public static function maybe_upgrade(): void {
        if ( get_option( 'xform_db_version' ) !== XFORM_VERSION ) {
            self::install();
        }
    }
}
