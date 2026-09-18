<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action('admin_init',function(){register_setting('emseo_group','emseo_settings',array('sanitize_callback'=>'emseo_sanitize_settings'));});
add_action('admin_menu',function(){add_menu_page('Emerge Mono SEO','Emerge SEO','manage_options','emerge-mono-seo','emseo_admin_page','dashicons-chart-area',4);});
add_action('admin_enqueue_scripts',function($hook){$page=sanitize_key(wp_unslash($_GET['page']??''));$embedded=in_array($page,array('emcore-content-edit','emcore-cpt-edit','ene-post','ene-page-edit'),true);if($hook!=='toplevel_page_emerge-mono-seo'&&!$embedded)return;if($hook==='toplevel_page_emerge-mono-seo'&&function_exists('emcore_shell_start')&&defined('EMCORE_URL')){wp_enqueue_style('emcore-shell',EMCORE_URL.'admin/assets/shell.css',array(),defined('EMCORE_VERSION')?EMCORE_VERSION:EMSEO_VERSION);}wp_enqueue_media();wp_enqueue_style('emseo-admin',EMSEO_URL.'admin/assets/admin.css',array(),EMSEO_VERSION);wp_enqueue_style('emseo-pages',EMSEO_URL.'admin/assets/pages.css',array('emseo-admin'),EMSEO_VERSION);wp_enqueue_style('emseo-media',EMSEO_URL.'admin/assets/media.css',array('emseo-pages'),EMSEO_VERSION);wp_enqueue_style('emseo-integration',EMSEO_URL.'admin/assets/integration.css',array('emseo-media'),EMSEO_VERSION);wp_enqueue_script('emseo-admin',EMSEO_URL.'admin/assets/admin.js',array('jquery'),EMSEO_VERSION,true);wp_localize_script('emseo-admin','emseoAdmin',array('ajaxUrl'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('emseo_pages')));});

