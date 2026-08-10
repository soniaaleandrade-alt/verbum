(function () {
  'use strict';

  var STAGES = [
    ['identification', 'Identificação'], ['project', 'Projeto da Obra'], ['planning', 'Planejamento'],
    ['development', 'Desenvolvimento'], ['general_review', 'Revisão Geral'], ['versions', 'Controle de Versões'],
    ['audit', 'Auditoria'], ['editorial_desk', 'Mesa Editorial'], ['layout', 'Diagramação'],
    ['legal', 'Trâmites Legais'], ['publication', 'Publicação']
  ];
  var VIEW_KEY = 'verbum_minhas_obras_view';

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

  function stageIndex(book) {
    var index = STAGES.findIndex(function (item) { return item[0] === book.stage; });
    return index >= 0 ? index : 0;
  }

  function stageLabel(book) {
    return STAGES[stageIndex(book)][1];
  }

  function progress(book) {
    if (book.stage === 'publication' && book.workflowStatus === 'Concluída') return 100;
    return Math.round((stageIndex(book) / STAGES.length) * 100);
  }

  function relativeDate(value) {
    if (!value) return '—';
    var date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    var today = new Date();
    date.setHours(0, 0, 0, 0);
    today.setHours(0, 0, 0, 0);
    var diff = Math.floor((today - date) / 86400000);
    if (diff === 0) return 'hoje';
    if (diff === 1) return 'ontem';
    if (diff > 1 && diff < 30) return 'há ' + diff + ' dias';
    return new Date(value).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
  }

  function projectName(state, projectId) {
    var project = (state.library.projects || []).find(function (item) { return String(item.id) === String(projectId); });
    return project ? project.name : 'Sem projeto';
  }

  function ui(root) {
    if (root.__minhasObras) return root.__minhasObras;
    var view = 'grid';
    try { view = localStorage.getItem(VIEW_KEY) || 'grid'; } catch (error) {}
    root.__minhasObras = { stage: 'all', status: 'active', sort: 'updated_desc', view: view === 'list' ? 'list' : 'grid', filtersOpen: false, project: 'all', genre: 'all', query: '' };
    return root.__minhasObras;
  }

  function filteredBooks(root) {
    var state = root.__vs || { library: { projects: [], books: [] } };
    var local = ui(root);
    var query = local.query.trim().toLocaleLowerCase('pt-BR');
    var books = (state.library.books || []).filter(function (book) {
      if (local.status !== 'all' && book.status !== local.status) return false;
      if (local.stage !== 'all' && String(book.stage) !== local.stage) return false;
      if (local.project !== 'all' && String(book.projectId) !== local.project) return false;
      if (local.genre !== 'all' && String(book.genre || book.category || '') !== local.genre) return false;
      if (!query) return true;
      var haystack = [book.title, book.subtitle, book.synopsis, book.genre, book.category, projectName(state, book.projectId), (book.keywords || []).join(' ')].join(' ').toLocaleLowerCase('pt-BR');
      return haystack.indexOf(query) >= 0;
    });
    books.sort(function (a, b) {
      if (local.sort === 'title_asc') return String(a.title).localeCompare(String(b.title), 'pt-BR');
      if (local.sort === 'title_desc') return String(b.title).localeCompare(String(a.title), 'pt-BR');
      if (local.sort === 'progress_desc') return progress(b) - progress(a);
      if (local.sort === 'created_asc') return String(a.createdAt || '').localeCompare(String(b.createdAt || ''));
      if (local.sort === 'created_desc') return String(b.createdAt || '').localeCompare(String(a.createdAt || ''));
      return String(b.updatedAt || '').localeCompare(String(a.updatedAt || ''));
    });
    return books;
  }

  function stageTabs(root) {
    var state = root.__vs;
    var local = ui(root);
    var base = (state.library.books || []).filter(function (book) { return local.status === 'all' || book.status === local.status; });
    var tabs = [['all', 'Todas']].concat(STAGES);
    return tabs.map(function (item) {
      var count = item[0] === 'all' ? base.length : base.filter(function (book) { return String(book.stage) === item[0]; }).length;
      return '<button type="button" data-minhas-stage="' + item[0] + '" class="' + (local.stage === item[0] ? 'is-active' : '') + '">' + esc(item[1]) + '<span>' + count + '</span></button>';
    }).join('');
  }

  function bookCard(root, book) {
    var state = root.__vs;
    var archived = book.status === 'archived';
    var pct = progress(book);
    var cover = book.coverUrl
      ? '<img src="' + esc(book.coverUrl) + '" alt="Capa de ' + esc(book.title) + '">'
      : '<span class="verbum-minhas-cover-placeholder" style="background:' + color(book.color) + '"><b>V</b></span>';
    var synopsis = String(book.synopsis || book.subtitle || 'Continue estruturando esta obra no fluxo editorial do Verbum Studio.');
    return '<article class="verbum-minhas-card' + (archived ? ' is-archived' : '') + '">' +
      '<div class="verbum-minhas-cover">' + cover + '<span class="verbum-minhas-stage-badge">' + esc(archived ? 'Arquivada' : stageLabel(book)) + '</span></div>' +
      '<div class="verbum-minhas-card-body">' +
        '<span class="verbum-minhas-project">' + esc(projectName(state, book.projectId)) + '</span>' +
        '<h3 title="' + esc(book.title) + '">' + esc(book.title) + '</h3>' +
        (book.subtitle ? '<p class="verbum-minhas-subtitle">' + esc(book.subtitle) + '</p>' : '') +
        '<p class="verbum-minhas-synopsis">' + esc(synopsis) + '</p>' +
        '<div class="verbum-minhas-imo"><span>IMO</span><strong>Ainda não calculado</strong><div><i></i></div></div>' +
        '<div class="verbum-minhas-progress"><div><span>Etapa ' + (stageIndex(book) + 1) + ' de ' + STAGES.length + '</span><strong>' + pct + '%</strong></div><div class="verbum-minhas-progress-track"><i style="width:' + pct + '%;background:' + color(book.color) + '"></i></div></div>' +
        '<div class="verbum-minhas-meta"><span>▤ ' + num(book.plannedChapters) + ' cap.</span><span> T 0 pal.</span><span>▢ ' + esc(relativeDate(book.updatedAt)) + '</span></div>' +
      '</div>' +
      '<div class="verbum-minhas-actions">' +
        (!archived ? '<button type="button" class="verbum-minhas-open" data-open-work="' + esc(book.id) + '">▣ Abrir Obra</button>' : '') +
        '<button type="button" class="verbum-minhas-link" data-edit-book="' + esc(book.id) + '">Editar</button>' +
        (!archived ? '<button type="button" class="verbum-minhas-link is-danger" data-archive-book="' + esc(book.id) + '">Arquivar</button>' : '') +
      '</div>' +
    '</article>';
  }

  function renderLibrary(root) {
    var state = root.__vs;
    if (!state || state.section !== 'library' || state.workspace) return;
    var main = root.querySelector('#verbum-main-content') || root.querySelector('.verbum-main');
    if (!main) return;
    var local = ui(root);
    var activeProjects = (state.library.projects || []).filter(function (project) { return project.status === 'active'; });
    var books = filteredBooks(root);
    var genres = Array.from(new Set((state.library.books || []).map(function (book) { return String(book.genre || book.category || '').trim(); }).filter(Boolean))).sort();
    var cards = books.length ? books.map(function (book) { return bookCard(root, book); }).join('') : '<div class="verbum-minhas-empty"><span>V</span><h3>Nenhuma obra encontrada</h3><p>Ajuste os filtros ou crie uma nova obra para continuar.</p>' + (activeProjects.length ? '<button type="button" data-new-book>+ Nova Obra</button>' : '<button type="button" data-new-project>Criar projeto</button>') + '</div>';
    var filterPanel = local.filtersOpen ? '<div class="verbum-minhas-filter-panel"><label>Situação<select data-minhas-status><option value="active"' + (local.status === 'active' ? ' selected' : '') + '>Ativas</option><option value="archived"' + (local.status === 'archived' ? ' selected' : '') + '>Arquivadas</option><option value="all"' + (local.status === 'all' ? ' selected' : '') + '>Todas</option></select></label><label>Projeto<select data-minhas-project><option value="all">Todos os projetos</option>' + (state.library.projects || []).map(function (project) { return '<option value="' + esc(project.id) + '"' + (local.project === String(project.id) ? ' selected' : '') + '>' + esc(project.name) + '</option>'; }).join('') + '</select></label><label>Gênero<select data-minhas-genre><option value="all">Todos os gêneros</option>' + genres.map(function (genre) { return '<option value="' + esc(genre) + '"' + (local.genre === genre ? ' selected' : '') + '>' + esc(genre) + '</option>'; }).join('') + '</select></label><button type="button" class="verbum-minhas-project-button" data-new-project>Gerenciar projetos</button></div>' : '';

    main.innerHTML = '<div class="verbum-minhas-obras" data-ready="1">' +
      '<section class="verbum-minhas-heading"><div><span class="verbum-minhas-icon">▥</span><div><h1>Minhas Obras</h1><p>Gerencie todas as suas obras desde a inspiração até a publicação.</p></div></div><button type="button" class="verbum-minhas-new" data-new-book' + (!activeProjects.length ? ' disabled' : '') + '>+ Nova Obra</button></section>' +
      '<label class="verbum-minhas-search"><span>⌕</span><input type="search" data-minhas-search value="' + esc(local.query) + '" placeholder="Pesquisar obras..."></label>' +
      '<nav class="verbum-minhas-stages" aria-label="Filtrar por etapa">' + stageTabs(root) + '</nav>' +
      '<section class="verbum-minhas-toolbar"><div><button type="button" class="verbum-minhas-filter-toggle' + (local.filtersOpen ? ' is-active' : '') + '" data-minhas-filters>☷ Filtros</button><select data-minhas-sort><option value="updated_desc"' + (local.sort === 'updated_desc' ? ' selected' : '') + '>Última edição</option><option value="title_asc"' + (local.sort === 'title_asc' ? ' selected' : '') + '>Título A–Z</option><option value="title_desc"' + (local.sort === 'title_desc' ? ' selected' : '') + '>Título Z–A</option><option value="progress_desc"' + (local.sort === 'progress_desc' ? ' selected' : '') + '>Maior progresso</option><option value="created_desc"' + (local.sort === 'created_desc' ? ' selected' : '') + '>Criação mais recente</option><option value="created_asc"' + (local.sort === 'created_asc' ? ' selected' : '') + '>Criação mais antiga</option></select></div><div class="verbum-minhas-view"><button type="button" data-minhas-view="grid" class="' + (local.view === 'grid' ? 'is-active' : '') + '" title="Grade">▦</button><button type="button" data-minhas-view="list" class="' + (local.view === 'list' ? 'is-active' : '') + '" title="Lista">☷</button></div></section>' +
      filterPanel +
      '<section class="verbum-minhas-results is-' + local.view + '">' + cards + '</section>' +
    '</div>';
    polishLabels(root);
  }

  function polishLabels(root) {
    var state = root.__vs || {};
    root.querySelectorAll('[data-section="library"] span:last-child').forEach(function (node) { node.textContent = 'Minhas Obras'; });
    var shortcut = root.querySelector('.verbum-dashboard-shortcut.is-library strong');
    if (shortcut) shortcut.textContent = 'Minhas Obras';
    if (state.section === 'library') {
      var heading = root.querySelector('.verbum-header h1');
      if (heading) heading.textContent = 'Minhas Obras';
    }
    var back = root.querySelector('.verbum-work-back');
    if (back && /Obras/.test(back.textContent || '')) back.textContent = '‹ Minhas Obras';
    var breadcrumb = root.querySelector('.verbum-work-breadcrumb');
    if (breadcrumb && /^Obras\s/.test(breadcrumb.textContent || '')) breadcrumb.innerHTML = breadcrumb.innerHTML.replace(/^Obras/, 'Minhas Obras');
  }

  function onClick(event) {
    var target = event.target.closest('[data-minhas-stage],[data-minhas-filters],[data-minhas-view]');
    if (!target) return;
    var root = target.closest('[data-verbum-app]');
    if (!root) return;
    var local = ui(root);
    if (target.hasAttribute('data-minhas-stage')) local.stage = target.getAttribute('data-minhas-stage') || 'all';
    if (target.hasAttribute('data-minhas-filters')) local.filtersOpen = !local.filtersOpen;
    if (target.hasAttribute('data-minhas-view')) {
      local.view = target.getAttribute('data-minhas-view') === 'list' ? 'list' : 'grid';
      try { localStorage.setItem(VIEW_KEY, local.view); } catch (error) {}
    }
    renderLibrary(root);
  }

  function onInput(event) {
    var root = event.target.closest('[data-verbum-app]');
    if (!root) return;
    var local = ui(root);
    if (event.target.matches('[data-minhas-search]')) { local.query = event.target.value || ''; renderLibrary(root); return; }
    if (event.target.matches('[data-minhas-status]')) { local.status = event.target.value || 'active'; renderLibrary(root); return; }
    if (event.target.matches('[data-minhas-project]')) { local.project = event.target.value || 'all'; renderLibrary(root); return; }
    if (event.target.matches('[data-minhas-genre]')) { local.genre = event.target.value || 'all'; renderLibrary(root); return; }
    if (event.target.matches('[data-minhas-sort]')) { local.sort = event.target.value || 'updated_desc'; renderLibrary(root); }
  }

  function boot(root) {
    polishLabels(root);
    renderLibrary(root);
    new MutationObserver(function () {
      polishLabels(root);
      var state = root.__vs;
      if (state && state.section === 'library' && !state.workspace && !root.querySelector('.verbum-minhas-obras[data-ready="1"]')) renderLibrary(root);
    }).observe(root, { childList: true, subtree: true });
  }

  document.addEventListener('click', onClick);
  document.addEventListener('input', onInput);
  document.addEventListener('change', onInput);
  document.querySelectorAll('[data-verbum-app]').forEach(boot);
})();
