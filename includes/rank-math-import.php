<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function emseo_rank_math_source_keys() {
	return array(
		'title'         => 'title',
		'description'   => 'description',
		'canonical'     => 'canonical_url',
		'social_image'  => 'facebook_image',
		'focus_keyword' => 'focus_keyword',
	);
}

function emseo_rank_math_value( $post_id, $key ) {
	if ( class_exists( '\\RankMath\\Post' ) ) {
		return \RankMath\Post::get_meta( $key, $post_id );
	}
	return get_post_meta( $post_id, 'rank_math_' . $key, true );
}

function emseo_rank_math_resolve_vars( $value, $post ) {
	if ( class_exists( '\\RankMath\\Helper' ) && method_exists( '\\RankMath\\Helper', 'replace_vars' ) ) {
		return \RankMath\Helper::replace_vars( (string) $value, $post );
	}
	return strtr( (string) $value, array(
		'%title%'       => get_the_title( $post ),
		'%sitename%'    => get_bloginfo( 'name' ),
		'%sep%'         => emseo_setting( 'separator', '｜' ),
		'%url%'         => get_permalink( $post ),
		'%excerpt%'     => emseo_clean_excerpt( $post ),
		'%excerpt_only%'=> emseo_clean_excerpt( $post ),
		'%currentyear%' => wp_date( 'Y' ),
	) );
}

function emseo_rank_math_post_ids() {
	global $wpdb;
	$keys = array_map( function( $key ) { return 'rank_math_' . $key; }, array_values( emseo_rank_math_source_keys() ) );
	$keys[] = 'rank_math_robots';
	$placeholders = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
	$sql = "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ({$placeholders})";
	return array_map( 'absint', $wpdb->get_col( $wpdb->prepare( $sql, $keys ) ) );
}

function emseo_rank_math_stats() {
	$ids = emseo_rank_math_post_ids();
	$eligible = array_filter( $ids, function( $id ) {
		$post = get_post( $id );
		return $post && 'revision' !== $post->post_type && 'attachment' !== $post->post_type;
	} );
	return array( 'detected' => count( $eligible ), 'imported' => absint( get_option( 'emseo_rank_math_imported_count', 0 ) ) );
}

function emseo_rank_math_robots_noindex( $post_id ) {
	$value = emseo_rank_math_value( $post_id, 'robots' );
	if ( is_array( $value ) ) return in_array( 'noindex', $value, true ) ? '1' : '0';
	return false !== strpos( (string) $value, 'noindex' ) ? '1' : '0';
}

function emseo_import_rank_math() {
	if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( '権限がありません。', 'emerge-mono-seo' ) );
	check_admin_referer( 'emseo_import_rank_math' );
	$overwrite = isset( $_POST['overwrite'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['overwrite'] ) );
	$count = 0;
	foreach ( emseo_rank_math_post_ids() as $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || 'revision' === $post->post_type || 'attachment' === $post->post_type ) continue;
		$backup = array();
		$changed = false;
		foreach ( emseo_rank_math_source_keys() as $target => $source ) {
			$source_value = emseo_rank_math_value( $post_id, $source );
			if ( '' === (string) $source_value ) continue;
			$target_key = '_emseo_' . $target;
			$current = get_post_meta( $post_id, $target_key, true );
			if ( ! $overwrite && '' !== (string) $current ) continue;
			$backup[ $target_key ] = $current;
			if ( in_array( $target, array( 'title', 'description', 'canonical' ), true ) ) $source_value = emseo_rank_math_resolve_vars( $source_value, $post );
			$value = in_array( $target, array( 'canonical', 'social_image' ), true ) ? esc_url_raw( $source_value ) : sanitize_text_field( $source_value );
			update_post_meta( $post_id, $target_key, $value );
			$changed = true;
		}
		$robots = emseo_rank_math_value( $post_id, 'robots' );
		if ( '' !== (string) maybe_serialize( $robots ) && ( $overwrite || '' === (string) get_post_meta( $post_id, '_emseo_noindex', true ) ) ) {
			$backup['_emseo_noindex'] = get_post_meta( $post_id, '_emseo_noindex', true );
			update_post_meta( $post_id, '_emseo_noindex', emseo_rank_math_robots_noindex( $post_id ) );
			$changed = true;
		}
		if ( $changed ) {
			if ( ! metadata_exists( 'post', $post_id, '_emseo_rank_math_backup' ) ) {
				update_post_meta( $post_id, '_emseo_rank_math_backup', array( 'created_at' => current_time( 'mysql' ), 'values' => $backup ) );
			}
			$count++;
		}
	}
	update_option( 'emseo_rank_math_imported_count', $count, false );
	wp_safe_redirect( add_query_arg( array( 'page' => 'emerge-mono-seo', 'emseo_imported' => $count ), admin_url( 'admin.php' ) ) );
	exit;
}
add_action( 'admin_post_emseo_import_rank_math', 'emseo_import_rank_math' );

