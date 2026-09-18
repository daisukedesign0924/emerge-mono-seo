<?php
/**
 * Shared GitHub release updater for the Emerge Mono WordPress plugin family.
 *
 * @package EmergeMono
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'Emerge_Mono_GitHub_Updater' ) ) {
	final class Emerge_Mono_GitHub_Updater {
		const OWNER = 'daisukedesign0924';
		const OPTION = 'emerge_mono_update_channels';
		const CACHE_VERSION = 1;
		private static $products = array();
		private static $booted = false;

		public static function register( $args ) {
			$args = wp_parse_args( $args, array( 'name' => '', 'slug' => '', 'repo' => '', 'version' => '', 'file' => '' ) );
			if ( ! $args['slug'] || ! $args['repo'] || ! $args['version'] || ! $args['file'] ) { return; }
			$args['basename'] = plugin_basename( $args['file'] );
			self::$products[ $args['slug'] ] = $args;
			if ( self::$booted ) { return; }
			self::$booted = true;
			add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject_updates' ) );
			add_filter( 'plugins_api', array( __CLASS__, 'plugin_information' ), 20, 3 );
			add_filter( 'upgrader_pre_download', array( __CLASS__, 'verify_download' ), 10, 4 );
			add_action( 'admin_menu', array( __CLASS__, 'settings_page' ) );
			add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		}

		private static function channel( $slug ) {
			$channels = get_option( self::OPTION, array() );
			$value = is_array( $channels ) && isset( $channels[ $slug ] ) ? $channels[ $slug ] : 'stable';
			return in_array( $value, array( 'stable', 'beta', 'alpha' ), true ) ? $value : 'stable';
		}

		private static function releases( $repo ) {
			$key = 'emgh_' . self::CACHE_VERSION . '_' . substr( md5( $repo ), 0, 24 );
			$cached = get_site_transient( $key );
			if ( is_array( $cached ) ) { return $cached; }
			$url = 'https://api.github.com/repos/' . rawurlencode( self::OWNER ) . '/' . rawurlencode( $repo ) . '/releases?per_page=20';
			$response = wp_safe_remote_get( $url, array( 'timeout' => 12, 'headers' => array( 'Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28', 'User-Agent' => 'Emerge-Mono-WordPress-Updater' ) ) );
			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) { return array(); }
			$items = json_decode( wp_remote_retrieve_body( $response ), true );
			$items = is_array( $items ) ? $items : array();
			set_site_transient( $key, $items, 6 * HOUR_IN_SECONDS );
			return $items;
		}

		private static function version_from_tag( $tag ) {
			$version = ltrim( trim( (string) $tag ), "vV" );
			return preg_match( '/^\d+\.\d+\.\d+(?:-(?:alpha|beta|rc)(?:\.\d+)?)?$/i', $version ) ? strtolower( $version ) : '';
		}

		private static function allowed( $version, $channel, $prerelease ) {
			$is_alpha = false !== strpos( $version, '-alpha' );
			$is_beta  = false !== strpos( $version, '-beta' ) || false !== strpos( $version, '-rc' );
			if ( 'stable' === $channel ) { return ! $prerelease && ! $is_alpha && ! $is_beta; }
			if ( 'beta' === $channel ) { return ! $is_alpha; }
			return true;
		}

		private static function candidate( $product ) {
			$channel = self::channel( $product['slug'] ); $best = null;
			foreach ( self::releases( $product['repo'] ) as $release ) {
				if ( ! empty( $release['draft'] ) ) { continue; }
				$version = self::version_from_tag( $release['tag_name'] ?? '' );
				if ( ! $version || ! self::allowed( $version, $channel, ! empty( $release['prerelease'] ) ) || version_compare( $version, $product['version'], '<=' ) ) { continue; }
				$asset = null;
				foreach ( $release['assets'] ?? array() as $item ) {
					if ( strtolower( $item['name'] ?? '' ) === strtolower( $product['slug'] . '-' . $version . '.zip' ) ) { $asset = $item; break; }
				}
				if ( ! $asset ) {
					foreach ( $release['assets'] ?? array() as $item ) { if ( preg_match( '/\.zip$/i', $item['name'] ?? '' ) ) { $asset = $item; break; } }
				}
				$digest = (string) ( $asset['digest'] ?? '' );
				if ( ! $asset || ! preg_match( '/^sha256:([a-f0-9]{64})$/i', $digest, $m ) ) { continue; }
				$release['_em_version'] = $version; $release['_em_asset'] = $asset; $release['_em_sha256'] = strtolower( $m[1] );
				if ( ! $best || version_compare( $version, $best['_em_version'], '>' ) ) { $best = $release; }
			}
			return $best;
		}

		public static function inject_updates( $transient ) {
			if ( ! is_object( $transient ) ) { $transient = new stdClass(); }
			if ( empty( $transient->response ) || ! is_array( $transient->response ) ) { $transient->response = array(); }
			foreach ( self::$products as $product ) {
				$release = self::candidate( $product ); if ( ! $release ) { continue; }
				$package = esc_url_raw( $release['_em_asset']['browser_download_url'] );
				set_site_transient( 'emgh_hash_' . md5( $package ), $release['_em_sha256'], 12 * HOUR_IN_SECONDS );
				$transient->response[ $product['basename'] ] = (object) array(
					'id' => 'github.com/' . self::OWNER . '/' . $product['repo'], 'slug' => $product['slug'], 'plugin' => $product['basename'],
					'new_version' => $release['_em_version'], 'url' => $release['html_url'] ?? '', 'package' => $package,
					'tested' => '', 'requires_php' => '', 'icons' => array(),
				);
			}
			return $transient;
		}

		public static function plugin_information( $result, $action, $args ) {
			if ( 'plugin_information' !== $action || empty( $args->slug ) || empty( self::$products[ $args->slug ] ) ) { return $result; }
			$product = self::$products[ $args->slug ]; $release = self::candidate( $product );
			if ( ! $release ) { return $result; }
			return (object) array( 'name' => $product['name'], 'slug' => $product['slug'], 'version' => $release['_em_version'],
				'author' => '<a href="https://github.com/' . esc_attr( self::OWNER ) . '">Emerge Mono</a>', 'homepage' => $release['html_url'] ?? '',
				'download_link' => $release['_em_asset']['browser_download_url'], 'sections' => array( 'description' => esc_html( $product['name'] ), 'changelog' => wp_kses_post( nl2br( $release['body'] ?? '' ) ) ) );
		}

		public static function verify_download( $reply, $package, $upgrader, $hook_extra ) {
			$expected = get_site_transient( 'emgh_hash_' . md5( $package ) );
			if ( ! $expected ) { return $reply; }
			require_once ABSPATH . 'wp-admin/includes/file.php';
			$file = download_url( $package, 300 );
			if ( is_wp_error( $file ) ) { return $file; }
			$actual = hash_file( 'sha256', $file );
			if ( ! is_string( $actual ) || ! hash_equals( strtolower( $expected ), strtolower( $actual ) ) ) {
				wp_delete_file( $file ); return new WP_Error( 'emerge_mono_checksum', 'Emerge Mono更新ファイルのSHA-256検証に失敗しました。更新を中止しました。' );
			}
			return $file;
		}

		public static function register_settings() {
			register_setting( 'emerge-mono-updates', self::OPTION, array( 'type' => 'array', 'sanitize_callback' => array( __CLASS__, 'sanitize_channels' ), 'default' => array() ) );
		}

		public static function sanitize_channels( $value ) {
			$out = array(); foreach ( self::$products as $slug => $product ) { $channel = sanitize_key( $value[ $slug ] ?? 'stable' ); $out[ $slug ] = in_array( $channel, array( 'stable', 'beta', 'alpha' ), true ) ? $channel : 'stable'; }
			return $out;
		}

		public static function settings_page() {
			add_options_page( 'Emerge Mono更新', 'Emerge Mono更新', 'manage_options', 'emerge-mono-updates', array( __CLASS__, 'render_settings' ) );
		}

		public static function render_settings() {
			if ( ! current_user_can( 'manage_options' ) ) { return; } $channels = get_option( self::OPTION, array() );
			echo '<div class="wrap"><h1>Emerge Mono 更新チャンネル</h1><p>本番サイトは「安定版のみ」を推奨します。アルファ版とベータ版は検証サイト専用です。</p><form method="post" action="options.php">';
			settings_fields( 'emerge-mono-updates' ); echo '<table class="widefat striped"><thead><tr><th>製品</th><th>現在</th><th>受け取る更新</th></tr></thead><tbody>';
			foreach ( self::$products as $slug => $product ) { $current = self::channel( $slug ); echo '<tr><td><strong>' . esc_html( $product['name'] ) . '</strong></td><td>' . esc_html( $product['version'] ) . '</td><td><select name="' . esc_attr( self::OPTION ) . '[' . esc_attr( $slug ) . ']">'; foreach ( array( 'stable' => '安定版のみ', 'beta' => 'ベータ版まで', 'alpha' => 'アルファ版まで' ) as $value => $label ) { echo '<option value="' . esc_attr( $value ) . '" ' . selected( $current, $value, false ) . '>' . esc_html( $label ) . '</option>'; } echo '</select></td></tr>'; }
			echo '</tbody></table>'; submit_button( '更新チャンネルを保存' ); echo '</form></div>';
		}
	}
}
