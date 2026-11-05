<?php
/**
 * Plugin Name: ChinaMudLogging Reviewed Article
 * Description: Keep the reviewed unit-task article visible instead of wrapping it as legacy source.
 * Version: 1.0.0
 */
defined('ABSPATH')||exit;
function cml_reviewed_unit_article():bool{
 $post=get_queried_object();
 return is_singular('post')&&$post instanceof WP_Post&&$post->post_name==='what-is-the-main-task-of-a-mud-logging-unit'&&get_post_meta($post->ID,'_cml_content_review_batch',true)==='source-content-20261105';
}
add_filter('render_block_core/post-content',function($html){
 if(cml_reviewed_unit_article())$GLOBALS['cml_reviewed_article_native_html']=$html;
 return $html;
},119);
add_filter('render_block_core/post-content',function($html){
 if(!cml_reviewed_unit_article()||!isset($GLOBALS['cml_reviewed_article_native_html']))return $html;
 $native=$GLOBALS['cml_reviewed_article_native_html'];unset($GLOBALS['cml_reviewed_article_native_html']);return $native;
},121);
add_filter('wpseo_metadesc',function($description){
 return cml_reviewed_unit_article()?(string)get_post_meta(get_queried_object_id(),'_yoast_wpseo_metadesc',true):$description;
},121);
