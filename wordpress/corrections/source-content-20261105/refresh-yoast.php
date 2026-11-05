<?php
/** Refresh only the guarded reviewed records; derived SEO data does not update post dates. */
if(!defined('WP_CLI')||!WP_CLI)exit;
putenv('CML_REVIEW_MODE=verify');
require __DIR__.'/apply-review.php';
$repository=YoastSEO()->classes->get(\Yoast\WP\SEO\Repositories\Indexable_Repository::class);
$builder=YoastSEO()->classes->get(\Yoast\WP\SEO\Builders\Indexable_Builder::class);
$seoBackup=dirname($backup).'/yoast-before-source-review-20261105.json';
if(is_file($seoBackup))throw new RuntimeException('SEO backup already exists: inspect previous run');
$old=[];
foreach($plan['records'] as $record){$indexable=$repository->find_by_id_and_type($record['id'],'post',false);$old[]=['id'=>$record['id'],'indexable'=>$indexable?$indexable->as_array():null];}
if(file_put_contents($seoBackup,wp_json_encode($old,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX)===false)throw new RuntimeException('SEO backup failed');
chmod($seoBackup,0600);
$refreshed=[];
foreach($plan['records'] as $record){
 $id=$record['id'];$invariants=cml_review_invariants($id);$modified=get_post($id)->post_modified_gmt;
 $existing=$repository->find_by_id_and_type($id,'post',false);
 $indexable=$builder->build_for_id_and_type($id,'post',$existing);
 if(!$indexable||$indexable->description!==$record['after']['meta']['_yoast_wpseo_metadesc']||$indexable->permalink!==$record['url']||cml_review_invariants($id)!==$invariants||get_post($id)->post_modified_gmt!==$modified)throw new RuntimeException('SEO refresh mismatch: '.$id);
 $refreshed[]=$id;
}
if(class_exists('WPSEO_Sitemaps_Cache'))WPSEO_Sitemaps_Cache::clear();
echo wp_json_encode(['observedAt'=>gmdate('c'),'refreshed'=>count($refreshed),'postDatesUnchanged'=>true,'backupSHA256'=>hash_file('sha256',$seoBackup)],JSON_PRETTY_PRINT)."\n";
