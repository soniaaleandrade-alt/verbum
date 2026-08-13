(function () {
  'use strict';

  var runtimeSrc = document.currentScript && document.currentScript.src ? document.currentScript.src : '';
  var runtimeBase = runtimeSrc ? runtimeSrc.replace(/workspace-mobile-runtime\.js(?:\?.*)?$/, '') : '';

  function loadAreaAssets() {
    if (!runtimeBase || document.querySelector('[data-verbum-workspace-assets]')) return;

    var style = document.createElement('link');
    style.rel = 'stylesheet';
    style.href = runtimeBase + 'styles/workspace-manager.css';
    style.setAttribute('data-verbum-workspace-assets', 'style');
    document.head.appendChild(style);

    var files = ['workspace-ui-runtime.js', 'workspace-manager-runtime.js', 'workspace-book-dialog-runtime.js'];
    function next(index) {
      if (index >= files.length) return;
      var script = document.createElement('script');
      script.src = runtimeBase + files[index];
      script.setAttribute('data-verbum-workspace-assets', files[index]);
      script.onload = function () { next(index + 1); };
      document.head.appendChild(script);
    }
    next(0);
  }

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

  loadAreaAssets();
  scan();
  new MutationObserver(scan).observe(document.documentElement, { childList: true, subtree: true });
})();
