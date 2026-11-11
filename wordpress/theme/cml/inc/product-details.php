<?php
/** Reviewed public presentation; original post and metadata remain the source record. */
defined('ABSPATH') || exit;
function cml_detail_data(): array {
    static $data;
    if ($data === null) $data=json_decode(file_get_contents(__DIR__.'/../assets/product-details.json'),true) ?: [];
    return $data;
}
function cml_detail_sorted($value) {
    if (!is_array($value)) return $value;
    if (array_keys($value)!==range(0,count($value)-1)) ksort($value,SORT_STRING);
    return array_map('cml_detail_sorted',$value);
}
function cml_detail_fingerprint(int $id): string {
    $specs=get_post_meta($id,'_cml_specifications',true);
    if (!is_array($specs)) $specs=[];
    return hash('sha256',get_post_field('post_content',$id,'raw')."\n".get_post_meta($id,'_cml_sku',true)."\n".json_encode(cml_detail_sorted($specs),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_LINE_TERMINATORS)."\n".get_post_meta($id,'_cml_source_id',true));
}
function cml_detail_record(int $id): ?array {
    $data=cml_detail_data(); $r=$data['products'][(string)$id]??null;
    if (!$r || get_post_status($id)!=='publish' || get_post_field('post_name',$id)!==$r['slug'] || !hash_equals($r['fingerprint'],cml_detail_fingerprint($id))) return null;
    $lang=function_exists('pll_get_post_language') ? pll_get_post_language($id) : 'en';
    return $lang===$r['language'] ? $r : null;
}
function cml_detail_citations(array $ids,string $language): string {
    $labels=['en'=>'Source','zh'=>'来源','es'=>'Fuente','ru'=>'Источник','ar'=>'المصدر','fr'=>'Source','de'=>'Quelle'];
    $links=[];$sources=cml_detail_data()['sources']??[];
    foreach(array_unique($ids) as $id){
        if(!isset($sources[$id]))continue;
        $s=$sources[$id];
        $links[]='<a href="'.esc_url($s['url']).'">'.esc_html($s['title'].($s['locator']?' · '.$s['locator']:'')).'</a>';
    }
    return $links ? '<small class="cml-detail-citations">'.esc_html($labels[$language]??$labels['en']).': '.implode(' · ',$links).'</small>' : '';
}
add_action('wp_enqueue_scripts',function () {
    if (is_singular('cml_product')) wp_enqueue_style('cml-product-details',get_theme_file_uri('assets/product-details.css'),['cml-site'],filemtime(get_theme_file_path('assets/product-details.css')));
},70);
add_filter('render_block_core/post-content',function (string $html): string {
    if (is_admin() || !is_singular('cml_product')) return $html;
    $id=get_queried_object_id(); $r=cml_detail_record($id);
    if (!$r) return $html;
    $c=cml_detail_data()['copy'][$r['language']];
    $out='<section class="cml-reviewed-details" data-product-detail-review="20261105"><h2>'.esc_html($c['heading']).'</h2>';
    if ($r['intro']) $out.='<p>'.esc_html($r['intro']).'</p>';
    $out.='<h3>'.esc_html($c['parameters']).'</h3><p class="cml-detail-note">'.esc_html($c['note']).'</p><table><thead><tr><th scope="col">'.esc_html($c['field']).'</th><th scope="col">'.esc_html($c['value']).'</th></tr></thead><tbody>';
    foreach ($r['rows'] as $row) {
        $out.='<tr><th scope="row">'.esc_html($row['label']).'</th><td>';
        if ($row['status']) {
            $out.='<span class="cml-detail-pending">'.esc_html($c['pending']).'</span>';
            if ($row['status']!=='pending') $out.='<small>'.esc_html($c[$row['status']]).'</small>';
        } else $out.='<bdi dir="ltr">'.esc_html($row['value']).'</bdi>';
        if(!empty($row['note']))$out.='<small>'.esc_html($row['note']).'</small>';
        $out.=cml_detail_citations($row['citations']??[],$r['language']);
        $out.='</td></tr>';
    }
    $out.='</tbody></table><h3>'.esc_html($c['selection']).'</h3><ul>';
    foreach ($r['checks'] as $check) $out.='<li>'.esc_html($check).'</li>';
    $out.='</ul>';
    if ($r['links']) {
        $out.='<h3>'.esc_html($c['documents']).'</h3><ul>';
        foreach ($r['links'] as $i=>$url) $out.='<li><a href="'.esc_url($url).'">'.esc_html((stripos($url,'.pdf')!==false?$c['manual']:$c['catalogue']).(count($r['links'])>1?' '.($i+1):'')).'</a></li>';
        $out.='</ul>';
    }
    return $out.'</section>';
},1000);
// The top summary must not advertise a source SKU as a verified supplied model.
add_filter('render_block',function (string $html,array $block): string {
    if (is_admin() || !is_singular('cml_product') || strpos($html,'class="cml-product-top"')===false) return $html;
    $r=cml_detail_record(get_queried_object_id()); if (!$r) return $html;
    $c=cml_detail_data()['copy'][$r['language']];
    $summary='<dl class="cml-specs"><div><dt>'.esc_html($r['designation']?$c['designation']:$c['status']).'</dt><dd>'.($r['designation']?'<bdi dir="ltr">'.esc_html($r['designation']).'</bdi>':esc_html($c['confirm'])).cml_detail_citations($r['designation_citations']??[],$r['language']).'</dd></div></dl>';
    return preg_replace('/<dl class="cml-specs">.*?<\/dl>/s',$summary,$html,1);
},1000,2);
