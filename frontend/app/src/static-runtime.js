(function () {
  'use strict';

  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char];
    });
  }

  function config() {
    return window.VerbumStudioConfig || { apiRoot: '/wp-json/verbum/v1', nonce: '', version: '1.0.0' };
  }

  function request(path, options) {
    var opts = options || {};
    var c = config();
    var headers = { 'X-WP-Nonce': c.nonce || '' };
    if (opts.body !== undefined) headers['Content-Type'] = 'application/json';
    return fetch(c.apiRoot + path, {
      method: opts.method || 'GET',
      credentials: 'same-origin',
      headers: headers,
      body: opts.body === undefined ? undefined : JSON.stringify(opts.body)
    }).then(function (response) {
      return response.json().catch(function () { throw new Error('A API retornou uma resposta inválida.'); }).then(function (payload) {
        if (!response.ok || !payload || !payload.success) {
          throw new Error(payload && payload.error && payload.error.message ? payload.error.message : 'Não foi possível comunicar com a API.');
        }
        return payload.data;
      });
    });
  }

  function userInitial(name) {
    return (String(name || 'V').trim().charAt(0) || 'V').toUpperCase();
  }

  function number(value) {
    var parsed = Number(value || 0);
    return Number.isFinite(parsed) ? parsed : 0;
  }

  function stateFor(root) {
    if (!root.__verbumState) {
      root.__verbumState = {
        user: { id: '0', name: 'Você', email: '' },
        library: { projects: [], books: [] },
        section: 'dashboard',
        filter: 'active',
        query: '',
        modal: null,
        loadingLibrary: false,
        libraryError: ''
      };
    }
    return root.__verbumState;
  }

  function navHtml(state) {
    var items = [
      ['dashboard', 'Início'],
      ['', 'Workspace'],
      ['library', 'Obras'],
      ['', 'Calendário editorial'],
      ['', 'Relatórios'],
      ['', 'Ideias']
    ];
    return items.map(function (item) {
      var section = item[0];
      var active = section && state.section === section;
      var disabled = !section;
      return '<button type="button" class="verbum-nav-item' + (active ? ' is-active' : '') + '"' +
        (active ? ' aria-current="page"' : '') + (disabled ? ' disabled aria-disabled="true"' : ' data-section="' + section + '"') + '>' +
        '<span class="verbum-nav-dot" aria-hidden="true"></span><span>' + escapeHtml(item[1]) + '</span></button>';
    }).join('');
  }

  function dashboardHtml(state) {
    var activeBooks = state.library.books.filter(function (book) { return book.status === 'active'; });
    var activeProjects = state.library.projects.filter(function (project) { return project.status === 'active'; });
    var plannedChapters = activeBooks.reduce(function (total, book) { return total + number(book.plannedChapters); }, 0);
    var wordGoal = activeBooks.reduce(function (total, book) { return total + number(book.wordGoal); }, 0);
    var currentBook = activeBooks[0];
    var continueHtml = currentBook ?
      '<div class="verbum-current-book"><span class="verbum-current-book-color" style="background-color:' + escapeHtml(currentBook.color || '#7a3042') + '"></span><div><span class="verbum-eyebrow">Identificação da Obra</span><h3>' + escapeHtml(currentBook.title) + '</h3><p>' + escapeHtml(currentBook.subtitle || 'Complete os dados da obra para avançar com segurança para o planejamento editorial.') + '</p></div><button type="button" class="verbum-primary-button" data-section="library">Abrir obra</button></div>' :
      '<div class="verbum-empty-state"><div class="verbum-empty-symbol">V</div><div><h3>Sua próxima obra começa aqui</h3><p>Crie uma obra para organizar planejamento, pesquisa, redação, revisão e publicação em um único fluxo.</p></div><button type="button" class="verbum-primary-button" data-section="library">Criar nova obra</button></div>';

    return '<div class="verbum-dashboard">' +
      '<section class="verbum-welcome"><span class="verbum-eyebrow">Seu espaço de escrita</span><h2>Bom trabalho, ' + escapeHtml(state.user.name) + '</h2><p>Organize sua obra com clareza, acompanhe cada etapa e mantenha o foco no que precisa ser escrito agora.</p></section>' +
      '<section class="verbum-stats" aria-label="Indicadores do painel">' +
        '<article class="verbum-stat-card"><span>Obras em andamento</span><strong>' + activeBooks.length + '</strong><small>' + (activeBooks.length ? activeProjects.length + ' projetos ativos' : 'Nenhuma obra ativa') + '</small></article>' +
        '<article class="verbum-stat-card"><span>Capítulos previstos</span><strong>' + plannedChapters + '</strong><small>' + (plannedChapters ? 'Meta inicial das obras' : 'Aguardando planejamento') + '</small></article>' +
        '<article class="verbum-stat-card"><span>Meta de palavras</span><strong>' + wordGoal.toLocaleString('pt-BR') + '</strong><small>' + (wordGoal ? 'Meta editorial cadastrada' : 'Defina na identificação da obra') + '</small></article>' +
        '<article class="verbum-stat-card"><span>Etapa atual</span><strong>' + (activeBooks.length ? '01' : '—') + '</strong><small>' + (activeBooks.length ? 'Identificação da Obra' : 'Sem obra ativa') + '</small></article>' +
      '</section>' +
      '<section class="verbum-panel verbum-continue"><div class="verbum-section-heading"><div><span class="verbum-eyebrow">Retome sua jornada</span><h2>Continue escrevendo</h2></div></div>' + continueHtml + '</section>' +
      '<div class="verbum-dashboard-grid"><section class="verbum-panel verbum-quick-actions"><div class="verbum-section-heading"><div><span class="verbum-eyebrow">Atalhos</span><h2>Ações rápidas</h2></div></div><div class="verbum-action-list">' +
        '<button type="button" class="verbum-action-card" data-section="library"><span class="verbum-action-plus">+</span><span><strong>Criar nova obra</strong><small>Inicie um novo projeto editorial.</small></span></button>' +
        '<button type="button" class="verbum-action-card" disabled aria-disabled="true"><span class="verbum-action-plus">+</span><span><strong>Registrar uma ideia</strong><small>Guarde um ponto para desenvolver depois.</small></span></button>' +
        '<button type="button" class="verbum-action-card" disabled aria-disabled="true"><span class="verbum-action-plus">+</span><span><strong>Abrir calendário</strong><small>Visualize seu planejamento editorial.</small></span></button>' +
      '</div></section><section class="verbum-panel verbum-recent-activity"><div class="verbum-section-heading"><div><span class="verbum-eyebrow">Histórico</span><h2>Atividade recente</h2></div></div><div class="verbum-activity-empty"><span class="verbum-activity-line"></span><p>Quando você começar a trabalhar em uma obra, suas atividades mais recentes aparecerão aqui.</p></div></section></div>' +
    '</div>';
  }

  function bookCard(book, project) {
    var archived = book.status === 'archived';
    return '<article class="verbum-book-card' + (archived ? ' is-archived' : '') + '">' +
      '<div class="verbum-book-color" style="background-color:' + escapeHtml(book.color || '#7a3042') + '"></div><div class="verbum-book-card-body"><div class="verbum-book-card-topline"><span class="verbum-book-project">' + escapeHtml(project.name) + '</span><span class="verbum-status-chip' + (archived ? ' is-muted' : '') + '">' + escapeHtml(archived ? 'Arquivada' : (book.workflowStatus || 'Planejamento')) + '</span></div>' +
      '<h3>' + escapeHtml(book.title) + '</h3>' + (book.subtitle ? '<p class="verbum-book-subtitle">' + escapeHtml(book.subtitle) + '</p>' : '') +
      '<div class="verbum-book-meta"><span>' + escapeHtml(book.genre || book.category || 'Sem gênero definido') + '</span><span>' + (number(book.plannedChapters) ? number(book.plannedChapters) + ' capítulos previstos' : 'Capítulos ainda não definidos') + '</span></div>' +
      '<div class="verbum-book-stage"><span>Etapa atual</span><strong>Identificação da Obra</strong></div></div>' +
      '<div class="verbum-book-card-actions"><button type="button" class="verbum-text-button" data-edit-book="' + escapeHtml(book.id) + '">Editar</button>' + (!archived ? '<button type="button" class="verbum-text-button is-danger" data-archive-book="' + escapeHtml(book.id) + '">Arquivar</button>' : '') + '</div></article>';
  }

  function libraryHtml(state) {
    var q = state.query.trim().toLocaleLowerCase('pt-BR');
    var activeProjects = state.library.projects.filter(function (p) { return p.status === 'active'; });
    var activeBooks = state.library.books.filter(function (b) { return b.status === 'active'; });
    var projects = state.library.projects.filter(function (project) {
      if (state.filter !== 'all' && project.status !== state.filter) return false;
      if (!q) return true;
      if ((project.name + ' ' + (project.description || '')).toLocaleLowerCase('pt-BR').indexOf(q) !== -1) return true;
      return state.library.books.some(function (book) {
        return book.projectId === project.id && (book.title + ' ' + (book.subtitle || '') + ' ' + (book.genre || '')).toLocaleLowerCase('pt-BR').indexOf(q) !== -1;
      });
    });
    var body;
    if (state.loadingLibrary) {
      body = '<section class="verbum-library-loading">Carregando seu Banco de Obras...</section>';
    } else if (!state.library.projects.length) {
      body = '<section class="verbum-panel verbum-library-empty"><span class="verbum-empty-symbol">V</span><div><span class="verbum-eyebrow">Primeiro passo</span><h3>Crie seu primeiro projeto</h3><p>O projeto reúne uma ou mais obras. Depois de criá-lo, você poderá cadastrar o primeiro livro e preencher sua identificação.</p></div><button type="button" class="verbum-primary-button" data-new-project>Criar projeto</button></section>';
    } else if (!projects.length) {
      body = '<section class="verbum-panel verbum-library-empty compact"><div><h3>Nenhum resultado encontrado</h3><p>Ajuste a busca ou o filtro para localizar suas obras.</p></div></section>';
    } else {
      body = '<div class="verbum-project-list">' + projects.map(function (project) {
        var books = state.library.books.filter(function (book) { return book.projectId === project.id && (state.filter === 'all' || book.status === state.filter); });
        return '<section class="verbum-panel verbum-project-section' + (project.status === 'archived' ? ' is-archived' : '') + '"><div class="verbum-project-heading"><div><span class="verbum-eyebrow">Projeto</span><h3>' + escapeHtml(project.name) + '</h3>' + (project.description ? '<p>' + escapeHtml(project.description) + '</p>' : '') + '</div><div class="verbum-project-actions"><span>' + books.length + ' ' + (books.length === 1 ? 'obra' : 'obras') + '</span><button type="button" class="verbum-text-button" data-edit-project="' + escapeHtml(project.id) + '">Editar</button>' + (project.status !== 'archived' ? '<button type="button" class="verbum-text-button is-danger" data-archive-project="' + escapeHtml(project.id) + '">Arquivar</button>' : '') + '</div></div>' +
          (books.length ? '<div class="verbum-book-grid">' + books.map(function (book) { return bookCard(book, project); }).join('') + '</div>' : '<div class="verbum-project-empty"><p>Nenhuma obra neste projeto.</p>' + (project.status === 'active' ? '<button type="button" class="verbum-text-button" data-new-book data-project="' + escapeHtml(project.id) + '">Adicionar obra</button>' : '') + '</div>') + '</section>';
      }).join('') + '</div>';
    }

    return '<div class="verbum-library"><section class="verbum-page-heading"><div><span class="verbum-eyebrow">Seu acervo editorial</span><h2>Banco de Obras</h2><p>Organize seus projetos e livros. Cada obra permanece vinculada ao projeto em que nasceu.</p></div><div class="verbum-page-actions"><button type="button" class="verbum-secondary-button" data-new-project>Novo projeto</button><button type="button" class="verbum-primary-button" data-new-book' + (!activeProjects.length ? ' disabled' : '') + '>Criar nova obra</button></div></section>' +
      '<section class="verbum-library-summary"><div><strong>' + activeProjects.length + '</strong><span>Projetos ativos</span></div><div><strong>' + activeBooks.length + '</strong><span>Obras ativas</span></div><div><strong>' + state.library.books.filter(function (b) { return b.status === 'archived'; }).length + '</strong><span>Obras arquivadas</span></div></section>' +
      '<section class="verbum-library-toolbar"><label class="verbum-search-field"><span class="sr-only">Buscar</span><input data-library-search value="' + escapeHtml(state.query) + '" placeholder="Buscar projeto, título, subtítulo ou gênero..."></label><div class="verbum-filter-tabs">' + ['active','archived','all'].map(function (filter) { return '<button type="button" data-library-filter="' + filter + '" class="' + (state.filter === filter ? 'is-active' : '') + '">' + (filter === 'active' ? 'Ativos' : filter === 'archived' ? 'Arquivados' : 'Todos') + '</button>'; }).join('') + '</div></section>' +
      (state.libraryError ? '<div class="verbum-inline-error"><span>' + escapeHtml(state.libraryError) + '</span><button type="button" data-reload-library>Tentar novamente</button></div>' : '') + body + '</div>';
  }

  function projectModal(state) {
    var modal = state.modal;
    if (!modal || modal.type !== 'project') return '';
    var project = modal.id ? state.library.projects.find(function (p) { return p.id === modal.id; }) : null;
    return '<div class="verbum-dialog-backdrop" data-close-modal><section class="verbum-dialog" role="dialog" aria-modal="true"><div class="verbum-dialog-header"><div><span class="verbum-eyebrow">Banco de Obras</span><h2>' + (project ? 'Editar projeto' : 'Novo projeto') + '</h2></div><button type="button" class="verbum-icon-button" data-close-modal>×</button></div><form class="verbum-form" data-project-form data-id="' + escapeHtml(project ? project.id : '') + '"><label><span>Nome do projeto *</span><input name="name" required maxlength="120" value="' + escapeHtml(project ? project.name : '') + '"></label><label><span>Descrição</span><textarea name="description" rows="4" maxlength="1000">' + escapeHtml(project ? project.description : '') + '</textarea></label><p class="verbum-form-error" data-form-error hidden></p><div class="verbum-dialog-actions"><button type="button" class="verbum-secondary-button" data-close-modal>Cancelar</button><button type="submit" class="verbum-primary-button">Salvar projeto</button></div></form></section></div>';
  }

  function option(value, selected) {
    return '<option value="' + escapeHtml(value) + '"' + (String(value) === String(selected) ? ' selected' : '') + '>' + escapeHtml(value) + '</option>';
  }

  function bookModal(state) {
    var modal = state.modal;
    if (!modal || modal.type !== 'book') return '';
    var book = modal.id ? state.library.books.find(function (b) { return b.id === modal.id; }) : null;
    var activeProjects = state.library.projects.filter(function (p) { return p.status === 'active'; });
    var projectId = book ? book.projectId : (modal.projectId || (activeProjects[0] && activeProjects[0].id) || '');
    return '<div class="verbum-dialog-backdrop" data-close-modal><section class="verbum-dialog verbum-dialog-wide" role="dialog" aria-modal="true"><div class="verbum-dialog-header"><div><span class="verbum-eyebrow">Identificação da Obra</span><h2>' + (book ? 'Editar obra' : 'Criar nova obra') + '</h2></div><button type="button" class="verbum-icon-button" data-close-modal>×</button></div><form class="verbum-form" data-book-form data-id="' + escapeHtml(book ? book.id : '') + '"><div class="verbum-form-section"><div class="verbum-form-section-heading"><strong>Informações principais</strong><small>Dados que identificam a obra no seu workspace.</small></div><div class="verbum-form-grid">' +
      '<label class="verbum-field-span-2"><span>Projeto *</span><select name="project_id" required><option value="">Selecione um projeto</option>' + activeProjects.map(function (p) { return '<option value="' + escapeHtml(p.id) + '"' + (p.id === projectId ? ' selected' : '') + '>' + escapeHtml(p.name) + '</option>'; }).join('') + '</select></label>' +
      '<label><span>Título da obra *</span><input name="title" required maxlength="180" value="' + escapeHtml(book ? book.title : '') + '"></label><label><span>Subtítulo</span><input name="subtitle" maxlength="220" value="' + escapeHtml(book ? book.subtitle || '' : '') + '"></label>' +
      '<label><span>Série (opcional)</span><input name="series" value="' + escapeHtml(book ? book.series || '' : '') + '"></label><label><span>Coleção</span><input name="collection" value="' + escapeHtml(book ? book.collection || '' : '') + '"></label>' +
      '<label><span>Categoria</span><input name="category" value="' + escapeHtml(book ? book.category || '' : '') + '"></label><label><span>Gênero</span><input name="genre" value="' + escapeHtml(book ? book.genre || '' : '') + '"></label>' +
      '<label><span>Público-alvo</span><input name="audience" value="' + escapeHtml(book ? book.audience || '' : '') + '"></label><label><span>Faixa etária</span><input name="age_range" value="' + escapeHtml(book ? book.ageRange || '' : '') + '"></label>' +
      '<label><span>Idioma</span><input name="language" value="' + escapeHtml(book ? book.language || 'Português' : 'Português') + '"></label><label><span>País</span><input name="country" value="' + escapeHtml(book ? book.country || 'Brasil' : 'Brasil') + '"></label>' +
      '<label><span>Autor</span><input name="author_name" value="' + escapeHtml(book ? book.authorName || '' : '') + '"></label><label><span>Coautor (opcional)</span><input name="coauthor_name" value="' + escapeHtml(book ? book.coauthorName || '' : '') + '"></label></div></div>' +
      '<div class="verbum-form-section"><div class="verbum-form-section-heading"><strong>Planejamento inicial</strong><small>Metas gerais; o planejamento editorial detalhado será feito na etapa própria.</small></div><div class="verbum-form-grid">' +
      '<label><span>Capítulos previstos</span><input type="number" min="0" name="planned_chapters" value="' + escapeHtml(book ? book.plannedChapters || '' : '') + '"></label><label><span>Meta de palavras</span><input type="number" min="0" step="100" name="word_goal" value="' + escapeHtml(book ? book.wordGoal || '' : '') + '"></label>' +
      '<label><span>Conclusão prevista</span><input type="date" name="target_date" value="' + escapeHtml(book ? book.targetDate || '' : '') + '"></label><label><span>Status</span><select name="workflow_status">' + ['Planejamento','Em andamento','Em pausa','Concluída'].map(function (s) { return option(s, book ? book.workflowStatus || 'Planejamento' : 'Planejamento'); }).join('') + '</select></label>' +
      '<label><span>Prioridade</span><select name="priority">' + ['Baixa','Normal','Alta'].map(function (s) { return option(s, book ? book.priority || 'Normal' : 'Normal'); }).join('') + '</select></label><label><span>Cor da obra</span><input type="color" name="color" value="' + escapeHtml(book ? book.color || '#7a3042' : '#7a3042') + '"></label>' +
      '<label class="verbum-field-span-2"><span>Tags</span><input name="tags" value="' + escapeHtml(book && Array.isArray(book.tags) ? book.tags.join(', ') : '') + '"></label><label class="verbum-field-span-2"><span>Anotações gerais</span><textarea name="notes" rows="4">' + escapeHtml(book ? book.notes || '' : '') + '</textarea></label></div></div>' +
      '<p class="verbum-form-error" data-form-error hidden></p><div class="verbum-dialog-actions"><button type="button" class="verbum-secondary-button" data-close-modal>Cancelar</button><button type="submit" class="verbum-primary-button">Salvar obra</button></div></form></section></div>';
  }

  function shellHtml(state) {
    var label = state.section === 'library' ? 'Obras' : 'Início';
    var content = state.section === 'library' ? libraryHtml(state) : dashboardHtml(state);
    return '<div class="verbum-app"><div class="verbum-shell"><button type="button" class="verbum-sidebar-backdrop" data-nav-close aria-label="Fechar navegação"></button><aside class="verbum-sidebar" data-sidebar><div class="verbum-brand"><span class="verbum-brand-mark">V</span><span><strong>VERBUM</strong><small>STUDIO</small></span></div><nav class="verbum-nav">' + navHtml(state) + '</nav><div class="verbum-sidebar-footer"><span class="verbum-sidebar-kicker">Ambiente editorial</span><p>Um espaço de trabalho para acompanhar cada etapa da sua obra.</p></div></aside><div class="verbum-shell-main"><header class="verbum-header"><div class="verbum-header-title"><button type="button" class="verbum-mobile-menu" data-nav-open aria-label="Abrir navegação"><span></span><span></span><span></span></button><div><span class="verbum-eyebrow">Área atual</span><h1>' + label + '</h1></div></div><div class="verbum-user-menu"><button type="button" class="verbum-user-trigger" data-user-trigger aria-expanded="false"><span class="verbum-avatar">' + escapeHtml(userInitial(state.user.name)) + '</span><span class="verbum-user-copy"><strong>' + escapeHtml(state.user.name) + '</strong><small>Minha conta</small></span><span class="verbum-user-chevron">⌄</span></button><div class="verbum-user-dropdown" data-user-dropdown hidden><button type="button">Meu perfil</button><button type="button">Configurações</button><span class="verbum-menu-separator"></span><button type="button">Sair</button></div></div></header><main class="verbum-main" id="verbum-main-content">' + content + '</main></div></div>' + projectModal(state) + bookModal(state) + '</div>';
  }

  function renderApp(root) {
    root.innerHTML = shellHtml(stateFor(root));
    bind(root);
  }

  function refreshLibrary(root) {
    var state = stateFor(root);
    state.loadingLibrary = true;
    state.libraryError = '';
    renderApp(root);
    return request('/library').then(function (library) {
      state.library = library || { projects: [], books: [] };
    }).catch(function (error) {
      state.libraryError = error.message || 'Não foi possível carregar o Banco de Obras.';
    }).finally(function () {
      state.loadingLibrary = false;
      renderApp(root);
    });
  }

  function formDataObject(form) {
    var data = {};
    new FormData(form).forEach(function (value, key) { data[key] = value; });
    return data;
  }

  function bind(root) {
    var state = stateFor(root);
    var sidebar = root.querySelector('[data-sidebar]');
    var backdrop = root.querySelector('[data-nav-close]');
    function closeNav() { if (sidebar) sidebar.classList.remove('is-open'); if (backdrop) backdrop.classList.remove('is-visible'); }
    var openButton = root.querySelector('[data-nav-open]');
    if (openButton) openButton.addEventListener('click', function () { if (sidebar) sidebar.classList.add('is-open'); if (backdrop) backdrop.classList.add('is-visible'); });
    if (backdrop) backdrop.addEventListener('click', closeNav);
    root.querySelectorAll('[data-section]').forEach(function (button) { button.addEventListener('click', function () { state.section = button.getAttribute('data-section') || 'dashboard'; renderApp(root); }); });
    var trigger = root.querySelector('[data-user-trigger]'); var dropdown = root.querySelector('[data-user-dropdown]');
    if (trigger && dropdown) trigger.addEventListener('click', function () { var hidden = dropdown.hasAttribute('hidden'); if (hidden) { dropdown.removeAttribute('hidden'); trigger.setAttribute('aria-expanded', 'true'); } else { dropdown.setAttribute('hidden', ''); trigger.setAttribute('aria-expanded', 'false'); } });
    var search = root.querySelector('[data-library-search]');
    if (search) search.addEventListener('change', function () { state.query = search.value || ''; renderApp(root); });
    root.querySelectorAll('[data-library-filter]').forEach(function (button) { button.addEventListener('click', function () { state.filter = button.getAttribute('data-library-filter') || 'active'; renderApp(root); }); });
    root.querySelectorAll('[data-new-project]').forEach(function (button) { button.addEventListener('click', function () { state.modal = { type: 'project' }; renderApp(root); }); });
    root.querySelectorAll('[data-new-book]').forEach(function (button) { button.addEventListener('click', function () { state.modal = { type: 'book', projectId: button.getAttribute('data-project') || '' }; renderApp(root); }); });
    root.querySelectorAll('[data-edit-project]').forEach(function (button) { button.addEventListener('click', function () { state.modal = { type: 'project', id: button.getAttribute('data-edit-project') }; renderApp(root); }); });
    root.querySelectorAll('[data-edit-book]').forEach(function (button) { button.addEventListener('click', function () { state.modal = { type: 'book', id: button.getAttribute('data-edit-book') }; renderApp(root); }); });
    root.querySelectorAll('[data-close-modal]').forEach(function (button) { button.addEventListener('click', function (event) { if (button.classList.contains('verbum-dialog-backdrop') && event.target !== button) return; state.modal = null; renderApp(root); }); });
    var reload = root.querySelector('[data-reload-library]'); if (reload) reload.addEventListener('click', function () { refreshLibrary(root); });
    root.querySelectorAll('[data-archive-project]').forEach(function (button) { button.addEventListener('click', function () { var id = button.getAttribute('data-archive-project'); var project = state.library.projects.find(function (p) { return p.id === id; }); if (!project || !window.confirm('Arquivar o projeto “' + project.name + '” e suas obras?')) return; request('/projects/' + id + '/archive', { method: 'POST' }).then(function () { return refreshLibrary(root); }).catch(function (e) { state.libraryError = e.message; renderApp(root); }); }); });
    root.querySelectorAll('[data-archive-book]').forEach(function (button) { button.addEventListener('click', function () { var id = button.getAttribute('data-archive-book'); var book = state.library.books.find(function (b) { return b.id === id; }); if (!book || !window.confirm('Arquivar a obra “' + book.title + '”?')) return; request('/books/' + id + '/archive', { method: 'POST' }).then(function () { return refreshLibrary(root); }).catch(function (e) { state.libraryError = e.message; renderApp(root); }); }); });
    var projectForm = root.querySelector('[data-project-form]');
    if (projectForm) projectForm.addEventListener('submit', function (event) { event.preventDefault(); var payload = formDataObject(projectForm); var id = projectForm.getAttribute('data-id'); var path = id ? '/projects/' + id : '/projects'; request(path, { method: id ? 'PATCH' : 'POST', body: payload }).then(function () { state.modal = null; return refreshLibrary(root); }).catch(function (e) { var error = projectForm.querySelector('[data-form-error]'); if (error) { error.textContent = e.message; error.removeAttribute('hidden'); } }); });
    var bookForm = root.querySelector('[data-book-form]');
    if (bookForm) bookForm.addEventListener('submit', function (event) { event.preventDefault(); var payload = formDataObject(bookForm); payload.project_id = number(payload.project_id); payload.planned_chapters = number(payload.planned_chapters); payload.word_goal = number(payload.word_goal); payload.tags = String(payload.tags || '').split(',').map(function (tag) { return tag.trim(); }).filter(Boolean); var id = bookForm.getAttribute('data-id'); var path = id ? '/books/' + id : '/books'; request(path, { method: id ? 'PATCH' : 'POST', body: payload }).then(function () { state.modal = null; return refreshLibrary(root); }).catch(function (e) { var error = bookForm.querySelector('[data-form-error]'); if (error) { error.textContent = e.message; error.removeAttribute('hidden'); } }); });
  }

  function loading() {
    return '<section class="verbum-app verbum-app-state verbum-loading"><span class="verbum-state-mark">V</span><strong>Carregando Verbum Studio...</strong><small>Preparando seu espaço de escrita.</small></section>';
  }

  function failure(message) {
    return '<section class="verbum-app verbum-app-state verbum-error" role="alert"><span class="verbum-state-mark">V</span><strong>Não foi possível abrir o Verbum Studio</strong><p>' + escapeHtml(message || 'A API principal não está respondendo. Tente novamente em alguns instantes.') + '</p></section>';
  }

  function mount(root) {
    root.innerHTML = loading();
    var state = stateFor(root);
    Promise.allSettled([request('/health'), request('/me'), request('/library')]).then(function (results) {
      var health = results[0]; var user = results[1]; var library = results[2];
      if (health.status !== 'fulfilled' || !health.value || health.value.status !== 'ok') { root.innerHTML = failure('A API principal não está respondendo.'); return; }
      state.user = user.status === 'fulfilled' && user.value && user.value.name ? user.value : { id: '0', name: 'Você', email: '' };
      if (library.status === 'fulfilled') state.library = library.value; else state.libraryError = 'Não foi possível carregar o Banco de Obras.';
      renderApp(root);
    }).catch(function (error) { root.innerHTML = failure(error && error.message); });
  }

  document.querySelectorAll('[data-verbum-app]').forEach(mount);
})();
