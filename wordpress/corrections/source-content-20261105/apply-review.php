<?php
/** Guarded existing-record correction. No new posts, media, translations or inquiry writes. */
if(!defined('WP_CLI')||!WP_CLI)throw new RuntimeException('WP-CLI only');
$mode=getenv('CML_REVIEW_MODE')?:'check';
if(!in_array($mode,['check','apply','verify','rollback'],true))throw new RuntimeException('Unknown mode');
$file=getenv('CML_REVIEW_MANIFEST');$backup=getenv('CML_REVIEW_BACKUP');
if(!$file||!is_file($file)||!$backup||!str_ends_with($backup,'.json'))throw new RuntimeException('Explicit manifest and private JSON backup required');
$plan=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
if(rtrim(home_url(),'/')!==rtrim($plan['site']??'','/'))throw new RuntimeException('Wrong site');
$directory=realpath(dirname($backup));$root=realpath(ABSPATH);
if(!$directory||$directory===$root||str_starts_with($directory,$root.DIRECTORY_SEPARATOR))throw new RuntimeException('Backup must be outside document root');
function cml_review_sorted($value){if(!is_array($value))return $value;if(!array_is_list($value))ksort($value);foreach($value as$key=>$part)$value[$key]=cml_review_sorted($part);return $value;}
function cml_review_hash($value):string{return hash('sha256',is_string($value)?$value:wp_json_encode(cml_review_sorted($value),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));}
function cml_review_values(int $id,array $keys):array{
 $p=get_post($id);$out=['title'=>$p->post_title,'content'=>$p->post_content,'excerpt'=>$p->post_excerpt];
 foreach($keys as$key)$out[$key]=get_post_meta($id,$key,true);return $out;
}
function cml_review_desired(int$id,array$after):array{
 // wp_insert_post filters slashed title/excerpt input before storing unslashed data.
 // Match that core representation (for example &gt;) without changing the reviewed text.
 return ['title'=>wp_unslash(sanitize_post_field('post_title',wp_slash($after['title']),$id,'db')),'content'=>$after['content'],'excerpt'=>wp_unslash(sanitize_post_field('post_excerpt',wp_slash($after['excerpt']),$id,'db'))]+$after['meta'];
}
function cml_review_invariants(int$id):array{
 $p=get_post($id);$terms=wp_get_object_terms($id,'cml_family',['fields'=>'ids','lang'=>'']);$terms=is_wp_error($terms)?[]:array_map('intval',$terms);sort($terms);
 return ['url'=>get_permalink($id),'slug'=>$p->post_name,'type'=>$p->post_type,'status'=>$p->post_status,'date'=>$p->post_date,'dateGMT'=>$p->post_date_gmt,'language'=>function_exists('pll_get_post_language')?pll_get_post_language($id):null,'translations'=>function_exists('pll_get_post_translations')?pll_get_post_translations($id):[],'image'=>get_post_thumbnail_id($id),'terms'=>$terms];
}
$records=$plan['records']??[];$ids=array_column($records,'id');
if(count($records)!==36||count(array_unique($ids))!==36)throw new RuntimeException('Expected 36 unique existing records');
$report=['observedAt'=>gmdate('c'),'mode'=>$mode,'batch'=>$plan['batch'],'records'=>36,'changed'=>0,'unchanged'=>0];
if($mode==='rollback'){
 $saved=json_decode(file_get_contents($backup),true,512,JSON_THROW_ON_ERROR);
 if(($saved['manifestSHA256']??'')!==hash_file('sha256',$file))throw new RuntimeException('Backup/manifest mismatch');
 foreach($saved['records']as$item){$id=$item['id'];$keys=$item['keys'];$values=cml_review_values($id,$keys);$state=cml_review_hash([cml_review_invariants($id),$values]);if(get_post_meta($id,'_cml_content_review_batch',true)!==$plan['batch']||get_post_meta($id,'_cml_content_review_state',true)!==$state)throw new RuntimeException('Rollback drift: '.$id);}
 global$wpdb;$wpdb->query('START TRANSACTION');
 try{
  foreach($saved['records']as$item){
   $id=$item['id'];$before=$item['post'];$r=wp_update_post(wp_slash(['ID'=>$id,'post_title'=>$before['post_title'],'post_content'=>$before['post_content'],'post_excerpt'=>$before['post_excerpt']]),true);if(is_wp_error($r))throw new RuntimeException($r->get_error_message());
   foreach($item['keys']as$key){if(array_key_exists($key,$item['meta']))update_post_meta($id,$key,wp_slash(maybe_unserialize($item['meta'][$key][0])));else delete_post_meta($id,$key);}
   foreach(['_cml_content_review_batch','_cml_content_review_state','_cml_content_review_provenance']as$key){if(array_key_exists($key,$item['meta']))update_post_meta($id,$key,wp_slash(maybe_unserialize($item['meta'][$key][0])));else delete_post_meta($id,$key);}
   if(cml_review_invariants($id)!==$item['invariants'])throw new RuntimeException('Rollback invariant failure');$report['changed']++;
  }
  $wpdb->query('COMMIT');
 }catch(Throwable$e){$wpdb->query('ROLLBACK');foreach($ids as$id)clean_post_cache($id);throw$e;}
 echo wp_json_encode($report,JSON_PRETTY_PRINT)."\n";return;
}
$planned=[];
foreach($records as$record){
 $id=(int)$record['id'];$p=get_post($id);if(!$p)throw new RuntimeException('Missing target');
 $invariant=cml_review_invariants($id);
 if($invariant['status']!=='publish'||$invariant['type']!==$record['type']||$invariant['slug']!==$record['slug']||$invariant['url']!==$record['url']||$invariant['language']!=='en'||$p->post_date_gmt!==$record['expected']['publishedAt'])throw new RuntimeException('Identity/publication drift: '.$id);
 if(cml_review_hash($record['after'])!==$record['afterSHA256'])throw new RuntimeException('Manifest integrity failure');
 $after=$record['after'];$keys=array_keys($after['meta']);$desired=cml_review_desired($id,$after);
 $current=cml_review_values($id,$keys);$done=cml_review_hash($current)===cml_review_hash($desired);
 if(!$done){
  if($p->post_modified_gmt!==$record['expected']['modifiedAt'])throw new RuntimeException('Edited since snapshot: '.$id);
  $oldKeys=array_diff(array_keys($record['expected']['fieldSHA256']),['title','content','excerpt']);$old=cml_review_values($id,$oldKeys);
  foreach($record['expected']['fieldSHA256']as$key=>$hash)if(cml_review_hash($old[$key])!==$hash)throw new RuntimeException('Field drift: '.$id.' '.$key);
  foreach(array_diff($keys,$oldKeys)as$key)if(get_post_meta($id,$key,true)!=='')throw new RuntimeException('New metadata already present: '.$id);
  $lock=explode(':',(string)get_post_meta($id,'_edit_lock',true));if((int)($lock[0]??0)>time()-300)throw new RuntimeException('Recent editor lock: '.$id);
 }
 if(wp_kses_post($after['content'])!==$after['content'])throw new RuntimeException('HTML sanitization mismatch: '.$id);
 foreach(parse_blocks($after['content'])as$block)if(!in_array($block['blockName'],['core/paragraph','core/heading','core/table',null],true))throw new RuntimeException('Unexpected block type');
 if(cml_clean_specifications($after['meta']['_cml_specifications'])!==$after['meta']['_cml_specifications'])throw new RuntimeException('Structured field sanitization mismatch: '.$id);
 if($mode==='verify'&&(!$done||get_post_meta($id,'_cml_content_review_batch',true)!==$plan['batch']))throw new RuntimeException('Published revision not verified: '.$id);
 $planned[]=['id'=>$id,'record'=>$record,'keys'=>$keys,'done'=>$done,'post'=>$p->to_array(),'meta'=>get_post_meta($id),'invariants'=>$invariant];
}
$report['unchanged']=count(array_filter($planned,fn($x)=>$x['done']));
if($mode==='apply'&&$report['unchanged']!==36){
 if(is_file($backup))throw new RuntimeException('Backup exists; reconcile previous run');
 $saved=['observedAt'=>gmdate('c'),'manifestSHA256'=>hash_file('sha256',$file),'records'=>$planned];$h=fopen($backup,'x');if(!$h)throw new RuntimeException('Backup creation failed');fwrite($h,wp_json_encode($saved,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));fclose($h);chmod($backup,0600);
 $verified=json_decode(file_get_contents($backup),true,512,JSON_THROW_ON_ERROR);if(count($verified['records'])!==36)throw new RuntimeException('Backup readback failed');
 $tar=substr($backup,0,-5).'.tar';if(file_exists($tar)||file_exists($tar.'.gz'))throw new RuntimeException('Archive already exists');
 $archive=new PharData($tar);$archive->addFile($backup,basename($backup));$compressed=$archive->compress(Phar::GZ);unset($compressed,$archive);
 if(!is_file($tar.'.gz')||!rename($tar.'.gz',$backup.'.tar.gz'))throw new RuntimeException('Backup archive failed');chmod($tar,0600);chmod($backup.'.tar.gz',0600);
 global$wpdb;$wpdb->query('START TRANSACTION');
 try{
  foreach($planned as$item){
   if($item['done'])continue;$id=$item['id'];$after=$item['record']['after'];
   $r=wp_update_post(wp_slash(['ID'=>$id,'post_title'=>$after['title'],'post_content'=>$after['content'],'post_excerpt'=>$after['excerpt']]),true);if(is_wp_error($r))throw new RuntimeException($r->get_error_message());
   foreach($after['meta']as$key=>$value)update_post_meta($id,$key,wp_slash($value));
   $desired=cml_review_desired($id,$after);
   $actual=cml_review_values($id,$item['keys']);$different=[];foreach($desired as$key=>$value)if(cml_review_hash($actual[$key])!==cml_review_hash($value))$different[]=$key;
   if($different||cml_review_invariants($id)!==$item['invariants']){
    if(defined('CML_SOURCE_REVIEW_ISOLATED')&&CML_SOURCE_REVIEW_ISOLATED)file_put_contents('/cml-review-tests/post-write-diff.json',wp_json_encode(['id'=>$id,'different'=>$different,'actual'=>$actual,'desired'=>$desired],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    throw new RuntimeException('Post-write verification failed: '.$id.' '.implode(',',$different));
   }
   update_post_meta($id,'_cml_content_review_provenance',wp_slash($after['source']));update_post_meta($id,'_cml_content_review_batch',$plan['batch']);update_post_meta($id,'_cml_content_review_state',cml_review_hash([cml_review_invariants($id),cml_review_values($id,$item['keys'])]));$report['changed']++;
  }
  $wpdb->query('COMMIT');
 }catch(Throwable$e){$wpdb->query('ROLLBACK');foreach($ids as$id)clean_post_cache($id);throw$e;}
 $report['backupSHA256']=hash_file('sha256',$backup);$report['archiveSHA256']=hash_file('sha256',$backup.'.tar.gz');
}
echo wp_json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
