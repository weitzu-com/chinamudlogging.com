<?php
/** Field-scoped, optimistic product-copy repair. Never restores a whole database. */
function cml_copy_protected(int $id): array {
    $p=get_post($id);
    if (!$p || $p->post_type!=='cml_product') throw new RuntimeException('Product missing: '.$id);
    $translations=function_exists('pll_get_post_translations')?pll_get_post_translations($id):['en'=>$id];
    ksort($translations);
    $families=wp_get_post_terms($id,'cml_family',['fields'=>'slugs']);
    if(is_wp_error($families))throw new RuntimeException('Product taxonomy unavailable');
    sort($families);
    return ['status'=>$p->post_status,'slug'=>$p->post_name,'content_sha256'=>hash('sha256',$p->post_content),'language'=>function_exists('pll_get_post_language')?pll_get_post_language($id):'en','translations'=>$translations,'families'=>$families,'thumbnail'=>(int)get_post_thumbnail_id($id),'sku'=>get_post_meta($id,'_cml_sku',true),'specifications'=>get_post_meta($id,'_cml_specifications',true),'source_id'=>get_post_meta($id,'_cml_source_id',true),'source_url'=>get_post_meta($id,'_cml_source_url',true),'description_sha256'=>hash('sha256',(string)get_post_meta($id,'_cml_description',true))];
}
function cml_copy_validate(array $row): string {
    $id=(int)$row['id'];$expected=$row['protected'];
    ksort($expected['translations']);sort($expected['families']);
    if(($expected['language']??'')!=='en' || ($expected['status']??'')!=='publish')throw new RuntimeException('Unreviewed language/status: '.$id);
    if(cml_copy_protected($id)!==$expected)throw new RuntimeException('Protected field changed: '.$id);
    $p=get_post($id);$current=['title'=>$p->post_title,'excerpt'=>$p->post_excerpt,'seo_description'=>get_post_meta($id,'_yoast_wpseo_metadesc',true),'seo_title'=>get_post_meta($id,'_yoast_wpseo_title',true)];
    if($current===$row['after'])return 'already_applied';
    if($current!==$row['before'])throw new RuntimeException('Concurrent copy edit: '.$id.' ('.implode(',',array_keys(array_filter($current,fn($v,$k)=>$v!==($row['before'][$k]??null),ARRAY_FILTER_USE_BOTH))).')');
    foreach(['title','excerpt'] as $key)if(!is_string($row['after'][$key]??null)||trim($row['after'][$key])===''||preg_match('~</?[a-z][^>]*>~i',$row['after'][$key]))throw new RuntimeException('Invalid replacement text: '.$id);
    return 'ready';
}
function cml_apply_product_copy(array $plan,bool $apply=false): array {
    $rows=$plan['changes']??[];
    if(!$rows)throw new RuntimeException('No reviewed changes');
    $ids=array_column($rows,'id');if(count(array_unique($ids))!==count($ids))throw new RuntimeException('Duplicate product targets');
    $states=[];
    foreach($rows as $row)$states[$row['id']]=cml_copy_validate($row);
    if(!$apply)return ['dry_run'=>true,'ready'=>count(array_filter($states,fn($s)=>$s==='ready')),'already_applied'=>count(array_filter($states,fn($s)=>$s==='already_applied')),'states'=>$states];
    $changed=[];
    foreach($rows as $row){
        $id=(int)$row['id'];if(cml_copy_validate($row)==='already_applied')continue;
        // Save filters can entity-encode comparison signs in retained catalogue
        // designations. Preserve the reviewed plain text and byte-exact source
        // body for this target only, while retaining WordPress save hooks.
        $body=get_post_field('post_content',$id);
        $exact=function($data,$postarr)use($id,$row,$body){
            if((int)($postarr['ID']??0)===$id){$data['post_title']=wp_slash($row['after']['title']);$data['post_excerpt']=wp_slash($row['after']['excerpt']);$data['post_content']=wp_slash($body);}
            return $data;
        };
        add_filter('wp_insert_post_data',$exact,PHP_INT_MAX,2);
        try{$result=wp_update_post(wp_slash(['ID'=>$id,'post_title'=>$row['after']['title'],'post_excerpt'=>$row['after']['excerpt']]),true);}
        finally{remove_filter('wp_insert_post_data',$exact,PHP_INT_MAX);}
        if(is_wp_error($result))throw new RuntimeException('Update failed: '.$id.' '.$result->get_error_message());
        foreach(['seo_description'=>'_yoast_wpseo_metadesc','seo_title'=>'_yoast_wpseo_title'] as $key=>$meta)if($row['before'][$key]!==$row['after'][$key])update_post_meta($id,$meta,wp_slash($row['after'][$key]));
        clean_post_cache($id);
        if(cml_copy_validate($row)!=='already_applied')throw new RuntimeException('Readback mismatch: '.$id);
        $changed[]=$id;
    }
    return ['applied'=>count($changed),'unchanged'=>count($rows)-count($changed),'ids'=>$changed];
}
if(defined('WP_CLI')&&WP_CLI){
    $file=getenv('CML_COPY_PLAN');
    if(!$file||!is_readable($file))throw new RuntimeException('CML_COPY_PLAN is required');
    $plan=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
    echo wp_json_encode(cml_apply_product_copy($plan,getenv('CML_APPLY_PRODUCT_COPY')==='1'),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
}
