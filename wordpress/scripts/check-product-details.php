<?php
/** Read-only release preflight: upload beside the wordpress directory in private staging. */
if (!function_exists('cml_detail_data')) require_once __DIR__.'/../theme/cml/inc/product-details.php';
$ok=[];$stale=[];
foreach (cml_detail_data()['products'] as $id=>$record) {
    if(cml_detail_record((int)$id))$ok[]=(int)$id;else $stale[]=(int)$id;
}
echo json_encode(['checked'=>count($ok)+count($stale),'matched'=>count($ok),'stale'=>$stale]);
if($stale)throw new RuntimeException('Source changed since review; release aborted');