function emseo_post_fields_panel($post_id,$compact=false){$post_id=absint($post_id);?>
	<div class="emseo-post-fields<?php echo $compact?' is-compact':'';?>" data-post-id="<?php echo esc_attr($post_id);?>">
		<?php if(!$post_id):?><p class="emseo-empty-note">最初にページを保存すると、ページ専用のSEO診断とAI提案を利用できます。</p><?php else:$audit=emseo_page_audit($post_id);?>
		<div class="emseo-audit-head"><strong>SEO / AI READY</strong><span><?php echo esc_html($audit['score']);?><small>/100</small></span></div>
		<div class="emseo-serp"><small><?php echo esc_html(wp_parse_url(get_permalink($post_id),PHP_URL_HOST));?></small><b data-preview="title"><?php echo esc_html(emseo_meta($post_id,'title')?:get_the_title($post_id));?></b><p data-preview="description"><?php echo esc_html(emseo_meta($post_id,'description')?:'検索結果に表示する説明を入力してください。');?></p></div>
		<div class="emseo-audit-list"><?php foreach($audit['checks'] as $check):?><span class="<?php echo $check['ok']?'ok':'todo';?>"><i></i><?php echo esc_html($check['label']);?></span><?php endforeach;?></div>
		<div class="emseo-field-tabs"><button type="button" class="on" data-seo-section="search">検索</button><button type="button" data-seo-section="social">SNS</button><button type="button" data-seo-section="ai">AI・構造化</button></div>
		<div class="emseo-field-section on" data-seo-panel="search">
		<label><span>SEOタイトル</span><input type="text" data-emseo="title" value="<?php echo esc_attr(emseo_meta($post_id,'title'));?>"></label>
		<label><span>メタディスクリプション</span><textarea rows="3" data-emseo="description"><?php echo esc_textarea(emseo_meta($post_id,'description'));?></textarea></label>
		<label><span>canonical URL</span><input type="url" data-emseo="canonical" value="<?php echo esc_attr(emseo_meta($post_id,'canonical'));?>" placeholder="未入力の場合は現在のURL"></label>
		<label><span>フォーカスキーワード</span><input type="text" data-emseo="focus_keyword" value="<?php echo esc_attr(emseo_meta($post_id,'focus_keyword'));?>"></label>
		<label class="emseo-inline"><input type="checkbox" data-emseo="noindex" value="1" <?php checked(emseo_meta($post_id,'noindex'),'1');?>> 検索結果に表示しない（noindex）</label></div>
		<div class="emseo-field-section" data-seo-panel="social">
		<label><span>SNS用タイトル（未入力はSEOタイトル）</span><input type="text" data-emseo="social_title" value="<?php echo esc_attr(emseo_meta($post_id,'social_title'));?>"></label>
		<label><span>SNS用説明文</span><textarea rows="3" data-emseo="social_description"><?php echo esc_textarea(emseo_meta($post_id,'social_description'));?></textarea></label>
		<label><span>SNSシェア画像</span><span class="emseo-media-field"><input type="url" data-emseo="social_image" value="<?php echo esc_attr(emseo_meta($post_id,'social_image'));?>" placeholder="画像URL"><button type="button" class="button emseo-pick-image">メディアから選択</button></span></label>
		</div><div class="emseo-field-section" data-seo-panel="ai">
		<label><span>ページ種別</span><select data-emseo="schema_type"><?php foreach(array('auto'=>'自動判定','WebPage'=>'一般ページ','Article'=>'記事','BlogPosting'=>'ブログ記事','NewsArticle'=>'ニュース','CreativeWork'=>'作品','ProfilePage'=>'プロフィール') as $v=>$label):?><option value="<?php echo esc_attr($v);?>" <?php selected(emseo_meta($post_id,'schema_type')?:'auto',$v);?>><?php echo esc_html($label);?></option><?php endforeach;?></select></label>
		<label><span>AI向け要約</span><textarea rows="4" data-emseo="ai_summary" placeholder="このページが誰に何を伝えるページかを、事実だけで簡潔に記載"><?php echo esc_textarea(emseo_meta($post_id,'ai_summary'));?></textarea></label>
		<button type="button" class="button emseo-ai-suggest">AIに改善案を作ってもらう</button><small class="emseo-ai-note">提案は自動保存されません。内容を確認してから反映できます。</small><div class="emseo-ai-result" hidden></div></div>
		<?php endif;?>
	</div><?php
}
function emseo_save_post_fields($post_id,$data){if(!$post_id||!current_user_can('edit_post',$post_id))return false;$data=is_array($data)?$data:array();foreach(array('title','focus_keyword','social_title','schema_type') as $key)update_post_meta($post_id,'_emseo_'.$key,sanitize_text_field($data[$key]??''));foreach(array('description','social_description','ai_summary') as $key)update_post_meta($post_id,'_emseo_'.$key,sanitize_textarea_field($data[$key]??''));foreach(array('canonical','social_image') as $key)update_post_meta($post_id,'_emseo_'.$key,esc_url_raw($data[$key]??''));update_post_meta($post_id,'_emseo_noindex',!empty($data['noindex'])?'1':'0');return true;}
add_action('wp_ajax_emseo_save_page',function(){check_ajax_referer('emseo_pages','nonce');$id=absint($_POST['post_id']??0);$raw=json_decode(sanitize_textarea_field(wp_unslash($_POST['fields']??'{}')),true);if(!emseo_save_post_fields($id,$raw))wp_send_json_error(array('message'=>'保存できません。'));wp_send_json_success(array('message'=>'ページSEOを保存しました。'));});
add_action('wp_ajax_emseo_ai_suggest',function(){check_ajax_referer('emseo_pages','nonce');$id=absint($_POST['post_id']??0);if(!$id||!current_user_can('edit_post',$id))wp_send_json_error(array('message'=>'権限がありません。'));$result=emseo_ai_generate($id);if(is_wp_error($result))wp_send_json_error(array('message'=>$result->get_error_message()));wp_send_json_success(array('suggestions'=>$result));});

