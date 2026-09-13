/**
 * Moves | Public theme
 *
 * Kept as readable source so a future production step can generate app.min.js.
 */

const menu = document.querySelector('.menu-toggle');
const nav = document.querySelector('.main-nav');
function closeMenu() { menu?.setAttribute('aria-expanded', 'false'); nav?.classList.remove('is-open'); }
menu?.addEventListener('click', () => { const open = menu.getAttribute('aria-expanded') !== 'true'; menu.setAttribute('aria-expanded', String(open)); nav.classList.toggle('is-open', open); });
document.addEventListener('keydown', e => { if (e.key === 'Escape' && menu?.getAttribute('aria-expanded') === 'true') { closeMenu(); menu.focus(); } });
document.addEventListener('click', e => { if (!e.target.closest('.site-header')) closeMenu(); });
nav?.addEventListener('click', e => { if (e.target.closest('a')) closeMenu(); });
document.querySelectorAll('[data-filter]').forEach(button => button.addEventListener('click', () => {
  const category = button.dataset.filter;
  document.querySelectorAll('[data-filter]').forEach(item => item.setAttribute('aria-pressed', String(item === button)));
  let count = 0;
  document.querySelectorAll('[data-category]').forEach(card => { card.hidden = category !== 'Todos' && card.dataset.category !== category; if (!card.hidden) count++; });
  const status = document.querySelector('#filter-status');
  status.textContent = `${count} ${count === 1 ? (status.dataset.singular || 'conceito') : (status.dataset.plural || 'conceitos')}`;
  document.dispatchEvent(new Event('moves:gallery-filtered'));
}));
const form = document.querySelector('#contact-form');
if (form) {
  const service = new URLSearchParams(location.search).get('servico');
  if ([...form.elements.servico.options].some(option => option.value === service)) form.elements.servico.value = service;
}

// Shared atmosphere and motion from the original homepage.
const motionPreference = matchMedia('(prefers-reduced-motion: reduce)');
const precisePointer = matchMedia('(hover: hover) and (pointer: fine)');
const glow = document.querySelector('.cursor-glow');
let glowFrame = 0;
let pointerX = 0;
let pointerY = 0;
function hideGlow() {
  cancelAnimationFrame(glowFrame);
  glowFrame = 0;
  if (glow) glow.style.opacity = '0';
}
window.addEventListener('pointermove', event => {
  if (!glow || motionPreference.matches || !precisePointer.matches || event.pointerType === 'touch') return;
  pointerX = event.clientX;
  pointerY = event.clientY;
  if (glowFrame) return;
  glowFrame = requestAnimationFrame(() => {
    glow.style.left = `${pointerX}px`;
    glow.style.top = `${pointerY}px`;
    glow.style.opacity = '.9';
    glowFrame = 0;
  });
}, { passive: true });
document.documentElement.addEventListener('pointerleave', hideGlow);
window.addEventListener('blur', hideGlow);
motionPreference.addEventListener('change', hideGlow);
precisePointer.addEventListener('change', hideGlow);

if (!motionPreference.matches && 'IntersectionObserver' in window) {
  const reveals = document.querySelectorAll('.reveal, .page-hero, .page-section, .detail-layout, .about-story, .contact-layout, .article-body, .case-cover, .cta-inner, .reference-intro, .reference-offerings, .about-renewed, .about-editorial, .service-fold');
  const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('in-view');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0, rootMargin: '0px 0px -24px 0px' });
  document.documentElement.classList.add('motion-ready');
  reveals.forEach(element => { element.classList.add('reveal'); observer.observe(element); });
}

document.querySelectorAll('.service-card, .project-card:not(.case-cover), .article-card, .about-photo').forEach(card => {
  card.addEventListener('pointermove', event => {
    if (motionPreference.matches || !precisePointer.matches || event.pointerType === 'touch') return;
    const rect = card.getBoundingClientRect();
    const x = ((event.clientX - rect.left) / rect.width - .5) * 6;
    const y = ((event.clientY - rect.top) / rect.height - .5) * 6;
    card.style.transform = `perspective(700px) rotateX(${-y}deg) rotateY(${x}deg) translateY(-4px)`;
  }, { passive: true });
  card.addEventListener('pointerleave', () => { card.style.transform = ''; });
});

// Metrics stay at zero until scrolling brings their strip into view.
(() => {
  const strip = document.querySelector('.stats-results');
  if (!strip) return;
  const counters = [...strip.querySelectorAll('[data-count]')];
  const initialScroll = window.scrollY;
  let hasScrolled = false;
  let started = false;
  let frame = 0;
  function paint(progress) {
    counters.forEach(el => {
      const value = `${el.dataset.prefix}${Math.round(Number(el.dataset.count) * progress)}${el.dataset.suffix}`;
      el.textContent = value;
      el.setAttribute('aria-label', value);
    });
  }
  paint(0);
  function check() {
    if (started || !hasScrolled || document.documentElement.matches('.intro-pending, .hero-arriving')) return;
    const rect = strip.getBoundingClientRect();
    const visible = Math.min(rect.bottom, window.innerHeight) - Math.max(rect.top, 0);
    if (visible < Math.min(rect.height, window.innerHeight) * .3) return;
    started = true;
    window.removeEventListener('scroll', onScroll);
    opening.disconnect();
    if (motionPreference.matches) { paint(1); return; }
    const began = performance.now();
    function tick(now) {
      const progress = Math.min((now - began) / 1800, 1);
      paint(1 - Math.pow(1 - progress, 3));
      if (progress < 1) frame = requestAnimationFrame(tick);
    }
    frame = requestAnimationFrame(tick);
  }
  function onScroll() {
    if (window.scrollY !== initialScroll) hasScrolled = true;
    check();
  }
  const opening = new MutationObserver(check);
  opening.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
  window.addEventListener('scroll', onScroll, { passive: true });
  motionPreference.addEventListener('change', () => {
    if (started && motionPreference.matches) { cancelAnimationFrame(frame); paint(1); }
  });
})();

