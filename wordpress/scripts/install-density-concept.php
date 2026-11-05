<?php
/** Explicitly disclosed category illustration; never assigns an inferred SKU. */
function cml_install_density_concept(string $source,bool $apply=false): array {
    $hash='0b995cd6a94352e12b095d8c9fd413235abc4faa66a1f2e2c4d7d7dfa4138517';
    if (!is_file($source) || hash_file('sha256',$source)!==$hash) throw new RuntimeException('Generated source hash mismatch');
    $posts=get_posts(['post_type'=>'cml_product','post_status'=>'publish','numberposts'=>2,'lang'=>'','meta_key'=>'_cml_source_id','meta_value'=>'product:242']);
    if(count($posts)!==1) throw new RuntimeException('Product identity is ambiguous');
    $post=$posts[0];$old=(int)get_post_thumbnail_id($post->ID);$file=get_attached_file($old);
    if (get_post_meta($post->ID,'_cml_sku',true)!=='' || $post->post_name!=='mud-density-sensor-for-oil-and-gas-mud-logging') throw new RuntimeException('Product model/identity changed concurrently');
    $current=$file&&is_file($file)?hash_file('sha256',$file):'';
    if (!in_array($current,[$hash,'751b04edb90cc3bff48ff519d46de92553685cc74a3f7c0550e376a54ae4c580'],true)) throw new RuntimeException('Thumbnail changed concurrently');
    $report=['product_id'=>$post->ID,'source_id'=>'product:242','old_thumbnail'=>$old,'old_sha256'=>$current,'source_sha256'=>$hash,'apply'=>$apply,'classification'=>'AI-generated category concept; model unconfirmed'];
    if (!$apply) return $report;
    if ($current===$hash) return $report+['thumbnail'=>$old,'already_applied'=>true];
    require_once ABSPATH.'wp-admin/includes/file.php';
    require_once ABSPATH.'wp-admin/includes/media.php';
    require_once ABSPATH.'wp-admin/includes/image.php';
    $temporary=wp_tempnam('mud-density-ai-concept.png');
    if (!$temporary || !copy($source,$temporary)) throw new RuntimeException('Unable to prepare media upload');
    $id=media_handle_sideload(['name'=>'mud-density-ai-concept.png','tmp_name'=>$temporary],$post->ID,'AI-generated mud density sensor concept illustration — model unconfirmed');
    if (is_wp_error($id)) throw new RuntimeException($id->get_error_message());
    update_post_meta($id,'_wp_attachment_image_alt','AI-generated illustration of a mud density sensor. Model and supplied appearance to be confirmed.');
    update_post_meta($id,'_cml_media_provenance',['type'=>'ai_concept','source_sha256'=>$hash,'authorization'=>'User explicitly authorized web image search and AI generation on 2026-11-05.','model_confirmed'=>false]);
    update_post_meta($post->ID,'_cml_thumbnail_before_ai',$old);
    if (!set_post_thumbnail($post->ID,$id)) throw new RuntimeException('Unable to set product thumbnail');
    clean_post_cache($post->ID);
    return $report+['thumbnail'=>$id,'url'=>wp_get_attachment_url($id)];
}
if (defined('WP_CLI') && WP_CLI) {
    $source=getenv('CML_DENSITY_SOURCE');
    if (!$source) throw new RuntimeException('CML_DENSITY_SOURCE is required');
    echo wp_json_encode(cml_install_density_concept($source,getenv('CML_APPLY_DENSITY_CONCEPT')==='1'),JSON_PRETTY_PRINT);
}