add_action('emcore_client_editor_after_fields',function($post){echo '<section class="emseo-embedded"><h3>検索・AI向け情報</h3>';emseo_post_fields_panel($post->ID,true);echo '</section>';});
add_action('emono_post_editor_sidebar',function($post_id){echo '<div class="ene-side-section emseo-embedded"><div class="ene-side-title">SEO / AI</div>';emseo_post_fields_panel($post_id,true);echo '</div>';});
add_action('emono_page_editor_sidebar',function($post_id){echo '<div class="ene-side-section emseo-embedded"><div class="ene-side-title">SEO / AI</div>';emseo_post_fields_panel($post_id,true);echo '</div>';});
add_action('emcore_after_save_post_fields',function($post_id,$request){$data=json_decode(wp_unslash($request['emseo']??'{}'),true);if(is_array($data)&&$data)emseo_save_post_fields($post_id,$data);},10,2);
add_action('emono_after_save_post',function($post_id,$request){$data=json_decode(wp_unslash($request['emseo']??'{}'),true);if(is_array($data)&&$data)emseo_save_post_fields($post_id,$data);},10,2);
add_action('emono_after_save_page',function($post_id,$request){$data=json_decode(wp_unslash($request['emseo']??'{}'),true);if(is_array($data)&&$data)emseo_save_post_fields($post_id,$data);},10,2);

add_filter('emerge_series_rail_items',function($items){
	$items[]=array('label'=>'SEO / AI','icon'=>'dashicons-chart-area','url'=>admin_url('admin.php?page=emerge-mono-seo'),'page'=>'emerge-mono-seo','group'=>'ANALYSIS','priority'=>300,'hook'=>'toplevel_page_emerge-mono-seo');return $items;
});

