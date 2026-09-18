<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function emseo_page_source_text( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post ) return '';
	$parts = array( $post->post_title, $post->post_excerpt, $post->post_content );
	foreach ( get_post_meta( $post_id ) as $key => $values ) {
		if ( strpos( $key, '_emcore_' ) !== 0 || preg_match( '/(_id|_font)$/', $key ) ) continue;
		$value = maybe_unserialize( $values[0] ?? '' );
		if ( is_scalar( $value ) && ! wp_http_validate_url( (string) $value ) ) $parts[] = (string) $value;
	}
	return trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( strip_shortcodes( implode( "\n", $parts ) ) ) ) );
}

function emseo_page_audit( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post ) return array( 'score' => 0, 'checks' => array() );
	$title = emseo_meta( $post_id, 'title' );
	$desc = emseo_meta( $post_id, 'description' );
	$text = emseo_page_source_text( $post_id );
	$checks = array(
		array( 'key'=>'title', 'label'=>'検索タイトル', 'ok'=>$title !== '', 'level'=>'error', 'message'=>$title === '' ? 'ページ専用の検索タイトルが未設定です。' : 'ページ専用タイトルを設定済みです。' ),
		array( 'key'=>'title_length', 'label'=>'タイトルの長さ', 'ok'=>$title === '' || ( mb_strlen( $title ) >= 12 && mb_strlen( $title ) <= 60 ), 'level'=>'warning', 'message'=>'12〜60文字を目安に、内容が伝わる固有のタイトルにします。' ),
		array( 'key'=>'description', 'label'=>'検索結果の説明', 'ok'=>$desc !== '', 'level'=>'error', 'message'=>$desc === '' ? 'メタディスクリプションが未設定です。' : '説明文を設定済みです。' ),
		array( 'key'=>'description_length', 'label'=>'説明文の長さ', 'ok'=>$desc === '' || ( mb_strlen( $desc ) >= 50 && mb_strlen( $desc ) <= 160 ), 'level'=>'warning', 'message'=>'50〜160文字を目安に、ページを読む理由を簡潔に伝えます。' ),
		array( 'key'=>'content', 'label'=>'検索可能な本文', 'ok'=>mb_strlen( $text ) >= 120, 'level'=>'warning', 'message'=>'画像だけでなく、検索エンジンとAIが読める説明文を用意します。' ),
		array( 'key'=>'image', 'label'=>'共有画像', 'ok'=>(bool)( emseo_meta( $post_id, 'social_image' ) || get_post_thumbnail_id( $post_id ) ), 'level'=>'warning', 'message'=>'SNS共有用画像またはアイキャッチを設定します。' ),
		array( 'key'=>'canonical', 'label'=>'正規URL', 'ok'=>(bool) get_permalink( $post_id ), 'level'=>'error', 'message'=>'公開URLを正規URLとして使用できます。' ),
		array( 'key'=>'schema', 'label'=>'ページ種別', 'ok'=>emseo_meta( $post_id, 'schema_type' ) !== '', 'level'=>'notice', 'message'=>'内容に合う構造化データの種類を確認してください。' ),
		array( 'key'=>'ai_summary', 'label'=>'AI向け要約', 'ok'=>emseo_meta( $post_id, 'ai_summary' ) !== '', 'level'=>'notice', 'message'=>'ページの主題を事実に基づく短い文章で明示します。' ),
	);
	$ok = count( array_filter( $checks, function( $check ){ return $check['ok']; } ) );
	return array( 'score'=>(int) round( $ok / count( $checks ) * 100 ), 'checks'=>$checks, 'text_length'=>mb_strlen( $text ) );
}

function emseo_ai_generate( $post_id ) {
	$provider = emseo_setting( 'ai_provider', 'openai' );
	$key = emseo_setting( 'ai_api_key' );
	if ( ! $key ) return new WP_Error( 'missing_key', '先にAI連携でAPIキーを設定してください。' );
	$post = get_post( $post_id );
	$text = mb_substr( emseo_page_source_text( $post_id ), 0, 12000 );
	$prompt = "次のWebページについて、事実を追加・創作せずSEOとAI検索向けの提案をJSONだけで返してください。キーは title, description, social_title, social_description, ai_summary。titleは60文字以内、descriptionは160文字以内。\nページ名: {$post->post_title}\nURL: ".get_permalink($post_id)."\n本文: {$text}";
	if ( $provider === 'anthropic' ) {
		$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', array( 'timeout'=>45, 'headers'=>array('content-type'=>'application/json','x-api-key'=>$key,'anthropic-version'=>'2023-06-01'), 'body'=>wp_json_encode(array('model'=>emseo_setting('ai_model','claude-sonnet-4-5'),'max_tokens'=>900,'messages'=>array(array('role'=>'user','content'=>$prompt)))) ) );
		$body = json_decode( wp_remote_retrieve_body( $response ), true ); $content = $body['content'][0]['text'] ?? '';
	} elseif ( $provider === 'gemini' ) {
		$model = rawurlencode( emseo_setting('ai_model','gemini-2.5-flash') );
		$response = wp_remote_post( 'https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent?key='.rawurlencode($key), array('timeout'=>45,'headers'=>array('content-type'=>'application/json'),'body'=>wp_json_encode(array('contents'=>array(array('parts'=>array(array('text'=>$prompt)))),'generationConfig'=>array('responseMimeType'=>'application/json'))) ) );
		$body = json_decode( wp_remote_retrieve_body( $response ), true ); $content = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
	} else {
		$response = wp_remote_post( 'https://api.openai.com/v1/responses', array('timeout'=>45,'headers'=>array('content-type'=>'application/json','authorization'=>'Bearer '.$key),'body'=>wp_json_encode(array('model'=>emseo_setting('ai_model','gpt-5-mini'),'input'=>$prompt)) ) );
		$body = json_decode( wp_remote_retrieve_body( $response ), true ); $content = $body['output'][0]['content'][0]['text'] ?? '';
	}
	if ( is_wp_error( $response ) ) return $response;
	if ( wp_remote_retrieve_response_code( $response ) >= 300 ) return new WP_Error( 'api_error', 'AI APIからエラーが返されました。キー・モデル・利用状況を確認してください。' );
	$content = preg_replace( '/^```(?:json)?|```$/m', '', trim( $content ) );
	$data = json_decode( trim( $content ), true );
	if ( ! is_array( $data ) ) return new WP_Error( 'invalid_response', 'AIの回答を読み取れませんでした。もう一度実行してください。' );
	return array_intersect_key( array_map( 'sanitize_textarea_field', $data ), array_flip(array('title','description','social_title','social_description','ai_summary')) );
}
