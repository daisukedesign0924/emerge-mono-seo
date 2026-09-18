<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function emseo_register_rewrites(){add_rewrite_rule('^llms\.txt$','index.php?emseo_llms=1','top');}
add_action('init','emseo_register_rewrites');
add_filter('query_vars',function($vars){$vars[]='emseo_llms';return $vars;});
add_action('template_redirect',function(){
	$request_path=isset($_SERVER['REQUEST_URI'])?wp_parse_url(esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])),PHP_URL_PATH):'';
	$home_path=wp_parse_url(home_url('/'),PHP_URL_PATH);$llms_path=trailingslashit($home_path?:'/').'llms.txt';
	if(!get_query_var('emseo_llms')&&untrailingslashit($request_path)!==untrailingslashit($llms_path))return;
	if(emseo_setting('llms_enabled')!=='1'){status_header(404);exit;}
	header('Content-Type: text/plain; charset=utf-8');header('X-Robots-Tag: index, follow');
	$name=get_bloginfo('name');echo esc_html( '# '.$name."\n\n> ".emseo_setting('ai_summary',get_bloginfo('description'))."\n\n" );
	echo esc_html( 'Canonical site: '.home_url('/')."\nLanguage: ".get_bloginfo('language')."\n" );
	$expertise=emseo_lines(emseo_setting('expertise'));if($expertise)echo esc_html( "\n## Expertise\n\n- ".implode("\n- ",$expertise)."\n" );
	$types=get_post_types(array('public'=>true),'names');unset($types['attachment']);
	$posts=get_posts(array('post_type'=>array_values($types),'post_status'=>'publish','posts_per_page'=>50,'orderby'=>'modified','order'=>'DESC'));
	if($posts){echo "\n## Main content\n\n";foreach($posts as $p)echo esc_html( '- ['.wp_strip_all_tags(get_the_title($p)).']('.get_permalink($p).'): '.emseo_clean_excerpt($p)."\n" );}
	echo esc_html( "\n## Discovery\n\n- [XML Sitemap](".home_url('/wp-sitemap.xml').")\n" );
	exit;
});
add_filter('robots_txt',function($output,$public){
	if(!$public)return $output;
	if(strpos($output,'Sitemap:')===false)$output.="\nSitemap: ".home_url('/wp-sitemap.xml')."\n";
	if(emseo_setting('ai_crawlers')==='block')foreach(array('GPTBot','ClaudeBot','Google-Extended','CCBot')as$bot)$output.="\nUser-agent: {$bot}\nDisallow: /\n";
	return $output;
},20,2);
