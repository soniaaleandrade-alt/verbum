(function () {
  'use strict';

  var STORAGE_KEY = 'verbum_sidebar_collapsed';

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char];
    });
  }

  function initial(name) {
    var parts = String(name || 'V').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return 'V';
    return (parts[0].charAt(0) + (parts.length > 1 ? parts[parts.length - 1].charAt(0) : '')).toUpperCase();
  }

  function icon(name) {
    var common = 'viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';
    var paths = {
      home: '<path d="m3 11 9-8 9 8"/><path d="M5 10v10h14V10M9 20v-6h6v6"/>',
      monitor: '<rect x="3" y="4" width="18" height="13" rx="2"/><path d="M8 21h8M12 17v4"/>',
      book: '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H11v16H6.5A2.5 2.5 0 0 0 4 21.5z"/><path d="M20 5.5A2.5 2.5 0 0 0 17.5 3H13v16h4.5a2.5 2.5 0 0 1 2.5 2.5z"/>',
      file: '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v5h5M9 13h6M9 17h5"/>',
      bulb: '<path d="M9 18h6M10 22h4"/><path d="M8.5 15.5A7 7 0 1 1 15.5 15.5c-.9.7-1.5 1.4-1.7 2.5h-3.6c-.2-1.1-.8-1.8-1.7-2.5z"/>',
      quote: '<path d="M8 10H4v5h4v-3c0-2-1-4-3-5M20 10h-4v5h4v-3c0-2-1-4-3-5"/>',
      calendar: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/>',
      clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
      chart: '<path d="M5 20V11M12 20V5M19 20V8"/>',
      cloud: '<path d="M7 18h10a4 4 0 0 0 .5-8 6 6 0 0 0-11-1.5A4.5 4.5 0 0 0 7 18z"/>',
      backup: '<ellipse cx="12" cy="5" rx="7" ry="3"/><path d="M5 5v6c0 1.7 3.1 3 7 3s7-1.3 7-3V5M5 11v6c0 1.7 3.1 3 7 3s7-1.3 7-3v-6"/>',
      sliders: '<path d="M4 7h10M18 7h2M4 17h2M10 17h10M14 4v6M6 14v6"/>',
      trash: '<path d="M4 7h16M9 7V4h6v3M7 7l1 14h8l1-14M10 11v6M14 11v6"/>'
    };
    return '<svg ' + common + '>' + (paths[name] || paths.file) + '</svg>';
  }

  function navIconName(label) {
    var value = String(label || '').trim().toLocaleLowerCase('pt-BR');
    if (value === 'painel') return 'home';
    if (value.indexOf('mesa de trabalho') >= 0) return 'monitor';
    if (value === 'obras' || value.indexOf('minhas obras') >= 0) return 'book';
    if (value.indexOf('base de conhecimento') >= 0) return 'file';
    if (value === 'ideias') return 'bulb';
    if (value.indexOf('cita') >= 0) return 'quote';
    if (value.indexOf('calendário') >= 0 || value.indexOf('calendario') >= 0) return 'calendar';
    if (value.indexOf('cronograma') >= 0) return 'clock';
    if (value.indexOf('relatório') >= 0 || value.indexOf('relatorio') >= 0) return 'chart';
    if (value.indexOf('cloud') >= 0) return 'cloud';
    if (value.indexOf('backup') >= 0) return 'backup';
    if (value.indexOf('prefer') >= 0) return 'sliders';
    if (value.indexOf('lixeira') >= 0) return 'trash';
    return 'file';
  }

  function ensureNavIcons(root) {
    root.querySelectorAll('.verbum-nav-item').forEach(function (item) {
      var mark = item.querySelector('.verbum-nav-dot');
      if (!mark) return;
      var copy = item.querySelector('span:last-child');
      var label = copy ? copy.textContent : item.textContent;
      var name = navIconName(label);
      if (mark.getAttribute('data-nav-icon') === name) return;
      mark.setAttribute('data-nav-icon', name);
      mark.classList.add('has-nav-icon');
      mark.innerHTML = icon(name);
    });
  }

  function updateContextClasses(root) {
    var shell = root.querySelector('.verbum-shell');
    if (!shell) return;
    shell.classList.toggle('has-minhas-obras', !!root.querySelector('.verbum-minhas-obras'));
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
    } catch (error) {}
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
    if (!footer) return;

    if (footer.getAttribute('data-profile-footer') !== '1') {
      var state = root.__vs || {};
      var user = state.user || {};
      var name = user.name || 'Usuário';
      var avatar = user.avatarUrl
        ? '<img src="' + esc(user.avatarUrl) + '" alt="">'
        : esc(initial(name));

      footer.setAttribute('data-profile-footer', '1');
      footer.innerHTML =
        '<div class="verbum-sidebar-profile">' +
          '<button type="button" class="verbum-sidebar-profile-main" data-verbum-profile aria-label="Abrir minha conta">' +
            '<span class="verbum-sidebar-avatar" aria-hidden="true">' + avatar + '</span>' +
            '<span class="verbum-sidebar-profile-copy">' +
              '<strong title="' + esc(name) + '">' + esc(name) + '</strong>' +
              '<small>Minha conta</small>' +
            '</span>' +
          '</button>' +
          '<button type="button" class="verbum-sidebar-logout" data-verbum-logout aria-label="Sair da conta" title="Sair">' +
            '<span class="verbum-sidebar-logout-icon" aria-hidden="true"></span>' +
            '<span class="verbum-sidebar-logout-label">Sair</span>' +
          '</button>' +
        '</div>' +
        '<button type="button" class="verbum-sidebar-collapse" data-sidebar-collapse aria-label="Recolher menu lateral">' +
          '<span class="verbum-sidebar-collapse-icon" aria-hidden="true"></span>' +
          '<span class="verbum-sidebar-collapse-label">Recolher</span>' +
        '</button>';
    }

    ensureNavIcons(root);
    updateContextClasses(root);
    applyCollapsedState(root);
  }

  function handleClick(event) {
    var logout = event.target.closest('[data-verbum-logout]');
    if (logout) {
      event.preventDefault();
      if (window.VerbumAuthProfile && typeof window.VerbumAuthProfile.logout === 'function') {
        window.VerbumAuthProfile.logout();
      } else {
        var config = window.VerbumStudioConfig || {};
        if (config.logoutUrl) window.location.assign(config.logoutUrl);
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