add_action('add_meta_boxes',function(){
	foreach(get_post_types(array('public'=>true),'names') as $type)if($type!=='attachment')add_meta_box('emseo-meta','Emerge SEO','emseo_meta_box',$type,'normal','default');
});
function emseo_meta_box($post){wp_nonce_field('emseo_post','emseo_nonce');?>
	<p><label><strong>SEOタイトル</strong><br><input class="widefat" type="text" name="emseo_title" maxlength="120" value="<?php echo esc_attr(emseo_meta($post->ID,'title')); ?>"></label></p>
	<p><label><strong>メタディスクリプション</strong><br><textarea class="widefat" rows="3" name="emseo_description" maxlength="320"><?php echo esc_textarea(emseo_meta($post->ID,'description')); ?></textarea></label></p>
	<p><label><strong>canonical URL</strong><br><input class="widefat" type="url" name="emseo_canonical" value="<?php echo esc_attr(emseo_meta($post->ID,'canonical')); ?>" placeholder="未入力の場合は現在のURL"></label></p>
	<p><label><strong>SNSシェア画像</strong><br><span class="emseo-media-field"><input class="widefat" type="url" name="emseo_social_image" value="<?php echo esc_attr(emseo_meta($post->ID,'social_image')); ?>" placeholder="画像URL"><button type="button" class="button emseo-pick-image">メディアから選択</button></span></label></p>
	<p><label><strong>フォーカスキーワード</strong><br><input class="widefat" type="text" name="emseo_focus_keyword" value="<?php echo esc_attr(emseo_meta($post->ID,'focus_keyword')); ?>"></label></p>
	<p><label><input type="checkbox" name="emseo_noindex" value="1" <?php checked(emseo_meta($post->ID,'noindex'),'1'); ?>> 検索結果に表示しない（noindex）</label></p>
<?php }
add_action('save_post',function($post_id){
	if(!isset($_POST['emseo_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['emseo_nonce'])),'emseo_post')||!current_user_can('edit_post',$post_id)||wp_is_post_revision($post_id))return;
	update_post_meta($post_id,'_emseo_title',sanitize_text_field(wp_unslash($_POST['emseo_title']??'')));
	update_post_meta($post_id,'_emseo_description',sanitize_textarea_field(wp_unslash($_POST['emseo_description']??'')));
	update_post_meta($post_id,'_emseo_canonical',esc_url_raw(wp_unslash($_POST['emseo_canonical']??'')));
	update_post_meta($post_id,'_emseo_social_image',esc_url_raw(wp_unslash($_POST['emseo_social_image']??'')));
	update_post_meta($post_id,'_emseo_focus_keyword',sanitize_text_field(wp_unslash($_POST['emseo_focus_keyword']??'')));
	update_post_meta($post_id,'_emseo_noindex',isset($_POST['emseo_noindex'])?'1':'0');
});

function emseo_field($key,$label,$type='text',$help=''){$s=emseo_settings();$value=$s[$key]??'';echo '<label class="emseo-field"><span>'.esc_html($label).'</span>';if($type==='textarea')echo '<textarea name="emseo_settings['.esc_attr($key).']" rows="3">'.esc_textarea($value).'</textarea>';else echo '<input type="'.esc_attr($type).'" name="emseo_settings['.esc_attr($key).']" value="'.esc_attr($value).'">';if($help)echo '<small>'.esc_html($help).'</small>';echo '</label>';}
function emseo_check($key,$label){$s=emseo_settings();echo '<label class="emseo-check"><input type="hidden" name="emseo_settings['.esc_attr($key).']" value="0"><input type="checkbox" name="emseo_settings['.esc_attr($key).']" value="1" '.checked($s[$key]??'0','1',false).'><span>'.esc_html($label).'</span></label>';}
function emseo_status_checks(){
	$checks=array(
		array('HTTPS',is_ssl(),'公開URLをHTTPSにする'),array('検索表示',get_option('blog_public'),'設定 → 表示設定で検索エンジンを許可'),
		array('サイト説明',(bool)get_bloginfo('description'),'サイトのキャッチフレーズを設定'),array('組織名',(bool)emseo_setting('organization_name'),'組織・事業者名を設定'),
		array('代表画像',(bool)(emseo_setting('default_image')||emseo_setting('logo')),'OGP用の画像を設定'),array('llms.txt',emseo_setting('llms_enabled')==='1','AI向けサイト案内を有効化'),
	);return $checks;
}

function emseo_quality_summary(){
	$settings=emseo_settings();$checks=emseo_status_checks();
	$seo_total=5;$seo_ok=0;foreach(array_slice($checks,0,5) as $check)if(!empty($check[1]))$seo_ok++;
	$ids=get_posts(array('post_type'=>'any','post_status'=>'publish','posts_per_page'=>50,'fields'=>'ids','orderby'=>'modified','order'=>'DESC'));
	$content_scores=array();foreach($ids as $id){$audit=emseo_page_audit($id);$content_scores[]=absint($audit['score']??0);}
	$content=$content_scores?(int)round(array_sum($content_scores)/count($content_scores)):0;
	$meo_fields=array('organization_name','telephone','postal_code','region','locality','street','opening_hours');$meo_ok=0;foreach($meo_fields as $key)if(!empty($settings[$key]))$meo_ok++;
	$ai_fields=array('llms_enabled','ai_summary','expertise','ai_api_key');$ai_ok=0;foreach($ai_fields as $key)if(!empty($settings[$key])&&$settings[$key]!=='0')$ai_ok++;
	return array(
		array('label'=>'SEO基盤','score'=>(int)round($seo_ok/$seo_total*100),'detail'=>'クロール・メタ情報','state'=>$seo_ok===$seo_total?'ready':'todo'),
		array('label'=>'コンテンツ品質','score'=>$content,'detail'=>$ids?count($ids).'件をローカル診断':'公開ページなし','state'=>$content>=75?'ready':'todo'),
		array('label'=>'MEO整合性','score'=>emseo_setting('local_enabled')==='1'?(int)round($meo_ok/count($meo_fields)*100):null,'detail'=>emseo_setting('local_enabled')==='1'?'サイト内設定':'MEO未使用','state'=>$meo_ok===count($meo_fields)?'ready':'todo'),
		array('label'=>'AI適合度','score'=>(int)round($ai_ok/count($ai_fields)*100),'detail'=>'理解・引用準備','state'=>$ai_ok>=3?'ready':'todo'),
	);
}

function emseo_priority_actions(){
	$actions=array();
	if(!get_option('blog_public'))$actions[]=array('critical','今すぐ直す','検索エンジンへの表示が無効です','設定 → 表示設定を確認してください。');
	if(!emseo_setting('default_description'))$actions[]=array('recommend','推奨','サイトの説明を追加','検索結果でサイトの内容を伝えます。');
	if(!emseo_setting('default_image')&&!emseo_setting('logo'))$actions[]=array('recommend','推奨','代表画像を設定','SNS共有時の見え方を整えます。');
	if(emseo_setting('local_enabled')==='1'&&(!emseo_setting('telephone')||!emseo_setting('street')))$actions[]=array('confirm','要確認','事業者情報が不足','電話番号と住所を人が確認して入力してください。');
	if(!$actions)$actions[]=array('ready','確認済み','基本設定は整っています','ページSEOの改善項目を確認してください。');
	return array_slice($actions,0,3);
}

function emseo_admin_page(){$s=emseo_settings();$core=defined('EMCORE_VERSION');$journal=defined('EMONO_VERSION');$conflict=emseo_has_competing_plugin();$core_shell=$core&&function_exists('emcore_shell_start');if($core_shell){emcore_shell_start('SEO / AI');}?>
<div class="emseo-wrap">
	<header class="emseo-head"><div><span class="emseo-kicker">EMERGE MONO / SEARCH INTELLIGENCE</span><h1>検索に伝わる状態を、<br>迷わず整える。</h1><p>従来SEO・MEOを土台に、AIが内容と根拠を理解しやすい状態まで整理します。AI検索への掲載や引用を保証するものではありません。</p></div><span class="emseo-live"><i></i>ACTIVE</span></header>
	<div class="emseo-series"><span class="<?php echo $core?'on':''; ?>">CORE <?php echo $core?esc_html(EMCORE_VERSION):'—'; ?></span><span class="<?php echo $journal?'on':''; ?>">JOURNAL <?php echo $journal?esc_html(EMONO_VERSION):'—'; ?></span><span class="on">STANDALONE</span></div>
	<?php if($conflict):?><div class="emseo-alert"><strong>重複保護が動作中</strong><p>別の主要SEOプラグインを検出したため、メタ情報と構造化データの重複出力を停止しています。llms.txtとrobots.txt設定は利用できます。</p></div><?php endif;?>
	<section class="emseo-quality"><div class="emseo-quality-head"><span class="emseo-kicker">QUALITY BREAKDOWN</span><p>総合点で隠さず、4つの品質を個別に表示します。</p></div><div class="emseo-quality-grid"><?php foreach(emseo_quality_summary() as $quality):?><article class="emseo-quality-card <?php echo esc_attr($quality['state']);?>"><small><?php echo esc_html($quality['label']);?></small><strong><?php echo $quality['score']===null?'—':esc_html($quality['score']);?></strong><span><?php echo esc_html($quality['detail']);?></span></article><?php endforeach;?></div></section>
	<section class="emseo-actions"><div class="emseo-actions-head"><span class="emseo-kicker">NEXT ACTIONS</span><h2>次にすること</h2></div><div class="emseo-action-grid"><?php foreach(emseo_priority_actions() as $action):?><article class="emseo-action-card is-<?php echo esc_attr($action[0]);?>"><i></i><div><small><?php echo esc_html($action[1]);?></small><b><?php echo esc_html($action[2]);?></b><p><?php echo esc_html($action[3]);?></p></div></article><?php endforeach;?></div></section>
	<?php emseo_rank_math_notice(); ?>
	<div class="emseo-workspace"> 
	<nav class="emseo-tabs"><button type="button" class="on" data-tab="basic">SEO</button><button type="button" data-tab="pages">ページSEO</button><button type="button" data-tab="assistant">AI提案</button><button type="button" data-tab="local">MEO</button><button type="button" data-tab="ai">LLMO / AIO</button><button type="button" data-tab="verify">連携</button><button type="button" data-tab="migration">移行</button></nav>
	<div class="emseo-workspace-main">
	<form method="post" action="options.php"><?php settings_fields('emseo_group');?>
	<div class="emseo-tab on" data-panel="basic"><div class="emseo-panel"><h2>検索表示の基本</h2><?php emseo_check('enabled','SEO出力を有効にする');emseo_field('title_format','タイトル形式','text','%title%・%site%・%sep%が使えます');emseo_field('separator','区切り文字');emseo_field('default_description','サイトの説明','textarea');emseo_field('default_image','標準シェア画像URL','url');?></div><div class="emseo-panel"><h2>組織・ブランド</h2><?php emseo_field('organization_name','組織・ブランド名');emseo_field('organization_description','組織の説明','textarea');emseo_field('logo','ロゴURL','url');emseo_field('same_as','公式プロフィールURL','textarea','1行に1URL');?></div></div>
	<div class="emseo-tab" data-panel="local"><div class="emseo-panel"><h2>ローカルビジネス</h2><?php emseo_check('local_enabled','店舗・事業所情報を構造化データへ追加');?><label class="emseo-field"><span>事業タイプ</span><select name="emseo_settings[organization_type]"><?php foreach(array('LocalBusiness','ProfessionalService','Store','Restaurant') as $v):?><option <?php selected($s['organization_type'],$v);?>><?php echo esc_html($v);?></option><?php endforeach;?></select></label><?php emseo_field('telephone','電話番号');emseo_field('email','メール','email');emseo_field('postal_code','郵便番号');emseo_field('region','都道府県');emseo_field('locality','市区町村');emseo_field('street','住所');?></div><div class="emseo-panel"><h2>位置・営業時間</h2><?php emseo_field('latitude','緯度');emseo_field('longitude','経度');emseo_field('opening_hours','営業時間','textarea','例: Mo-Fr 09:00-18:00（1行に1件）');emseo_field('map_url','GoogleマップURL','url');emseo_field('price_range','価格帯','text','例: ¥¥');?></div></div>
	<div class="emseo-tab" data-panel="ai"><div class="emseo-panel"><h2>AIによる情報発見</h2><?php emseo_check('llms_enabled','/llms.txt を公開する');emseo_field('ai_summary','AI向けサイト概要','textarea');emseo_field('expertise','専門領域','textarea','1行に1項目');?></div><div class="emseo-panel"><h2>AIクローラー</h2><label class="emseo-field"><span>robots.txtでの扱い</span><select name="emseo_settings[ai_crawlers]"><option value="allow" <?php selected($s['ai_crawlers'],'allow');?>>許可する</option><option value="block" <?php selected($s['ai_crawlers'],'block');?>>主要AIクローラーを拒否</option></select><small>検索表示とは別の設定です。目的に合わせて選択してください。</small></label><div class="emseo-linkbox"><a href="<?php echo esc_url(home_url('/llms.txt'));?>" target="_blank">llms.txtを確認 ↗</a><a href="<?php echo esc_url(home_url('/robots.txt'));?>" target="_blank">robots.txtを確認 ↗</a><a href="<?php echo esc_url(home_url('/wp-sitemap.xml'));?>" target="_blank">XML Sitemapを確認 ↗</a></div></div></div>
	<div class="emseo-tab" data-panel="assistant"><div class="emseo-panel"><h2>AI改善提案</h2><p class="emseo-copy">ページ本文とCore／Journalの入力内容を読み、検索タイトル・説明文・SNS文・AI向け要約の案を作ります。自動公開や自動保存は行いません。</p><label class="emseo-field"><span>AIサービス</span><select name="emseo_settings[ai_provider]"><option value="openai" <?php selected($s['ai_provider'],'openai');?>>OpenAI</option><option value="anthropic" <?php selected($s['ai_provider'],'anthropic');?>>Anthropic</option><option value="gemini" <?php selected($s['ai_provider'],'gemini');?>>Google Gemini</option></select></label><?php emseo_field('ai_model','モデル名');emseo_field('ai_api_key','APIキー','password','WordPressのデータベースに保存され、選択したAIサービスへの提案リクエストにだけ使用します。');?></div><div class="emseo-panel"><h2>安全な運用</h2><ol class="emseo-steps"><li>ページSEOの診断結果を確認</li><li>必要なページだけAI提案を実行</li><li>提案内容を人が確認して反映</li><li>最後に保存して公開へ反映</li></ol><p class="emseo-copy">Googleの公開情報を固定ルールとして過剰最適化するのではなく、基本要件を診断し、文章作成だけをAIで補助します。</p></div></div>
	<div class="emseo-tab" data-panel="verify"><div class="emseo-panel"><h2>検索サービス認証</h2><p class="emseo-copy">ここではサイト所有権の確認コードを設定します。アクセス解析APIへの接続とは別です。</p><?php emseo_field('verification_google','Google Search Console認証コード');?><div class="emseo-connection-state"><i class="<?php echo emseo_setting('verification_google')?'ok':'';?>"></i><span><b>Google</b><small><?php echo emseo_setting('verification_google')?'確認コード設定済み':'未設定';?> — Site Kitデータ連携は今後対応</small></span></div><?php emseo_field('verification_bing','Bing Webmaster Tools認証コード');?><div class="emseo-connection-state"><i class="<?php echo emseo_setting('verification_bing')?'ok':'';?>"></i><span><b>Microsoft Bing</b><small><?php echo emseo_setting('verification_bing')?'確認コード設定済み':'未設定';?> — API接続済みを意味しません</small></span></div></div><div class="emseo-panel"><h2>Emerge Mono連携</h2><p class="emseo-copy">Coreでは独立HTMLのheadへ直接出力します。JournalではWorks・News・Blogの投稿種別を自動判定します。どちらも入っていない通常のWordPressでも利用できます。</p></div></div>
	<footer class="emseo-save"><?php submit_button('設定を保存','primary','submit',false);?><span>変更は保存後すぐに公開側へ反映されます。</span></footer></form>
	<div class="emseo-tab emseo-pages-tab" data-panel="pages"><?php emseo_pages_panel();?></div>
	<div class="emseo-tab" data-panel="migration"><?php emseo_rank_math_import_panel();emseo_migrations_panel(); ?></div>
	</div></div>
</div><?php if($core_shell&&function_exists('emcore_shell_end')){emcore_shell_end();} }

function emseo_pages_panel(){
	$types=get_post_types(array('public'=>true),'objects');unset($types['attachment']);$posts=get_posts(array('post_type'=>array_keys($types),'post_status'=>array('publish','draft','pending','private'),'posts_per_page'=>-1,'orderby'=>'modified','order'=>'DESC'));
	echo '<div class="emseo-panel emseo-page-manager"><div class="emseo-manager-head"><div><h2>ページSEO</h2><p class="emseo-copy">Core・Journal・通常のWordPressをまとめて管理します。</p></div><input type="search" id="emseo-page-search" placeholder="タイトル・URLを検索"></div><div class="emseo-page-table">';
	foreach($posts as $p){$audit=emseo_page_audit($p->ID);$ready=$audit['score']>=75;echo '<button type="button" class="emseo-page-row" data-post="'.esc_attr($p->ID).'" data-search="'.esc_attr(strtolower($p->post_title.' '.get_permalink($p))).'"><span><b>'.esc_html($p->post_title?:'無題').'</b><small>'.esc_html(get_permalink($p)).'</small></span><i>'.esc_html($types[$p->post_type]->labels->singular_name??$p->post_type).'</i><em class="'.esc_attr($ready?'is-ready':'').'">'.esc_html($audit['score']).'/100</em></button><template id="emseo-fields-'.esc_attr($p->ID).'">';emseo_post_fields_panel($p->ID);echo '</template>';}
	echo '</div></div><div class="emseo-page-modal" id="emseo-page-modal" hidden><div class="emseo-page-modal-card" role="dialog" aria-modal="true"><span class="emseo-kicker">PAGE SEO / EMERGE MONO</span><h2 id="emseo-modal-title">ページSEOを編集</h2><div id="emseo-modal-fields"></div><p id="emseo-modal-message"></p><div class="emseo-modal-actions"><button type="button" class="button" data-action="cancel">キャンセル</button><button type="button" class="button button-primary" data-action="save">保存</button></div></div></div>';
}
