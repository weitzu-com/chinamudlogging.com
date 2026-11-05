<?php
defined('ABSPATH') || exit;

function cml_hubspot_copy(): array {
    $copy = [
        'en' => ['English equipment inquiry', 'This English form stores your contact details and requirements in HubSpot CRM so our team can respond. Load the form to connect to HubSpot.', 'Load inquiry form', 'Open form in a separate page', 'Use the local website form instead', 'Loading inquiry form…', 'Selected product:'],
        'zh' => ['英文设备询价', '此英文表单将联系方式和需求存入 HubSpot CRM，供团队回复。点击加载后连接 HubSpot。', '加载询价表单', '在独立页面打开表单', '改用本站中文表单', '正在加载询价表单…', '所选产品：'],
        'es' => ['Consulta de equipos en inglés', 'Este formulario en inglés guarda sus datos de contacto y requisitos en HubSpot CRM para responder a su consulta. Cargue el formulario para conectarse a HubSpot.', 'Cargar formulario', 'Abrir formulario en otra página', 'Usar el formulario local en español', 'Cargando formulario…', 'Producto seleccionado:'],
        'fr' => ['Demande de matériel en anglais', 'Ce formulaire en anglais enregistre vos coordonnées et besoins dans HubSpot CRM pour permettre à notre équipe de répondre. Chargez le formulaire pour vous connecter à HubSpot.', 'Charger le formulaire', 'Ouvrir le formulaire sur une autre page', 'Utiliser le formulaire local en français', 'Chargement du formulaire…', 'Produit sélectionné :'],
        'ru' => ['Запрос оборудования на английском', 'Эта английская форма сохраняет контактные данные и требования в HubSpot CRM для ответа нашей команды. Загрузите форму для подключения к HubSpot.', 'Загрузить форму', 'Открыть форму на отдельной странице', 'Использовать форму сайта на русском', 'Загрузка формы…', 'Выбранный товар:'],
        'ar' => ['استفسار عن المعدات بالإنجليزية', 'يحفظ هذا النموذج الإنجليزي بيانات الاتصال والمتطلبات في HubSpot CRM ليرد فريقنا. حمّل النموذج للاتصال بخدمة HubSpot.', 'تحميل نموذج الاستفسار', 'فتح النموذج في صفحة مستقلة', 'استخدام نموذج الموقع بالعربية', 'جارٍ تحميل النموذج…', 'المنتج المختار:'],
        'de' => ['Geräteanfrage auf Englisch', 'Dieses englische Formular speichert Ihre Kontaktdaten und Anforderungen in HubSpot CRM, damit unser Team antworten kann. Laden Sie das Formular, um eine Verbindung zu HubSpot herzustellen.', 'Anfrageformular laden', 'Formular auf separater Seite öffnen', 'Lokales Formular auf Deutsch verwenden', 'Formular wird geladen…', 'Ausgewähltes Produkt:'],
    ];
    $unavailable = [
        'en' => 'The form could not finish loading. Open it on a separate page or use the local website form below.',
        'zh' => '表单暂未加载完成。请在独立页面打开，或使用下方本站表单。',
        'es' => 'El formulario no terminó de cargar. Ábralo en otra página o use el formulario local de abajo.',
        'fr' => 'Le formulaire ne se charge pas. Ouvrez-le sur une autre page ou utilisez le formulaire local ci-dessous.',
        'ru' => 'Форма не загрузилась. Откройте её на отдельной странице или используйте форму сайта ниже.',
        'ar' => 'تعذر تحميل النموذج. افتحه في صفحة مستقلة أو استخدم نموذج الموقع أدناه.',
        'de' => 'Das Formular konnte nicht geladen werden. Öffnen Sie es separat oder nutzen Sie das lokale Formular unten.',
    ];
    return array_combine(['title', 'notice', 'load', 'link', 'fallback', 'loading', 'product'], $copy[cml_language()] ?? $copy['en'])
        + ['unavailable' => $unavailable[cml_language()] ?? $unavailable['en']];
}

function cml_hubspot_form(): string {
    $copy = cml_hubspot_copy();
    $product = isset($_GET['product']) && is_string($_GET['product']) ? mb_substr(sanitize_text_field(wp_unslash($_GET['product'])), 0, 300) : '';
    return '<section class="cml-hubspot" aria-labelledby="cml-hubspot-title" data-cml-hubspot data-unavailable="' . esc_attr($copy['unavailable']) . '" data-loading="' . esc_attr($copy['loading']) . '">'
        . '<h2 id="cml-hubspot-title">' . esc_html($copy['title']) . '</h2><p>' . esc_html($copy['notice']) . ' <a href="' . esc_url(cml_page_url('privacy')) . '">' . esc_html(cml_t('privacy')) . '</a></p>'
        . ($product !== '' ? '<p class="cml-hubspot-product">' . esc_html($copy['product']) . ' <bdi>' . esc_html($product) . '</bdi></p>' : '')
        . '<button type="button" class="cml-button" data-cml-hubspot-load>' . esc_html($copy['load']) . '</button><p role="status" aria-live="polite" data-cml-hubspot-status></p><div data-cml-hubspot-mount lang="en" dir="ltr"></div>'
        . '<p><a href="https://fbj32.share-eu1.hsforms.com/2DArFZZKFTBi1Cj67aD0aFg" target="_blank" rel="noopener noreferrer">' . esc_html($copy['link']) . ' ↗</a></p></section>';
}
