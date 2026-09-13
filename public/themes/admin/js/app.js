const menuButton = document.querySelector('.studio-menu-toggle');
const sidebar = document.querySelector('.studio-sidebar');
const backdrop = document.querySelector('.studio-backdrop');

function closeStudioMenu() {
  document.body.classList.remove('studio-menu-open');
  menuButton?.setAttribute('aria-expanded', 'false');
}

menuButton?.addEventListener('click', () => {
  const open = !document.body.classList.contains('studio-menu-open');
  document.body.classList.toggle('studio-menu-open', open);
  menuButton.setAttribute('aria-expanded', String(open));
});
backdrop?.addEventListener('click', closeStudioMenu);
sidebar?.addEventListener('click', event => { if (event.target.closest('a')) closeStudioMenu(); });
document.addEventListener('keydown', event => { if (event.key === 'Escape') closeStudioMenu(); });
