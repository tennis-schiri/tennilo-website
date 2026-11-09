/* TENNILO theme: system preference by default; manual choice is local-only.
   No tracking, no cookies and no network requests. Runs early to prevent a color flash. */
(() => {
  'use strict';
  const key = 'tennilo-color-theme';
  const root = document.documentElement;
  const media = window.matchMedia('(prefers-color-scheme: dark)');
  const english = root.lang.toLowerCase().startsWith('en');
  const labels = english
    ? { enable: 'Enable dark mode', disable: 'Enable light mode', dark: 'Dark', light: 'Light' }
    : { enable: 'Dunkelmodus einschalten', disable: 'Hellmodus einschalten', dark: 'Dunkel', light: 'Hell' };

  function storedChoice() {
    try {
      const value = window.localStorage.getItem(key);
      return value === 'light' || value === 'dark' ? value : null;
    } catch (_) { return null; }
  }
  function remember(choice) {
    try { window.localStorage.setItem(key, choice); } catch (_) { /* unavailable storage is OK */ }
  }
  function currentTheme() {
    return storedChoice() || (media.matches ? 'dark' : 'light');
  }
  function updateControls() {
    const dark = root.getAttribute('data-theme') === 'dark';
    document.querySelectorAll('[data-theme-toggle]').forEach(button => {
      button.setAttribute('aria-pressed', String(dark));
      button.setAttribute('aria-label', dark ? labels.disable : labels.enable);
      button.setAttribute('title', dark ? labels.disable : labels.enable);
      const label = button.querySelector('[data-theme-label]');
      if (label) label.textContent = dark ? labels.light : labels.dark;
    });
  }
  function applyTheme(choice) {
    root.setAttribute('data-theme', choice);
    root.style.colorScheme = choice;
    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.setAttribute('content', choice === 'dark' ? '#0b1425' : '#F7F4ED');
    updateControls();
  }
  function initButtons() {
    document.querySelectorAll('[data-theme-toggle]').forEach(button => {
      button.addEventListener('click', () => {
        const next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        remember(next);
        applyTheme(next);
      });
    });
    updateControls();
  }
  applyTheme(currentTheme());
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initButtons, { once: true });
  } else {
    initButtons();
  }
  const updateFromSystem = () => { if (!storedChoice()) applyTheme(currentTheme()); };
  if (media.addEventListener) media.addEventListener('change', updateFromSystem);
  else if (media.addListener) media.addListener(updateFromSystem);
  window.addEventListener('storage', event => {
    if (event.key === key || event.key === null) applyTheme(currentTheme());
  });
})();
