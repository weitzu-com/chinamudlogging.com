<?php
/** Scoped CLI release: preflight all records, preserve edits, then commit atomically. */
if (!(defined('WP_CLI') && WP_CLI) && !(defined('CML_FULL_RELEASE_ADMIN') && CML_FULL_RELEASE_ADMIN && current_user_can('manage_options'))) throw new RuntimeException('Authorized administrator or WP-CLI required.');
if (empty($args[0]) || !is_file($args[0])) throw new RuntimeException('Explicit local publication manifest required.');
$manifest_file = realpath($args[0]);
$data = json_decode(file_get_contents($manifest_file), true, 512, JSON_THROW_ON_ERROR);
if (($data['schemaVersion'] ?? null) !== 1 || ($data['batch'] ?? '') !== 'full-translations-v1' || count($data['records'] ?? []) !== 60) throw new RuntimeException('Invalid full translation batch.');
$mode = $cml_full_action ?? (getenv('CML_FULL_ACTION') ?: 'check');
if (!in_array($mode, ['check','apply','verify'], true)) throw new RuntimeException('Unknown action.');
$languages = ['zh','es','ru','ar','fr','de']; $seen=[]; $planned=[]; $counts=array_fill_keys($languages,0);
function cml_full_find(string $key): WP_Post {
    $matches = get_posts(['post_type'=>'cml_product','post_status'=>['publish','draft','pending','private','trash'],'numberposts'=>2,'lang'=>'','meta_key'=>'_cml_import_key','meta_value'=>$key]);
    if (count($matches)!==1) throw new RuntimeException('Nonunique product key: '.$key);
    return $matches[0];
}
function cml_full_check_editor(int $id): void {
    $stored=get_post_meta($id,'_cml_editor_snapshot',true);$current=cml_editor_snapshot($id);
    if (!is_array($stored)) throw new RuntimeException('Editor snapshot missing: '.$id);
    ksort($stored);ksort($current);
    if ($stored!==$current) throw new RuntimeException('Manual editor changes require reconciliation: '.$id);
}
function cml_full_matches_baseline(array $record, string $prefix, string $actual): bool {
    $raw=$record[$prefix.'Content'] ?? '';
    if (!hash_equals($record[$prefix.'ContentSHA256'],hash('sha256',$raw))) throw new RuntimeException('Baseline integrity failure.');
    // The original importer runs through WordPress KSES. Account only for that
    // deterministic sanitization; editor snapshots still reject concurrent edits.
    return $actual===$raw || $actual===wp_kses_post($raw);
}
foreach ($data['records'] as $record) {
    $key=$record['key'] ?? ''; $lang=$record['language'] ?? '';
    if (isset($seen[$key]) || !in_array($lang,$languages,true) || !preg_match('/^product:\d+:(zh|es|ru|ar|fr|de)$/',$key) || !str_ends_with($key,':'.$lang)) throw new RuntimeException('Invalid source/language identity.');
    $seen[$key]=true;$counts[$lang]++;
    $post=cml_full_find($key);$english=cml_full_find($record['sourceKey']);
    if (pll_get_post_language($post->ID)!==$lang || pll_get_post_language($english->ID)!=='en' || $post->ID===$english->ID || $post->post_name!==$record['slug'] || $post->post_status!=='publish') throw new RuntimeException('Target identity, route or publication changed: '.$key);
    if (pll_get_post($english->ID,$lang)!==$post->ID) throw new RuntimeException('Language association changed: '.$key);
    cml_full_check_editor($english->ID);cml_full_check_editor($post->ID);
    if (!cml_full_matches_baseline($record,'expectedEnglish',$english->post_content)) throw new RuntimeException('Frozen English body differs: '.$key);
    if (!hash_equals($record['contentSHA256'],hash('sha256',$record['content']))) throw new RuntimeException('Candidate integrity failure: '.$key);
    $current=hash('sha256',$post->post_content);$done=hash_equals($record['contentSHA256'],$current);
    if (!$done && !cml_full_matches_baseline($record,'expectedPrevious',$post->post_content)) throw new RuntimeException('Target body changed: '.$key);
    if ($mode==='verify' && (!$done || get_post_meta($post->ID,'_cml_localization_mode',true)!=='full-source-translation-reviewed' || get_post_meta($post->ID,'_cml_full_translation_sha256',true)!==$record['translationSHA256'])) throw new RuntimeException('Published content does not match: '.$key);
    $blocks=parse_blocks($record['content']);
    foreach ($blocks as $block) if (!in_array($block['blockName'],['core/paragraph','core/heading','core/table'],true)) throw new RuntimeException('Unexpected block type: '.$key);
    if (wp_kses_post($record['content'])!==$record['content']) throw new RuntimeException('Candidate requires HTML sanitization: '.$key);
    $specs=cml_clean_specifications($record['specifications']);
    if (count($specs)!==count($record['specifications'])) throw new RuntimeException('Structured specification lost: '.$key);
    $planned[]=['id'=>$post->ID,'key'=>$key,'url'=>get_permalink($post),'done'=>$done,'record'=>$record,'before'=>['content'=>$post->post_content,'image'=>get_post_thumbnail_id($post->ID),'families'=>cml_snapshot_families($post->ID),'meta'=>array_combine(['_cml_specifications','_cml_localization_mode','_cml_review','_cml_editor_snapshot','_cml_full_translation_sha256','_cml_full_source_sha256'],array_map(fn($name)=>get_post_meta($post->ID,$name,true),['_cml_specifications','_cml_localization_mode','_cml_review','_cml_editor_snapshot','_cml_full_translation_sha256','_cml_full_source_sha256']))]];
}
if (array_filter($counts,fn($n)=>$n!==10)) throw new RuntimeException('Each language must contain ten products.');
if ($mode==='apply') {
    $rollback=dirname($manifest_file).'/before-'.$data['batch'].'.json';
    if (!is_file($rollback)) { if (file_put_contents($rollback,wp_json_encode($planned,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),LOCK_EX)===false) throw new RuntimeException('Cannot save rollback records.'); chmod($rollback,0600); }
    global $wpdb; $wpdb->query('START TRANSACTION');
    try {
        foreach ($planned as $item) {
            if ($item['done']) continue;
            $id=$item['id'];$record=$item['record'];
            $result=wp_update_post(wp_slash(['ID'=>$id,'post_content'=>$record['content']]),true);
            if (is_wp_error($result)) throw new RuntimeException('Post update failed: '.$item['key']);
            update_post_meta($id,'_cml_specifications',wp_slash(cml_clean_specifications($record['specifications'])));
            update_post_meta($id,'_cml_localization_mode','full-source-translation-reviewed');
            update_post_meta($id,'_cml_review','approved');
            update_post_meta($id,'_cml_full_translation_sha256',$record['translationSHA256']);
            update_post_meta($id,'_cml_full_source_sha256',$record['sourceSHA256']);
            update_post_meta($id,'_cml_editor_snapshot',wp_slash(cml_editor_snapshot($id)));
            if (hash('sha256',get_post_field('post_content',$id,'raw'))!==$record['contentSHA256'] || get_post_thumbnail_id($id)!==$item['before']['image'] || cml_snapshot_families($id)!==$item['before']['families']) throw new RuntimeException('Post-write verification failed: '.$item['key']);
        }
        $wpdb->query('COMMIT');
    } catch (Throwable $error) { $wpdb->query('ROLLBACK');foreach($planned as $item)clean_post_cache($item['id']);throw $error; }
}
$report=['action'=>$mode,'records'=>count($planned),'languages'=>$counts,'unchanged'=>count(array_filter($planned,fn($x)=>$x['done'])),'changed'=>$mode==='apply'?count(array_filter($planned,fn($x)=>!$x['done'])):0,'urls'=>array_map(fn($x)=>['key'=>$x['key'],'url'=>$x['url'],'contentSHA256'=>$x['record']['contentSHA256']],$planned)];
echo wp_json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
