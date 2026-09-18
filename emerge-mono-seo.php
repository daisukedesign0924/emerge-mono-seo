<?php
/**
 * Plugin Name:       Emerge Mono SEO
 * Plugin URI:        https://github.com/daisukedesign0924/emerge-mono-seo
 * Description:       SEO・MEO・LLMO・AIOを一体管理し、Emerge Monoシリーズと深く連携する拡張プラグイン。
 * Version:           0.5.4
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            DAISUKE DESIGN
 * License:           GPLv2 or later
 * Text Domain:       emerge-mono-seo
 * Update URI:        https://github.com/daisukedesign0924/emerge-mono-seo
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'EMSEO_VERSION', '0.5.4' );
define( 'EMSEO_FILE', __FILE__ );
define( 'EMSEO_PATH', plugin_dir_path( __FILE__ ) );
define( 'EMSEO_URL', plugin_dir_url( __FILE__ ) );

require_once EMSEO_PATH . 'includes/class-emerge-mono-github-updater.php';
Emerge_Mono_GitHub_Updater::register( array(
	'name' => 'Emerge Mono SEO', 'slug' => 'emerge-mono-seo', 'repo' => 'emerge-mono-seo',
	'version' => EMSEO_VERSION, 'file' => EMSEO_FILE,
) );

require_once EMSEO_PATH . 'includes/settings.php';
require_once EMSEO_PATH . 'includes/context.php';
require_once EMSEO_PATH . 'includes/audit.php';
require_once EMSEO_PATH . 'includes/output.php';
require_once EMSEO_PATH . 'includes/ai-discovery.php';
if ( is_admin() ) {
	require_once EMSEO_PATH . 'includes/rank-math-import.php';
	require_once EMSEO_PATH . 'includes/migrations.php';
	require_once EMSEO_PATH . 'admin/admin.php';
}

register_activation_hook( __FILE__, 'emseo_activate' );
function emseo_activate() {
	if ( ! get_option( 'emseo_settings' ) ) update_option( 'emseo_settings', emseo_defaults(), false );
	emseo_register_rewrites();
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

add_action( 'plugins_loaded', function () {
	do_action( 'emerge_mono_seo_loaded', EMSEO_VERSION );
} );
