<?php
/**
 * Plugin Name: XForm
 * Description: Lightweight custom HTML/CSS forms with shortcodes, queued delivery and CSV exports.
 * Version: 1.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: XForm
 * Text Domain: xform
 */
defined( 'ABSPATH' ) || exit;
define( 'XFORM_VERSION', '1.1.0' );
define( 'XFORM_FILE', __FILE__ );
define( 'XFORM_PATH', plugin_dir_path( __FILE__ ) );
define( 'XFORM_URL', plugin_dir_url( __FILE__ ) );

require_once XFORM_PATH . 'includes/class-xform-db.php';
require_once XFORM_PATH . 'includes/class-xform-forms.php';
require_once XFORM_PATH . 'includes/class-xform-submissions.php';
require_once XFORM_PATH . 'includes/class-xform-shortcodes.php';
require_once XFORM_PATH . 'admin/class-xform-admin.php';

register_activation_hook( __FILE__, array( 'XForm_DB', 'install' ) );
add_action( 'plugins_loaded', static function () {
    XForm_DB::maybe_upgrade();
    XForm_Submissions::init();
    XForm_Shortcodes::init();
    if ( is_admin() ) {
        XForm_Admin::init();
    }
} );
