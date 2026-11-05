(() => {
  const dialog = document.getElementById('cml-locale-dialog');
  const open = document.querySelector('[data-locale-open]');
  if (!dialog || !open || typeof dialog.showModal !== 'function') return;
  const close = () => { dialog.close(); open.focus(); };
  open.hidden = false;
  open.addEventListener('click', () => dialog.showModal());
  dialog.querySelector('[data-locale-close]').addEventListener('click', close);
  dialog.addEventListener('click', (event) => {
    if (event.target !== dialog) return;
    const rect = dialog.getBoundingClientRect();
    if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) close();
  });
})();