function emseo_restore_rank_math_import() {
	if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( '権限がありません。', 'emerge-mono-seo' ) );
	check_admin_referer( 'emseo_restore_rank_math' );
	$ids = get_posts( array( 'post_type' => 'any', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1, 'meta_key' => '_emseo_rank_math_backup' ) );
	$count = 0;
	foreach ( $ids as $post_id ) {
		$backup = get_post_meta( $post_id, '_emseo_rank_math_backup', true );
		if ( empty( $backup['values'] ) || ! is_array( $backup['values'] ) ) continue;
		foreach ( $backup['values'] as $key => $value ) {
			if ( '' === (string) $value ) delete_post_meta( $post_id, $key ); else update_post_meta( $post_id, $key, $value );
		}
		delete_post_meta( $post_id, '_emseo_rank_math_backup' );
		$count++;
	}
	delete_option( 'emseo_rank_math_imported_count' );
	wp_safe_redirect( add_query_arg( array( 'page' => 'emerge-mono-seo', 'emseo_restored' => $count ), admin_url( 'admin.php' ) ) );
	exit;
}
add_action( 'admin_post_emseo_restore_rank_math', 'emseo_restore_rank_math_import' );

function emseo_rank_math_notice() {
	if ( isset( $_GET['emseo_imported'] ) ) echo '<div class="emseo-alert"><strong>Rank Mathデータを読み込みました</strong><p>' . esc_html( absint( $_GET['emseo_imported'] ) ) . '件を処理しました。Rank Mathの元データは変更していません。</p></div>';
	if ( isset( $_GET['emseo_restored'] ) ) echo '<div class="emseo-alert"><strong>読み込み前へ戻しました</strong><p>' . esc_html( absint( $_GET['emseo_restored'] ) ) . '件を復元しました。</p></div>';
}

function emseo_rank_math_import_panel() {
	$stats = emseo_rank_math_stats();
	?>
	<div class="emseo-panel">
		<h2>Rank Mathから引き継ぐ</h2>
		<p class="emseo-copy">ページID・URL・Rank Mathの保存データには触れず、Emerge Mono SEO用の項目へコピーします。Rank Mathが有効な間は重複保護によりEmerge Mono SEOの公開出力は停止します。</p>
		<div class="emseo-linkbox"><span>検出した投稿・ページ <strong><?php echo esc_html( $stats['detected'] ); ?></strong></span><span>前回の処理件数 <strong><?php echo esc_html( $stats['imported'] ); ?></strong></span></div>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="emseo_import_rank_math"><?php wp_nonce_field( 'emseo_import_rank_math' ); ?>
			<label class="emseo-check"><input type="checkbox" name="overwrite" value="1"><span>すでに入力済みのEmerge Mono SEO項目も上書きする</span></label>
			<?php submit_button( 'Rank Mathデータを読み込む', 'primary', 'submit', false ); ?>
		</form>
		<?php if ( $stats['imported'] ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:16px">
			<input type="hidden" name="action" value="emseo_restore_rank_math"><?php wp_nonce_field( 'emseo_restore_rank_math' ); ?>
			<?php submit_button( '読み込み前へ戻す', 'secondary', 'submit', false ); ?>
		</form>
		<?php endif; ?>
	</div>
	<div class="emseo-panel"><h2>引き継ぐ情報</h2><p class="emseo-copy">SEOタイトル、メタディスクリプション、canonical URL、Facebookシェア画像、フォーカスキーワード、noindex設定を対象にします。サイト全体の設定と構造化データは、内容を確認しながらEmerge Mono SEO側で設定します。</p></div>
	<?php
}
