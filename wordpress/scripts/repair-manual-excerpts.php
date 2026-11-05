<?php
/** Run only through wp eval-file after a fresh database backup. */
if (!defined('WP_CLI') || !WP_CLI) exit(1);
$replacement = [
1670=>'RADA-32 DDLJA5 radar level transmitter manual and source specifications. Confirm the supplied configuration and certification before ordering.',
1671=>'SK-2N01G shale density meter datasheet and Chinese manual. Confirm the supplied instrument and dimensions before ordering.',
1672=>'YC-03D electric mud degasser technical reference from the source quotation. Conflicting explosion-protection markings require nameplate confirmation.',
1673=>'Wireless hydrogen sulfide sensor category manual. The specific product model and supplied configuration require nameplate confirmation.',
1674=>'Wireless drawworks and winch sensor category manual. Confirm the specific model and installation interface for your application.',
1675=>'Wireless standpipe pressure sensor category manual. Confirm the specific model, pressure range and output before ordering.',
1676=>'IML-P10K casing pressure sensor manual and source specifications. Confirm the supplied configuration and certification before ordering.',
1677=>'Wireless pump-stroke sensor category manual. Confirm the specific model, installation gap and interface before ordering.',
1678=>'Bee TRQ electric torque sensor manual. Confirm the supplied sensor, wireless node and electrical interface before ordering.',
];
$check=[];
foreach ($replacement as $id=>$excerpt) {
    $post=get_post($id);
    if (!$post || $post->post_type!=='cml_product' || $post->post_status!=='publish' || stripos($post->post_title,'manual')===false) WP_CLI::error('Manual identity/status changed: '.$id);
    if ($post->post_excerpt!==$excerpt && !preg_match('/draft.*Not published\./is',$post->post_excerpt)) WP_CLI::error('Concurrent excerpt changed: '.$id);
    $check[$id]=['before'=>$post->post_excerpt,'after'=>$excerpt,'title'=>$post->post_title,'url'=>get_permalink($id)];
}
if (getenv('CML_APPLY_MANUAL_EXCERPTS')!=='1') { echo wp_json_encode(['dry_run'=>true,'changes'=>$check],JSON_PRETTY_PRINT);return; }
foreach ($replacement as $id=>$excerpt) {
    if (get_post_field('post_excerpt',$id)!==$excerpt) {
        $result=wp_update_post(['ID'=>$id,'post_excerpt'=>$excerpt],true);
        if (is_wp_error($result)) WP_CLI::error($result->get_error_message());
    }
    clean_post_cache($id);
}
echo wp_json_encode(['applied'=>count($replacement),'changes'=>$check],JSON_PRETTY_PRINT);
