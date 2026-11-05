<?php
/** Guarded reversible containment for an explicit, reviewed manifest. WP-CLI only. */
if (!defined('WP_CLI') || !WP_CLI) throw new RuntimeException('WP-CLI only');
$mode=getenv('CML_CONTAIN_MODE')?:'check';
if(!in_array($mode,['check','apply','rollback'],true))throw new RuntimeException('Unknown mode');
$planPath=getenv('CML_CONTAIN_PLAN');
if(!$planPath||!is_readable($planPath))throw new RuntimeException('Explicit plan file required');
$plan=json_decode(file_get_contents($planPath),true,512,JSON_THROW_ON_ERROR);
if(rtrim(home_url(),'/')!==rtrim($plan['site']??'','/'))throw new RuntimeException('Wrong site');
$backup=getenv('CML_CONTAIN_BACKUP');
if(!$backup||str_starts_with(realpath(dirname($backup))?:'',realpath(ABSPATH)))throw new RuntimeException('Private backup path outside document root required');
$changes=$plan['changes']??[];
if(!$changes||count($changes)>20)throw new RuntimeException('Bounded nonempty manifest required');
$report=['observedAt'=>gmdate('c'),'mode'=>$mode,'results'=>[]];
if($mode==='rollback'){
 $saved=json_decode(file_get_contents($backup),true,512,JSON_THROW_ON_ERROR);
 if(($saved['planSHA256']??'')!==hash_file('sha256',$planPath))throw new RuntimeException('Backup/plan mismatch');
 foreach($saved['posts']as$row){
  $p=get_post($row['ID']);
  if(!$p||$p->post_status!=='draft'||hash('sha256',$p->post_content)!==$row['contentSHA256'])throw new RuntimeException('Rollback state drift');
  if((string)get_post_meta($p->ID,'_cml_containment_backup',true)!==$backup)throw new RuntimeException('Rollback ownership mismatch');
  if(!hash_equals((string)get_post_meta($p->ID,'_cml_containment_state_hash',true),hash('sha256',wp_json_encode($p->to_array()))))throw new RuntimeException('Draft was edited after containment; do not overwrite');
 }
 foreach($saved['posts']as$row){
  $result=wp_update_post(['ID'=>$row['ID'],'post_status'=>'publish'],true);
  if(is_wp_error($result))throw new RuntimeException($result->get_error_message());
  delete_post_meta($row['ID'],'_cml_containment_backup');delete_post_meta($row['ID'],'_cml_containment_state_hash');
  $report['results'][]=['id'=>$row['ID'],'status'=>get_post_status($row['ID'])];
 }
}else{
 $saved=['observedAt'=>gmdate('c'),'planSHA256'=>hash_file('sha256',$planPath),'posts'=>[]];
 foreach($changes as$row){
  $p=get_post((int)$row['id']);
  if(!$p||$p->post_type!=='cml_product'||$p->post_status!=='publish'||get_permalink($p)!==$row['url']||$p->post_title!==$row['title']||$p->post_modified_gmt!==$row['expectedModifiedAt']||hash('sha256',$p->post_content)!==$row['expectedContentSHA256'])throw new RuntimeException('Post drift: '.$row['id']);
  if(!str_ends_with($p->post_name,'manual-enrichment-draft')||!str_contains(strtolower($p->post_content),'draft'))throw new RuntimeException('Missing explicit draft marker');
  $review=get_post_meta($p->ID,'_cml_review',true);
  if(in_array(strtolower(is_string($review)?$review:''),['approved','accepted','verified'],true))throw new RuntimeException('Explicit approved status; manual reconciliation needed');
  $lock=explode(':',(string)get_post_meta($p->ID,'_edit_lock',true));
  if((int)($lock[0]??0)>time()-300)throw new RuntimeException('Recent editor lock');
  $saved['posts'][]=['ID'=>$p->ID,'post'=>$p->to_array(),'meta'=>get_post_meta($p->ID),'contentSHA256'=>hash('sha256',$p->post_content)];
  $report['results'][]=['id'=>$p->ID,'before'=>'publish','planned'=>'draft','review'=>$review?:null];
 }
 if($mode==='apply'){
  $handle=fopen($backup,'x');if(!$handle)throw new RuntimeException('Backup already exists or cannot be created');
  fwrite($handle,wp_json_encode($saved,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));fclose($handle);chmod($backup,0600);
  $verified=json_decode(file_get_contents($backup),true,512,JSON_THROW_ON_ERROR);
  if(count($verified['posts'])!==count($changes))throw new RuntimeException('Backup readback failed');
  $tar=$backup.'.tar.gz';
  if(file_exists($tar))throw new RuntimeException('Archive already exists');
  exec('tar -czf '.escapeshellarg($tar).' -C '.escapeshellarg(dirname($backup)).' '.escapeshellarg(basename($backup)),$output,$code);
  if($code!==0||!is_file($tar))throw new RuntimeException('Backup archive failed');chmod($tar,0600);
  foreach($changes as$row){
   $result=wp_update_post(['ID'=>(int)$row['id'],'post_status'=>'draft'],true);
   if(is_wp_error($result))throw new RuntimeException($result->get_error_message());
   if(get_post_status($row['id'])!=='draft'||hash('sha256',get_post($row['id'])->post_content)!==$row['expectedContentSHA256'])throw new RuntimeException('Containment verification failed');
   update_post_meta($row['id'],'_cml_containment_backup',$backup);
   update_post_meta($row['id'],'_cml_containment_state_hash',hash('sha256',wp_json_encode(get_post($row['id'])->to_array())));
  }
  $report['backupSHA256']=hash_file('sha256',$backup);$report['archiveSHA256']=hash_file('sha256',$tar);
 }
}
echo wp_json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
