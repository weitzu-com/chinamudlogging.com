(() => {
  const { createElement } = wp.element;
  for (const view of ['header', 'footer', 'home', 'catalogue', 'product', 'inquiry', 'applications']) {
    wp.blocks.registerBlockType(`cml/${view}`, {
      apiVersion: 3, title: `ChinaMudLogging: ${view}`, category: 'widgets', icon: 'products',
      attributes: { limit: { type: 'integer', default: 12 }, hero: { type: 'string' }, intro: { type: 'string' } },
      edit: ({ attributes, setAttributes }) => createElement('div', wp.blockEditor.useBlockProps(),
        view === 'home' && createElement(wp.blockEditor.InspectorControls, {},
          createElement(wp.components.PanelBody, { title: 'Homepage copy' },
            createElement(wp.components.TextControl, { label: 'Headline', value: attributes.hero || '', onChange: (hero) => setAttributes({ hero }) }),
            createElement(wp.components.TextareaControl, { label: 'Introduction', value: attributes.intro || '', onChange: (intro) => setAttributes({ intro }) }),
          )),
        createElement(wp.serverSideRender.default || wp.serverSideRender, { block: `cml/${view}`, attributes })),
      save: () => null,
    });
  }
})();