// Only the background moves; screenshots and controls remain steady.
(() => {
  const panels = [...document.querySelectorAll('.project-showcase')];
  if (!panels.length) return;
  let scheduled = 0;
  function render() {
    scheduled = 0;
    const viewport = window.innerHeight;
    const maxTravel = window.innerWidth <= 640 ? 220 : 500;
    for (const panel of panels) {
      if (panel.hidden) continue;
      const box = panel.getBoundingClientRect();
      if (box.bottom < 0 || box.top > viewport) continue;
      const progress = Math.max(-1, Math.min(1, (viewport / 2 - (box.top + box.height / 2)) / ((viewport + box.height) / 2)));
      panel.style.setProperty('--parallax-offset', `${motionPreference.matches ? 0 : -progress * maxTravel}px`);
    }
  }
  function schedule() {
    if (!scheduled) scheduled = requestAnimationFrame(render);
  }
  window.addEventListener('scroll', schedule, { passive: true });
  window.addEventListener('resize', schedule, { passive: true });
  document.addEventListener('moves:gallery-filtered', schedule);
  motionPreference.addEventListener('change', schedule);
  panels.forEach(panel => panel.querySelector('img')?.addEventListener('load', schedule, { once: true }));
  schedule();
})();

// Brand opening migrated from the static reference without inline JavaScript.
(() => {
  const root = document.documentElement;
  const intro = document.querySelector('.brand-intro');
  const preference = matchMedia('(prefers-reduced-motion: reduce)');
  if (!intro || preference.matches || !root.classList.contains('intro-pending')) {
    root.classList.remove('intro-pending');
    intro?.remove();
    return;
  }

  const logo = intro.querySelector('.intro-logo');
  const shine = intro.querySelector('.intro-shine');
  const caption = intro.querySelector('.intro-caption');
  const skip = intro.querySelector('.intro-skip');
  const surfaces = [...document.querySelectorAll('.site-header, main, .footer, .skip-link')];
  const animations = [];
  let stopped = false;
  let holdTimer;
  const fallbackTimer = setTimeout(() => finish(false), 8000);

  surfaces.forEach(element => {
    if (!element.inert) {
      element.inert = true;
      element.dataset.introInert = '';
    }
  });

  function release() {
    clearTimeout(fallbackTimer);
    clearTimeout(holdTimer);
    surfaces.forEach(element => {
      if (element.hasAttribute('data-intro-inert')) {
        element.inert = false;
        element.removeAttribute('data-intro-inert');
      }
    });
    root.classList.remove('intro-pending');
  }

  function finish(animate) {
    if (stopped) return;
    stopped = true;
    const restoreFocus = intro.contains(document.activeElement);
    release();
    if (animate && !preference.matches) {
      root.classList.add('hero-arriving');
      const exit = intro.animate(
        [{ opacity: 1, transform: 'translateY(0)' }, { opacity: 0, transform: 'translateY(-8%)' }],
        { duration: 850, easing: 'cubic-bezier(.22,1,.36,1)', fill: 'forwards' }
      );
      exit.finished.catch(() => {}).finally(() => {
        intro.remove();
        animations.forEach(animation => animation.cancel());
      });
      setTimeout(() => root.classList.remove('hero-arriving'), 2200);
    } else {
      intro.remove();
      animations.forEach(animation => animation.cancel());
    }
    if (restoreFocus) document.querySelector('.brand')?.focus({ preventScroll: true });
    preference.removeEventListener('change', onPreferenceChange);
    document.removeEventListener('keydown', onKeydown);
  }

  function onPreferenceChange() {
    if (preference.matches) finish(false);
  }

  function onKeydown(event) {
    if (event.key === 'Escape') finish(false);
  }

  preference.addEventListener('change', onPreferenceChange);
  document.addEventListener('keydown', onKeydown);
  skip?.addEventListener('click', () => finish(false));

  async function play() {
    try {
      await logo.decode();
      if (stopped || !root.classList.contains('intro-pending')) {
        finish(false);
        return;
      }
      const reveal = logo.animate([
        { opacity: 0, clipPath: 'inset(0 100% 0 0)', transform: 'translateX(-28px) scale(.94)', filter: 'blur(14px)' },
        { opacity: 1, clipPath: 'inset(0 0% 0 0)', transform: 'translateX(0) scale(1)', filter: 'blur(0px)' }
      ], { duration: 1700, easing: 'cubic-bezier(.16,1,.3,1)', fill: 'forwards' });
      const glint = shine.animate(
        [{ transform: 'translateX(-180%) skewX(-18deg)', opacity: 0 }, { opacity: .8, offset: .4 }, { transform: 'translateX(360%) skewX(-18deg)', opacity: 0 }],
        { duration: 1700, easing: 'ease-in-out', fill: 'forwards' }
      );
      const words = caption.animate(
        [{ opacity: 0, transform: 'translateY(12px)', letterSpacing: '.65em' }, { opacity: 1, transform: 'translateY(0)', letterSpacing: '.3em' }],
        { duration: 900, delay: 800, fill: 'forwards', easing: 'ease-out' }
      );
      animations.push(reveal, glint, words);
      await Promise.all(animations.map(animation => animation.finished));
      if (!stopped) holdTimer = setTimeout(() => finish(true), 2000);
    } catch {
      finish(false);
    }
  }

  play();
})();
