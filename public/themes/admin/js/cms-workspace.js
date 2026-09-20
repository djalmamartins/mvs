(() => {
  const open = (dialog, trigger) => {
    if (!dialog) return;
    const form = dialog.querySelector('form');
    if (form && trigger?.dataset.cmsEdit) {
      form.querySelector('[name="id"]')?.setAttribute('value', trigger.dataset.id || '0');
      const name = form.querySelector('[name="name"]');
      if (name) name.value = trigger.dataset.name || '';
      const type = form.querySelector('[name="type"]');
      if (type && trigger.dataset.type) type.value = trigger.dataset.type;
      const title = dialog.querySelector('[data-modal-title]');
      if (title) title.textContent = trigger.dataset.modalTitle || 'Editar';
    }
    if (typeof dialog.showModal === 'function') dialog.showModal();
  };
  document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-cms-modal-open]');
    if (trigger) {
      const dialog = document.querySelector(trigger.dataset.cmsModalOpen);
      open(dialog, trigger);
      return;
    }
    const close = event.target.closest('[data-cms-modal-close]');
    if (close) close.closest('dialog')?.close();
  });
  document.querySelectorAll('.moves-modal').forEach((dialog) => {
    dialog.addEventListener('click', (event) => {
      if (event.target === dialog) dialog.close();
    });
  });
})();