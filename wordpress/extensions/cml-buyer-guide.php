<?php
/**
 * Plugin Name: ChinaMudLogging Procurement Guide
 * Description: English procurement guidance for the existing catalogue and logging unit family.
 * Version: 1.0.0
 * Requires PHP: 8.2
 */
defined('ABSPATH') || exit;

function cml_buyer_context(): string {
    if (is_admin() || !function_exists('cml_language') || cml_language() !== 'en') return '';
    foreach (['q', 'family'] as $key) {
        if (isset($_GET[$key]) && (!is_scalar($_GET[$key]) || (string)$_GET[$key] !== '')) return '';
    }
    if (isset($_GET['catalog_page']) && (!is_scalar($_GET['catalog_page']) || !in_array((string)$_GET['catalog_page'], ['', '1'], true))) return '';
    if (is_paged()) return '';
    if (is_tax('cml_family', 'mud-logging-units')) return 'units';
    $ids = get_option('cml_pages', []);
    return !empty($ids['en']['products']) && is_page((int)$ids['en']['products']) ? 'catalogue' : '';
}
function cml_buyer_meta(string $kind): array {
    return $kind === 'units'
        ? ['Mud Logging Units & Surface Logging Systems | ChinaMudLogging.com', 'Compare mud logging unit configurations, sensor integration, gas analysis scope and RFQ requirements. Review system boundaries before requesting a quotation.']
        : ['Mud Logging Equipment & Sensors Catalogue | ChinaMudLogging.com', 'Select mud logging equipment, complete units, sensors and drilling monitoring components. Compare procurement routes and prepare a configuration-specific RFQ.'];
}
foreach (['wpseo_title', 'pre_get_document_title'] as $hook) {
    add_filter($hook, function ($title) { $kind = cml_buyer_context(); return $kind ? cml_buyer_meta($kind)[0] : $title; }, 90);
}
add_filter('wpseo_metadesc', function ($description) { $kind = cml_buyer_context(); return $kind ? cml_buyer_meta($kind)[1] : $description; }, 90);
add_filter('render_block_core/post-title', function ($html) {
    return cml_buyer_context() === 'catalogue' ? '<h1 class="wp-block-post-title alignwide">Mud logging equipment and sensors</h1>' : $html;
});
add_action('wp_enqueue_scripts', function () {
    if (!cml_buyer_context()) return;
    wp_register_style('cml-buyer-guide', false, [], '1.0.0');
    wp_enqueue_style('cml-buyer-guide');
    wp_add_inline_style('cml-buyer-guide', '.cml-buyer{margin-block:2rem;padding:clamp(1rem,3vw,2rem);border:1px solid #ccd6dc;border-radius:12px;background:#f5f8f8}.cml-buyer h2{font-size:clamp(1.5rem,3vw,2.25rem)}.cml-buyer table{width:100%;border-collapse:collapse;min-width:560px}.cml-buyer th,.cml-buyer td{padding:.8rem;border:1px solid #ccd6dc;text-align:left;vertical-align:top}.cml-buyer-scroll{max-width:100%;overflow-x:auto}.cml-buyer nav,.cml-buyer-links{display:flex;flex-wrap:wrap;gap:.75rem 1.25rem}.cml-buyer summary{cursor:pointer;padding-block:.8rem;font-weight:600}.cml-buyer li{margin-block:.5rem}.cml-buyer a{overflow-wrap:anywhere}.cml-buyer nav a{min-height:44px;display:inline-flex;align-items:center}.cml-buyer:target{scroll-margin-top:2rem}');
}, 30);
function cml_buyer_link(string $path, string $label): string {
    return '<a href="' . esc_url(home_url($path)) . '">' . esc_html($label) . '</a>';
}
function cml_buyer_rfq(string $product, string $label): string {
    return '<a class="cml-button" href="' . esc_url(add_query_arg('product', $product, cml_page_url('contact', 'en'))) . '">' . esc_html($label) . ' →</a>';
}
function cml_buyer_intro(string $kind): string {
    ob_start(); ?>
    <section class="cml-buyer" aria-labelledby="cml-buyer-heading">
      <p class="eyebrow">Equipment selection &amp; procurement</p>
      <h2 id="cml-buyer-heading"><?php echo $kind === 'units' ? 'Choose the scope of your mud logging unit' : 'Find the right route to a mud logging equipment quotation'; ?></h2>
      <p><?php echo $kind === 'units' ? 'A complete logging unit, an upgrade to an existing surface logging system and a replacement sensor are different purchases. Define the boundary before comparing quotations; a sensor listed in this family is not a complete unit.' : 'Start with the application: a new logging unit, a system upgrade or an individual instrument. Compare the required measurement and existing installation before choosing a model.'; ?></p>
      <nav aria-label="Procurement shortcuts"><a href="#cml-equipment-list">Browse equipment</a><a href="#cml-procurement-details">Compare requirements</a><a href="#cml-rfq-checklist">Prepare an RFQ</a></nav>
      <?php if ($kind === 'catalogue'): ?>
      <div class="cml-buyer-links"><?php
        foreach (['mud-logging-units' => 'Mud logging units & systems', 'mud-logging-sensors' => 'Wired mud logging sensors', 'wireless-sensors' => 'Wireless sensors & accessories', 'formation-evaluation' => 'Formation evaluation equipment', 'drilling-monitoring' => 'Drilling monitoring components'] as $slug => $label) echo cml_buyer_link('/product-category/' . $slug . '/', $label);
      ?></div>
      <?php else: ?>
      <p>System starting points: <?php echo cml_buyer_link('/product/mud-logging-unit/', 'Mud Logging Unit') . ' · ' . cml_buyer_link('/product/sls-mud-logging-unit/', 'SLS Mud Logging Unit') . ' · ' . cml_buyer_link('/product/surface-logging-systems/', 'Surface logging systems'); ?>. Review each record and request the current configuration; these are not a declaration of stock or interchangeable specifications.</p>
      <?php endif; ?>
    </section><div id="cml-equipment-list"></div>
    <?php return (string)ob_get_clean();
}
function cml_buyer_catalogue_details(): string {
    ob_start(); ?>
    <section class="cml-buyer" id="cml-procurement-details" aria-labelledby="cml-route-heading">
      <h2 id="cml-route-heading">Choose equipment by the decision you need to make</h2>
      <div class="cml-buyer-scroll" role="region" aria-label="Equipment selection comparison" tabindex="0"><table><caption>Start with the application, then check the product record</caption>
        <thead><tr><th scope="col">Your requirement</th><th scope="col">Where to start</th><th scope="col">Key selection checks</th></tr></thead>
        <tbody>
          <tr><th scope="row">A new logging unit or configured system</th><td><?php echo cml_buyer_link('/product-category/mud-logging-units/', 'Mud logging unit buying guide'); ?></td><td>System boundary, measurement channels, gas analysis, utilities and acceptance scope.</td></tr>
          <tr><th scope="row">A measured variable or replacement sensor</th><td><?php echo cml_buyer_link('/product-category/mud-logging-sensors/', 'Wired sensor catalogue'); ?></td><td>Range, units, power, output, mounting and existing system compatibility.</td></tr>
          <tr><th scope="row">A wireless measurement link</th><td><?php echo cml_buyer_link('/product-category/wireless-sensors/', 'Wireless sensors and accessories'); ?></td><td>Sensor, transmission and receiving components, power arrangements and site conditions.</td></tr>
          <tr><th scope="row">Sample evaluation or gas/sample handling</th><td><?php echo cml_buyer_link('/product-category/formation-evaluation/', 'Formation evaluation equipment'); ?></td><td>Required workflow, instrument function, sample handling and accessories.</td></tr>
          <tr><th scope="row">Drilling data acquisition or monitoring components</th><td><?php echo cml_buyer_link('/product-category/drilling-monitoring/', 'Drilling monitoring components'); ?></td><td>Which functions already exist, interfaces, installation and integration responsibility.</td></tr>
        </tbody></table></div>
      <h3>Compare the same measurement before comparing models</h3>
      <p>For a sensor replacement, start with the existing nameplate and connection details. For example, review the <?php echo cml_buyer_link('/product/mud-temperature-sensor/', 'mud temperature sensor') . ', ' . cml_buyer_link('/product/flow-out-sensor-paddle-for-mud-logging-unit/', 'paddle flow-out sensor') . ' or ' . cml_buyer_link('/product/pump-stroke-sensor-for-mud-logging/', 'pump stroke sensor'); ?> record for the required function. A similar product name does not confirm an interchangeable output, mounting or configuration.</p>
      <h3 id="cml-rfq-checklist">What to include in an equipment RFQ</h3>
      <ol><li>State the application and whether this is a new installation, upgrade or replacement.</li><li>Include model/nameplate details, range and units, power, signal/protocol and connection or mounting requirements.</li><li>List quantity, destination, target date and required documentation. Tell us which details are still unknown.</li><li>Ask the offer to identify the proposed configuration, accessories, exclusions, acceptance criteria and commercial terms.</li></ol>
      <div class="cml-actions"><?php echo cml_buyer_rfq('Mud logging equipment selection', 'Request equipment selection'); ?><?php echo cml_buyer_rfq('Mud logging sensor replacement', 'Request a sensor replacement'); ?></div>
      <details><summary>Do I need a complete logging unit or individual components?</summary><p>If you need a new integrated workflow, define the overall system and required functions first. If an installed system is operating and only one instrument or function needs replacement, provide its technical details. <?php echo cml_buyer_link('/product-category/mud-logging-units/', 'Compare complete units and upgrade scopes'); ?>.</p></details>
      <details><summary>Can you quote when the exact model is unknown?</summary><p>Start with the required measurement and application, the existing equipment and any known interface details. Our team can review what is missing before proposing a configuration. Catalogue data does not establish current price, stock, delivery or certification for your order. <?php echo cml_buyer_link('/services/', 'Review sourcing and technical support'); ?>.</p></details>
    </section>
    <?php return (string)ob_get_clean();
}
function cml_buyer_details(string $kind): string {
    if ($kind === 'catalogue') return cml_buyer_catalogue_details();
    ob_start(); ?>
    <section class="cml-buyer" id="cml-procurement-details" aria-labelledby="cml-route-heading">
      <h2 id="cml-route-heading"><?php echo $kind === 'units' ? 'Compare a new unit, an upgrade and replacement components' : 'Compare equipment by procurement scope'; ?></h2>
      <div class="cml-buyer-scroll" role="region" aria-label="Procurement comparison" tabindex="0"><table>
        <caption>What to confirm before requesting a quotation</caption><thead><tr><th scope="col">Purchase route</th><th scope="col">Information to supply</th><th scope="col">Confirm in the offer</th></tr></thead>
        <tbody>
          <tr><th scope="row">New mud logging unit / surface logging system</th><td>Rig application, measurement channels, gas analysis scope, work environment, utilities and destination.</td><td>Included sensors, acquisition hardware, software, housing, documentation and acceptance scope.</td></tr>
          <tr><th scope="row">Upgrade an existing system</th><td>Current unit model, channel list, power, signals/protocols, connector details and available drawings.</td><td>Compatibility, interfaces, configuration work and responsibility for integration.</td></tr>
          <tr><th scope="row">Replace a sensor or accessory</th><td>Existing nameplate, measured variable, operating range, output, supply voltage, mounting and quantity.</td><td>Exact replacement or proposed alternative, connection details and required documentation.</td></tr>
          <tr><th scope="row">Formation evaluation or drilling monitoring components</th><td>Sample or measurement workflow, required instrument, data connection and installation conditions.</td><td>Standalone instrument versus integrated function, accessories and operator requirements.</td></tr>
        </tbody>
      </table></div>
      <h3>Match the measurement to the equipment</h3>
      <p><?php echo cml_buyer_link('/product-category/mud-logging-sensors/', 'Compare wired sensors') . ' and ' . cml_buyer_link('/product-category/wireless-sensors/', 'wireless sensor components'); ?> using the existing acquisition interface. For a drilling monitoring system, identify the required sensors, acquisition and display functions separately rather than assuming every catalogue component forms a complete package.</p>
      <p>Examples to review: <?php echo cml_buyer_link('/product/mud-temperature-sensor/', 'mud temperature sensor') . ' · ' . cml_buyer_link('/product/flow-out-sensor-paddle-for-mud-logging-unit/', 'paddle flow-out sensor') . ' · ' . cml_buyer_link('/product/pump-stroke-sensor-for-mud-logging/', 'pump stroke sensor'); ?>. These links lead to product records, not a confirmation of compatibility.</p>
      <h3 id="cml-rfq-checklist">RFQ checklist for a comparable quotation</h3>
      <ul>
        <li><strong>Application and scope:</strong> new unit, upgrade, replacement, rig type and required measurements.</li>
        <li><strong>Measurement and integration:</strong> channel list, range and units, required output/protocol, existing hardware and software interfaces.</li>
        <li><strong>Installation:</strong> supply voltage, connectors, mounting/process connection, environmental conditions and any hazardous-area requirements.</li>
        <li><strong>Gas analysis and sample handling:</strong> state the required functions, sampling arrangement and interfaces if they are part of the project.</li>
        <li><strong>Deliverables:</strong> requested drawings, manuals, calibration records, certification evidence, spares and acceptance criteria.</li>
        <li><strong>Commercial basis:</strong> quantity, destination, requested delivery terms and target date. Availability, price and lead time require a current offer.</li>
      </ul>
      <p>Do not have a complete specification? Start with your application and existing equipment details. Nameplate photos can help identify a replacement; if drawings are needed, our team can agree how to exchange them.</p>
      <div class="cml-actions"><?php echo cml_buyer_rfq($kind === 'units' ? 'Mud logging unit - new system configuration' : 'Mud logging equipment selection', $kind === 'units' ? 'Request a unit configuration' : 'Request equipment selection'); ?><?php echo cml_buyer_rfq('Existing mud logging system - upgrade or replacement', 'Ask about an upgrade or replacement'); ?></div>
      <h3>Questions buyers ask</h3>
      <details><summary>What is included in a mud logging unit?</summary><p>The included scope depends on the configuration. Ask for an itemized equipment and channel list, gas analysis scope, acquisition/software interfaces, utilities and documentation. A listing or photo does not establish what is included in your offer. <?php echo cml_buyer_link('/what-is-mud-logging-unit/', 'Read the logging unit overview'); ?>.</p></details>
      <details><summary>Can I buy sensors separately for an existing logging unit?</summary><p>Yes, you can request an individual sensor quotation. Supply the old model and nameplate, measurement range, power, output, connector and installation details. A matching measurement name alone does not prove interchangeability.</p></details>
      <details><summary>Are commissioning, training and certification included?</summary><p>These must be explicitly agreed in the quotation, along with responsibilities, documentation and acceptance criteria. Do not assume inclusion or hazardous-area approval from a catalogue entry. <?php echo cml_buyer_link('/services/', 'Review the sourcing and support scope'); ?>.</p></details>
      <p class="cml-note">Submitting a request does not confirm an order, price or configuration. Our team will review the requirement.</p>
    </section>
    <?php return (string)ob_get_clean();
}
add_filter('render_block_cml/catalogue', function ($html) {
    $kind = cml_buyer_context();
    return $kind ? cml_buyer_intro($kind) . $html . cml_buyer_details($kind) : $html;
});
