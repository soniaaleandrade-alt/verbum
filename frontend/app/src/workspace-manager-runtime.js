(function () {
  'use strict';

  function ensureGlobalSidebarStyle() {
    if (document.getElementById('verbum-global-sidebar-hotfix')) return;
    var style = document.createElement('style');
    style.id = 'verbum-global-sidebar-hotfix';
    style.textContent = '.verbum-shell.has-official-dashboard .verbum-nav-group:nth-of-type(n+4){display:block!important}';
    document.head.appendChild(style);
  }

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

  function clearWorkRoute() {
    try {
      var url = new URL(window.location.href);
      ['verbum_work', 'verbum_stage', 'verbum_chapter', 'verbum_chapter_stage'].forEach(function (key) {
        url.searchParams.delete(key);
      });
      window.history.pushState({}, '', url);
      window.dispatchEvent(new Event('verbum:routechange'));
    } catch (error) {}
  }

  function prepareWorkspaceState(root) {
    if (root.__vs) {
      root.__vs.section = 'workspace';
      root.__vs.workspace = null;
    }
    clearWorkRoute();

    var shell = root.querySelector('.verbum-shell');
    if (shell) shell.classList.remove('has-official-dashboard', 'has-hidden-header', 'has-minhas-obras');
    var header = root.querySelector('.verbum-header');
    if (header) {
      header.hidden = false;
      header.removeAttribute('hidden');
    }
  }

  function openWorkspace(root, attempt) {
    prepareWorkspaceState(root);
    if (window.VerbumWorkspaceUI && typeof window.VerbumWorkspaceUI.open === 'function') {
      window.VerbumWorkspaceUI.open(root);
      return;
    }
    if (attempt >= 40) return;
    window.setTimeout(function () { openWorkspace(root, attempt + 1); }, 50);
  }

  document.addEventListener('click', function (event) {
    var target = event.target;
    if (!(target instanceof Element)) return;
    var button = target.closest('[data-verbum-area-trabalho]');
    if (!button) return;
    var root = button.closest('[data-verbum-app]');
    if (!root) return;
    event.preventDefault();
    event.stopPropagation();
    openWorkspace(root, 0);
  }, true);

  function boot() {
    ensureGlobalSidebarStyle();

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
