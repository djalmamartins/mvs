(() => {
  'use strict';
  const form = document.querySelector('#editor');
  const field = form?.querySelector('[data-editor="moves"]');
  if (!(form instanceof HTMLFormElement) || !(field instanceof HTMLTextAreaElement)) return;
  const user = document.body.dataset.editorUser || '0';
  const documentKey = field.dataset.editorDocument || 'content:new';
  const key = `moves-editor:draft:${user}:${documentKey}`;
  const savedKey = new URLSearchParams(location.search).get('saved_key');
  if (savedKey) localStorage.removeItem(`moves-editor:draft:${user}:${savedKey}`);

  const values = () => {
    const data = {};
    new FormData(form).forEach((value, name) => {
      if (name === '_token' || name === 'action' || name === 'autosave_key' || value instanceof File) return;
      if (Object.hasOwn(data, name)) data[name] = [].concat(data[name], String(value));
      else data[name] = String(value);
    });
    return data;
  };
  const apply = (data) => Object.entries(data).forEach(([name, value]) => {
    const controls = form.querySelectorAll(`[name="${CSS.escape(name)}"]`);
    controls.forEach((control) => {
      if (!(control instanceof HTMLInputElement || control instanceof HTMLTextAreaElement || control instanceof HTMLSelectElement)) return;
      if (control.type === 'checkbox') control.checked = [].concat(value).includes(control.value);
      else if (!Array.isArray(value)) control.value = value;
      control.dispatchEvent(new Event('change', { bubbles: true }));
      control.dispatchEvent(new Event('input', { bubbles: true }));
    });
  });

  const baseline = JSON.stringify(values());
  try {
    const draft = JSON.parse(localStorage.getItem(key) || 'null');
    const savedAt = Date.parse(field.dataset.editorSavedAt || '') || 0;
    if (draft?.savedAt > savedAt && JSON.stringify(draft.values) !== baseline) {
      if (window.confirm('Existe um rascunho local mais recente para este conteúdo. Deseja recuperá-lo?')) apply(draft.values);
      else localStorage.removeItem(key);
    }
  } catch { localStorage.removeItem(key); }

  let timer;
  const persist = () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
      const data = values();
      if (JSON.stringify(data) === baseline) return;
      localStorage.setItem(key, JSON.stringify({ documentKey, savedAt: Date.now(), values: data }));
      form.dataset.autosaveState = 'saved';
    }, 700);
  };
  form.addEventListener('input', persist);
  form.addEventListener('change', persist);
})();
