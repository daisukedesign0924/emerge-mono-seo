<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function emseo_defaults() {
	return array(
		'enabled' => '1', 'separator' => '｜', 'title_format' => '%title% %sep% %site%',
		'default_description' => get_bloginfo( 'description' ), 'default_image' => '',
		'organization_type' => 'Organization', 'organization_name' => get_bloginfo( 'name' ),
		'organization_description' => get_bloginfo( 'description' ), 'logo' => '', 'same_as' => '',
		'local_enabled' => '0', 'telephone' => '', 'email' => get_option( 'admin_email' ),
		'street' => '', 'locality' => '', 'region' => '', 'postal_code' => '', 'country' => 'JP',
		'latitude' => '', 'longitude' => '', 'opening_hours' => '', 'map_url' => '', 'price_range' => '',
		'llms_enabled' => '1', 'ai_summary' => get_bloginfo( 'description' ), 'expertise' => '',
		'ai_crawlers' => 'allow', 'verification_google' => '', 'verification_bing' => '',
		'ai_provider' => 'openai', 'ai_api_key' => '', 'ai_model' => 'gpt-5-mini',
	);
}
function emseo_settings() { return wp_parse_args( get_option( 'emseo_settings', array() ), emseo_defaults() ); }
function emseo_setting( $key, $fallback = '' ) { $s = emseo_settings(); return isset( $s[ $key ] ) ? $s[ $key ] : $fallback; }

function emseo_sanitize_settings( $input ) {
	$defaults = emseo_defaults(); $out = array();
	foreach ( $defaults as $key => $default ) {
		$value = isset( $input[ $key ] ) ? wp_unslash( $input[ $key ] ) : '';
		if ( in_array( $key, array( 'enabled','local_enabled','llms_enabled' ), true ) ) $out[ $key ] = $value === '1' ? '1' : '0';
		elseif ( in_array( $key, array( 'default_image','logo','map_url' ), true ) ) $out[ $key ] = esc_url_raw( $value );
		elseif ( $key === 'ai_api_key' ) $out[ $key ] = sanitize_text_field( $value );
		elseif ( in_array( $key, array( 'default_description','organization_description','ai_summary' ), true ) ) $out[ $key ] = sanitize_textarea_field( $value );
		elseif ( in_array( $key, array( 'same_as','opening_hours','expertise' ), true ) ) $out[ $key ] = sanitize_textarea_field( $value );
		else $out[ $key ] = sanitize_text_field( $value );
	}
	$types = array( 'Organization','LocalBusiness','ProfessionalService','Store','Restaurant' );
	if ( ! in_array( $out['organization_type'], $types, true ) ) $out['organization_type'] = 'Organization';
	$out['ai_crawlers'] = in_array( $out['ai_crawlers'], array( 'allow','block' ), true ) ? $out['ai_crawlers'] : 'allow';
	$out['ai_provider'] = in_array( $out['ai_provider'], array( 'openai','anthropic','gemini' ), true ) ? $out['ai_provider'] : 'openai';
	return $out;
}

function emseo_has_competing_plugin() {
	return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' );
}
