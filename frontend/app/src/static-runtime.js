(function () {
  'use strict';

  var roots = document.querySelectorAll('[data-verbum-app]');
  if (!roots.length) return;

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char];
    });
  }

  function color(value) {
    return /^#[0-9a-f]{6}$/i.test(String(value || '')) ? String(value) : '#7a3042';
  }

  function num(value) {
    var parsed = Number(value || 0);
    return Number.isFinite(parsed) ? parsed : 0;
  }

  function config() {
    return window.VerbumStudioConfig || { apiRoot: '/wp-json/verbum/v1', nonce: '', version: '1.0.0' };
  }

  function api(path, options) {
    var opts = options || {};
    var cfg = config();
    var headers = { 'X-WP-Nonce': cfg.nonce || '' };
    if (opts.body !== undefined) headers['Content-Type'] = 'application/json';

    return fetch(cfg.apiRoot + path, {
      method: opts.method || 'GET',
      credentials: 'same-origin',
      headers: headers,
      body: opts.body === undefined ? undefined : JSON.stringify(opts.body)
    }).then(function (response) {
      return response.json().catch(function () {
        throw new Error('A API retornou uma resposta inválida.');
      }).then(function (payload) {
        if (!response.ok || !payload || !payload.success) {
          throw new Error(payload && payload.error && payload.error.message ? payload.error.message : 'Não foi possível comunicar com a API.');
        }
        return payload.data;
      });
    });
  }

  function initial(name) {
    return (String(name || 'V').trim().charAt(0) || 'V').toUpperCase();
  }

  function state(root) {
    if (!root.__verbumState) {
      root.__verbumState = {
        user: { id: '0', name: 'Você', email: '' },
        library: { projects: [], books: [] },
        section: 'dashboard',
        filter: 'active',
        query: '',
        modal: null,
        libraryLoading: false,
        libraryError: ''
      };
    }
    return root.__verbumState;
  }

  function navHtml(s) {
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
      var active = section && s.section === section;
      return '<button type="button" class="verbum-nav-item' + (active ? ' is-active' : '') + '"' +
        (active ? ' aria-current="page"' : '') +
        (!section ? ' disabled aria-disabled="true"' : ' data-section="' + section + '"') + '>' +
        '<span class="verbum-nav-dot" aria-hidden="true"></span><span>' + esc(item[1]) + '</span></button>';
    }).join('');
  }

  function dashboardHtml(s) {
    var books = s.library.books.filter(function (book) { return book.status === 'active'; });
    var projects = s.library.projects.filter(function (project) { return project.status === 'active'; });
    var chapters = books.reduce(function (sum, book) { return sum + num(book.plannedChapters); }, 0);
    var wordGoal = books.reduce(function (sum, book) { return sum + num(book.wordGoal); }, 0);
    var current = books[0];

    var continueHtml = current
      ? '<div class="verbum-current-book"><span class="verbum-current-book-color" style="background-color:' + color(current.color) + '"></span><div><span class="verbum-eyebrow">Identificação da Obra</span><h3>' + esc(current.title) + '</h3><p>' + esc(current.subtitle || 'Complete os dados da obra para avançar com segurança para o planejamento editorial.') + '</p></div><button type="button" class="verbum-primary-button" data-section="library">Abrir obra</button></div>'
      : '<div class="verbum-empty-state"><div class="verbum-empty-symbol">V</div><div><h3>Sua próxima obra começa aqui</h3><p>Crie uma obra para organizar planejamento, pesquisa, redação, revisão e publicação em um único fluxo.</p></div><button type="button" class="verbum-primary-button" data-section="library">Criar nova obra</button></div>';

    return '<div class="verbum-dashboard">' +
      '<section class="verbum-welcome"><span class="verbum-eyebrow">Seu espaço de escrita</span><h2>Bom trabalho, ' + esc(s.user.name) + '</h2><p>Organize sua obra com clareza, acompanhe cada etapa e mantenha o foco no que precisa ser escrito agora.</p></section>' +
      '<section class="verbum-stats" aria-label="Indicadores do painel">' +
        '<article class="verbum-stat-card"><span>Obras em andamento</span><strong>' + books.length + '</strong><small>' + (books.length ? projects.length + ' projetos ativos' : 'Nenhuma obra ativa') + '</small></article>' +
        '<article class="verbum-stat-card"><span>Capítulos previstos</span><strong>' + chapters + '</strong><small>' + (chapters ? 'Meta inicial das obras' : 'Aguardando planejamento') + '</small></article>' +
        '<article class="verbum-stat-card"><span>Meta de palavras</span><strong>' + wordGoal.toLocaleString('pt-BR') + '</strong><small>' + (wordGoal ? 'Meta editorial cadastrada' : 'Defina na identificação da obra') + '</small></article>' +
        '<article class="verbum-stat-card"><span>Etapa atual</span><strong>' + (books.length ? '01' : '—') + '</strong><small>' + (books.length ? 'Identificação da Obra' : 'Sem obra ativa') + '</small></article>' +
      '</section>' +
      '<section class="verbum-panel verbum-continue"><div class="verbum-section-heading"><div><span class="verbum-eyebrow">Retome sua jornada</span><h2>Continue escrevendo</h2></div></div>' + continueHtml + '</section>' +
      '<div class="verbum-dashboard-grid">' +
        '<section class="verbum-panel verbum-quick-actions"><div class="verbum-section-heading"><div><span class="verbum-eyebrow">Atalhos</span><h2>Ações rápidas</h2></div></div><div class="verbum-action-list">' +
          '<button type="button" class="verbum-action-card" data-section="library"><span class="verbum-action-plus">+</span><span><strong>Criar nova obra</strong><small>Inicie um novo projeto editorial.</small></span></button>' +
          '<button type="button" class="verbum-action-card" disabled aria-disabled="true"><span class="verbum-action-plus">+</span><span><strong>Registrar uma ideia</strong><small>Guarde um ponto para desenvolver depois.</small></span></button>' +
          '<button type="button" class="verbum-action-card" disabled aria-disabled="true"><span class="verbum-action-plus">+</span><span><strong>Abrir calendário</strong><small>Visualize seu planejamento editorial.</small></span></button>' +
        '</div></section>' +
        '<section class="verbum-panel verbum-recent-activity"><div class="verbum-section-heading"><div><span class="verbum-eyebrow">Histórico</span><h2>Atividade recente</h2></div></div><div class="verbum-activity-empty"><span class="verbum-activity-line"></span><p>Quando você começar a trabalhar em uma obra, suas atividades mais recentes aparecerão aqui.</p></div></section>' +
      '</div>' +
    '</div>';
  }

  function bookCard(book, project) {
    var archived = book.status === 'archived';
    return '<article class="verbum-book-card' + (archived ? ' is-archived' : '') + '">' +
      '<div class="verbum-book-color" style="background-color:' + color(book.color) + '"></div>' +
      '<div class="verbum-book-card-body"><div class="verbum-book-card-topline"><span class="verbum-book-project">' + esc(project.name) + '</span><span class="verbum-status-chip' + (archived ? ' is-muted' : '') + '">' + esc(archived ? 'Arquivada' : (book.workflowStatus || 'Planejamento')) + '</span></div>' +
      '<h3>' + esc(book.title) + '</h3>' + (book.subtitle ? '<p class="verbum-book-subtitle">' + esc(book.subtitle) + '</p>' : '') +
      '<div class="verbum-book-meta"><span>' + esc(book.genre || book.category || 'Sem gênero definido') + '</span><span>' + (num(book.plannedChapters) ? num(book.plannedChapters) + ' capítulos previstos' : 'Capítulos ainda não definidos') + '</span></div>' +
      '<div class="verbum-book-stage"><span>Etapa atual</span><strong>Identificação da Obra</strong></div></div>' +
      '<div class="verbum-book-card-actions"><button type="button" class="verbum-text-button" data-edit-book="' + esc(book.id) + '">Editar</button>' +
      (!archived ? '<button type="button" class="verbum-text-button is-danger" data-archive-book="' + esc(book.id) + '">Arquivar</button>' : '') + '</div></article>';
  }

  function filteredProjects(s) {
    var query = s.query.trim().toLocaleLowerCase('pt-BR');
    return s.library.projects.filter(function (project) {
      if (s.filter !== 'all') {
        var projectMatchesStatus = project.status === s.filter;
        var hasBookWithStatus = s.library.books.some(function (book) {
          return book.projectId === project.id && book.status === s.filter;
        });
        if (!projectMatchesStatus && !hasBookWithStatus) return false;
      }

      if (!query) return true;
      if ((project.name + ' ' + (project.description || '')).toLocaleLowerCase('pt-BR').indexOf(query) !== -1) return true;
      return s.library.books.some(function (book) {
        return book.projectId === project.id && (book.title + ' ' + (book.subtitle || '') + ' ' + (book.genre || '')).toLocaleLowerCase('pt-BR').indexOf(query) !== -1;
      });
    });
  }

  function libraryHtml(s) {
    var activeProjects = s.library.projects.filter(function (project) { return project.status === 'active'; });
    var activeBooks = s.library.books.filter(function (book) { return book.status === 'active'; });
    var projects = filteredProjects(s);
    var body = '';

    if (s.libraryLoading) {
      body = '<section class="verbum-library-loading" aria-live="polite">Carregando seu Banco de Obras...</section>';
    } else if (!s.library.projects.length) {
      body = '<section class="verbum-panel verbum-library-empty"><span class="verbum-empty-symbol">V</span><div><span class="verbum-eyebrow">Primeiro passo</span><h3>Crie seu primeiro projeto</h3><p>O projeto reúne uma ou mais obras. Depois de criá-lo, você poderá cadastrar o primeiro livro e preencher sua identificação.</p></div><button type="button" class="verbum-primary-button" data-new-project>Criar projeto</button></section>';
    } else if (!projects.length) {
      body = '<section class="verbum-panel verbum-library-empty compact"><div><h3>Nenhum resultado encontrado</h3><p>Ajuste a busca ou o filtro para localizar suas obras.</p></div></section>';
    } else {
      body = '<div class="verbum-project-list">' + projects.map(function (project) {
        var books = s.library.books.filter(function (book) {
          return book.projectId === project.id && (s.filter === 'all' || book.status === s.filter);
        });
        return '<section class="verbum-panel verbum-project-section' + (project.status === 'archived' ? ' is-archived' : '') + '">' +
          '<div class="verbum-project-heading"><div><span class="verbum-eyebrow">Projeto</span><h3>' + esc(project.name) + '</h3>' + (project.description ? '<p>' + esc(project.description) + '</p>' : '') + '</div>' +
          '<div class="verbum-project-actions"><span>' + books.length + ' ' + (books.length === 1 ? 'obra' : 'obras') + '</span><button type="button" class="verbum-text-button" data-edit-project="' + esc(project.id) + '">Editar</button>' +
          (project.status !== 'archived' ? '<button type="button" class="verbum-text-button is-danger" data-archive-project="' + esc(project.id) + '">Arquivar</button>' : '') + '</div></div>' +
          (books.length
            ? '<div class="verbum-book-grid">' + books.map(function (book) { return bookCard(book, project); }).join('') + '</div>'
            : '<div class="verbum-project-empty"><p>Nenhuma obra neste projeto.</p>' + (project.status === 'active' ? '<button type="button" class="verbum-text-button" data-new-book data-project="' + esc(project.id) + '">Adicionar obra</button>' : '') + '</div>') +
          '</section>';
      }).join('') + '</div>';
    }

    return '<div class="verbum-library">' +
      '<section class="verbum-page-heading"><div><span class="verbum-eyebrow">Seu acervo editorial</span><h2>Banco de Obras</h2><p>Organize seus projetos e livros. Cada obra permanece vinculada ao projeto em que nasceu.</p></div><div class="verbum-page-actions"><button type="button" class="verbum-secondary-button" data-new-project>Novo projeto</button><button type="button" class="verbum-primary-button" data-new-book' + (!activeProjects.length ? ' disabled' : '') + '>Criar nova obra</button></div></section>' +
      '<section class="verbum-library-summary"><div><strong>' + activeProjects.length + '</strong><span>Projetos ativos</span></div><div><strong>' + activeBooks.length + '</strong><span>Obras ativas</span></div><div><strong>' + s.library.books.filter(function (book) { return book.status === 'archived'; }).length + '</strong><span>Obras arquivadas</span></div></section>' +
      '<section class="verbum-library-toolbar"><label class="verbum-search-field"><span class="sr-only">Buscar</span><input data-library-search value="' + esc(s.query) + '" placeholder="Buscar projeto, título, subtítulo ou gênero..."></label><div class="verbum-filter-tabs">' +
      ['active', 'archived', 'all'].map(function (filter) {
        return '<button type="button" data-library-filter="' + filter + '" class="' + (s.filter === filter ? 'is-active' : '') + '">' + (filter === 'active' ? 'Ativos' : filter === 'archived' ? 'Arquivados' : 'Todos') + '</button>';
      }).join('') + '</div></section>' +
      (s.libraryError ? '<div class="verbum-inline-error"><span>' + esc(s.libraryError) + '</span><button type="button" data-reload-library>Tentar novamente</button></div>' : '') + body + '</div>';
  }

  function projectModal(s) {
    if (!s.modal || s.modal.type !== 'project') return '';
    var project = s.modal.id ? s.library.projects.find(function (item) { return item.id === s.modal.id; }) : null;
    return '<div class="verbum-dialog-backdrop" data-close-modal><section class="verbum-dialog" role="dialog" aria-modal="true"><div class="verbum-dialog-header"><div><span class="verbum-eyebrow">Banco de Obras</span><h2>' + (project ? 'Editar projeto' : 'Novo projeto') + '</h2></div><button type="button" class="verbum-icon-button" data-close-modal aria-label="Fechar">×</button></div>' +
      '<form class="verbum-form" data-project-form data-id="' + esc(project ? project.id : '') + '"><label><span>Nome do projeto *</span><input name="name" required maxlength="120" value="' + esc(project ? project.name : '') + '"></label><label><span>Descrição</span><textarea name="description" rows="4" maxlength="1000">' + esc(project ? project.description : '') + '</textarea></label><p class="verbum-form-error" data-form-error hidden></p><div class="verbum-dialog-actions"><button type="button" class="verbum-secondary-button" data-close-modal>Cancelar</button><button type="submit" class="verbum-primary-button">Salvar projeto</button></div></form></section></div>';
  }

  function option(value, selected) {
    return '<option value="' + esc(value) + '"' + (String(value) === String(selected) ? ' selected' : '') + '>' + esc(value) + '</option>';
  }

  function bookModal(s) {
    if (!s.modal || s.modal.type !== 'book') return '';
    var book = s.modal.id ? s.library.books.find(function (item) { return item.id === s.modal.id; }) : null;
    var selectable = s.library.projects.filter(function (project) {
      return project.status === 'active' || (book && project.id === book.projectId);
    });
    var projectId = book ? book.projectId : (s.modal.projectId || (selectable[0] && selectable[0].id) || '');

    function val(key, fallback) {
      return esc(book && book[key] != null ? book[key] : (fallback || ''));
    }

    return '<div class="verbum-dialog-backdrop" data-close-modal><section class="verbum-dialog verbum-dialog-wide" role="dialog" aria-modal="true"><div class="verbum-dialog-header"><div><span class="verbum-eyebrow">Identificação da Obra</span><h2>' + (book ? 'Editar obra' : 'Criar nova obra') + '</h2></div><button type="button" class="verbum-icon-button" data-close-modal aria-label="Fechar">×</button></div>' +
      '<form class="verbum-form" data-book-form data-id="' + esc(book ? book.id : '') + '">' +
      '<div class="verbum-form-section"><div class="verbum-form-section-heading"><strong>Informações principais</strong><small>Dados que identificam a obra no seu workspace.</small></div><div class="verbum-form-grid">' +
      '<label class="verbum-field-span-2"><span>Projeto *</span><select name="project_id" required><option value="">Selecione um projeto</option>' + selectable.map(function (project) { return '<option value="' + esc(project.id) + '"' + (project.id === projectId ? ' selected' : '') + '>' + esc(project.name) + '</option>'; }).join('') + '</select></label>' +
      '<label><span>Título da obra *</span><input name="title" required maxlength="180" value="' + val('title') + '"></label><label><span>Subtítulo</span><input name="subtitle" maxlength="220" value="' + val('subtitle') + '"></label>' +
      '<label><span>Série (opcional)</span><input name="series" maxlength="140" value="' + val('series') + '"></label><label><span>Coleção</span><input name="collection" maxlength="140" value="' + val('collection') + '"></label>' +
      '<label><span>Categoria</span><input name="category" maxlength="100" value="' + val('category') + '"></label><label><span>Gênero</span><input name="genre" maxlength="100" value="' + val('genre') + '"></label>' +
      '<label><span>Público-alvo</span><input name="audience" maxlength="160" value="' + val('audience') + '"></label><label><span>Faixa etária</span><input name="age_range" maxlength="80" value="' + val('ageRange') + '"></label>' +
      '<label><span>Idioma</span><input name="language" maxlength="80" value="' + val('language', 'Português') + '"></label><label><span>País</span><input name="country" maxlength="80" value="' + val('country', 'Brasil') + '"></label>' +
      '<label><span>Autor</span><input name="author_name" maxlength="140" value="' + val('authorName') + '"></label><label><span>Coautor (opcional)</span><input name="coauthor_name" maxlength="140" value="' + val('coauthorName') + '"></label></div></div>' +
      '<div class="verbum-form-section"><div class="verbum-form-section-heading"><strong>Planejamento inicial</strong><small>Metas gerais; o planejamento editorial detalhado será feito na etapa própria.</small></div><div class="verbum-form-grid">' +
      '<label><span>Capítulos previstos</span><input type="number" min="0" name="planned_chapters" value="' + val('plannedChapters') + '"></label><label><span>Meta de palavras</span><input type="number" min="0" step="100" name="word_goal" value="' + val('wordGoal') + '"></label>' +
      '<label><span>Conclusão prevista</span><input type="date" name="target_date" value="' + val('targetDate') + '"></label><label><span>Status</span><select name="workflow_status">' + ['Planejamento', 'Em andamento', 'Em pausa', 'Concluída'].map(function (item) { return option(item, book ? book.workflowStatus || 'Planejamento' : 'Planejamento'); }).join('') + '</select></label>' +
      '<label><span>Prioridade</span><select name="priority">' + ['Baixa', 'Normal', 'Alta'].map(function (item) { return option(item, book ? book.priority || 'Normal' : 'Normal'); }).join('') + '</select></label><label><span>Cor da obra</span><input type="color" name="color" value="' + color(book ? book.color : '') + '"></label>' +
      '<label class="verbum-field-span-2"><span>Tags</span><input name="tags" value="' + esc(book && Array.isArray(book.tags) ? book.tags.join(', ') : '') + '"></label><label class="verbum-field-span-2"><span>Anotações gerais</span><textarea name="notes" rows="4" maxlength="3000">' + val('notes') + '</textarea></label></div></div>' +
      '<p class="verbum-form-error" data-form-error hidden></p><div class="verbum-dialog-actions"><button type="button" class="verbum-secondary-button" data-close-modal>Cancelar</button><button type="submit" class="verbum-primary-button">Salvar obra</button></div></form></section></div>';
  }

  function shellHtml(s) {
    var pageLabel = s.section === 'library' ? 'Obras' : 'Início';
    var content = s.section === 'library' ? libraryHtml(s) : dashboardHtml(s);
    return '<div class="verbum-app"><div class="verbum-shell">' +
      '<button type="button" class="verbum-sidebar-backdrop" data-nav-close aria-label="Fechar navegação"></button>' +
      '<aside class="verbum-sidebar" data-sidebar aria-label="Navegação principal"><div class="verbum-brand"><span class="verbum-brand-mark">V</span><span><strong>VERBUM</strong><small>STUDIO</small></span></div><nav class="verbum-nav">' + navHtml(s) + '</nav><div class="verbum-sidebar-footer"><span class="verbum-sidebar-kicker">Ambiente editorial</span><p>Um espaço de trabalho para acompanhar cada etapa da sua obra.</p></div></aside>' +
      '<div class="verbum-shell-main"><header class="verbum-header"><div class="verbum-header-title"><button type="button" class="verbum-mobile-menu" data-nav-open aria-label="Abrir navegação"><span></span><span></span><span></span></button><div><span class="verbum-eyebrow">Área atual</span><h1>' + pageLabel + '</h1></div></div>' +
      '<div class="verbum-user-menu"><button type="button" class="verbum-user-trigger" data-user-trigger aria-haspopup="menu" aria-expanded="false"><span class="verbum-avatar">' + esc(initial(s.user.name)) + '</span><span class="verbum-user-copy"><strong>' + esc(s.user.name) + '</strong><small>Minha conta</small></span><span class="verbum-user-chevron">⌄</span></button><div class="verbum-user-dropdown" data-user-dropdown role="menu" hidden><button type="button">Meu perfil</button><button type="button">Configurações</button><span class="verbum-menu-separator"></span><button type="button">Sair</button></div></div></header>' +
      '<main class="verbum-main" id="verbum-main-content">' + content + '</main></div></div>' + projectModal(s) + bookModal(s) + '</div>';
  }

  function render(root) {
    root.innerHTML = shellHtml(state(root));
    bind(root);
  }

  function reloadLibrary(root) {
    var s = state(root);
    s.libraryLoading = true;
    s.libraryError = '';
    render(root);
    return api('/library').then(function (library) {
      s.library = library || { projects: [], books: [] };
    }).catch(function (error) {
      s.libraryError = error.message || 'Não foi possível carregar o Banco de Obras.';
    }).finally(function () {
      s.libraryLoading = false;
      render(root);
    });
  }

  function formObject(form) {
    var data = {};
    new FormData(form).forEach(function (value, key) { data[key] = value; });
    return data;
  }

  function showFormError(form, error) {
    var node = form.querySelector('[data-form-error]');
    if (!node) return;
    node.textContent = error && error.message ? error.message : 'Não foi possível salvar.';
    node.removeAttribute('hidden');
  }

  function bind(root) {
    var s = state(root);
    var sidebar = root.querySelector('[data-sidebar]');
    var backdrop = root.querySelector('[data-nav-close]');
    var openNav = root.querySelector('[data-nav-open]');

    function closeNav() {
      if (sidebar) sidebar.classList.remove('is-open');
      if (backdrop) backdrop.classList.remove('is-visible');
    }

    if (openNav) openNav.addEventListener('click', function () {
      if (sidebar) sidebar.classList.add('is-open');
      if (backdrop) backdrop.classList.add('is-visible');
    });
    if (backdrop) backdrop.addEventListener('click', closeNav);

    root.querySelectorAll('[data-section]').forEach(function (button) {
      button.addEventListener('click', function () {
        s.section = button.getAttribute('data-section') || 'dashboard';
        closeNav();
        render(root);
      });
    });

    var userTrigger = root.querySelector('[data-user-trigger]');
    var userDropdown = root.querySelector('[data-user-dropdown]');
    if (userTrigger && userDropdown) userTrigger.addEventListener('click', function () {
      var opening = userDropdown.hasAttribute('hidden');
      if (opening) userDropdown.removeAttribute('hidden'); else userDropdown.setAttribute('hidden', '');
      userTrigger.setAttribute('aria-expanded', opening ? 'true' : 'false');
    });

    var search = root.querySelector('[data-library-search]');
    if (search) search.addEventListener('input', function () {
      s.query = search.value || '';
      render(root);
      var replacement = root.querySelector('[data-library-search]');
      if (replacement) {
        replacement.focus();
        replacement.setSelectionRange(replacement.value.length, replacement.value.length);
      }
    });

    root.querySelectorAll('[data-library-filter]').forEach(function (button) {
      button.addEventListener('click', function () {
        s.filter = button.getAttribute('data-library-filter') || 'active';
        render(root);
      });
    });

    root.querySelectorAll('[data-new-project]').forEach(function (button) {
      button.addEventListener('click', function () { s.modal = { type: 'project' }; render(root); });
    });
    root.querySelectorAll('[data-new-book]').forEach(function (button) {
      button.addEventListener('click', function () { s.modal = { type: 'book', projectId: button.getAttribute('data-project') || '' }; render(root); });
    });
    root.querySelectorAll('[data-edit-project]').forEach(function (button) {
      button.addEventListener('click', function () { s.modal = { type: 'project', id: button.getAttribute('data-edit-project') }; render(root); });
    });
    root.querySelectorAll('[data-edit-book]').forEach(function (button) {
      button.addEventListener('click', function () { s.modal = { type: 'book', id: button.getAttribute('data-edit-book') }; render(root); });
    });
    root.querySelectorAll('[data-close-modal]').forEach(function (button) {
      button.addEventListener('click', function (event) {
        if (button.classList.contains('verbum-dialog-backdrop') && event.target !== button) return;
        s.modal = null;
        render(root);
      });
    });

    var reload = root.querySelector('[data-reload-library]');
    if (reload) reload.addEventListener('click', function () { reloadLibrary(root); });

    root.querySelectorAll('[data-archive-project]').forEach(function (button) {
      button.addEventListener('click', function () {
        var id = button.getAttribute('data-archive-project');
        var project = s.library.projects.find(function (item) { return item.id === id; });
        if (!project || !window.confirm('Arquivar o projeto “' + project.name + '” e suas obras?')) return;
        api('/projects/' + id + '/archive', { method: 'POST' }).then(function () { return reloadLibrary(root); }).catch(function (error) { s.libraryError = error.message; render(root); });
      });
    });

    root.querySelectorAll('[data-archive-book]').forEach(function (button) {
      button.addEventListener('click', function () {
        var id = button.getAttribute('data-archive-book');
        var book = s.library.books.find(function (item) { return item.id === id; });
        if (!book || !window.confirm('Arquivar a obra “' + book.title + '”?')) return;
        api('/books/' + id + '/archive', { method: 'POST' }).then(function () { return reloadLibrary(root); }).catch(function (error) { s.libraryError = error.message; render(root); });
      });
    });

    var projectForm = root.querySelector('[data-project-form]');
    if (projectForm) projectForm.addEventListener('submit', function (event) {
      event.preventDefault();
      var payload = formObject(projectForm);
      var id = projectForm.getAttribute('data-id');
      api(id ? '/projects/' + id : '/projects', { method: id ? 'PATCH' : 'POST', body: payload })
        .then(function () { s.modal = null; return reloadLibrary(root); })
        .catch(function (error) { showFormError(projectForm, error); });
    });

    var bookForm = root.querySelector('[data-book-form]');
    if (bookForm) bookForm.addEventListener('submit', function (event) {
      event.preventDefault();
      var payload = formObject(bookForm);
      payload.project_id = num(payload.project_id);
      payload.planned_chapters = num(payload.planned_chapters);
      payload.word_goal = num(payload.word_goal);
      payload.tags = String(payload.tags || '').split(',').map(function (tag) { return tag.trim(); }).filter(Boolean);
      var id = bookForm.getAttribute('data-id');
      api(id ? '/books/' + id : '/books', { method: id ? 'PATCH' : 'POST', body: payload })
        .then(function () { s.modal = null; return reloadLibrary(root); })
        .catch(function (error) { showFormError(bookForm, error); });
    });
  }

  function loading() {
    return '<section class="verbum-app verbum-app-state verbum-loading" aria-live="polite"><span class="verbum-state-mark">V</span><strong>Carregando Verbum Studio...</strong><small>Preparando seu espaço de escrita.</small></section>';
  }

  function failure(message) {
    return '<section class="verbum-app verbum-app-state verbum-error" role="alert"><span class="verbum-state-mark">V</span><strong>Não foi possível abrir o Verbum Studio</strong><p>' + esc(message || 'A API principal não está respondendo. Tente novamente em alguns instantes.') + '</p></section>';
  }

  function mount(root) {
    root.innerHTML = loading();
    var s = state(root);
    Promise.allSettled([api('/health'), api('/me'), api('/library')]).then(function (results) {
      var health = results[0];
      var user = results[1];
      var library = results[2];
      if (health.status !== 'fulfilled' || !health.value || health.value.status !== 'ok') {
        root.innerHTML = failure('A API principal não está respondendo.');
        return;
      }
      s.user = user.status === 'fulfilled' && user.value && user.value.name ? user.value : { id: '0', name: 'Você', email: '' };
      if (library.status === 'fulfilled') s.library = library.value || { projects: [], books: [] };
      else s.libraryError = 'Não foi possível carregar o Banco de Obras.';
      render(root);
    }).catch(function (error) {
      root.innerHTML = failure(error && error.message);
    });
  }

  roots.forEach(mount);
})();
