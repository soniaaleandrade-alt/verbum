(function () {
  'use strict';

  var LEGACY = 'Projeto Teste Verbum';
  var TYPES = [
    ['book', 'Livro'], ['ebook', 'eBook'], ['devotional', 'Devocional'],
    ['article', 'Artigo / Ensaio'], ['course', 'Apostila / Curso'],
    ['academic', 'Projeto acadêmico'], ['other', 'Outro']
  ];
  var cache = null;

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char];
    });
  }

  function apiRoot() {
    return String((window.VerbumStudioConfig || {}).apiRoot || '/wp-json/verbum/v1').replace(/\/$/, '');
  }

  async function api(path, options) {
    var config = window.VerbumStudioConfig || {};
    var settings = Object.assign({ method: 'GET', credentials: 'same-origin' }, options || {});
    settings.headers = Object.assign({ 'X-WP-Nonce': config.nonce || '' }, settings.headers || {});
    if (settings.body && typeof settings.body !== 'string') {
      settings.headers['Content-Type'] = 'application/json';
      settings.body = JSON.stringify(settings.body);
    }
    var response = await fetch(apiRoot() + path, settings);
    var payload = await response.json();
    if (!response.ok || !payload.success) throw new Error(payload.error ? payload.error.message : 'Falha na API.');
    return payload.data;
  }

  function decode(value) {
    var text = String(value || '');
    var match = text.match(/^\[\[workspace_type:([a-z_]+)\]\]\s*/);
    return {
      type: match ? match[1] : 'book',
      description: text.replace(/^\[\[workspace_type:[a-z_]+\]\]\s*/, '').trim()
    };
  }

  function typeLabel(type) {
    var found = TYPES.find(function (item) { return item[0] === type; });
    return found ? found[1] : 'Outro';
  }

  function projects(library) {
    return (library.projects || []).filter(function (project) {
      return project.status === 'active' && project.name !== LEGACY;
    });
  }

  async function getLibrary(force) {
    if (cache && !force) return cache;
    cache = await api('/library');
    return cache;
  }

  function main(root) {
    return root.querySelector('#verbum-main-content') || root.querySelector('.verbum-main');
  }

  function render(root, library) {
    var list = projects(library);
    var books = library.books || [];
    var cards = list.map(function (project) {
      var details = decode(project.description);
      var count = books.filter(function (book) {
        return book.status === 'active' && String(book.projectId) === String(project.id);
      }).length;
      return '<article class="vs-area-card"><div class="vs-area-card-meta"><span class="vs-area-type">' + esc(typeLabel(details.type)) + '</span><span>' + count + ' ' + (count === 1 ? 'obra' : 'obras') + '</span></div><h3>' + esc(project.name) + '</h3><p>' + esc(details.description || 'Organize aqui as obras deste projeto editorial.') + '</p><button type="button" data-area-ver-obras>Ver obras</button></article>';
    }).join('');

    main(root).innerHTML = '<section class="vs-area-page"><header class="vs-area-head"><div><small>WORKSPACE EDITORIAL</small><h1>Área de Trabalho</h1><p>Crie e organize seus projetos editoriais antes de cadastrar as obras.</p></div><button type="button" data-area-nova>+ Nova Área de Trabalho</button></header><div class="vs-area-summary"><div><strong>' + list.length + '</strong><span>Áreas ativas</span></div><div><strong>' + books.filter(function (book) { return book.status === 'active'; }).length + '</strong><span>Obras ativas</span></div><div><strong>' + TYPES.length + '</strong><span>Tipos disponíveis</span></div></div><div class="vs-area-title"><h2>Suas Áreas de Trabalho</h2><p>Cada área reúne obras pertencentes ao mesmo projeto editorial.</p></div><div class="vs-area-grid">' + (cards || '<div class="vs-area-empty"><h2>Crie sua primeira Área de Trabalho</h2><p>Depois disso, você poderá cadastrar uma obra dentro dela.</p><button type="button" data-area-nova>+ Nova Área de Trabalho</button></div>') + '</div></section>';
  }

  function showModal(root) {
    var options = TYPES.map(function (item) {
      return '<option value="' + item[0] + '">' + esc(item[1]) + '</option>';
    }).join('');
    root.insertAdjacentHTML('beforeend', '<div class="vs-area-backdrop" data-area-modal><section class="vs-area-modal"><div class="vs-area-modal-head"><div><small>ÁREA DE TRABALHO</small><h2>Criar nova Área de Trabalho</h2></div><button type="button" data-area-fechar>×</button></div><form data-area-form><label><span>Nome da área *</span><input name="area_name" required maxlength="120"></label><label><span>Tipo de projeto *</span><select name="area_type">' + options + '</select></label><label><span>Descrição</span><textarea name="area_description" rows="4" maxlength="900"></textarea></label><p data-area-error hidden></p><div class="vs-area-actions"><button type="button" data-area-fechar>Cancelar</button><button type="submit">Criar Área de Trabalho</button></div></form></section></div>');
  }

  async function open(root) {
    main(root).innerHTML = '<div class="vs-area-loading">Carregando Área de Trabalho...</div>';
    try { render(root, await getLibrary(true)); }
    catch (error) { main(root).innerHTML = '<div class="vs-area-error">' + esc(error.message) + '</div>'; }
  }

  document.addEventListener('click', function (event) {
    var root = event.target.closest('[data-verbum-app]');
    if (!root) return;
    if (event.target.closest('[data-area-nova]')) { event.preventDefault(); showModal(root); }
    if (event.target.closest('[data-area-fechar]')) { event.preventDefault(); var modal = root.querySelector('[data-area-modal]'); if (modal) modal.remove(); }
    if (event.target.closest('[data-area-ver-obras]')) {
      var libraryButton = Array.from(root.querySelectorAll('.verbum-nav-item')).find(function (button) { return (button.textContent || '').trim() === 'Minhas Obras'; });
      if (libraryButton) libraryButton.click();
    }
  }, true);

  document.addEventListener('submit', function (event) {
    var form = event.target.closest('[data-area-form]');
    if (!form) return;
    event.preventDefault();
    var root = form.closest('[data-verbum-app]');
    var errorNode = form.querySelector('[data-area-error]');
    var submit = form.querySelector('button[type="submit"]');
    var name = String(form.elements.area_name.value || '').trim();
    var type = String(form.elements.area_type.value || 'book');
    var description = String(form.elements.area_description.value || '').trim();
    if (!name) { errorNode.hidden = false; errorNode.textContent = 'Informe o nome da Área de Trabalho.'; return; }
    submit.disabled = true;
    submit.textContent = 'Criando...';
    api('/projects', { method: 'POST', body: { name: name, description: '[[workspace_type:' + type + ']]\n' + description } }).then(function () {
      cache = null;
      var modal = root.querySelector('[data-area-modal]');
      if (modal) modal.remove();
      return open(root);
    }).catch(function (error) {
      errorNode.hidden = false;
      errorNode.textContent = error.message;
      submit.disabled = false;
      submit.textContent = 'Criar Área de Trabalho';
    });
  }, true);

  window.VerbumWorkspaceUI = { open: open, getLibrary: getLibrary, projects: projects };
})();
