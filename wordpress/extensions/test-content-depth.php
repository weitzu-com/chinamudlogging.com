<?php
if (!defined('CML_DEPTH_ISOLATED_TEST') || !CML_DEPTH_ISOLATED_TEST) throw new RuntimeException('Isolated test only');
define('CML_PRIORITY_ISOLATED_TEST', true);
require '/cml/extensions/test-seo-priority.php';
require '/cml/extensions/cml-content-depth.php';
$assertions=0;
$verify=function($ok,$message)use(&$assertions){$assertions++;if(!$ok)throw new RuntimeException($message);};
$list='<h1>EXISTING ARCHIVE</h1><form>EXISTING FILTER</form><article>PRODUCT CARDS</article>';
$data=cml_depth_copy();
foreach($data['languages'] as $lang=>$copy){
 foreach($copy['families'] as $slug=>$points){
  $term=get_term_by('slug',$slug.'-'.$lang,'cml_family'); $set(['cml_family'=>$term->slug]);$_GET=[];
  $c=cml_depth_context();$verify(($c['kind']??'')==='family','Exact translated taxonomy identity');
  $html=apply_filters('render_block_cml/catalogue',$list);
  $verify(substr_count($html,$list)===1 && substr_count($html,'id="cml-depth-guide"')===1,'One guide and untouched listing');
  $verify(substr_count($html,'<h1>')===1 && substr_count($html,'<form>')===1,'No extra heading or form');
  foreach($points as $point)$verify(str_contains($html,esc_html($point)),'Family-specific decision survives rendering');
  $verify(str_contains($html,'hreflang="en"') && str_contains($html,'/product-category/'.$slug.'/'),'Honest full-English route');
  preg_match_all('/href="([^"]+)"/',$html,$matches);$requests=[];foreach($matches[1] as $href){parse_str(wp_parse_url(html_entity_decode($href),PHP_URL_QUERY)?:'',$q);if(isset($q['product']))$requests[]=$q['product'];}
  $verify(in_array($term->name.' — '.$copy['request'],$requests,true),'Localized request decoded exactly');
  $schema=apply_filters('wpseo_schema_webpage',['dateModified'=>'2026-11-01T00:00:00Z']);$verify($schema['dateModified']===CML_DEPTH_REVISION,'Changed-family revision');
  $entry=apply_filters('wpseo_sitemap_entry',['loc'=>get_term_link($term),'mod'=>'2026-11-01T00:00:00Z'],'term',$term);$verify($entry['mod']===CML_DEPTH_REVISION,'Category sitemap revision');
  $_GET['catalog_page']='2';$verify(cml_depth_family()===[] && apply_filters('render_block_cml/catalogue',$list)===$list,'No duplicate pagination guide');unset($_GET['catalog_page']);
  $_GET['q']=['bad'];$verify(cml_depth_family()===[],'Array search excluded');$_GET=[];
 }
}
foreach($data['profiles'] as $role=>$profile){
 $id=$make('depth-'.$role);$name=$role==='board'?'密度传感电路板':($role==='mount'?'泵冲支架':$profile['names'][0]);
 update_post_meta($id,'_cml_sku','Model 1%FS / 中文');update_post_meta($id,'_cml_specifications',[['label'=>'Name','raw'=>$name],['label'=>'Source URL','raw'=>'https://emall.epec.com/productd/141652/15487381.']]);
 $set(['post_type'=>'cml_product','p'=>$id]);$c=cml_depth_context();$verify(($c['profile']['role']??'')===$role,'Component precedence '.$role);
 $verify(cml_priority_sensor(get_post($id))===[],'No full-instrument profile on a component '.$role);
 $source=get_post_field('post_content',$id);$html=apply_filters('render_block_core/post-content',$source);
 $verify(substr_count($html,$source)===1 && get_post_field('post_content',$id)===$source,'Stored source preserved');
 $verify(substr_count($html,'id="cml-depth-guide"')===1 && !str_contains($html,'id="cml-priority-guide"'),'No stacked generic guide');
 $verify(str_contains($html,'Model 1%FS / 中文') && str_contains($html,esc_html($name)),'Verbatim source identity');
 $verify(str_contains($html,'#cml-depth-role-'.$role),'Shared checks linked instead of repeated');
 foreach($profile['missing'] as $missing)$verify(str_contains($html,esc_html($missing)),'Role-specific document gaps');
 $verify(!str_contains($html,'<script')&&!str_contains($html,'<form')&&!preg_match('/href="[^"]*\.pdf/i',$html),'No invented downloads or submission');
 preg_match_all('/href="([^"]+)"/',$html,$matches);$values=[];foreach($matches[1] as $href){$href=html_entity_decode($href);parse_str(wp_parse_url($href,PHP_URL_QUERY)?:'',$args);if(isset($args['product'])){$verify(!preg_match('/%(?![0-9a-fA-F]{2})/',$href),'Valid percent escaping');$values[]=$args['product'];}}
 $verify(in_array(get_the_title($id).' — catalogue designation: Model 1%FS / 中文',$values,true),'Exact model handoff');
 update_post_meta($id,'_cml_sku','');$verify(cml_depth_profile(get_post($id))===[],'Unknown-model item excluded');
 update_post_meta($id,'_cml_sku','Known');update_post_meta($id,'_test_lang','zh');$verify(cml_depth_profile(get_post($id))===[],'Translated technical records excluded');
 update_post_meta($id,'_test_lang','en');update_post_meta($id,'_cml_specifications',[['label'=>'Name','raw'=>$name],['label'=>'Source URL','raw'=>'https://unapproved.example/item']]);$verify(cml_depth_profile(get_post($id))===[],'Unapproved source excluded');
}
foreach($data['legacy'] as $slug=>$record){
 $id=$make($slug);update_post_meta($id,'_cml_source_id',$record['sourceId']);$set(['post_type'=>'cml_product','p'=>$id]);
 $source=get_post_field('post_content',$id);$html=apply_filters('render_block_core/post-content',$source);$verify(str_contains($html,esc_html($record['title']))&&str_contains($html,$source),'Legacy requirement route and original text');
 if($slug==='mud-logging-sensors')foreach(array_keys($data['profiles']) as $role)$verify(str_contains($html,'id="cml-depth-role-'.$role.'"'),'Shared role checklist target exists');
 update_post_meta($id,'_cml_source_id','wrong-source');$verify(cml_depth_context()===[],'Slug alone is not proof of source identity');
}
$id=$make($data['article']['slug'],'post');$set(['post_type'=>'post','p'=>$id]);$source=get_post_field('post_content',$id);$html=apply_filters('render_block_core/post-content',$source);
$verify(str_contains($html,'Follow the information chain')&&str_contains($html,$source),'Equipment-task article grounded in source workflow');
$verify(!str_contains($html,'<h1')&&!str_contains($html,'<form'),'Article heading and inquiry boundary');
$verify(apply_filters('wpseo_metadesc','old')===$data['article']['description'],'Article-specific snippet');
$verify(apply_filters('wpseo_schema_webpage',['dateModified'=>'2027-01-01T00:00:00Z'])['dateModified']==='2027-01-01T00:00:00Z','Later actual dates preserved');
$id=$make('generic-unidentified-sensor');update_post_meta($id,'_cml_sku','TSFS-H2A-1');update_post_meta($id,'_cml_specifications',[['label'=>'Name','raw'=>'传感器 / Sensor.'],['label'=>'Source URL','raw'=>'https://emall.epec.com/productd/141652/15487381']]);$set(['post_type'=>'cml_product','p'=>$id]);
$verify(cml_depth_context()===[],'No measurement inferred from suggestive SKU');
file_put_contents('/cml-depth-results/depth-results.json',wp_json_encode(['passed'=>true,'assertions'=>$assertions,'families'=>30,'profiles'=>18,'legacy'=>2,'articles'=>1,'basePriorityAssertions'=>338]));
