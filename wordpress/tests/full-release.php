<?php
if (!defined('CML_INTEGRATION_TEST') || !CML_INTEGRATION_TEST) throw new RuntimeException('Disposable local environment required.');
wp_set_current_user(1);
require '/cml/extensions/cml-full-translations.php';
$manifest=json_decode(file_get_contents('/full-release/publish.json'),true);
$bundle=json_decode(file_get_contents('/bundle/bundle.json'),true);
$production_media=json_decode(file_get_contents('/cml-results/media-map.json'),true);
$map=[];
foreach ($bundle['media'] as $asset) {
    $images=get_posts(['post_type'=>'attachment','post_status'=>'inherit','numberposts'=>1,'lang'=>'','meta_key'=>'_cml_asset_key','meta_value'=>$asset['sha256']]);
    if ($images && isset($production_media[$asset['path']])) $map[$production_media[$asset['path']]]=wp_get_attachment_url($images[0]->ID);
}
foreach ($manifest['records'] as &$record) {
    foreach (['content','expectedEnglishContent','expectedPreviousContent'] as $name) {
        $record[$name]=str_replace(array_keys($map),array_values($map),$record[$name]);
        $record[$name.'SHA256']=hash('sha256',$record[$name]);
    }
    $target=get_posts(['post_type'=>'cml_product','numberposts'=>1,'lang'=>'','meta_key'=>'_cml_import_key','meta_value'=>$record['key']])[0];
    wp_update_post(wp_slash(['ID'=>$target->ID,'post_content'=>$record['expectedPreviousContent']]));
    update_post_meta($target->ID,'_cml_editor_snapshot',wp_slash(cml_editor_snapshot($target->ID)));
}
unset($record);
file_put_contents('/cml-results/local-full-release.json',wp_json_encode($manifest));
echo 'Local baseline and manifest prepared.';
