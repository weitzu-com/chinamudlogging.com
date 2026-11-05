<?php
/** Presentation derivatives only: original attachment IDs/files remain source evidence. */
defined('ABSPATH') || exit;
// These legacy homepage URLs have a one-year CDN/browser cache lifetime.
// Content versioning makes their replacement visible without global cache purge.
add_filter('theme_file_uri',function (string $uri,string $file): string {
    if (!in_array($file,['assets/field/unit.webp','assets/field/sensor.webp','assets/field/wireless.webp','assets/field/formation.webp','assets/field/drilling.webp'],true)) return $uri;
    $path=get_theme_file_path($file);
    return is_file($path) ? add_query_arg('v',substr(hash_file('sha256',$path),0,16),$uri) : $uri;
},20,2);
add_action('wp_enqueue_scripts',function () {
    wp_enqueue_style('cml-brand-media',get_theme_file_uri('assets/branded-products.css'),['cml-site'],filemtime(get_theme_file_path('assets/branded-products.css')));
},60);
function cml_brand_asset(int $thumbnail): ?array {
    static $map = null, $resolved = [];
    if (array_key_exists($thumbnail,$resolved)) return $resolved[$thumbnail];
    if ($map === null) {
        $file = get_theme_file_path('assets/catalogue/manifest.json');
        $data = is_readable($file) ? json_decode(file_get_contents($file),true) : [];
        $map = [];
        foreach (($data['images'] ?? []) as $image) $map[$image['source_sha256']] = $image;
    }
    $file = get_attached_file($thumbnail);
    $uploads = wp_upload_dir();
    if (!$file || !is_file($file) || !str_starts_with(wp_normalize_path($file),wp_normalize_path($uploads['basedir']).'/')) return $resolved[$thumbnail] = null;
    $hash = hash_file('sha256',$file);
    $asset = $map[$hash] ?? null;
    if (!$asset || !is_file(get_theme_file_path('assets/catalogue/'.$asset['file']))) return $resolved[$thumbnail] = null;
    return $resolved[$thumbnail] = $asset;
}
add_filter('post_thumbnail_html',function (string $html,int $post_id,int $thumbnail,$size,$attr): string {
    if (is_admin() || get_post_type($post_id)!=='cml_product' || get_post_status($post_id)!=='publish') return $html;
    $asset=cml_brand_asset($thumbnail);
    if (!$asset) return $html;
    $attr=is_array($attr)?$attr:[];
    $full=get_theme_file_uri('assets/catalogue/'.$asset['file']);
    $small=get_theme_file_uri('assets/catalogue/'.$asset['small']);
    $large=in_array($size,['full','large'],true);
    $hero=is_singular('cml_product') && get_queried_object_id()===$post_id;
    $alt=$attr['alt']??get_post_meta($thumbnail,'_wp_attachment_image_alt',true);
    if (!$alt) $alt=wp_specialchars_decode(get_the_title($post_id),ENT_QUOTES);
    $class=trim(($attr['class']??'wp-post-image').' cml-branded-image');
    $sizes=$hero?'(max-width: 700px) calc(100vw - 2rem), 50vw':'(max-width: 700px) calc(100vw - 4rem), (max-width: 1100px) 45vw, 30vw';
    return '<img src="'.esc_url($large?$full:$small).'" srcset="'.esc_url($small).' 800w, '.esc_url($full).' 1200w" sizes="'.esc_attr($sizes).'" width="1200" height="900" class="'.esc_attr($class).'" alt="'.esc_attr($alt).'" loading="'.($hero?'eager':'lazy').'" decoding="async"'.($hero?' fetchpriority="high"':'').'>';
},30,5);
add_filter('post_thumbnail_url',function ($url,$post,$size) {
    $post=get_post($post);
    if (is_admin() || !$post || $post->post_type!=='cml_product' || $post->post_status!=='publish') return $url;
    $asset=cml_brand_asset((int)get_post_thumbnail_id($post));
    return $asset ? get_theme_file_uri('assets/catalogue/'.$asset['file']) : $url;
},30,3);
foreach (['wpseo_opengraph_image','wpseo_twitter_image'] as $hook) add_filter($hook,function ($url) {
    return is_singular('cml_product') ? (get_the_post_thumbnail_url(get_queried_object_id(),'full') ?: $url) : $url;
},30);
