<?php
/**
 * Plugin Name: ChinaMudLogging Full Translation Release
 * Description: Administrator-only, checksummed release of the approved sixty product translations. Deactivate after verification.
 * Version: 1.0.0
 */
defined('ABSPATH') || exit;
const CML_FULL_MANIFEST_SHA = '29233fab0251578eac0b492b55f0a3e564d3e69bbd34823172cbd165db5ed4e3';
function cml_full_admin_run(string $json, string $action): array {
    if (!current_user_can('manage_options')) throw new RuntimeException('Administrator access required.');
    if (!hash_equals(CML_FULL_MANIFEST_SHA,hash('sha256',$json))) throw new RuntimeException('The approved manifest checksum does not match.');
    $dir=trailingslashit(get_temp_dir()).'cml-full-'.wp_generate_password(20,false,false);
    if (!mkdir($dir,0700)) throw new RuntimeException('Cannot create a private release directory.');
    $file=$dir.'/publish.json';file_put_contents($file,$json,LOCK_EX);chmod($file,0600);
    $args=[$file];$cml_full_action=$action;
    if (!defined('CML_FULL_RELEASE_ADMIN')) define('CML_FULL_RELEASE_ADMIN',true);
    ob_start();
    try {
        require __DIR__.'/publish-full-translations.php';
        $report=json_decode(ob_get_contents(),true,512,JSON_THROW_ON_ERROR);
        $backup=$dir.'/before-full-translations-v1.json';
        if (is_file($backup)) update_option('cml_full_release_rollback',file_get_contents($backup),false);
        return $report;
    } finally {
        ob_end_clean();
        $backup=$dir.'/before-full-translations-v1.json';
        if (is_file($backup)) update_option('cml_full_release_rollback',file_get_contents($backup),false);
        // Only these newly created temporary files are removed. The rollback
        // copy remains private in WordPress options and is downloadable by admins.
        foreach ([$file,$dir.'/before-full-translations-v1.json'] as $temporary) if (is_file($temporary)) unlink($temporary);
        rmdir($dir);
    }
}
add_action('admin_menu',function(){add_management_page('Full product translations','Full product translations','manage_options','cml-full-release','cml_full_admin_page');});
add_action('admin_post_cml_full_release',function(){
    if (!current_user_can('manage_options')) wp_die('Administrator access required.');
    check_admin_referer('cml_full_release');
    $action=sanitize_key($_POST['release_action'] ?? '');
    try {
        if ($action==='check') {
            if (empty($_FILES['manifest']['tmp_name']) || !is_uploaded_file($_FILES['manifest']['tmp_name']) || $_FILES['manifest']['size']>4000000) throw new RuntimeException('Upload the approved JSON manifest.');
            $json=file_get_contents($_FILES['manifest']['tmp_name']);
        } else $json=get_option('cml_full_release_manifest','');
        if (!in_array($action,['check','apply','verify'],true)) throw new RuntimeException('Invalid action.');
        $report=cml_full_admin_run($json,$action);
        if ($action==='check') update_option('cml_full_release_manifest',$json,false);
        update_option('cml_full_release_report',['time'=>gmdate('c'),'result'=>$report],false);
        delete_option('cml_full_release_error');
    } catch (Throwable $e) {update_option('cml_full_release_error',$e->getMessage(),false);}
    wp_safe_redirect(admin_url('tools.php?page=cml-full-release'));exit;
});
add_action('admin_post_cml_full_rollback_download',function(){
    if (!current_user_can('manage_options')) wp_die('Administrator access required.');
    check_admin_referer('cml_full_rollback_download');
    $backup=get_option('cml_full_release_rollback','');if (!$backup) wp_die('No rollback has been generated.');
    nocache_headers();header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename="cml-before-full-translations.json"');echo $backup;exit;
});
function cml_full_admin_page(): void {
    if (!current_user_can('manage_options')) return;
    echo '<div class="wrap"><h1>Full product translations</h1><p>Approved scope: 10 existing products × zh / es / ru / ar / fr / de. Existing routes, images, language associations and English content must match the frozen baseline. No inquiries or users are changed.</p><p>Approved manifest SHA256: <code>'.esc_html(CML_FULL_MANIFEST_SHA).'</code></p>';
    $error=get_option('cml_full_release_error','');if ($error) echo '<div class="notice notice-error"><p>'.esc_html($error).'</p></div>';
    $report=get_option('cml_full_release_report',[]);
    if ($report) echo '<h2>Last verified operation</h2><pre style="white-space:pre-wrap;max-height:36em;overflow:auto">'.esc_html(wp_json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)).'</pre>';
    echo '<form method="post" enctype="multipart/form-data" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('cml_full_release');
    echo '<input type="hidden" name="action" value="cml_full_release"><label>Approved publication manifest <input type="file" name="manifest" accept="application/json,.json"></label><p><button class="button" name="release_action" value="check">Preflight all 60 translations</button></p>';
    if (get_option('cml_full_release_manifest','')) echo '<p><button class="button button-primary" name="release_action" value="apply">Publish approved full translations</button> <button class="button" name="release_action" value="verify">Verify all 60 published translations</button></p>';
    echo '</form>';
    if (get_option('cml_full_release_rollback','')) echo '<p><a class="button" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=cml_full_rollback_download'),'cml_full_rollback_download')).'">Download private rollback records</a></p>';
    echo '<p>After verification, download the rollback records and deactivate this release module. WordPress revisions also retain the previous bodies.</p></div>';
}
