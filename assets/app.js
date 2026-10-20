'use strict';

/* Pemilih tema: auto / terang / gelap, tersimpan di localStorage. */
(() => {
  const KEY = 'aduan-dashboard-theme';
  const control = document.getElementById('theme-select');
  const system = matchMedia('(prefers-color-scheme: dark)');
  let preference = 'auto';
  try { preference = localStorage.getItem(KEY) || 'auto'; } catch {}

  const apply = () => {
    document.documentElement.dataset.theme = preference === 'auto' ? (system.matches ? 'dark' : 'light') : preference;
    control.value = preference;
  };
  control.addEventListener('change', () => {
    preference = control.value;
    try { localStorage.setItem(KEY, preference); } catch {}
    apply();
  });
  system.addEventListener('change', () => { if (preference === 'auto') apply(); });
  apply();
})();

/* Konfirmasi sebelum arsip, dan kirim otomatis filter yang berubah. */
document.querySelectorAll('[data-confirm]').forEach(form =>
  form.addEventListener('submit', e => { if (!confirm(form.dataset.confirm)) e.preventDefault(); }));
document.querySelectorAll('[data-autosubmit]').forEach(el =>
  el.addEventListener('change', () => el.form.submit()));
