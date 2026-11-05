<?php
if (!defined('CML_PAGINATION_ISOLATED') || !CML_PAGINATION_ISOLATED) exit;
require_once '/wordpress/wp-load.php';
require_once '/cml/extensions/cml-pagination-seo.php';
$count=0;
$assert=function($ok,$message)use(&$count){if(!$ok)throw new RuntimeException($message);$count++;};
$query=function($args){$GLOBALS['wp_query']=new WP_Query($args);$GLOBALS['wp_the_query']=$GLOBALS['wp_query'];};
$first=['en-US'=>'https://example.test/product/','ar'=>'https://example.test/ar/product-ar/'];
foreach(['en','zh','es','ru','ar','fr','de']as$lang){
 $id=wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>'Products '.$lang,'post_name'=>'product-'.$lang,'post_content'=>'<!-- wp:cml/catalogue /-->']);
 $query(['page_id'=>$id]);$base=get_permalink($id);$body=get_post($id)->post_content;
 foreach(['2','15','26']as$page){
  $_GET=['catalog_page'=>$page,'utm_source'=>'ignored'];$expected=add_query_arg('catalog_page',(int)$page,$base);
  $assert(apply_filters('wpseo_canonical',$base)===$expected,'Each valid page has its own canonical');
  $assert(apply_filters('wpseo_opengraph_url',$base)===$expected,'Open Graph agrees');
  $schema=apply_filters('wpseo_schema_webpage',['@id'=>$base.'#webpage','url'=>$base,'breadcrumb'=>['@id'=>$base.'#breadcrumb'],'potentialAction'=>[['@type'=>'ReadAction','target'=>[$base]]]]);
  $assert($schema['@id']===$expected.'#webpage'&&$schema['url']===$expected&&$schema['potentialAction'][0]['target']===[$expected],'Graph identity and read target agree');
  $assert(apply_filters('pll_rel_hreflang_attributes',$first)===[],'No page-one language alternates on subsequent pages');
  $assert(apply_filters('wpseo_robots','index, follow, max-image-preview:large')==='index, follow, max-image-preview:large','Unfiltered pagination remains indexable');
 }
 foreach([[],['catalog_page'=>'1'],['catalog_page'=>['bad']],['catalog_page'=>'2<script>']]as$params){
  $_GET=$params;$assert(apply_filters('wpseo_canonical',$base)===$base,'Default and malformed values do not inject canonical URLs');
  $assert(apply_filters('pll_rel_hreflang_attributes',$first)===$first,'Default views retain language links');
 }
 foreach([['q'=>'pressure'],['family'=>'wireless-sensors'],['q'=>['bad']],['q'=>'pressure','catalog_page'=>'2']]as$params){
  $_GET=$params;$assert(apply_filters('wpseo_canonical',$base)===$base,'Filtered results do not become pagination canonicals');
  $assert(str_starts_with(apply_filters('wpseo_robots','index, follow, noarchive'),'noindex, follow'),'Filtered results are noindex with follow retained');
  $native=apply_filters('wp_robots',['nofollow'=>true]);
  $assert(!empty($native['nofollow'])&&!empty($native['noindex'])&&empty($native['index']),'Existing restrictive instructions survive alongside core directives');
 }
 $assert(get_post($id)->post_content===$body,'Metadata-only change does not rewrite content');
}
$_GET=['catalog_page'=>'2'];
$term=wp_insert_term('Sensors','cml_family',['slug'=>'sensor-fixture']);$query(['cml_family'=>'sensor-fixture']);$base=get_term_link($term['term_id']);
$assert(apply_filters('wpseo_canonical',$base)===add_query_arg('catalog_page',2,$base),'Taxonomy pagination self canonical');
$id=wp_insert_post(['post_type'=>'cml_product','post_status'=>'publish','post_title'=>'Product','post_content'=>'SOURCE']);$query(['post_type'=>'cml_product','p'=>$id]);
$assert(apply_filters('wpseo_canonical','unchanged')==='unchanged','Product detail untouched');
$id=wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>'Contact','post_content'=>'<!-- wp:cml/inquiry /-->']);$query(['page_id'=>$id]);$_GET=['q'=>'anything','catalog_page'=>'2'];
$assert(apply_filters('wpseo_canonical','unchanged')==='unchanged'&&apply_filters('wpseo_robots','index, follow')==='index, follow','Contact page untouched');
file_put_contents('/cml-pagination-results/results.json',wp_json_encode(['passed'=>true,'assertions'=>$count,'languages'=>7],JSON_PRETTY_PRINT));
