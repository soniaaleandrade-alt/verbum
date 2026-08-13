(function () {
  'use strict';

  function prepare(root) {
    root.querySelectorAll('.verbum-nav-item').forEach(function (button) {
      var label = (button.textContent || '').trim();
      if (label !== 'Mesa de Trabalho' && label !== 'Workspace' && label !== 'Área de Trabalho') return;

      var text = button.querySelector('span:last-child');
      if (text && text.textContent !== 'Área de Trabalho') text.textContent = 'Área de Trabalho';
      if (button.disabled) button.disabled = false;
      if (button.hasAttribute('aria-disabled')) button.removeAttribute('aria-disabled');
      if (button.getAttribute('data-verbum-area-trabalho') !== '1') button.setAttribute('data-verbum-area-trabalho', '1');
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
    function run() {
      document.querySelectorAll('[data-verbum-app]').forEach(prepare);
    }

    run();
    new MutationObserver(function (mutations) {
      var relevant = mutations.some(function (mutation) {
        return mutation.addedNodes && mutation.addedNodes.length > 0;
      });
      if (relevant) run();
    }).observe(document.documentElement, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
