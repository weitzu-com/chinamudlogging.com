<?php
if(!defined('CML_COPY_ISOLATED_TEST')||!CML_COPY_ISOLATED_TEST)throw new RuntimeException('Disposable WordPress required');
require '/wordpress/wp-load.php';require '/cml/scripts/apply-product-copy.php';
$checks=[];$assert=function($value,$name)use(&$checks){$checks[]=['name'=>$name,'passed'=>(bool)$value];if(!$value)throw new RuntimeException($name);};
$rows=[];
foreach([['unknown-density-sensor','mud-density-sensor-for-mud-logging','Mud Density Sensor — Model Identification'],['comparison-sensor','100℃<42V Sensor','100℃<42V Sensor']] as $i=>$fixture){
    [$slug,$title,$after]=$fixture;
    $id=wp_insert_post(wp_slash(['post_type'=>'cml_product','post_status'=>'publish','post_name'=>$slug,'post_title'=>$title,'post_content'=>'<p>Retained source body &amp; installation note.</p>','post_excerpt'=>$slug]));
    update_post_meta($id,'_cml_sku','TSFS-H2A-1');update_post_meta($id,'_cml_specifications',[['label'=>'Name','raw'=>'Sensor']]);update_post_meta($id,'_yoast_wpseo_metadesc',$slug);
    $p=get_post($id);
    $rows[]=['id'=>$id,'before'=>['title'=>$p->post_title,'excerpt'=>$p->post_excerpt,'seo_description'=>$slug,'seo_title'=>''],'after'=>['title'=>$after,'excerpt'=>'Sensor component; model-matched measurement and interface documents are required.','seo_description'=>'Sensor component; model-matched measurement and interface documents are required.','seo_title'=>''],'protected'=>cml_copy_protected($id)];
}
$plan=['changes'=>$rows];$assert(cml_apply_product_copy($plan)['ready']===2,'Dry-run validates all records without changes');
$assert(cml_apply_product_copy($plan,true)['applied']===2,'Only reviewed copy fields apply');
foreach($rows as $row)$assert(cml_copy_validate($row)==='already_applied','Readback and all protected fields match '.$row['id']);
$assert(cml_apply_product_copy($plan,true)['applied']===0,'Repeated apply is idempotent');
$id=$rows[0]['id'];wp_update_post(wp_slash(['ID'=>$id,'post_title'=>$rows[0]['before']['title'],'post_excerpt'=>$rows[0]['before']['excerpt']]));update_post_meta($id,'_yoast_wpseo_metadesc',$rows[0]['before']['seo_description']);
$other=$rows[1]['id'];wp_update_post(['ID'=>$other,'post_excerpt'=>'Another editor changed this']);
$caught=false;try{cml_apply_product_copy($plan,true);}catch(RuntimeException $e){$caught=str_contains($e->getMessage(),'Concurrent copy edit');}
$assert($caught&&get_post_field('post_excerpt',$id)===$rows[0]['before']['excerpt'],'Preflight rejects later conflicts before writing the first row');
wp_update_post(wp_slash(['ID'=>$other,'post_excerpt'=>$rows[1]['after']['excerpt']]));update_post_meta($id,'_cml_sku','Changed model');
$caught=false;try{cml_apply_product_copy($plan);}catch(RuntimeException $e){$caught=str_contains($e->getMessage(),'Protected field changed');}$assert($caught,'Model/specification identity drift is rejected');
$reviewed=json_decode(file_get_contents('/cml/content-repairs/product-copy-plan-20261105.json'),true);
$assert(count($reviewed['audited'])===375&&count($reviewed['changes'])===267,'Complete reviewed inventory and selected change count');
foreach($reviewed['changes'] as $row){
    $assert($row['protected']['language']==='en','Non-English approved copy is excluded '.$row['id']);
    $assert(!str_contains($row['after']['excerpt'],'source catalogue note:')&&!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+){2,}$/',$row['after']['excerpt']),'No slug/template excerpt '.$row['id']);
    $generic=$row['id']===125;
    foreach(($row['protected']['specifications']?:[]) as $field)if(($field['label']??'')==='Name'&&str_starts_with($field['raw'],'传感器 / '))$generic=true;
    if($generic)$assert(!preg_match('/\b(?:H2S|hydrogen|Hall|photoelectric)\b/i',$row['after']['excerpt']),'Unknown identity is not inferred from SKU '.$row['id']);
}
$out=['passed'=>count($checks),'total'=>count($checks),'tests'=>$checks];file_put_contents('/copy-results/results.json',wp_json_encode($out,JSON_PRETTY_PRINT));echo 'Product-copy tests: '.count($checks);
