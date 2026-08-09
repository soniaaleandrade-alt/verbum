(function () {
  'use strict';

  function ensureWorkspaceMenu(root) {
    var header = root.querySelector('.verbum-work-header');
    if (!header || header.querySelector('[data-nav-open]')) return;

    var back = header.querySelector('.verbum-work-back');
    if (!back) return;

    var actions = document.createElement('div');
    actions.className = 'verbum-work-header-actions';

    var menu = document.createElement('button');
    menu.type = 'button';
    menu.className = 'verbum-mobile-menu';
    menu.setAttribute('data-nav-open', '');
    menu.setAttribute('aria-label', 'Abrir navegação');
    menu.innerHTML = '<span></span><span></span><span></span>';

    header.insertBefore(actions, back);
    actions.appendChild(menu);
    actions.appendChild(back);
  }

  function scan() {
    document.querySelectorAll('[data-verbum-app]').forEach(ensureWorkspaceMenu);
  }

  scan();
  new MutationObserver(scan).observe(document.documentElement, { childList: true, subtree: true });
})();
