(function () {
  'use strict';

  var config = window.VerbumStudioConfig || { apiRoot: '/wp-json/verbum/v1', nonce: '' };

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char];
    });
  }

  function initials(name) {
    var parts = String(name || '').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return 'V';
    return (parts[0].charAt(0) + (parts.length > 1 ? parts[parts.length - 1].charAt(0) : '')).toUpperCase();
  }

  function api(path, options) {
    options = options || {};
    var isForm = typeof FormData !== 'undefined' && options.body instanceof FormData;
    var headers = { 'X-WP-Nonce': config.nonce || '' };
    if (options.body !== undefined && !isForm) headers['Content-Type'] = 'application/json';
    return fetch((config.apiRoot || '/wp-json/verbum/v1') + path, {
      method: options.method || 'GET',
      credentials: 'same-origin',
      headers: headers,
      body: options.body === undefined ? undefined : (isForm ? options.body : JSON.stringify(options.body))
    }).then(function (response) {
      return response.json().catch(function () { throw new Error('A API retornou uma resposta inválida.'); }).then(function (payload) {
        if (!response.ok || !payload || !payload.success) {
          throw new Error(payload && payload.error && payload.error.message ? payload.error.message : 'Não foi possível salvar seu perfil.');
        }
        return payload.data;
      });
    });
  }

  function displayName(profile) {
    return String(profile && (profile.displayName || profile.name) || 'Usuário').trim() || 'Usuário';
  }

  function avatarMarkup(profile, alt) {
    var name = displayName(profile);
    if (profile && profile.avatarUrl) {
      return '<img src="' + esc(profile.avatarUrl) + '" alt="' + esc(alt || ('Foto de ' + name)) + '">';
    }
    return esc(initials(name));
  }

  function updateSidebar(profile) {
    document.querySelectorAll('[data-verbum-app]').forEach(function (root) {
      if (root.__vs && root.__vs.user) {
        root.__vs.user.name = displayName(profile);
        root.__vs.user.email = profile.email || root.__vs.user.email || '';
        root.__vs.user.avatarUrl = profile.avatarUrl || '';
      }

      var avatar = root.querySelector('.verbum-sidebar-avatar');
      if (avatar) avatar.innerHTML = avatarMarkup(profile, '');
      var name = root.querySelector('.verbum-sidebar-profile-copy strong');
      if (name) {
        name.textContent = displayName(profile);
        name.setAttribute('title', displayName(profile));
      }

      var headerName = root.querySelector('.verbum-user-copy strong');
      if (headerName) headerName.textContent = displayName(profile);
      var headerAvatar = root.querySelector('.verbum-user-trigger .verbum-avatar');
      if (headerAvatar) headerAvatar.innerHTML = avatarMarkup(profile, '');
    });
  }

  function updateProfileDialog(profile, form) {
    var backdrop = form.closest('[data-profile-backdrop]');
    if (!backdrop) return;
    var avatar = backdrop.querySelector('[data-profile-avatar]');
    if (avatar) avatar.innerHTML = avatarMarkup(profile, 'Foto de perfil');
    var headingName = backdrop.querySelector('.verbum-profile-avatar-copy strong');
    if (headingName) headingName.textContent = displayName(profile);
    var headingEmail = backdrop.querySelector('.verbum-profile-avatar-copy small');
    if (headingEmail) headingEmail.textContent = profile.email || '';

    var displayInput = form.querySelector('[name="display_name"]');
    if (displayInput && profile.displayName) displayInput.value = profile.displayName;
    var firstInput = form.querySelector('[name="first_name"]');
    if (firstInput && profile.firstName != null) firstInput.value = profile.firstName;
    var lastInput = form.querySelector('[name="last_name"]');
    if (lastInput && profile.lastName != null) lastInput.value = profile.lastName;
  }

  function removeAreaAtual(scope) {
    (scope || document).querySelectorAll('.verbum-header .verbum-eyebrow').forEach(function (node) {
      if (String(node.textContent || '').trim().toLocaleLowerCase('pt-BR') === 'área atual') node.remove();
    });
  }

  function formObject(form) {
    var payload = {};
    new FormData(form).forEach(function (value, key) {
      if (key !== 'avatar') payload[key] = value;
    });
    return payload;
  }

  function feedback(form, message, isError) {
    var node = form.querySelector('[data-profile-feedback]');
    if (!node) return;
    node.className = isError ? 'verbum-auth-error' : 'verbum-auth-success verbum-profile-saved';
    node.textContent = message || '';
  }

  function setBusy(form, busy) {
    var button = form.querySelector('.verbum-profile-primary');
    if (!button) return;
    button.disabled = !!busy;
    button.textContent = busy ? 'Salvando...' : 'Salvar alterações';
  }

  function saveProfile(event) {
    var form = event.target;
    if (!form || !form.matches('[data-profile-form]')) return;

    event.preventDefault();
    event.stopPropagation();
    event.stopImmediatePropagation();

    setBusy(form, true);
    feedback(form, '', false);
    var avatarInput = form.querySelector('input[name="avatar"]');

    api('/profile', { method: 'PATCH', body: formObject(form) })
      .then(function () {
        if (!avatarInput || !avatarInput.files || !avatarInput.files[0]) return null;
        var body = new FormData();
        body.append('avatar', avatarInput.files[0]);
        return api('/profile/avatar', { method: 'POST', body: body });
      })
      .then(function () { return api('/profile'); })
      .then(function (profile) {
        updateSidebar(profile);
        updateProfileDialog(profile, form);
        if (avatarInput) avatarInput.value = '';
        feedback(form, 'Alterações salvas.', false);
        window.dispatchEvent(new CustomEvent('verbum:profile-updated', { detail: profile }));
      })
      .catch(function (error) {
        feedback(form, error.message || 'Não foi possível salvar as alterações.', true);
      })
      .finally(function () { setBusy(form, false); });
  }

  document.addEventListener('submit', saveProfile, true);
  removeAreaAtual(document);

  new MutationObserver(function (mutations) {
    mutations.forEach(function (mutation) {
      mutation.addedNodes.forEach(function (node) {
        if (node.nodeType === 1) removeAreaAtual(node);
      });
    });
  }).observe(document.documentElement, { childList: true, subtree: true });
})();
