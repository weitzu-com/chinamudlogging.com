<?php
/** Read-only candidate validation, run with WordPress loaded from private staging. */
$candidate=json_decode(file_get_contents(__DIR__.'/../theme/cml/assets/product-details.json'),true);
if(($candidate['source_review']??'')!=='2026-11-11')throw new RuntimeException('Wrong source-review candidate');
$matched=0;$failed=[];
foreach($candidate['products'] as $id=>$r){
    $id=(int)$id;
    if(get_post_status($id)!=='publish'||get_post_field('post_name',$id)!==$r['slug']||pll_get_post_language($id)!==$r['language']||!hash_equals($r['fingerprint'],cml_detail_fingerprint($id)))$failed[]=$id;
    else $matched++;
    foreach($r['rows'] as $row)foreach($row['citations']??[] as $ref){
        if(!isset($candidate['sources'][$ref])||strpos($candidate['sources'][$ref]['url'],'https://')!==0)throw new RuntimeException('Invalid source reference');
    }
}
echo json_encode(['candidate_products'=>count($candidate['products']),'matched'=>$matched,'failed'=>$failed,'sources'=>count($candidate['sources'])]);
if($failed||$matched!==375)throw new RuntimeException('Source changed; abort this candidate');
