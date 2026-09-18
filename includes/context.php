<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function emseo_clean_excerpt( $post ) {
	if ( ! $post instanceof WP_Post ) return '';
	$text = $post->post_excerpt ? $post->post_excerpt : $post->post_content;
	$text = wp_strip_all_tags( strip_shortcodes( $text ) );
	return wp_trim_words( preg_replace( '/\s+/u', ' ', $text ), 38, '' );
}
function emseo_meta( $post_id, $key ) { return $post_id ? (string) get_post_meta( $post_id, '_emseo_' . $key, true ) : ''; }

function emseo_context( $seed = array() ) {
	$post = get_queried_object(); $post = $post instanceof WP_Post ? $post : null;
	$id = ! empty( $seed['post_id'] ) ? absint( $seed['post_id'] ) : ( $post ? $post->ID : 0 );
	if ( $id && ( ! $post || $post->ID !== $id ) ) $post = get_post( $id );
	$is_home = is_front_page() || is_home();
	$title = $id ? ( emseo_meta( $id, 'title' ) ?: get_the_title( $id ) ) : get_bloginfo( 'name' );
	$desc = $id ? ( emseo_meta( $id, 'description' ) ?: emseo_clean_excerpt( $post ) ) : emseo_setting( 'default_description' );
	if ( ! $desc ) $desc = emseo_setting( 'default_description' );
	$canonical = ! empty( $seed['canonical'] ) ? $seed['canonical'] : ( $id ? ( emseo_meta( $id, 'canonical' ) ?: get_permalink( $id ) ) : home_url( '/' ) );
	$image = $id ? emseo_meta( $id, 'social_image' ) : '';
	$social_title = $id ? emseo_meta( $id, 'social_title' ) : '';
	$social_description = $id ? emseo_meta( $id, 'social_description' ) : '';
	if ( ! $image && $id ) $image = get_the_post_thumbnail_url( $id, 'full' );
	if ( ! $image ) $image = emseo_setting( 'default_image' );
	$type = $id ? get_post_type( $id ) : 'website';
	if ( ! empty( $seed['content_type'] ) ) $type = sanitize_key( $seed['content_type'] );
	$article_types = array( 'post','en_blog','en_news','article' );
	return apply_filters( 'emseo_context', array(
		'post' => $post, 'post_id' => $id, 'title' => $title, 'description' => $desc,
		'canonical' => $canonical, 'image' => $image, 'content_type' => $type,
		'social_title' => $social_title, 'social_description' => $social_description,
		'schema_type' => $id ? emseo_meta($id,'schema_type') : '', 'ai_summary' => $id ? emseo_meta($id,'ai_summary') : '',
		'is_home' => $is_home, 'is_article' => in_array( $type, $article_types, true ),
		'noindex' => $id && emseo_meta( $id, 'noindex' ) === '1',
		'product' => isset( $seed['product'] ) ? $seed['product'] : ( defined( 'EMONO_VERSION' ) ? 'journal' : ( defined( 'EMCORE_VERSION' ) ? 'core' : 'standalone' ) ),
	), $seed );
}

add_filter( 'emerge_mono_document_context', function ( $context ) {
	if ( isset( $context['content_type'] ) ) {
		if ( in_array( $context['content_type'], array( 'work','en_work' ), true ) ) $context['content_type'] = 'creative_work';
		if ( in_array( $context['content_type'], array( 'blog','news' ), true ) ) $context['content_type'] = 'article';
	}
	return $context;
}, 20 );
