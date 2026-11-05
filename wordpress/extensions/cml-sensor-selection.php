<?php
/**
 * Plugin Name: ChinaMudLogging Sensor Selection
 * Description: Source-bounded selection, compatibility and documentation guidance for priority English sensors.
 * Version: 1.0.0
 * Requires PHP: 8.2
 */
defined('ABSPATH') || exit;
function cml_sensor_records(): array {
    return [
        'mud-temperature-sensor' => ['temperature', 'Mud Temperature Sensor: Selection & Interface Review', 'Mud temperature sensor', 'The reviewed record describes drilling-fluid temperature measurement but does not identify a model-specific range, sensing element or output. These must be confirmed for the proposed version.'],
        'drilling-fluid-temperature-sensor' => ['temperature', 'Drilling Fluid Temperature Sensor: Probe & Interface Selection', 'Drilling fluid temperature sensor', 'The record describes an immersion probe and protective cage. Measurement range, output, insertion length and connection are configuration items to confirm, not fixed specifications established by this page.'],
        'flow-out-sensor-paddle-for-mud-logging-unit' => ['flow', 'Paddle Flow-Out Sensor for Mud Logging: Relative Flow', 'Paddle flow-out sensor for mud logging', 'The original table below describes 0 ~ 100% relative change, with no absolute range. It must not be presented as a verified L/min or volume measurement. Its listed electrical values apply only to this catalogue record.'],
        'mud-flow-out-sensor-paddle-type' => ['flow', 'Mud Flow-Out Sensor, Paddle Type: Replacement & Interface Review', 'Paddle-type mud flow-out sensor', 'This sparse record does not establish an absolute flow range, transfer curve or model-specific electrical interface. Do not copy specifications from another paddle sensor solely because the names are similar.'],
        'pump-stroke-sensor-for-mud-logging' => ['pump', 'Pump Stroke Sensor for Mud Logging: Selection & Compatibility', 'Pump stroke sensor for mud logging', 'The reviewed record identifies a proximity-sensor application but does not provide a model-matched wiring diagram or electrical limits. Confirm the exact version before selecting a replacement.'],
        'mud-logging-pump-stroke-sensor' => ['pump', 'Mud Logging Pump Stroke Sensor: Electrical & Mounting Review', 'Mud logging pump stroke sensor', 'The source description mentions a two-wire system and lists electrical limits below. These do not, by themselves, identify the receiving input circuit or establish compatibility with your acquisition system.'],
    ];
}
function cml_sensor_context(): array {
    if (is_admin() || !is_singular('cml_product') || !function_exists('cml_language') || cml_language() !== 'en') return [];
    $post = get_queried_object();
    $record = cml_sensor_records()[$post->post_name ?? ''] ?? [];
    return $record ? ['slug' => $post->post_name, 'type' => $record[0], 'title' => $record[1], 'label' => $record[2], 'sourceNote' => $record[3]] : [];
}
function cml_sensor_url(string $slug): string { return home_url('/product/' . $slug . '/'); }
function cml_sensor_link(string $slug, string $label): string { return '<a href="' . esc_url(cml_sensor_url($slug)) . '">' . esc_html($label) . '</a>'; }
function cml_sensor_rfq(array $context, bool $documents = false): string {
    $request = $context['label'] . ($documents ? ' - request current datasheet, wiring and model-matched documentation' : ' - selection and interface compatibility review');
    return '<a class="cml-button" href="' . esc_url(add_query_arg('product', $request, cml_page_url('contact', 'en'))) . '">' . ($documents ? 'Request technical documents' : 'Check this sensor for my system') . ' →</a>';
}
foreach (['wpseo_title', 'pre_get_document_title'] as $hook) add_filter($hook, function ($title) { $c = cml_sensor_context(); return $c ? $c['title'] . ' | ChinaMudLogging.com' : $title; }, 95);
add_filter('wpseo_metadesc', function ($description) {
    $c = cml_sensor_context();
    return $c ? $c['label'] . ': review selection, installation and acquisition interfaces. Request a model-matched datasheet, wiring details and quotation.' : $description;
}, 95);
add_action('wp_enqueue_scripts', function () {
    if (!cml_sensor_context()) return;
    wp_register_style('cml-sensor-selection', false, [], '1.0.0'); wp_enqueue_style('cml-sensor-selection');
    wp_add_inline_style('cml-sensor-selection', '.cml-sensor-guide{margin-block:2rem;padding:clamp(1rem,3vw,2rem);background:#f4f8f8;border:1px solid #ccd6dc;border-radius:12px}.cml-sensor-guide h2{font-size:clamp(1.5rem,3vw,2.1rem)}.cml-sensor-guide nav{display:flex;gap:.7rem 1.2rem;flex-wrap:wrap}.cml-sensor-guide nav a{display:inline-flex;align-items:center;min-height:44px}.cml-sensor-table{overflow-x:auto;max-width:100%}.cml-sensor-table table{width:100%;min-width:520px;border-collapse:collapse}.cml-sensor-table td,.cml-sensor-table th{padding:.8rem;text-align:left;vertical-align:top;border:1px solid #ccd6dc}.cml-sensor-guide li{margin-block:.5rem}.cml-sensor-guide a{overflow-wrap:anywhere}.cml-sensor-source{scroll-margin-top:2rem}');
}, 30);
function cml_sensor_quick(array $c): string {
    return '<section class="cml-sensor-guide" aria-labelledby="cml-sensor-heading"><p class="eyebrow">Selection &amp; system integration</p><h2 id="cml-sensor-heading">Before choosing this ' . esc_html($c['type'] === 'temperature' ? 'temperature sensor' : ($c['type'] === 'flow' ? 'flow-out sensor' : 'pump stroke sensor')) . '</h2><p>' . esc_html($c['sourceNote']) . '</p><nav aria-label="Sensor selection shortcuts"><a href="#cml-sensor-compatibility">Check compatibility</a><a href="#cml-sensor-documents">Technical documentation</a><a href="#cml-sensor-source">Review the source record</a></nav><div class="cml-actions">' . cml_sensor_rfq($c) . '</div></section>';
}
function cml_sensor_content(array $c): string {
    $rows = [
        'temperature' => [
            ['Measurement requirement', 'Requested temperature range and units, normal process conditions and required response. The page does not establish a sensing element or accuracy for an unspecified model.'],
            ['Probe and installation', 'Insertion length, mounting/process connection, wetted materials and clearance. Match the actual measurement point and existing fitting.'],
            ['Electrical interface', 'Exact sensing element or transmitter, supply requirements, output type, wire count, connector and pinout. A temperature sensor is not automatically compatible with every analogue input.'],
            ['Acquisition configuration', 'Input circuit, scaling, engineering units and any required calibration record. Identify what is included in the sensor versus the receiving system.'],
        ],
        'flow' => [
            ['Measurement objective', 'Relative flow-out trend or an absolute flow/volume requirement? State the intended units and validation basis. A relative paddle indication does not establish a calibrated absolute flow rate.'],
            ['Mechanical installation', 'Flow-line geometry, available mounting space, paddle dimensions, permitted travel and the installation drawing for the offered model.'],
            ['Electrical interface', 'Supply, output, input loading, cable/connector and wiring diagram. Use the values for the exact listed and offered version, not a similar-looking sensor.'],
            ['Acquisition and validation', 'Input scaling, reference/zero conditions and the required comparison or calibration procedure for the installation. Do not infer L/min from a percentage alone.'],
        ],
        'pump' => [
            ['Counting requirement', 'Mud-pump stroke counting or a different rotational/counting application? Identify the pump and target arrangement, the expected count and required indication.'],
            ['Mounting and target', 'Bracket/thread dimensions, target material/geometry, working clearance and installation conditions. Confirm limits from the offered model documentation.'],
            ['Electrical interface', 'Supply, wire count, switching/output behaviour, load requirements, connector and receiving input thresholds. Do not infer a pinout or switching circuit from a photograph.'],
            ['Acquisition configuration', 'Input frequency capability, count settings, pulses per stroke/cycle and required verification. If calculated pump rate or volume is needed, specify its calculation and calibration basis separately.'],
        ],
    ][$c['type']];
    $documents = [
        'temperature' => ['Model-specific datasheet: sensing element, range, output and environmental limits.', 'Probe/mechanical drawing: insertion length, connection, materials and installation dimensions.', 'Wiring/connector drawing and the intended acquisition input requirements.'],
        'flow' => ['Model-specific datasheet stating relative/absolute measurement definition and electrical output.', 'Paddle and mounting drawing, installation requirements and transfer/validation information.', 'Wiring/connector drawing and receiving input/loading requirements.'],
        'pump' => ['Model-specific proximity-switch datasheet with electrical behaviour, loading and response limits.', 'Target and mounting drawing, connection dimensions and operating clearance.', 'Wiring/connector drawing and the counting/input configuration required by the existing system.'],
    ][$c['type']];
    $related = [
        'temperature' => ['mud-temperature-sensor' => 'Mud temperature catalogue record', 'drilling-fluid-temperature-sensor' => 'Drilling fluid temperature probe record'],
        'flow' => ['flow-out-sensor-paddle-for-mud-logging-unit' => 'Paddle flow-out source specification record', 'mud-flow-out-sensor-paddle-type' => 'Paddle-type flow-out catalogue record'],
        'pump' => ['pump-stroke-sensor-for-mud-logging' => 'Pump stroke application record', 'mud-logging-pump-stroke-sensor' => 'Pump stroke electrical specification record'],
    ][$c['type']];
    ob_start(); ?>
    <section class="cml-sensor-guide" id="cml-sensor-compatibility" aria-labelledby="cml-sensor-check-heading">
      <h2 id="cml-sensor-check-heading">Selection and interface compatibility checklist</h2>
      <p>Compare the offered model with the actual installation and acquisition input. A matching product name or connector shape does not establish electrical or mechanical interchangeability.</p>
      <p class="cml-note">On a small screen, scroll the comparison sideways to read all columns.</p>
      <div class="cml-sensor-table" role="region" aria-label="Scrollable sensor compatibility checklist" tabindex="0"><table><thead><tr><th scope="col">Decision</th><th scope="col">Confirm before quotation</th></tr></thead><tbody><?php foreach ($rows as [$label, $value]) echo '<tr><th scope="row">' . esc_html($label) . '</th><td>' . esc_html($value) . '</td></tr>'; ?></tbody></table></div>
      <?php if ($c['type'] === 'flow'): ?>
      <h3>Is this the same as a mud flow meter?</h3><p>Specify whether you need a relative flow indication, an absolute flow rate or accumulated volume. The paddle source record that lists a percentage explicitly says relative change, with no absolute range. It does not substantiate a calibrated drilling mud flow meter in absolute units. Ask for a model and installation-specific measurement definition before comparing offers.</p>
      <?php elseif ($c['type'] === 'pump'): ?>
      <h3>What does a pump stroke sensor establish?</h3><p>The source records describe proximity sensing for pump/counting applications. Agree the target arrangement and receiving input before selecting a replacement. A stroke count alone does not establish measured fluid flow or delivered volume.</p>
      <?php else: ?>
      <h3>Which temperature sensor details are still needed?</h3><p>The reviewed temperature records do not establish a complete model-specific electrical and mechanical configuration. Provide the existing nameplate or required measurement point, then confirm the sensing element/transmitter, range, output, probe geometry and acquisition input together.</p>
      <?php endif; ?>
      <h3 id="cml-sensor-documents">Technical documentation to obtain for the offered model</h3>
      <p>The material currently shown is an on-page catalogue record. This review has not verified a separate, model-matched manufacturer manual, wiring drawing or approval certificate; the list below is a request checklist, not a set of available downloads.</p>
      <ul><?php foreach ($documents as $document) echo '<li>' . esc_html($document) . '</li>'; ?><li>Calibration/inspection records and acceptance criteria required for your order.</li><li>If a hazardous-area installation is required, the exact model and configuration, applicable approval documents and any barrier/interface requirements for technical review. Generic catalogue statements are not proof of approval.</li></ul>
      <div class="cml-actions"><?php echo cml_sensor_rfq($c, true); ?></div>
      <h3>Prepare a sensor RFQ</h3><p>Include the existing model/nameplate, required measurement and units, supply and receiving interface, connector details, mounting dimensions, quantity, destination and target date. Mark unknown details so they can be reviewed. Our team will confirm the proposed configuration, documentation and commercial terms in the quotation.</p>
      <div class="cml-actions"><?php echo cml_sensor_rfq($c); ?></div>
      <h3>Related catalogue records and system scope</h3><ul><?php foreach ($related as $slug => $label) if ($slug !== $c['slug']) echo '<li>' . cml_sensor_link($slug, $label) . ' — compare documentation; not a confirmed equivalent.</li>'; ?></ul>
      <p>Review the <?php echo '<a href="' . esc_url(home_url('/product-category/mud-logging-sensors/')) . '">mud logging sensor catalogue</a>'; ?>, <?php echo '<a href="' . esc_url(home_url('/product-category/mud-logging-units/')) . '">complete logging unit and upgrade scope</a>'; ?> or <?php echo '<a href="' . esc_url(cml_page_url('products', 'en')) . '">equipment procurement guide</a>'; ?> to check the wider system boundary.</p>
    </section>
    <?php return (string)ob_get_clean();
}
add_filter('render_block_cml/product', function ($html) { $c = cml_sensor_context(); return $c ? $html . cml_sensor_quick($c) : $html; });
add_filter('render_block_core/post-content', function ($html) {
    $c = cml_sensor_context();
    return $c ? '<section id="cml-sensor-source" class="cml-sensor-source" aria-label="Original catalogue record"><p class="cml-note">Original catalogue reference: statements below are not a verified model-matched wiring diagram, calibration record or approval certificate. Confirm the exact supplied configuration.</p>' . $html . '</section>' . cml_sensor_content($c) : $html;
});
