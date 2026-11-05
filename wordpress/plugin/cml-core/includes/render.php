<?php
defined('ABSPATH') || exit;

function cml_render(string $view, array $attributes = []): string {
    ob_start();
    if ($view === 'header') {
        echo '<a class="skip-link" href="#main">' . esc_html(cml_t('skip')) . '</a><div class="cml-header"><a class="cml-brand" href="' . esc_url(cml_page_url('home')) . '">CHINA<span>MUDLOGGING</span><small>' . esc_html(cml_t('tagline')) . '</small></a><details class="cml-mobile"><summary>' . esc_html(cml_t('menu')) . '</summary>';
        cml_navigation(); echo '</details><div class="cml-desktop">'; cml_navigation(); echo '</div></div>';
        if (function_exists('pll_the_languages')) {
            echo '<nav class="cml-languages" aria-label="Languages">';
            $languages = pll_the_languages(['raw' => 1, 'hide_if_no_translation' => 1, 'hide_if_empty' => 1]);
            foreach (($languages ?: []) as $language) {
                if (!empty($language['no_translation'])) continue;
                echo '<a lang="' . esc_attr($language['slug']) . '" href="' . esc_url($language['url']) . '" ' . (!empty($language['current_lang']) ? 'aria-current="true"' : '') . '>' . esc_html($language['name']) . '</a>';
            }
            echo '</nav>';
        }
    } elseif ($view === 'footer') {
        echo '<div class="cml-footer"><div><strong>ChinaMudLogging.com</strong><p>' . esc_html(cml_t('tagline')) . '</p></div><div><a href="mailto:sales@chinamudlogging.com">sales@chinamudlogging.com</a><br><a href="tel:+8618354639099"><bdi>+86 183 5463 9099</bdi></a><p>' . esc_html(cml_t('location')) . '</p></div><div><a href="' . esc_url(cml_page_url('privacy')) . '">' . esc_html(cml_t('privacy')) . '</a><p>© ' . esc_html(wp_date('Y')) . ' ChinaMudLogging.com</p></div></div>';
    } elseif ($view === 'home') {
        $hero_image = get_the_post_thumbnail_url(get_the_ID(), 'full') ?: get_option('cml_hero_image', '');
        echo '<section class="cml-hero"><div><p class="eyebrow">' . esc_html(cml_t('tagline')) . '</p><h1>' . esc_html($attributes['hero'] ?? cml_t('hero')) . '</h1><p class="cml-lede">' . esc_html($attributes['intro'] ?? cml_t('intro')) . '</p><div class="cml-actions"><a class="cml-button" href="' . esc_url(cml_page_url('products')) . '">' . esc_html(cml_t('products')) . ' →</a><a class="cml-button secondary" href="' . esc_url(cml_page_url('contact')) . '">' . esc_html(cml_t('quote')) . '</a></div></div><img src="' . esc_url($hero_image) . '" alt="' . esc_attr(cml_t('hero_alt')) . '" fetchpriority="high" width="900" height="700"></section>';
        echo '<section class="cml-section"><p class="eyebrow">' . esc_html(cml_t('selection')) . '</p><h2>' . esc_html(cml_t('families')) . '</h2>'; cml_family_cards(); echo '</section>';
        echo '<section class="cml-section"><h2>' . esc_html(cml_t('featured')) . '</h2>'; cml_cards(['posts_per_page' => 10, 'meta_query' => [['key' => '_cml_source_id', 'value' => ['product:261', 'product:258', 'product:260', 'product:257', 'product:251', 'product:245', 'product:259', 'product:273', 'product:307', 'product:297'], 'compare' => 'IN']], 'orderby' => 'title', 'order' => 'ASC']); echo '</section>';
        echo '<section class="cml-band"><h2>' . esc_html(cml_t('workflow_title')) . '</h2><p>' . esc_html(cml_t('workflow')) . '</p><a class="cml-button" href="' . esc_url(cml_page_url('contact')) . '">' . esc_html(cml_t('quote')) . ' →</a></section>';
    } elseif ($view === 'catalogue') {
        if (cml_language() !== 'en') echo '<p class="cml-note"><a href="' . esc_url(cml_page_url('products', 'en')) . '">' . esc_html(cml_t('full_catalogue')) . ' →</a></p>';
        $family = isset($_GET['family']) && is_string($_GET['family']) ? sanitize_title(wp_unslash($_GET['family'])) : '';
        if (!$family && is_tax('cml_family')) $family = get_queried_object()->slug;
        $query = isset($_GET['q']) && is_string($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
        echo '<form class="cml-filter" method="get" action="' . esc_url(cml_page_url('products')) . '"><label>' . esc_html(cml_t('search')) . '<input name="q" value="' . esc_attr($query) . '" maxlength="200"></label><label>' . esc_html(cml_t('families')) . '<select name="family"><option value="">' . esc_html(cml_t('all')) . '</option>';
        $terms = get_terms(['taxonomy' => 'cml_family', 'hide_empty' => true]);
        foreach (is_wp_error($terms) ? [] : $terms as $term) echo '<option value="' . esc_attr($term->slug) . '" ' . selected($family, $term->slug, false) . '>' . esc_html($term->name) . '</option>';
        echo '</select></label><button class="cml-button" type="submit">' . esc_html(cml_t('search')) . '</button></form>';
        $args = ['posts_per_page' => 12, 'paged' => max(1, absint($_GET['catalog_page'] ?? 1)), 's' => mb_substr($query, 0, 200)];
        if (is_tax('cml_family')) $args['tax_query'] = [['taxonomy' => 'cml_family', 'field' => 'term_id', 'terms' => get_queried_object_id()]];
        elseif ($family) $args['tax_query'] = [['taxonomy' => 'cml_family', 'field' => 'slug', 'terms' => $family]];
        cml_cards($args, true);
    } elseif ($view === 'product') {
        $id = get_the_ID();
        echo '<div class="cml-product-top"><div>' . get_the_post_thumbnail($id, 'large', ['class' => 'cml-product-image']) . '</div><div><p class="eyebrow">' . esc_html(cml_t('products')) . '</p><h1>' . esc_html(get_the_title()) . '</h1><p>' . esc_html(get_the_excerpt()) . '</p><dl class="cml-specs">';
        foreach (['sku' => 'model', 'weight' => 'weight', 'signal' => 'signal', 'range' => 'range', 'interface' => 'interface'] as $key => $label) {
            $value = get_post_meta($id, '_cml_' . $key, true);
            if ($value !== '') echo '<div><dt>' . esc_html(cml_t($label)) . '</dt><dd><bdi>' . esc_html($value) . '</bdi></dd></div>';
        }
        echo '</dl><a class="cml-button" href="' . esc_url(add_query_arg('product', rawurlencode(html_entity_decode(get_the_title(), ENT_QUOTES | ENT_HTML5, 'UTF-8')), cml_page_url('contact'))) . '">' . esc_html(cml_t('quote')) . ' →</a><p class="cml-note">' . esc_html(cml_t('confirmation')) . '</p></div></div>';
    } elseif ($view === 'inquiry') {
        echo '<div class="cml-inquiry-layout"><aside><p class="eyebrow">' . esc_html(cml_t('contact')) . '</p><h2>' . esc_html(cml_t('quote')) . '</h2><p>' . esc_html(cml_t('form_intro')) . '</p><a href="mailto:sales@chinamudlogging.com">sales@chinamudlogging.com</a><p><bdi>+86 183 5463 9099</bdi></p></aside><form data-cml-inquiry data-token="' . esc_url(rest_url('cml/v1/form-token')) . '" data-endpoint="' . esc_url(rest_url('cml/v1/inquiries')) . '" data-sending="' . esc_attr(cml_t('sending')) . '" data-saved="' . esc_attr(cml_t('saved')) . '" data-error="' . esc_attr(cml_t('form_error')) . '">';
        foreach (['name', 'email', 'company', 'country', 'product'] as $field) {
            $required = in_array($field, ['name', 'email'], true) ? 'required' : '';
            $autocomplete = ['name' => 'name', 'email' => 'email', 'company' => 'organization', 'country' => 'country-name', 'product' => 'off'][$field];
            echo '<label>' . esc_html(cml_t($field)) . ($required ? ' *' : '') . '<input name="' . esc_attr($field) . '" type="' . ($field === 'email' ? 'email' : 'text') . '" autocomplete="' . esc_attr($autocomplete) . '" maxlength="' . (['name' => 160, 'email' => 254, 'company' => 200, 'country' => 120, 'product' => 300][$field]) . '" ' . $required . '></label>';
        }
        echo '<label>' . esc_html(cml_t('message')) . ' *<textarea name="message" rows="6" maxlength="2500" required></textarea></label><div class="cml-trap" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div><input type="hidden" name="language" value="' . esc_attr(cml_language()) . '"><label class="cml-consent"><input type="checkbox" name="consent" required> ' . esc_html(cml_t('consent')) . ' <a href="' . esc_url(cml_page_url('privacy')) . '">' . esc_html(cml_t('privacy')) . '</a></label><button class="cml-button" type="submit">' . esc_html(cml_t('submit')) . '</button><p role="status" aria-live="polite"></p><noscript>' . esc_html(cml_t('form_error')) . '</noscript></form></div>';
    } elseif ($view === 'measurement') {
        echo '<section><h2>' . esc_html(cml_t('measurement')) . '</h2><p>' . esc_html(cml_t('measurement_note')) . '</p><button type="button" class="cml-button secondary" id="measurement-toggle" data-disabled-text="' . esc_attr(cml_t('measurement_disabled')) . '" data-enabled-text="' . esc_attr(cml_t('measurement_enabled')) . '">' . esc_html(cml_t('measurement_toggle')) . '</button><p id="measurement-status" role="status">' . esc_html(cml_t('measurement_enabled')) . '</p></section>';
    } elseif ($view === 'applications') {
        echo '<div class="cml-grid">';
        foreach (['app1', 'app2', 'app3'] as $key) echo '<article class="cml-card"><h2>' . esc_html(cml_t($key)) . '</h2><p>' . esc_html(cml_t($key . '_body')) . '</p><a href="' . esc_url(cml_page_url('contact')) . '">' . esc_html(cml_t('quote')) . ' →</a></article>';
        echo '</div>';
    }
    return (string)ob_get_clean();
}
function cml_navigation(): void {
    echo '<nav class="cml-nav" aria-label="' . esc_attr(cml_t('menu')) . '">';
    foreach (['products', 'applications', 'services', 'about', 'insights', 'contact'] as $key) {
        // English-only Insights remains accessible without pretending it is translated.
        if ($key === 'insights' && cml_language() !== 'en') continue;
        $active = rtrim(wp_parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') === rtrim(wp_parse_url(cml_page_url($key), PHP_URL_PATH) ?: '/', '/') || ($key === 'products' && (is_singular('cml_product') || is_tax('cml_family')));
        echo '<a href="' . esc_url(cml_page_url($key)) . '"' . ($key === 'contact' ? ' class="cml-nav-cta"' : '') . ($active ? ' aria-current="page"' : '') . '>' . esc_html(cml_t($key)) . '</a>';
    }
    echo '</nav>';
}
function cml_family_cards(): void {
    $terms = get_terms(['taxonomy' => 'cml_family', 'hide_empty' => true]);
    echo '<div class="cml-grid cml-families">';
    foreach (is_wp_error($terms) ? [] : $terms as $term) {
        echo '<a class="cml-card" href="' . esc_url(get_term_link($term)) . '"><span class="eyebrow">' . esc_html($term->count) . ' · ' . esc_html(cml_t('products')) . '</span><h3>' . esc_html($term->name) . '</h3><span>→</span></a>';
    }
    echo '</div>';
}
function cml_cards(array $args, bool $paginate = false): void {
    $query = new WP_Query(array_merge(['post_type' => 'cml_product', 'post_status' => 'publish', 'lang' => cml_language()], $args));
    echo '<div class="cml-grid">';
    foreach ($query->posts as $post) {
        echo '<article class="cml-card">' . get_the_post_thumbnail($post, 'medium_large', ['loading' => 'lazy']) . '<h3><a href="' . esc_url(get_permalink($post)) . '">' . esc_html(get_the_title($post)) . '</a></h3><p>' . esc_html(get_the_excerpt($post)) . '</p><a href="' . esc_url(get_permalink($post)) . '">' . esc_html(cml_t('specifications')) . ' →</a></article>';
    }
    echo '</div>';
    if (!$query->post_count) echo '<p>' . esc_html(cml_t('no_results')) . '</p>';
    if ($paginate && $query->max_num_pages > 1) {
        echo '<nav class="cml-pagination" aria-label="Pagination">' . wp_kses_post(paginate_links([
            'base' => add_query_arg('catalog_page', '%#%'), 'format' => '',
            'current' => max(1, (int)($args['paged'] ?? 1)), 'total' => $query->max_num_pages,
        ])) . '</nav>';
    }
}
