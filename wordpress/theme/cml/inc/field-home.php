<?php
/** Field / Industrial homepage presentation; catalogue and inquiries stay in Core. */
defined('ABSPATH') || exit;

function cml_field_is_home(): bool {
    return is_page() && has_block('cml/home', get_post());
}

add_filter('body_class', function (array $classes): array {
    if (cml_field_is_home()) $classes[] = 'cml-field-home';
    return $classes;
});
add_action('wp_enqueue_scripts', function () {
    if (!cml_field_is_home()) return;
    wp_enqueue_style('cml-field-home', get_theme_file_uri('assets/field-home.css'), ['cml-site'], filemtime(get_theme_file_path('assets/field-home.css')));
});

add_filter('render_block_cml/home', function (string $content, array $block): string {
    if (!function_exists('cml_t') || !cml_field_is_home()) return $content;
    $attributes = $block['attrs'] ?? [];
    $english = cml_language() === 'en';
    $headline = $attributes['hero'] ?? ($english ? 'Mud logging equipment. Selected for your wellsite.' : cml_t('hero'));
    $intro = $attributes['intro'] ?? ($english ? 'Units, sensors and formation evaluation tools. Share your measurement and interface requirements for a configuration review.' : cml_t('intro'));
    $hero = get_theme_file_uri('assets/field/rig-dusk.webp');
    $families = get_terms(['taxonomy' => 'cml_family', 'hide_empty' => true]);
    $images = [
        'mud-logging-units' => 'unit.webp',
        'mud-logging-sensors' => 'sensor.webp',
        'wireless-sensors' => 'wireless.webp',
        'formation-evaluation' => 'formation.webp',
        'drilling-monitoring' => 'drilling.webp',
    ];
    $order = array_keys($images);
    $families = is_wp_error($families) ? [] : $families;
    usort($families, function ($a, $b) use ($order) {
        $rank = function ($term) use ($order) {
            $slug = preg_replace('/-(zh|es|ru|ar|fr|de)$/', '', $term->slug);
            $position = array_search($slug, $order, true);
            return $position === false ? count($order) : $position;
        };
        return $rank($a) <=> $rank($b);
    });
    ob_start(); ?>
    <div class="field-landing" data-design="field-industrial-a">
      <section class="field-hero" aria-labelledby="field-title">
        <img class="field-hero-image" src="<?php echo esc_url($hero); ?>" width="1672" height="941" alt="" fetchpriority="high" decoding="async">
        <div class="field-hero-shade" aria-hidden="true"></div>
        <div class="field-wrap field-hero-content">
          <p class="field-kicker"><?php echo esc_html(cml_t('tagline')); ?></p>
          <h1 id="field-title"><?php echo esc_html($headline); ?></h1>
          <p class="field-intro"><?php echo esc_html($intro); ?></p>
          <div class="cml-actions">
            <a class="cml-button" href="<?php echo esc_url(cml_page_url('products')); ?>"><?php echo esc_html($english ? 'Explore equipment' : cml_t('products')); ?> <span aria-hidden="true">↗</span></a>
            <a class="cml-button secondary" href="<?php echo esc_url(cml_page_url('contact')); ?>"><?php echo esc_html(cml_t('quote')); ?></a>
          </div>
        </div>
      </section>
      <section class="field-wrap field-families" aria-labelledby="field-families-title">
        <div class="field-section-label"><h2 id="field-families-title"><?php echo esc_html(cml_t('families')); ?></h2><span><?php echo esc_html(cml_t('selection')); ?></span></div>
        <div class="field-family-grid">
        <?php foreach ($families as $index => $family):
            $link = get_term_link($family);
            if (is_wp_error($link)) continue;
            $slug = preg_replace('/-(zh|es|ru|ar|fr|de)$/', '', $family->slug);
            $image = $images[$slug] ?? '';
        ?>
          <a class="field-family" href="<?php echo esc_url($link); ?>">
            <span class="field-index" aria-hidden="true"><?php echo esc_html(sprintf('%02d', $index + 1)); ?></span>
            <?php if ($image): ?><img src="<?php echo esc_url(get_theme_file_uri('assets/field/' . $image)); ?>" alt="" width="360" height="270" loading="lazy" decoding="async"><?php endif; ?>
            <h3><?php echo esc_html($family->name); ?></h3><span class="field-arrow" aria-hidden="true">↗</span>
          </a>
        <?php endforeach; ?>
        </div>
      </section>
      <section class="field-wrap field-equipment" aria-labelledby="field-equipment-title">
        <div class="field-section-label"><h2 id="field-equipment-title"><?php echo esc_html(cml_t('featured')); ?></h2><a href="<?php echo esc_url(cml_page_url('products')); ?>"><?php echo esc_html(cml_t('products')); ?> <span aria-hidden="true">↗</span></a></div>
        <?php
        // Preserve curated product order and resolve actual published translations.
        $ids = [];
        foreach (['rada-32-ddlja5-80ghz-radar-level-transmitter', 'cl-md23-mud-density-sensor', 'yc-03d-electric-mud-degasser'] as $slug) {
            $posts = get_posts(['post_type' => 'cml_product', 'post_status' => 'publish', 'name' => $slug, 'lang' => 'en', 'numberposts' => 1]);
            if (!$posts) continue;
            $id = function_exists('pll_get_post') ? pll_get_post($posts[0]->ID, cml_language()) : $posts[0]->ID;
            if ($id && get_post_status($id) === 'publish') $ids[] = $id;
        }
        if ($ids) cml_cards(['post__in' => $ids, 'orderby' => 'post__in', 'posts_per_page' => 3]);
        else cml_cards(['posts_per_page' => 3]);
        ?>
      </section>
      <section class="field-wrap field-rfq" aria-labelledby="field-rfq-title">
        <div><p class="field-kicker"><?php echo esc_html(cml_t('workflow_title')); ?></p><h2 id="field-rfq-title"><?php echo esc_html($english ? 'Start with your measurement.' : cml_t('quote')); ?></h2><p><?php echo esc_html(cml_t('workflow')); ?></p></div>
        <a class="cml-button" href="<?php echo esc_url(cml_page_url('contact')); ?>"><?php echo esc_html(cml_t('quote')); ?> <span aria-hidden="true">↗</span></a>
      </section>
    </div>
    <?php return (string) ob_get_clean();
}, 10, 2);
