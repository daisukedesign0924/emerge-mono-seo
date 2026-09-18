<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function emseo_format_title( $ctx ) {
	$format = emseo_setting( 'title_format', '%title% %sep% %site%' );
	if ( $ctx['is_home'] ) return get_bloginfo( 'name' );
	return trim( strtr( $format, array( '%title%' => $ctx['title'], '%sep%' => emseo_setting( 'separator', '｜' ), '%site%' => get_bloginfo( 'name' ) ) ) );
}
function emseo_lines( $value ) { return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $value ) ) ) ); }

function emseo_schema_graph( $ctx ) {
	$home = home_url( '/' ); $site_id = $home . '#website'; $org_id = $home . '#organization';
	$graph = array();
	if ( $ctx['is_home'] ) {
		$local_type = emseo_setting( 'organization_type', 'LocalBusiness' );
		if ( $local_type === 'Organization' ) $local_type = 'LocalBusiness';
		$org = array( '@type' => emseo_setting( 'local_enabled' ) === '1' ? $local_type : 'Organization', '@id' => $org_id, 'name' => emseo_setting( 'organization_name' ), 'url' => $home );
		foreach ( array( 'description' => 'organization_description', 'logo' => 'logo', 'telephone' => 'telephone', 'email' => 'email', 'priceRange' => 'price_range' ) as $prop => $setting ) if ( emseo_setting( $setting ) ) $org[ $prop ] = emseo_setting( $setting );
		$same = array_values( array_filter( emseo_lines( emseo_setting( 'same_as' ) ), 'wp_http_validate_url' ) ); if ( $same ) $org['sameAs'] = $same;
		if ( emseo_setting( 'local_enabled' ) === '1' ) {
			$address = array( '@type' => 'PostalAddress' );
			foreach ( array( 'streetAddress'=>'street','addressLocality'=>'locality','addressRegion'=>'region','postalCode'=>'postal_code','addressCountry'=>'country' ) as $prop=>$key ) if ( emseo_setting( $key ) ) $address[$prop]=emseo_setting($key);
			if ( count( $address ) > 1 ) $org['address'] = $address;
			if ( emseo_setting( 'latitude' ) && emseo_setting( 'longitude' ) ) $org['geo'] = array( '@type'=>'GeoCoordinates','latitude'=>(float)emseo_setting('latitude'),'longitude'=>(float)emseo_setting('longitude') );
			$hours=emseo_lines(emseo_setting('opening_hours')); if($hours)$org['openingHours']=$hours;
			if(emseo_setting('map_url'))$org['hasMap']=emseo_setting('map_url');
		}
		$graph[]=$org;
		$graph[]=array('@type'=>'WebSite','@id'=>$site_id,'url'=>$home,'name'=>get_bloginfo('name'),'description'=>emseo_setting('default_description'),'publisher'=>array('@id'=>$org_id),'inLanguage'=>get_bloginfo('language'));
	}
	$auto_type = $ctx['is_article'] ? 'Article' : ( $ctx['content_type']==='creative_work' ? 'CreativeWork' : 'WebPage' );
	$allowed_types=array('WebPage','Article','BlogPosting','NewsArticle','CreativeWork','ProfilePage');$schema_type=in_array($ctx['schema_type'],$allowed_types,true)?$ctx['schema_type']:$auto_type;
	$page = array( '@type' => $schema_type, '@id' => $ctx['canonical'].'#primary', 'url' => $ctx['canonical'], 'name' => $ctx['title'], 'description' => $ctx['description'], 'isPartOf' => array( '@id' => $site_id ), 'inLanguage' => get_bloginfo( 'language' ) );
	if($ctx['ai_summary'])$page['abstract']=$ctx['ai_summary'];
	if($ctx['image'])$page['image']=$ctx['image'];
	if($ctx['post'] instanceof WP_Post){$page['datePublished']=get_post_time('c',true,$ctx['post']);$page['dateModified']=get_post_modified_time('c',true,$ctx['post']);$page['author']=array('@type'=>'Person','name'=>get_the_author_meta('display_name',$ctx['post']->post_author));}
	if($ctx['is_article'])$page['publisher']=array('@id'=>$org_id);
	$graph[]=$page;
	if(!$ctx['is_home'])$graph[]=array('@type'=>'BreadcrumbList','@id'=>$ctx['canonical'].'#breadcrumb','itemListElement'=>array(array('@type'=>'ListItem','position'=>1,'name'=>get_bloginfo('name'),'item'=>$home),array('@type'=>'ListItem','position'=>2,'name'=>$ctx['title'],'item'=>$ctx['canonical'])));
	return array('@context'=>'https://schema.org','@graph'=>apply_filters('emseo_schema_graph',$graph,$ctx));
}

