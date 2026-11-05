(() => {
  const section = document.querySelector('[data-cml-hubspot]');
  if (!section) return;
  const formId = '0c0ac565-9285-4c18-b50a-3ebb683d1a16';
  const button = section.querySelector('[data-cml-hubspot-load]');
  const mount = section.querySelector('[data-cml-hubspot-mount]');
  const status = section.querySelector('[data-cml-hubspot-status]');
  button.addEventListener('click', () => {
    if (button.disabled) return;
    button.disabled = true;
    button.hidden = true;
    status.textContent = section.dataset.loading;
    const timeout = setTimeout(() => { status.textContent = section.dataset.unavailable; }, 20000);
    const ready = (event) => {
      if (event.detail?.formId !== formId) return;
      clearTimeout(timeout);
      status.textContent = '';
      window.removeEventListener('hs-form-event:on-ready', ready);
    };
    // Register before the official embed runs, per HubSpot's global events API.
    window.addEventListener('hs-form-event:on-ready', ready);
    const frame = document.createElement('div');
    frame.className = 'hs-form-frame';
    frame.dataset.region = 'eu1';
    frame.dataset.formId = formId;
    frame.dataset.portalId = '25732190';
    mount.replaceChildren(frame);
    const script = document.createElement('script');
    script.src = 'https://js-eu1.hsforms.net/forms/embed/25732190.js';
    script.defer = true;
    script.addEventListener('error', () => {
      clearTimeout(timeout);
      status.textContent = section.dataset.unavailable;
      window.removeEventListener('hs-form-event:on-ready', ready);
    });
    document.body.append(script);
  });
})();
