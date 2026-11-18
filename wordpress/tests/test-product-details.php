<?php
/** Meaningful integration checks in a disposable public-product fixture. */
if (!defined('CML_COPY_ISOLATED_TEST')) throw new RuntimeException('Isolated fixtures only');
require_once '/wordpress/wp-load.php';
$data=cml_detail_data(); $total=0; $passed=0;
$check=function($ok,$label)use(&$total,&$passed){$total++;if(!$ok)throw new RuntimeException($label);$passed++;};
$check(count($data['products'])===375,'Complete inventory');
$check(count($data['copy'])===7,'Seven languages');
foreach ($data['products'] as $id=>$r) {
    $id=(int)$id;
    $check(cml_detail_record($id)!==null,'Source fingerprint '.$id);
    $query=new WP_Query(['post_type'=>'cml_product','p'=>$id,'lang'=>$r['language']]);
    $GLOBALS['wp_query']=$query;$GLOBALS['wp_the_query']=$query;$query->the_post();
    $html=apply_filters('render_block_core/post-content','OLD SOURCE CONTENT',[]);
    $check(strpos($html,'data-product-detail-review="20261105"')!==false,'Reviewed render '.$id);
    $check(strpos($html,'OLD SOURCE CONTENT')===false,'No legacy duplication '.$id);
    $check(strpos($html,'scope="row"')!==false,'Accessible parameters '.$id);
    $hero='<div class="cml-product-top"><h1>Identity</h1><dl class="cml-specs"><div>OLD SKU</div></dl><a class="cml-button" href="/quote/?product=preserved">Enquiry</a></div>';
    $rendered=render_block(['blockName'=>'core/html','attrs'=>[],'innerBlocks'=>[],'innerHTML'=>$hero,'innerContent'=>[$hero]]);
    $check(strpos($rendered,'product=preserved')!==false && strpos($rendered,'OLD SKU')===false,'Preserve enquiry, remove unverified SKU '.$id);
    $seen=[];
    foreach ($r['rows'] as $row) {
        $key=$row['label'].':'.$row['value'];
        $check(!isset($seen[$key]),'Deduplicated table '.$id);$seen[$key]=true;
        foreach($row['citations']??[] as $source){
            $check(strpos($html,esc_url($data['sources'][$source]['url']))!==false,'Visible source link '.$id);
        }
    }
    $before=get_post_meta($id,'_cml_sku',true);
    update_post_meta($id,'_cml_sku','changed source');
    $check(cml_detail_record($id)===null,'Stale review guard '.$id);
    update_post_meta($id,'_cml_sku',wp_slash($before));
}
$check($data['products']['102']['rows'][array_search('Measurement range',array_column($data['products']['102']['rows'],'label'))]['status']==='conflict','Pressure conflict stays pending');
$check(count(array_filter($data['products']['125']['rows'],fn($r)=>$r['status']==='pending'))===4,'Unidentified sensor not invented');
file_put_contents('/copy-fixture/detail-test-results.json',json_encode(['passed'=>$passed,'total'=>$total]));
