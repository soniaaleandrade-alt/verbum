(function () {
  'use strict';

  var STORAGE_KEY = 'verbum_sidebar_collapsed';

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char];
    });
  }

  function initial(name) {
    return String(name || 'V').trim().charAt(0).toUpperCase() || 'V';
  }

  function isCollapsed() {
    try {
      return window.localStorage.getItem(STORAGE_KEY) === '1';
    } catch (error) {
      return false;
    }
  }

  function storeCollapsed(collapsed) {
    try {
      window.localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0');
    } catch (error) {
      // Storage is optional; the interface continues to work without it.
    }
  }

  function applyCollapsedState(root) {
    var shell = root.querySelector('.verbum-shell');
    if (!shell) return;
    if (window.innerWidth <= 820) {
      shell.classList.remove('is-sidebar-collapsed');
      return;
    }
    shell.classList.toggle('is-sidebar-collapsed', isCollapsed());
  }

  function renderProfile(root) {
    var footer = root.querySelector('.verbum-sidebar-footer');
    if (!footer || footer.getAttribute('data-profile-footer') === '1') {
      applyCollapsedState(root);
      return;
    }

    var state = root.__vs || {};
    var user = state.user || {};
    var name = user.name || 'Usuário';

    footer.setAttribute('data-profile-footer', '1');
    footer.innerHTML =
      '<div class="verbum-sidebar-profile">' +
        '<span class="verbum-sidebar-avatar" aria-hidden="true">' + esc(initial(name)) + '</span>' +
        '<span class="verbum-sidebar-profile-copy">' +
          '<strong title="' + esc(name) + '">' + esc(name) + '</strong>' +
          '<small>Minha conta</small>' +
        '</span>' +
        '<button type="button" class="verbum-sidebar-logout" data-verbum-logout aria-label="Sair da conta" title="Sair">' +
          '<span class="verbum-sidebar-logout-icon" aria-hidden="true"></span>' +
          '<span class="verbum-sidebar-logout-label">Sair</span>' +
        '</button>' +
      '</div>' +
      '<button type="button" class="verbum-sidebar-collapse" data-sidebar-collapse aria-label="Recolher menu lateral">' +
        '<span class="verbum-sidebar-collapse-icon" aria-hidden="true"></span>' +
        '<span class="verbum-sidebar-collapse-label">Recolher</span>' +
      '</button>';

    applyCollapsedState(root);
  }

  function handleClick(event) {
    var logout = event.target.closest('[data-verbum-logout]');
    if (logout) {
      var config = window.VerbumStudioConfig || {};
      if (config.logoutUrl) {
        window.location.assign(config.logoutUrl);
      }
      return;
    }

    var collapse = event.target.closest('[data-sidebar-collapse]');
    if (!collapse) return;

    var root = collapse.closest('[data-verbum-app]');
    if (!root) return;
    var shell = root.querySelector('.verbum-shell');
    if (!shell || window.innerWidth <= 820) return;

    var collapsed = !shell.classList.contains('is-sidebar-collapsed');
    shell.classList.toggle('is-sidebar-collapsed', collapsed);
    storeCollapsed(collapsed);
    collapse.setAttribute('aria-label', collapsed ? 'Expandir menu lateral' : 'Recolher menu lateral');
  }

  function boot(root) {
    renderProfile(root);
    new MutationObserver(function () {
      renderProfile(root);
    }).observe(root, { childList: true, subtree: true });
  }

  document.addEventListener('click', handleClick);
  window.addEventListener('resize', function () {
    document.querySelectorAll('[data-verbum-app]').forEach(applyCollapsedState);
  });
  document.querySelectorAll('[data-verbum-app]').forEach(boot);
})();