function emseo_head_markup( $seed = array() ) {
	if ( emseo_setting( 'enabled' ) !== '1' || emseo_has_competing_plugin() ) return '';
	$ctx=emseo_context($seed); $title=emseo_format_title($ctx); $social_title=$ctx['social_title']?:$title;$social_description=$ctx['social_description']?:$ctx['description'];$robots=$ctx['noindex']?'noindex, follow':'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';
	$out="\n<!-- Emerge Mono SEO ".esc_html(EMSEO_VERSION)." -->\n";
	$out.='<meta name="description" content="'.esc_attr($ctx['description']).'">' . "\n";
	$out.='<meta name="robots" content="'.esc_attr($robots).'">' . "\n";
	$out.='<link rel="canonical" href="'.esc_url($ctx['canonical']).'">' . "\n";
	$out.='<meta property="og:type" content="'.($ctx['is_article']?'article':'website').'">' . "\n";
	$out.='<meta property="og:title" content="'.esc_attr($social_title).'"><meta property="og:description" content="'.esc_attr($social_description).'">' . "\n";
	$out.='<meta property="og:url" content="'.esc_url($ctx['canonical']).'"><meta property="og:site_name" content="'.esc_attr(get_bloginfo('name')).'">' . "\n";
	if($ctx['image'])$out.='<meta property="og:image" content="'.esc_url($ctx['image']).'">' . "\n";
	$out.='<meta name="twitter:card" content="'.($ctx['image']?'summary_large_image':'summary').'">' . "\n";
	$out.='<meta name="twitter:title" content="'.esc_attr($social_title).'"><meta name="twitter:description" content="'.esc_attr($social_description).'">' . "\n";
	if(emseo_setting('verification_google'))$out.='<meta name="google-site-verification" content="'.esc_attr(emseo_setting('verification_google')).'">' . "\n";
	if(emseo_setting('verification_bing'))$out.='<meta name="msvalidate.01" content="'.esc_attr(emseo_setting('verification_bing')).'">' . "\n";
	$out.='<script type="application/ld+json">'.wp_json_encode(emseo_schema_graph($ctx),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).'</script>' . "\n<!-- /Emerge Mono SEO -->\n";
	return $out;
}

add_action( 'wp_head', function(){
	// Output is assembled exclusively with context-specific escaping in emseo_head_markup().
	echo emseo_head_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}, 1 );
add_action( 'wp', function(){
	if(emseo_setting('enabled')==='1'&&!emseo_has_competing_plugin()){
		remove_action('wp_head','rel_canonical');
		remove_action('wp_head','wp_robots',1);
	}
},0);
add_filter( 'pre_get_document_title', function($title){if(emseo_setting('enabled')!=='1'||emseo_has_competing_plugin()||is_admin())return $title;return emseo_format_title(emseo_context());}, 20 );
add_filter( 'emerge_mono_document_head', function($head,$context){return $head.emseo_head_markup($context);}, 10, 2 );
add_filter( 'emerge_mono_document_html', function($html,$context){
	if(emseo_setting('enabled')!=='1'||emseo_has_competing_plugin())return $html;
	$title=esc_html(emseo_format_title(emseo_context($context)));
	return preg_match('/<title\b[^>]*>.*?<\/title>/is',$html)?preg_replace('/<title\b[^>]*>.*?<\/title>/is','<title>'.$title.'</title>',$html,1):str_ireplace('</head>','<title>'.$title.'</title></head>',$html);
},20,2);
