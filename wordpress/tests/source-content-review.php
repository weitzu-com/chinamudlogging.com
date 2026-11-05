<?php
if(!defined('CML_SOURCE_REVIEW_ISOLATED')||!CML_SOURCE_REVIEW_ISOLATED)exit;
require_once '/wordpress/wp-load.php';
if(!defined('WP_CLI'))define('WP_CLI',true);
if(!function_exists('pll_get_post_language')){function pll_get_post_language($id){return get_post_meta($id,'_test_language',true)?:'en';}}
if(!function_exists('pll_get_post_translations')){function pll_get_post_translations($id){return ['en'=>(int)$id];}}
function fixture_sort($x){if(!is_array($x))return$x;if(!array_is_list($x))ksort($x);foreach($x as$k=>$v)$x[$k]=fixture_sort($v);return$x;}
function fixture_hash($x){return hash('sha256',is_string($x)?$x:wp_json_encode(fixture_sort($x),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));}
$dir='/cml-review-tests';$manifest=$dir.'/fixture.json';$backup=$dir.'/before.json';$phase=CML_SOURCE_REVIEW_PHASE;
$assert=function($ok,$label){if(!$ok)throw new RuntimeException($label);};
if($phase==='setup'){
 $data=json_decode(file_get_contents('/cml/corrections/source-content-20261105/reviewed-manifest.json'),true);$data['site']=home_url();$snapshots=[];
 $attachment=wp_insert_post(['post_type'=>'attachment','post_status'=>'inherit','post_title'=>'Existing image','post_mime_type'=>'image/png']);
 $term=wp_insert_term('Fixture family','cml_family');
 foreach($data['records']as&$record){
  $id=wp_insert_post(wp_slash(['post_type'=>$record['type'],'post_status'=>'publish','post_title'=>'Before '.$record['id'],'post_name'=>$record['slug'],'post_content'=>'<!-- wp:paragraph --><p>Original source 3.7V 400r; operator\'s note and 来源.</p><!-- /wp:paragraph -->','post_excerpt'=>'Original excerpt']));
  update_post_meta($id,'_test_language','en');update_post_meta($id,'_thumbnail_id',$attachment);
  if($record['type']==='cml_product')wp_set_object_terms($id,[$term['term_id']],'cml_family');
  $meta=['_cml_sku'=>'原型号\\source','_cml_specifications'=>[['label'=>'Original','raw'=>'中文\\3.7V','value'=>'','unit'=>'']], '_cml_description'=>'Original source summary','_yoast_wpseo_title'=>'','_yoast_wpseo_metadesc'=>'Old SEO','_cml_source_url'=>'/old-source/'];
  foreach($meta as$key=>$value)update_post_meta($id,$key,wp_slash($value));
  $p=get_post($id);$fields=['title'=>$p->post_title,'content'=>$p->post_content,'excerpt'=>$p->post_excerpt]+$meta;
  $record['id']=$id;$record['url']=get_permalink($id);$record['expected']=['modifiedAt'=>$p->post_modified_gmt,'publishedAt'=>$p->post_date_gmt,'fieldSHA256'=>array_map('fixture_hash',$fields)];
  $snapshots[$id]=['fields'=>$fields,'date'=>$p->post_date_gmt,'image'=>$attachment];
 }
 unset($record);$control=wp_insert_post(['post_type'=>'post','post_status'=>'publish','post_title'=>'Unrelated control','post_content'=>'UNCHANGED CONTROL']);
 file_put_contents($manifest,wp_json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
 file_put_contents($dir.'/before-snapshots.json',wp_json_encode(['records'=>$snapshots,'control'=>$control,'postCount'=>count(get_posts(['post_type'=>['post','page','cml_product'],'post_status'=>'publish','numberposts'=>-1,'suppress_filters'=>true]))]));return;
}
$data=json_decode(file_get_contents($manifest),true);$fixtureBefore=json_decode(file_get_contents($dir.'/before-snapshots.json'),true);$expectedException=false;
if($phase==='check')foreach($data['records']as$r)if(wp_kses_post($r['after']['content'])!==$r['after']['content']){file_put_contents($dir.'/html-diff.json',wp_json_encode(['id'=>$r['id'],'candidate'=>$r['after']['content'],'sanitized'=>wp_kses_post($r['after']['content'])],JSON_PRETTY_PRINT));break;}
if($phase==='scope'){$bad=$data;$bad['records'][]=$bad['records'][0];file_put_contents($dir.'/wrong-scope.json',wp_json_encode($bad));$file=$dir.'/wrong-scope.json';$mode='check';$expectedException=true;}
else{$file=$manifest;$mode=match($phase){'check'=>'check','verify'=>'verify','rollback'=>'rollback',default=>'apply'};}
if($phase==='drift'){wp_update_post(['ID'=>$data['records'][0]['id'],'post_excerpt'=>'CONCURRENT EDIT']);$expectedException=true;}
putenv('CML_REVIEW_MANIFEST='.$file);putenv('CML_REVIEW_BACKUP='.$backup);putenv('CML_REVIEW_MODE='.$mode);
ob_start();$caught=null;
try{require '/cml/corrections/source-content-20261105/apply-review.php';}catch(Throwable$e){$caught=$e;}
$output=ob_get_clean();
if($expectedException){
 $assert($caught instanceof Throwable,'Expected guard rejection');
 if($phase==='drift'){$id=$data['records'][0]['id'];$assert(get_post($id)->post_excerpt==='CONCURRENT EDIT','Concurrent edit preserved');wp_update_post(wp_slash(['ID'=>$id,'post_excerpt'=>$data['records'][0]['after']['excerpt']]));}
 else $assert(!is_file($backup),'Scope rejection does not create backup or mutate');
 file_put_contents($dir.'/'.$phase.'.json',wp_json_encode(['passed'=>true,'message'=>$caught->getMessage()]));return;
}
if($caught)throw$caught;
$report=json_decode($output,true,512,JSON_THROW_ON_ERROR);$assert($report['records']===36,'Exact scope');
if($phase==='check')$assert($report['changed']===0&&!is_file($backup),'Check is read-only');
if($phase==='apply'){
 $assert($report['changed']===36,'36 guarded existing-record updates');
 $archive=new PharData($backup.'.tar.gz');$restored=json_decode($archive[basename($backup)]->getContent(),true,512,JSON_THROW_ON_ERROR);$assert(count($restored['records'])===36,'Real tar.gz backup can be read');
}
if($phase==='verify'){
 require '/cml/corrections/source-content-20261105/cml-reviewed-article.php';
 add_filter('render_block_core/post-content',fn($html)=>'<details>OLD WRAPPER'.$html.'</details>',120);
 add_filter('wpseo_metadesc',fn($html)=>'OLD SEO DESCRIPTION',120);
 foreach($data['records']as$articleRecord){
  if($articleRecord['type']!=='post')continue;
  $GLOBALS['wp_query']=new WP_Query(['p'=>$articleRecord['id'],'post_type'=>'post']);
  $reviewed=$articleRecord['slug']==='what-is-the-main-task-of-a-mud-logging-unit';
  $assert(apply_filters('render_block_core/post-content','REVIEWED BODY')===($reviewed?'REVIEWED BODY':'<details>OLD WRAPPERREVIEWED BODY</details>'),'Reviewed article visible; unrelated wrappers preserved');
  $assert(apply_filters('wpseo_metadesc','INPUT')===($reviewed?$articleRecord['after']['meta']['_yoast_wpseo_metadesc']:'OLD SEO DESCRIPTION'),'Reviewed metadata scope');
 }
 wp_reset_query();
}
if($phase==='idempotent')$assert($report['changed']===0&&$report['unchanged']===36,'Idempotent re-run');
foreach($data['records']as$r){
 $id=$r['id'];$p=get_post($id);$assert($p->post_date_gmt===$fixtureBefore['records'][$id]['date']&&get_post_thumbnail_id($id)===$fixtureBefore['records'][$id]['image'],'Original date and image preserved: '.wp_json_encode([$phase,$id,$p->post_date_gmt,$fixtureBefore['records'][$id]['date'],get_post_thumbnail_id($id),$fixtureBefore['records'][$id]['image']]));
 if($phase==='check'||$phase==='rollback'){
  foreach($fixtureBefore['records'][$id]['fields']as$key=>$value){$actual=match($key){'title'=>$p->post_title,'content'=>$p->post_content,'excerpt'=>$p->post_excerpt,default=>get_post_meta($id,$key,true)};$assert(fixture_hash($actual)===fixture_hash($value),'Original field restored: '.$key);}
  if($phase==='rollback')$assert(!metadata_exists('post',$id,'_cml_content_review_batch'),'New review marker removed on rollback');
 }else{
  $assert($p->post_content===$r['after']['content']&&html_entity_decode($p->post_title,ENT_QUOTES|ENT_HTML5,'UTF-8')===$r['after']['title']&&html_entity_decode($p->post_excerpt,ENT_QUOTES|ENT_HTML5,'UTF-8')===$r['after']['excerpt'],'Reviewed content and decoded title/excerpt exact');
  foreach($r['after']['meta']as$key=>$value)$assert(fixture_hash(get_post_meta($id,$key,true))===fixture_hash($value),'Reviewed metadata exact');
 }
}
$assert(get_post($fixtureBefore['control'])->post_content==='UNCHANGED CONTROL','Unrelated content untouched');
$assert(count(get_posts(['post_type'=>['post','page','cml_product'],'post_status'=>'publish','numberposts'=>-1,'suppress_filters'=>true]))===$fixtureBefore['postCount'],'No duplicate posts or publication changes');
file_put_contents($dir.'/'.$phase.'.json',wp_json_encode(['passed'=>true,'records'=>36,'report'=>$report],JSON_PRETTY_PRINT));
