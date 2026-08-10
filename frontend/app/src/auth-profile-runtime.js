(function () {
  'use strict';

  var config = window.VerbumStudioConfig || { apiRoot: '/wp-json/verbum/v1', nonce: '', authenticated: false, appUrl: '/' };
  var appUrl = config.appUrl || '/';

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char];
    });
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
          var error = new Error(payload && payload.error && payload.error.message ? payload.error.message : 'Não foi possível comunicar com a API.');
          error.code = payload && payload.error ? payload.error.code : '';
          error.status = response.status;
          throw error;
        }
        return payload.data;
      });
    });
  }

  function cleanUrl() {
    var url = new URL(window.location.href);
    ['verbum_reset', 'verbum_verify', 'key', 'login', 'token', 'email'].forEach(function (key) { url.searchParams.delete(key); });
    return url.toString();
  }

  function modeFromUrl() {
    var params = new URLSearchParams(window.location.search);
    if (params.get('verbum_reset') === '1') return 'reset';
    if (params.get('verbum_verify') === '1') return 'verify';
    return 'login';
  }

  function brandPanel() {
    return '<aside class="verbum-auth-brand-panel">' +
      '<div class="verbum-auth-brand"><span class="verbum-auth-brand-mark">V</span><span><strong>VERBUM STUDIO</strong><small>SISTEMA OPERACIONAL PARA ESCRITORES</small></span></div>' +
      '<div class="verbum-auth-message"><span>Seu espaço editorial</span><h1>Da primeira ideia à publicação.</h1><p>Organize suas obras, preserve seu processo criativo e acompanhe cada etapa do trabalho editorial em um só lugar.</p></div>' +
      '<div class="verbum-auth-quote">Escrever com clareza também é cuidar da obra.</div>' +
    '</aside>';
  }

  function loginForm() {
    return '<div class="verbum-auth-card" data-auth-card>' +
      '<div class="verbum-auth-card-header"><span>Bem-vindo de volta</span><h2>Entre no Verbum Studio</h2><p>Acesse seu espaço de escrita e continue de onde parou.</p></div>' +
      '<form class="verbum-auth-form" data-auth-login>' +
        '<label class="verbum-auth-field"><span>E-mail</span><input type="email" name="email" autocomplete="email" required></label>' +
        '<label class="verbum-auth-field"><span>Senha</span><input type="password" name="password" autocomplete="current-password" required></label>' +
        '<div class="verbum-auth-row"><label class="verbum-auth-check"><input type="checkbox" name="remember" value="1"><span>Lembrar meu acesso</span></label><button type="button" class="verbum-auth-link" data-auth-mode="forgot">Esqueci minha senha</button></div>' +
        '<div data-auth-feedback></div><button class="verbum-auth-submit" type="submit">Entrar</button>' +
        '<div class="verbum-auth-divider">ou</div><p class="verbum-auth-switch">Ainda não tem uma conta? <button type="button" class="verbum-auth-link" data-auth-mode="register">Criar conta</button></p>' +
      '</form></div>';
  }

  function registerForm() {
    return '<div class="verbum-auth-card" data-auth-card>' +
      '<div class="verbum-auth-card-header"><span>Crie seu espaço</span><h2>Criar conta</h2><p>Comece com seus dados básicos. Depois você poderá completar seu perfil.</p></div>' +
      '<form class="verbum-auth-form" data-auth-register>' +
        '<div class="verbum-auth-grid"><label class="verbum-auth-field"><span>Nome</span><input name="first_name" autocomplete="given-name" required></label><label class="verbum-auth-field"><span>Sobrenome</span><input name="last_name" autocomplete="family-name" required></label></div>' +
        '<label class="verbum-auth-field"><span>E-mail</span><input type="email" name="email" autocomplete="email" required></label>' +
        '<div class="verbum-auth-grid"><label class="verbum-auth-field"><span>Senha</span><input type="password" name="password" minlength="8" autocomplete="new-password" required></label><label class="verbum-auth-field"><span>Confirmar senha</span><input type="password" name="confirm_password" minlength="8" autocomplete="new-password" required></label></div>' +
        '<label class="verbum-auth-check"><input type="checkbox" name="accepted_terms" value="1" required><span>Li e aceito os Termos de Uso e a Política de Privacidade.</span></label>' +
        '<div data-auth-feedback></div><button class="verbum-auth-submit" type="submit">Criar minha conta</button>' +
        '<p class="verbum-auth-switch">Já possui uma conta? <button type="button" class="verbum-auth-link" data-auth-mode="login">Entrar</button></p>' +
      '</form></div>';
  }

  function forgotForm() {
    return '<div class="verbum-auth-card" data-auth-card>' +
      '<div class="verbum-auth-card-header"><span>Recuperar acesso</span><h2>Redefinir senha</h2><p>Informe seu e-mail. Se houver uma conta cadastrada, enviaremos um link de redefinição.</p></div>' +
      '<form class="verbum-auth-form" data-auth-forgot><label class="verbum-auth-field"><span>E-mail</span><input type="email" name="email" required></label><div data-auth-feedback></div><button class="verbum-auth-submit">Enviar instruções</button><p class="verbum-auth-switch"><button type="button" class="verbum-auth-link" data-auth-mode="login">Voltar para entrar</button></p></form>' +
    '</div>';
  }

  function resetForm() {
    return '<div class="verbum-auth-card" data-auth-card>' +
      '<div class="verbum-auth-card-header"><span>Novo acesso</span><h2>Escolha uma nova senha</h2><p>Use pelo menos 8 caracteres.</p></div>' +
      '<form class="verbum-auth-form" data-auth-reset><label class="verbum-auth-field"><span>Nova senha</span><input type="password" name="password" minlength="8" required></label><label class="verbum-auth-field"><span>Confirmar senha</span><input type="password" name="confirm_password" minlength="8" required></label><div data-auth-feedback></div><button class="verbum-auth-submit">Salvar nova senha</button></form>' +
    '</div>';
  }

  function verifyCard() {
    return '<div class="verbum-auth-card" data-auth-card><div class="verbum-auth-card-header"><span>Confirmação de e-mail</span><h2>Confirmando seu e-mail</h2><p>Aguarde enquanto validamos o endereço informado no cadastro.</p></div><div data-auth-feedback class="verbum-auth-success">Processando a confirmação...</div></div>';
  }

  function renderAuth(root, mode) {
    mode = mode || 'login';
    root.removeAttribute('data-verbum-app');
    root.setAttribute('data-verbum-auth-root', '1');
    var content = mode === 'register' ? registerForm() : mode === 'forgot' ? forgotForm() : mode === 'reset' ? resetForm() : mode === 'verify' ? verifyCard() : loginForm();
    root.innerHTML = '<div class="verbum-auth-shell">' + brandPanel() + '<main class="verbum-auth-main">' + content + '</main></div>';
    root.__verbumAuthMode = mode;
    if (mode === 'verify') verifyFromUrl(root);
  }

  function feedback(form, message, success) {
    var node = form.querySelector('[data-auth-feedback]');
    if (!node) return;
    node.className = success ? 'verbum-auth-success' : 'verbum-auth-error';
    node.textContent = message || '';
  }

  function setBusy(form, busy) {
    var button = form.querySelector('.verbum-auth-submit');
    if (button) button.disabled = !!busy;
  }

  function formDataObject(form) {
    var data = {};
    new FormData(form).forEach(function (value, key) { data[key] = value; });
    return data;
  }

  function verifyFromUrl(root) {
    var params = new URLSearchParams(window.location.search);
    api('/auth/verify-email', { method: 'POST', body: { email: params.get('email') || '', token: params.get('token') || '' } }).then(function (result) {
      var card = root.querySelector('[data-auth-card]');
      if (!card) return;
      card.innerHTML = '<div class="verbum-auth-card-header"><span>Confirmação concluída</span><h2>E-mail confirmado</h2><p>' + esc(result.message || 'Seu e-mail foi confirmado com sucesso.') + '</p></div><button type="button" class="verbum-auth-submit" data-auth-finish>Continuar</button>';
    }).catch(function (error) {
      var node = root.querySelector('[data-auth-feedback]');
      if (node) { node.className = 'verbum-auth-error'; node.textContent = error.message; }
    });
  }

  function profileInitial(name) {
    var parts = String(name || '').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return 'V';
    return (parts[0].charAt(0) + (parts.length > 1 ? parts[parts.length - 1].charAt(0) : '')).toUpperCase();
  }

  function profileMarkup(profile) {
    var avatar = profile.avatarUrl ? '<img src="' + esc(profile.avatarUrl) + '" alt="Foto de perfil">' : esc(profileInitial(profile.name));
    return '<div class="verbum-profile-backdrop" data-profile-backdrop><section class="verbum-profile-dialog" role="dialog" aria-modal="true" aria-label="Minha conta">' +
      '<header class="verbum-profile-head"><div><span>Verbum Studio</span><h2>Minha conta</h2></div><button type="button" class="verbum-profile-close" data-profile-close aria-label="Fechar">×</button></header>' +
      '<form class="verbum-profile-body" data-profile-form>' +
        '<div class="verbum-profile-avatar-row"><span class="verbum-profile-avatar" data-profile-avatar>' + avatar + '</span><div class="verbum-profile-avatar-copy"><strong>' + esc(profile.name || 'Sua conta') + '</strong><small>' + esc(profile.email || '') + '</small><label class="verbum-profile-file">Trocar foto<input type="file" name="avatar" accept="image/jpeg,image/png,image/webp"></label></div></div>' +
        '<div class="verbum-profile-grid">' +
          '<label class="verbum-auth-field"><span>Nome</span><input name="first_name" value="' + esc(profile.firstName || '') + '" required></label>' +
          '<label class="verbum-auth-field"><span>Sobrenome</span><input name="last_name" value="' + esc(profile.lastName || '') + '" required></label>' +
          '<label class="verbum-auth-field verbum-profile-span-2"><span>Nome de exibição</span><input name="display_name" value="' + esc(profile.displayName || profile.name || '') + '"></label>' +
          '<label class="verbum-auth-field"><span>Telefone</span><input name="phone" value="' + esc(profile.phone || '') + '"></label>' +
          '<label class="verbum-auth-field"><span>País</span><input name="country" value="' + esc(profile.country || 'Brasil') + '"></label>' +
          '<label class="verbum-auth-field"><span>Idioma</span><select name="language"><option value="pt_BR"' + (profile.language === 'pt_BR' ? ' selected' : '') + '>Português (Brasil)</option><option value="en_US"' + (profile.language === 'en_US' ? ' selected' : '') + '>English</option><option value="es_ES"' + (profile.language === 'es_ES' ? ' selected' : '') + '>Español</option></select></label>' +
          '<label class="verbum-auth-field"><span>Fuso horário</span><select name="timezone"><option value="America/Sao_Paulo"' + (profile.timezone === 'America/Sao_Paulo' ? ' selected' : '') + '>Brasília</option><option value="America/Manaus"' + (profile.timezone === 'America/Manaus' ? ' selected' : '') + '>Manaus</option><option value="America/Rio_Branco"' + (profile.timezone === 'America/Rio_Branco' ? ' selected' : '') + '>Rio Branco</option><option value="UTC"' + (profile.timezone === 'UTC' ? ' selected' : '') + '>UTC</option></select></label>' +
          '<label class="verbum-auth-field verbum-profile-span-2"><span>Sobre você</span><textarea name="bio" rows="4">' + esc(profile.bio || '') + '</textarea></label>' +
        '</div>' +
        '<div class="verbum-profile-verification' + (profile.emailVerified ? ' is-verified' : '') + '"><div><strong>' + (profile.emailVerified ? 'E-mail confirmado' : 'Confirme seu e-mail') + '</strong><div>' + esc(profile.email || '') + '</div></div>' + (profile.emailVerified ? '<span>Concluído</span>' : '<button type="button" class="verbum-auth-link" data-resend-verification>Reenviar verificação</button>') + '</div>' +
        '<div data-profile-feedback></div><div class="verbum-profile-actions"><button type="button" class="verbum-profile-secondary" data-profile-close>Cancelar</button><button type="submit" class="verbum-profile-primary">Salvar alterações</button></div>' +
      '</form></section></div>';
  }

  function openProfile(root) {
    api('/profile').then(function (profile) {
      var existing = document.querySelector('[data-profile-backdrop]');
      if (existing) existing.remove();
      root.insertAdjacentHTML('beforeend', profileMarkup(profile));
    }).catch(function (error) {
      if (error.status === 401) {
        config.authenticated = false;
        window.location.assign(appUrl);
        return;
      }
      window.alert(error.message || 'Não foi possível carregar seu perfil.');
    });
  }

  function logout() {
    return api('/auth/logout', { method: 'POST', body: {} }).catch(function () { return null; }).then(function () {
      config.authenticated = false;
      window.location.assign(appUrl);
    });
  }

  function handleAuthSubmit(event) {
    var form = event.target;
    if (!form.matches('[data-auth-login],[data-auth-register],[data-auth-forgot],[data-auth-reset],[data-profile-form]')) return;
    event.preventDefault();

    if (form.matches('[data-profile-form]')) {
      var payload = formDataObject(form);
      delete payload.avatar;
      var status = form.querySelector('[data-profile-feedback]');
      if (status) { status.className = ''; status.textContent = ''; }
      var avatarInput = form.querySelector('input[name="avatar"]');
      api('/profile', { method: 'PATCH', body: payload }).then(function () {
        if (!avatarInput || !avatarInput.files || !avatarInput.files[0]) return null;
        var body = new FormData(); body.append('avatar', avatarInput.files[0]);
        return api('/profile/avatar', { method: 'POST', body: body });
      }).then(function () {
        if (status) { status.className = 'verbum-auth-success'; status.textContent = 'Perfil atualizado com sucesso.'; }
        window.setTimeout(function () { window.location.reload(); }, 450);
      }).catch(function (error) {
        if (status) { status.className = 'verbum-auth-error'; status.textContent = error.message; }
      });
      return;
    }

    setBusy(form, true);
    var data = formDataObject(form);

    if (form.matches('[data-auth-login]')) {
      data.remember = !!form.querySelector('input[name="remember"]:checked');
      api('/auth/login', { method: 'POST', body: data }).then(function (result) {
        if (result.nonce) config.nonce = result.nonce;
        config.authenticated = true;
        window.location.assign(cleanUrl());
      }).catch(function (error) { feedback(form, error.message, false); setBusy(form, false); });
      return;
    }

    if (form.matches('[data-auth-register]')) {
      if (data.password !== data.confirm_password) { feedback(form, 'As senhas precisam ser iguais.', false); setBusy(form, false); return; }
      data.accepted_terms = !!form.querySelector('input[name="accepted_terms"]:checked');
      delete data.confirm_password;
      api('/auth/register', { method: 'POST', body: data }).then(function (result) {
        if (result.nonce) config.nonce = result.nonce;
        config.authenticated = true;
        window.location.assign(cleanUrl());
      }).catch(function (error) { feedback(form, error.message, false); setBusy(form, false); });
      return;
    }

    if (form.matches('[data-auth-forgot]')) {
      api('/auth/forgot-password', { method: 'POST', body: data }).then(function (result) {
        feedback(form, result.message || 'Verifique seu e-mail.', true); setBusy(form, false);
      }).catch(function (error) { feedback(form, error.message, false); setBusy(form, false); });
      return;
    }

    if (form.matches('[data-auth-reset]')) {
      if (data.password !== data.confirm_password) { feedback(form, 'As senhas precisam ser iguais.', false); setBusy(form, false); return; }
      var params = new URLSearchParams(window.location.search);
      api('/auth/reset-password', { method: 'POST', body: { login: params.get('login') || '', key: params.get('key') || '', password: data.password } }).then(function (result) {
        feedback(form, result.message || 'Senha alterada.', true);
        window.setTimeout(function () { window.location.assign(cleanUrl()); }, 900);
      }).catch(function (error) { feedback(form, error.message, false); setBusy(form, false); });
    }
  }

  function handleClick(event) {
    var mode = event.target.closest('[data-auth-mode]');
    if (mode) {
      var root = mode.closest('[data-verbum-auth-root]');
      if (root) renderAuth(root, mode.getAttribute('data-auth-mode') || 'login');
      return;
    }

    if (event.target.closest('[data-auth-finish]')) { window.location.assign(cleanUrl()); return; }

    var profileOpen = event.target.closest('[data-verbum-profile]');
    if (profileOpen) {
      var appRoot = profileOpen.closest('[data-verbum-app]');
      if (appRoot) openProfile(appRoot);
      return;
    }

    if (event.target.closest('[data-profile-close]')) {
      var modal = event.target.closest('[data-profile-backdrop]');
      if (modal) modal.remove();
      return;
    }

    var resend = event.target.closest('[data-resend-verification]');
    if (resend) {
      resend.disabled = true;
      api('/auth/resend-verification', { method: 'POST', body: {} }).then(function (result) {
        resend.textContent = result.message || 'Enviado';
      }).catch(function (error) { resend.textContent = error.message; resend.disabled = false; });
      return;
    }
  }

  document.addEventListener('submit', handleAuthSubmit);
  document.addEventListener('click', handleClick);

  var roots = Array.prototype.slice.call(document.querySelectorAll('[data-verbum-app]'));
  var initialMode = modeFromUrl();
  if (!config.authenticated || initialMode === 'reset' || initialMode === 'verify') {
    roots.forEach(function (root) { renderAuth(root, initialMode); });
  }

  window.VerbumAuthProfile = { openProfile: openProfile, logout: logout, renderAuth: renderAuth };
})();
