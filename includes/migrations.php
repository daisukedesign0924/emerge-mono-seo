<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function emseo_migration_providers() {
	return array(
		'yoast' => array('label'=>'Yoast SEO','meta'=>array('title'=>'_yoast_wpseo_title','description'=>'_yoast_wpseo_metadesc','canonical'=>'_yoast_wpseo_canonical','social_image'=>'_yoast_wpseo_opengraph-image','focus_keyword'=>'_yoast_wpseo_focuskw'),'noindex'=>'_yoast_wpseo_meta-robots-noindex'),
		'seopress' => array('label'=>'SEOPress','meta'=>array('title'=>'_seopress_titles_title','description'=>'_seopress_titles_desc','canonical'=>'_seopress_robots_canonical','social_image'=>'_seopress_social_fb_img','focus_keyword'=>'_seopress_analysis_target_kw'),'noindex'=>'_seopress_robots_index'),
		'tsf' => array('label'=>'The SEO Framework','meta'=>array('title'=>'_genesis_title','description'=>'_genesis_description','canonical'=>'_genesis_canonical_uri','social_image'=>'_social_image_url'),'noindex'=>'_genesis_noindex'),
		'aioseo' => array('label'=>'All in One SEO','table'=>'aioseo_posts'),
	);
}
function emseo_provider_ids($slug,$provider){
	global $wpdb;
	if(!empty($provider['table'])){
		$table=$wpdb->prefix.$provider['table'];
		if($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table))!==$table)return array();
		return array_map('absint',$wpdb->get_col("SELECT DISTINCT post_id FROM {$table} WHERE post_id > 0"));
	}
	$keys=array_values($provider['meta']);$keys[]=$provider['noindex'];
	$ph=implode(',',array_fill(0,count($keys),'%s'));
	return array_map('absint',$wpdb->get_col($wpdb->prepare("SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ({$ph})",$keys)));
}
function emseo_provider_values($slug,$provider,$post_id){
	global $wpdb;$out=array();
	if(!empty($provider['table'])){
		$table=$wpdb->prefix.$provider['table'];$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE post_id=%d LIMIT 1",$post_id),ARRAY_A);
		if(!$row)return array();
		$map=array('title'=>'title','description'=>'description','canonical'=>'canonical_url','social_image'=>'og_image_url','focus_keyword'=>'keyphrases');
		foreach($map as $target=>$source)if(!empty($row[$source]))$out[$target]=$row[$source];
		$out['noindex']=isset($row['robots_noindex'])&&in_array((string)$row['robots_noindex'],array('1','true'),true)?'1':'0';
		return $out;
	}
	foreach($provider['meta'] as $target=>$source){$v=get_post_meta($post_id,$source,true);if(''!==(string)$v)$out[$target]=$v;}
	$n=get_post_meta($post_id,$provider['noindex'],true);
	if(''!==(string)$n)$out['noindex']=in_array(strtolower((string)$n),array('1','true','yes','noindex'),true)?'1':'0';
	return $out;
}
function emseo_migration_stats($slug,$provider){return array('detected'=>count(emseo_provider_ids($slug,$provider)),'imported'=>absint(get_option('emseo_'.$slug.'_imported_count',0)));}
function emseo_import_provider(){
	if(!current_user_can('manage_options'))wp_die('権限がありません。');
	$slug=sanitize_key(wp_unslash($_POST['provider']??''));$all=emseo_migration_providers();if(!isset($all[$slug]))wp_die('移行元が不正です。');
	check_admin_referer('emseo_import_'.$slug);$overwrite=!empty($_POST['overwrite']);$count=0;
	foreach(emseo_provider_ids($slug,$all[$slug]) as $post_id){$post=get_post($post_id);if(!$post||in_array($post->post_type,array('revision','attachment'),true))continue;$backup=array();$changed=false;
		foreach(emseo_provider_values($slug,$all[$slug],$post_id) as $target=>$value){$key='_emseo_'.$target;$current=get_post_meta($post_id,$key,true);if(!$overwrite&&''!==(string)$current)continue;$backup[$key]=array('exists'=>metadata_exists('post',$post_id,$key),'value'=>$current);if(in_array($target,array('canonical','social_image'),true))$value=esc_url_raw($value);elseif($target==='noindex')$value=$value==='1'?'1':'0';else$value=sanitize_text_field(is_array($value)?implode(', ',$value):$value);update_post_meta($post_id,$key,$value);$changed=true;}
		if($changed){$bk='_emseo_migration_backup_'.$slug;if(!metadata_exists('post',$post_id,$bk))update_post_meta($post_id,$bk,array('created_at'=>current_time('mysql'),'values'=>$backup));$count++;}
	}
	update_option('emseo_'.$slug.'_imported_count',$count,false);wp_safe_redirect(add_query_arg(array('page'=>'emerge-mono-seo','tab'=>'migration','emseo_provider'=>$slug,'emseo_imported'=>$count),admin_url('admin.php')));exit;
}
add_action('admin_post_emseo_import_provider','emseo_import_provider');
function emseo_restore_provider(){
	if(!current_user_can('manage_options'))wp_die('権限がありません。');$slug=sanitize_key(wp_unslash($_POST['provider']??''));$all=emseo_migration_providers();if(!isset($all[$slug]))wp_die('移行元が不正です。');check_admin_referer('emseo_restore_'.$slug);$bk='_emseo_migration_backup_'.$slug;
	$ids=get_posts(array('post_type'=>'any','post_status'=>'any','fields'=>'ids','posts_per_page'=>-1,'meta_key'=>$bk));$count=0;foreach($ids as $id){$backup=get_post_meta($id,$bk,true);foreach((array)($backup['values']??array()) as $key=>$old){if(empty($old['exists']))delete_post_meta($id,$key);else update_post_meta($id,$key,$old['value']);}delete_post_meta($id,$bk);$count++;}delete_option('emseo_'.$slug.'_imported_count');wp_safe_redirect(add_query_arg(array('page'=>'emerge-mono-seo','tab'=>'migration','emseo_provider'=>$slug,'emseo_restored'=>$count),admin_url('admin.php')));exit;
}
add_action('admin_post_emseo_restore_provider','emseo_restore_provider');
function emseo_migrations_panel(){foreach(emseo_migration_providers() as $slug=>$provider){$s=emseo_migration_stats($slug,$provider);echo '<div class="emseo-panel"><h2>'.esc_html($provider['label']).'から引き継ぐ</h2><p class="emseo-copy">元データを変更せず、Emerge Mono SEOへコピーします。停止・削除済みでも保存データが残っていれば検出します。</p><div class="emseo-linkbox"><span>検出した投稿・ページ <strong>'.esc_html($s['detected']).'</strong></span><span>前回の処理件数 <strong>'.esc_html($s['imported']).'</strong></span></div><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="emseo_import_provider"><input type="hidden" name="provider" value="'.esc_attr($slug).'">';wp_nonce_field('emseo_import_'.$slug);echo '<label class="emseo-check"><input type="checkbox" name="overwrite" value="1"><span>入力済み項目も上書きする</span></label><button class="button button-primary"'.(!$s['detected']?' disabled':'').'>安全に読み込む</button></form>';
		if($s['imported']){echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="emseo-restore"><input type="hidden" name="action" value="emseo_restore_provider"><input type="hidden" name="provider" value="'.esc_attr($slug).'">';wp_nonce_field('emseo_restore_'.$slug);echo '<button class="button">読み込み前へ戻す</button></form>';}echo '</div>';}}
