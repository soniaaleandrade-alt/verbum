(function () {
  'use strict';

  function prepare(root) {
    root.querySelectorAll('.verbum-nav-item').forEach(function (button) {
      var label = (button.textContent || '').trim();
      if (label !== 'Mesa de Trabalho' && label !== 'Workspace' && label !== 'Área de Trabalho') return;
      var text = button.querySelector('span:last-child');
      if (text) text.textContent = 'Área de Trabalho';
      button.disabled = false;
      button.removeAttribute('aria-disabled');
      button.setAttribute('data-verbum-area-trabalho', '1');
    });
  }

  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-verbum-area-trabalho]');
    if (!button) return;
    var root = button.closest('[data-verbum-app]');
    if (!root || !window.VerbumWorkspaceUI) return;
    event.preventDefault();
    event.stopPropagation();
    window.VerbumWorkspaceUI.open(root);
  }, true);

  function boot() {
    document.querySelectorAll('[data-verbum-app]').forEach(prepare);
    new MutationObserver(function () {
      document.querySelectorAll('[data-verbum-app]').forEach(prepare);
    }).observe(document.documentElement, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
