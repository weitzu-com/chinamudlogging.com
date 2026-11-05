<?php
/** Centralized language panel, based on the observed Tesla region/language pattern. */
defined('ABSPATH') || exit;
function cml_locale_copy(): array {
    $rows = [
        'en'=>['Language','Choose your language','Close','Global','Asia Pacific','Middle East','Europe','Current page','Product catalogue','Home'],
        'zh'=>['语言','选择语言','关闭','全球','亚太','中东','欧洲','当前页面','产品目录','首页'],
        'es'=>['Idioma','Elija su idioma','Cerrar','Global','Asia Pacífico','Oriente Medio','Europa','Página actual','Catálogo de productos','Inicio'],
        'ru'=>['Язык','Выберите язык','Закрыть','Весь мир','Азиатско-Тихоокеанский регион','Ближний Восток','Европа','Текущая страница','Каталог продукции','Главная'],
        'ar'=>['اللغة','اختر لغتك','إغلاق','عالمي','آسيا والمحيط الهادئ','الشرق الأوسط','أوروبا','الصفحة الحالية','كتالوج المنتجات','الرئيسية'],
        'fr'=>['Langue','Choisissez votre langue','Fermer','Monde','Asie-Pacifique','Moyen-Orient','Europe','Page actuelle','Catalogue de produits','Accueil'],
        'de'=>['Sprache','Wählen Sie Ihre Sprache','Schließen','Weltweit','Asien-Pazifik','Naher Osten','Europa','Aktuelle Seite','Produktkatalog','Startseite'],
    ];
    return array_combine(['label','heading','close','global','asia','middle','europe','page','catalogue','home'], $rows[cml_language()] ?? $rows['en']);
}
function cml_locale_links(): array {
    $raw = function_exists('pll_the_languages') ? pll_the_languages(['raw'=>1,'hide_if_no_translation'=>0,'hide_if_empty'=>0]) : [];
    $by_lang = [];
    foreach (($raw ?: []) as $row) $by_lang[$row['slug']] = $row;
    $names = ['en'=>'English','zh'=>'简体中文','es'=>'Español','ru'=>'Русский','ar'=>'العربية','fr'=>'Français','de'=>'Deutsch'];
    $links = [];
    foreach ($names as $lang=>$name) {
        $row = $by_lang[$lang] ?? [];
        $available = !empty($row['url']) && empty($row['no_translation']);
        $target = $available ? $row['url'] : cml_page_url('home',$lang);
        $note = $available ? 'page' : 'home';
        if (is_singular('cml_product')) {
            $id = function_exists('pll_get_post') ? pll_get_post(get_queried_object_id(),$lang) : 0;
            $available = $id && get_post_status($id)==='publish';
            $target = $available ? get_permalink($id) : cml_page_url('products',$lang);
            $note = $available ? 'page' : 'catalogue';
        } elseif (is_tax('cml_family')) {
            $id = function_exists('pll_get_term') ? pll_get_term(get_queried_object_id(),$lang) : 0;
            $term_url = $id ? get_term_link((int)$id,'cml_family') : false;
            $available = $term_url && !is_wp_error($term_url);
            $target = $available ? $term_url : cml_page_url('products',$lang);
            $note = $available ? 'page' : 'catalogue';
        }
        // Keep an explicitly selected product when switching inquiry language.
        if (is_page() && has_block('cml/inquiry',get_post()) && isset($_GET['product']) && is_string($_GET['product'])) {
            $product = mb_substr(sanitize_text_field(wp_unslash($_GET['product'])),0,300);
            $target = add_query_arg('product',rawurlencode($product),$target);
        }
        $links[$lang] = ['name'=>$name,'url'=>$target,'note'=>$note];
    }
    return $links;
}
add_action('wp_enqueue_scripts',function () {
    wp_enqueue_style('cml-language-panel',get_theme_file_uri('assets/language-panel.css'),['cml-site'],filemtime(get_theme_file_path('assets/language-panel.css')));
    wp_enqueue_script('cml-language-panel',get_theme_file_uri('assets/language-panel.js'),[],filemtime(get_theme_file_path('assets/language-panel.js')),true);
},50);
add_filter('render_block_cml/header',function (string $original): string {
    if (is_admin() || !function_exists('cml_t')) return $original;
    $copy=cml_locale_copy();$links=cml_locale_links();$current=cml_language();
    $groups=['global'=>['en'],'asia'=>['zh'],'middle'=>['ar'],'europe'=>['es','fr','de','ru']];
    ob_start(); ?>
    <a class="skip-link" href="#main"><?php echo esc_html(cml_t('skip')); ?></a>
    <div class="cml-header cml-header-global">
      <a class="cml-brand" href="<?php echo esc_url(cml_page_url('home')); ?>">CHINA<span>MUDLOGGING</span><small><?php echo esc_html(cml_t('tagline')); ?></small></a>
      <div class="cml-header-tools"><div class="cml-desktop"><?php cml_navigation(); ?></div>
        <button class="cml-locale-button" type="button" data-locale-open aria-haspopup="dialog" aria-controls="cml-locale-dialog" aria-label="<?php echo esc_attr($copy['label'].': '.($links[$current]['name']??'English')); ?>" hidden>
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="9"/><ellipse cx="12" cy="12" rx="4" ry="9"/><path d="M3 12h18"/></svg><span><?php echo esc_html($links[$current]['name']??'English'); ?></span>
        </button>
        <details class="cml-mobile"><summary><?php echo esc_html(cml_t('menu')); ?></summary><?php cml_navigation(); ?></details>
      </div>
    </div>
    <dialog id="cml-locale-dialog" class="cml-locale-dialog" aria-labelledby="cml-locale-title">
      <div class="cml-locale-top"><h2 id="cml-locale-title"><?php echo esc_html($copy['heading']); ?></h2><button type="button" data-locale-close aria-label="<?php echo esc_attr($copy['close']); ?>">×</button></div>
      <nav class="cml-locale-grid" aria-label="<?php echo esc_attr($copy['label']); ?>">
      <?php foreach ($groups as $group=>$langs): ?><section><h3><?php echo esc_html($copy[$group]); ?></h3>
        <?php foreach ($langs as $lang): $link=$links[$lang]; ?><a href="<?php echo esc_url($link['url']); ?>" hreflang="<?php echo esc_attr($lang); ?>" data-locale="<?php echo esc_attr($lang); ?>" <?php echo $lang===$current?'aria-current="true"':''; ?>><bdi lang="<?php echo esc_attr($lang); ?>"><?php echo esc_html($link['name']); ?></bdi><small><?php echo esc_html($copy[$link['note']]); ?></small></a><?php endforeach; ?>
      </section><?php endforeach; ?>
      </nav>
    </dialog>
    <noscript><nav class="cml-locale-fallback" aria-label="<?php echo esc_attr($copy['label']); ?>"><?php foreach($links as $lang=>$link) echo '<a lang="'.esc_attr($lang).'" href="'.esc_url($link['url']).'">'.esc_html($link['name']).'</a>'; ?></nav></noscript>
    <?php return (string)ob_get_clean();
});
