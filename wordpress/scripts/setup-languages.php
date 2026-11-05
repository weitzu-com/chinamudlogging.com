<?php
/** Run once with wp eval-file after activating Polylang 3.9.1 and cml-core. */
if (!defined('ABSPATH')) throw new RuntimeException('Run inside WordPress.');
if (!function_exists('PLL') || !isset(PLL()->model->languages)) throw new RuntimeException('Polylang 3.9.1 required.');
$languages = [
    ['en', 'en_US', 'English'], ['zh', 'zh_CN', '中文'], ['es', 'es_ES', 'Español'],
    ['ru', 'ru_RU', 'Русский'], ['ar', 'ar', 'العربية'], ['fr', 'fr_FR', 'Français'], ['de', 'de_DE', 'Deutsch'],
];
foreach ($languages as $order => [$slug, $locale, $name]) {
    if (!PLL()->model->languages->get($slug)) {
        $result = PLL()->model->languages->add(['slug' => $slug, 'locale' => $locale, 'name' => $name, 'rtl' => $slug === 'ar', 'term_group' => $order]);
        if (is_wp_error($result)) throw new RuntimeException($result->get_error_message());
    }
}
foreach (['default_lang' => 'en', 'force_lang' => 1, 'hide_default' => true, 'rewrite' => true, 'browser' => false, 'redirect_lang' => true] as $key => $value) {
    $result = PLL()->options->set($key, $value);
    if ($result->has_errors()) throw new RuntimeException($result->get_error_message());
}
PLL()->options->save();
flush_rewrite_rules();
