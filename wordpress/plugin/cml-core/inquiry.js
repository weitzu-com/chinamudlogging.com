(() => {
  const form = document.querySelector('[data-cml-inquiry]');
  if (!form) return;
  const status = form.querySelector('[role="status"]');
  const submit = form.querySelector('button[type="submit"]');
  const selected = new URL(location.href).searchParams.get('product');
  if (selected) form.elements.product.value = selected.slice(0, 300);
  let requestId = crypto.randomUUID();
  let lastPayload = '';
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (submit.disabled || !form.reportValidity()) return;
    const values = Object.fromEntries(new FormData(form));
    const payload = { ...values, source: location.pathname, consent: form.elements.consent.checked };
    const serialized = JSON.stringify(payload);
    if (lastPayload && lastPayload !== serialized) requestId = crypto.randomUUID();
    lastPayload = serialized;
    submit.disabled = true;
    status.textContent = form.dataset.sending;
    try {
      const tokenResponse = await fetch(form.dataset.token, { cache: 'no-store', credentials: 'same-origin' });
      if (!tokenResponse.ok) throw new Error('token');
      const { nonce } = await tokenResponse.json();
      const response = await fetch(form.dataset.endpoint, {
        method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ...payload, nonce, request_id: requestId }),
      });
      const result = await response.json();
      if (!response.ok || !result.saved) throw new Error('save');
      status.textContent = `${form.dataset.saved} ${result.receipt}`;
      form.reset(); requestId = crypto.randomUUID(); lastPayload = '';
    } catch {
      status.textContent = form.dataset.error;
    } finally { submit.disabled = false; }
  });
})();
