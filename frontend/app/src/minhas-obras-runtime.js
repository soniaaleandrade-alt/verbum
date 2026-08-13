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

  function clamp(value, min, max) {
    var parsed = Number(value);
    if (!Number.isFinite(parsed)) parsed = min;
    return Math.max(min, Math.min(max, parsed));
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
    root.__minhasObras = {
      stage: 'all', status: 'active', sort: 'updated_desc', view: view === 'list' ? 'list' : 'grid',
      filtersOpen: false, project: 'all', genre: 'all', query: '', menuBookId: null,
      deleteBookId: null, coverBookId: null, coverDraft: null, coverPositions: {}, positionsLoaded: false,
      busy: false, notice: '', error: '', drag: null
    };
    return root.__minhasObras;
  }

  function findBook(root, id) {
    var books = (root.__vs && root.__vs.library && root.__vs.library.books) || [];
    return books.find(function (book) { return String(book.id) === String(id); }) || null;
  }

  function positionFor(root, bookId) {
    var local = ui(root);
    var value = local.coverPositions[String(bookId)] || {};
    return {
      x: clamp(value.x == null ? 50 : value.x, 0, 100),
      y: clamp(value.y == null ? 50 : value.y, 0, 100),
      zoom: clamp(value.zoom == null ? 1 : value.zoom, 1, 2.5)
    };
  }

  async function api(path, options) {
    var config = window.VerbumStudioConfig || {};
    var root = String(config.apiRoot || '/wp-json/verbum/v1').replace(/\/$/, '');
    var settings = Object.assign({ method: 'GET', credentials: 'same-origin' }, options || {});
    settings.headers = Object.assign({ 'X-WP-Nonce': config.nonce || '' }, settings.headers || {});
    if (settings.body && typeof settings.body !== 'string') {
      settings.headers['Content-Type'] = 'application/json';
      settings.body = JSON.stringify(settings.body);
    }
    var response = await fetch(root + path, settings);
    var payload;
    try { payload = await response.json(); } catch (error) { throw new Error('A API retornou uma resposta inválida.'); }
    if (!response.ok || !payload || !payload.success) {
      throw new Error(payload && payload.error && payload.error.message ? payload.error.message : 'Não foi possível comunicar com a API.');
    }
    return payload.data;
  }

  async function loadCoverPositions(root) {
    var local = ui(root);
    if (local.positionsLoaded) return;
    local.positionsLoaded = true;
    try {
      local.coverPositions = await api('/books/cover-positions');
      if (root.__vs && root.__vs.section === 'library' && !root.__vs.workspace) renderLibrary(root);
    } catch (error) {
      local.positionsLoaded = false;
    }
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

  function coverStyle(root, bookId) {
    var position = positionFor(root, bookId);
    return 'object-position:' + position.x + '% ' + position.y + '%;transform:scale(' + position.zoom + ');transform-origin:' + position.x + '% ' + position.y + '%;';
  }

  function bookCard(root, book) {
    var state = root.__vs;
    var local = ui(root);
    var archived = book.status === 'archived';
    var pct = progress(book);
    var cover = book.coverUrl
      ? '<img src="' + esc(book.coverUrl) + '" alt="Capa de ' + esc(book.title) + '" style="' + coverStyle(root, book.id) + '">'
      : '<span class="verbum-minhas-cover-placeholder" style="background:' + color(book.color) + '"><b>V</b></span>';
    var synopsis = String(book.synopsis || book.subtitle || 'Continue estruturando esta obra no fluxo editorial do Verbum Studio.');
    var menu = local.menuBookId === String(book.id)
      ? '<div class="verbum-minhas-card-menu" role="menu">' +
          (book.coverUrl ? '<button type="button" data-adjust-cover="' + esc(book.id) + '">Ajustar imagem</button>' : '') +
          '<button type="button" class="is-danger" data-delete-book="' + esc(book.id) + '">Excluir obra</button>' +
        '</div>'
      : '';
    return '<article class="verbum-minhas-card' + (archived ? ' is-archived' : '') + '" data-book-card="' + esc(book.id) + '">' +
      '<div class="verbum-minhas-cover">' + cover +
        '<button type="button" class="verbum-minhas-more" data-minhas-menu="' + esc(book.id) + '" aria-label="Mais ações para ' + esc(book.title) + '" aria-expanded="' + (local.menuBookId === String(book.id) ? 'true' : 'false') + '">⋮</button>' +
        menu + '<span class="verbum-minhas-stage-badge">' + esc(archived ? 'Arquivada' : stageLabel(book)) + '</span></div>' +
      '<div class="verbum-minhas-card-body">' +
        '<span class="verbum-minhas-project">' + esc(projectName(state, book.projectId)) + '</span>' +
        '<h3 title="' + esc(book.title) + '">' + esc(book.title) + '</h3>' +
        (book.subtitle ? '<p class="verbum-minhas-subtitle">' + esc(book.subtitle) + '</p>' : '') +
        '<p class="verbum-minhas-synopsis">' + esc(synopsis) + '</p>' +
        '<div class="verbum-minhas-imo"><span>IMO</span><strong>Ainda não calculado</strong><div><i></i></div></div>' +
        '<div class="verbum-minhas-progress"><div><span>Etapa ' + (stageIndex(book) + 1) + ' de ' + STAGES.length + '</span><strong>' + pct + '%</strong></div><div class="verbum-minhas-progress-track"><i style="width:' + pct + '%;background:' + color(book.color) + '"></i></div></div>' +
        '<div class="verbum-minhas-meta"><span>▤ ' + num(book.plannedChapters) + ' cap.</span><span>T 0 pal.</span><span>▢ ' + esc(relativeDate(book.updatedAt)) + '</span></div>' +
      '</div>' +
      '<div class="verbum-minhas-actions">' +
        (!archived ? '<button type="button" class="verbum-minhas-open" data-open-work="' + esc(book.id) + '">▣ Abrir Obra</button>' : '') +
        '<button type="button" class="verbum-minhas-link" data-edit-book="' + esc(book.id) + '">Editar</button>' +
        (!archived ? '<button type="button" class="verbum-minhas-link is-danger" data-archive-book="' + esc(book.id) + '">Arquivar</button>' : '') +
      '</div>' +
    '</article>';
  }

  function modalMarkup(root) {
    var local = ui(root);
    var deleteBook = local.deleteBookId ? findBook(root, local.deleteBookId) : null;
    var coverBook = local.coverBookId ? findBook(root, local.coverBookId) : null;
    var notice = local.notice ? '<div class="verbum-minhas-notice" role="status">' + esc(local.notice) + '</div>' : '';
    var error = local.error ? '<div class="verbum-minhas-modal-error" role="alert">' + esc(local.error) + '</div>' : '';

    if (deleteBook) {
      return notice + '<div class="verbum-minhas-modal-backdrop" data-minhas-modal-backdrop><section class="verbum-minhas-modal verbum-minhas-delete-modal" role="dialog" aria-modal="true" aria-labelledby="verbum-delete-title">' +
        '<h2 id="verbum-delete-title">Excluir obra?</h2>' +
        '<p>Você está prestes a excluir permanentemente a obra <strong>“' + esc(deleteBook.title) + '”</strong>.</p>' +
        '<p class="verbum-minhas-warning">Esta ação também remove os capítulos e dados editoriais vinculados à obra e não poderá ser desfeita.</p>' +
        error + '<div class="verbum-minhas-modal-actions"><button type="button" data-cancel-delete' + (local.busy ? ' disabled' : '') + '>Cancelar</button><button type="button" class="is-danger" data-confirm-delete="' + esc(deleteBook.id) + '"' + (local.busy ? ' disabled' : '') + '>' + (local.busy ? 'Excluindo…' : 'Excluir obra') + '</button></div>' +
      '</section></div>';
    }

    if (coverBook && coverBook.coverUrl) {
      var draft = local.coverDraft || positionFor(root, coverBook.id);
      return notice + '<div class="verbum-minhas-modal-backdrop" data-minhas-modal-backdrop><section class="verbum-minhas-modal verbum-minhas-cover-modal" role="dialog" aria-modal="true" aria-labelledby="verbum-cover-title">' +
        '<div class="verbum-minhas-modal-heading"><div><h2 id="verbum-cover-title">Ajustar imagem do card</h2><p>Arraste a imagem ou use os controles para escolher o enquadramento.</p></div><button type="button" class="verbum-minhas-close" data-cancel-cover aria-label="Fechar">×</button></div>' +
        '<div class="verbum-minhas-cover-preview" data-cover-preview><img src="' + esc(coverBook.coverUrl) + '" alt="Prévia da capa de ' + esc(coverBook.title) + '" data-cover-preview-image style="object-position:' + draft.x + '% ' + draft.y + '%;transform:scale(' + draft.zoom + ');transform-origin:' + draft.x + '% ' + draft.y + '%;"><span>Arraste para posicionar</span></div>' +
        '<div class="verbum-minhas-cover-controls">' +
          '<label>Horizontal <input type="range" min="0" max="100" step="1" value="' + draft.x + '" data-cover-x><output data-cover-x-output>' + Math.round(draft.x) + '%</output></label>' +
          '<label>Vertical <input type="range" min="0" max="100" step="1" value="' + draft.y + '" data-cover-y><output data-cover-y-output>' + Math.round(draft.y) + '%</output></label>' +
          '<label>Zoom <input type="range" min="1" max="2.5" step="0.05" value="' + draft.zoom + '" data-cover-zoom><output data-cover-zoom-output>' + Number(draft.zoom).toFixed(2) + '×</output></label>' +
        '</div>' + error +
        '<div class="verbum-minhas-modal-actions"><button type="button" data-reset-cover' + (local.busy ? ' disabled' : '') + '>Restaurar padrão</button><span></span><button type="button" data-cancel-cover' + (local.busy ? ' disabled' : '') + '>Cancelar</button><button type="button" class="is-primary" data-save-cover="' + esc(coverBook.id) + '"' + (local.busy ? ' disabled' : '') + '>' + (local.busy ? 'Salvando…' : 'Salvar enquadramento') + '</button></div>' +
      '</section></div>';
    }

    return notice;
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
      filterPanel + '<section class="verbum-minhas-results is-' + local.view + '">' + cards + '</section>' + modalMarkup(root) + '</div>';
    polishLabels(root);
  }

  function setTextIfChanged(node, value) {
    if (node && (node.textContent || '') !== value) node.textContent = value;
  }

  function polishLabels(root) {
    var state = root.__vs || {};
    root.querySelectorAll('[data-section="library"] span:last-child').forEach(function (node) { setTextIfChanged(node, 'Minhas Obras'); });
    root.querySelectorAll('[data-section="library"]').forEach(function (node) { if ((node.textContent || '').trim() === 'Ir para Obras') node.textContent = 'Ir para Minhas Obras'; });
    var shortcut = root.querySelector('.verbum-dashboard-shortcut.is-library strong');
    setTextIfChanged(shortcut, 'Minhas Obras');
    root.querySelectorAll('.verbum-dashboard-kicker').forEach(function (node) { if ((node.textContent || '').trim() === 'LIVRO') node.textContent = 'OBRA'; });
    if (state.section === 'library') setTextIfChanged(root.querySelector('.verbum-header h1'), 'Minhas Obras');
    var back = root.querySelector('.verbum-work-back');
    if (back && (back.textContent || '').trim() === '‹ Obras') back.textContent = '‹ Minhas Obras';
    var breadcrumb = root.querySelector('.verbum-work-breadcrumb');
    if (breadcrumb && /^Obras\s/.test(breadcrumb.textContent || '')) breadcrumb.innerHTML = breadcrumb.innerHTML.replace(/^Obras/, 'Minhas Obras');
  }

  function updateCoverPreview(root) {
    var local = ui(root);
    if (!local.coverDraft) return;
    var draft = local.coverDraft;
    var image = root.querySelector('[data-cover-preview-image]');
    if (image) {
      image.style.objectPosition = draft.x + '% ' + draft.y + '%';
      image.style.transform = 'scale(' + draft.zoom + ')';
      image.style.transformOrigin = draft.x + '% ' + draft.y + '%';
    }
    var x = root.querySelector('[data-cover-x]'); var y = root.querySelector('[data-cover-y]'); var zoom = root.querySelector('[data-cover-zoom]');
    if (x) x.value = String(draft.x); if (y) y.value = String(draft.y); if (zoom) zoom.value = String(draft.zoom);
    setTextIfChanged(root.querySelector('[data-cover-x-output]'), Math.round(draft.x) + '%');
    setTextIfChanged(root.querySelector('[data-cover-y-output]'), Math.round(draft.y) + '%');
    setTextIfChanged(root.querySelector('[data-cover-zoom-output]'), Number(draft.zoom).toFixed(2) + '×');
  }

  async function confirmDelete(root, bookId) {
    var local = ui(root);
    if (local.busy) return;
    local.busy = true; local.error = ''; renderLibrary(root);
    try {
      await api('/books/' + encodeURIComponent(bookId), { method: 'DELETE' });
      root.__vs.library.books = (root.__vs.library.books || []).filter(function (book) { return String(book.id) !== String(bookId); });
      delete local.coverPositions[String(bookId)];
      local.deleteBookId = null; local.menuBookId = null; local.busy = false; local.notice = 'Obra excluída com sucesso.';
      renderLibrary(root);
      window.setTimeout(function () { local.notice = ''; if (root.__vs && root.__vs.section === 'library' && !root.__vs.workspace) renderLibrary(root); }, 3000);
    } catch (error) {
      local.busy = false; local.error = error instanceof Error ? error.message : 'Não foi possível excluir a obra.'; renderLibrary(root);
    }
  }

  async function saveCover(root, bookId) {
    var local = ui(root);
    if (local.busy || !local.coverDraft) return;
    local.busy = true; local.error = ''; renderLibrary(root);
    try {
      var saved = await api('/books/' + encodeURIComponent(bookId) + '/cover-position', { method: 'PATCH', body: local.coverDraft });
      local.coverPositions[String(bookId)] = { x: saved.x, y: saved.y, zoom: saved.zoom };
      local.coverBookId = null; local.coverDraft = null; local.busy = false; local.notice = 'Enquadramento da imagem salvo.';
      renderLibrary(root);
      window.setTimeout(function () { local.notice = ''; if (root.__vs && root.__vs.section === 'library' && !root.__vs.workspace) renderLibrary(root); }, 2500);
    } catch (error) {
      local.busy = false; local.error = error instanceof Error ? error.message : 'Não foi possível salvar o enquadramento.'; renderLibrary(root);
    }
  }

  function onClick(event) {
    var target = event.target.closest('[data-minhas-stage],[data-minhas-filters],[data-minhas-view],[data-minhas-menu],[data-adjust-cover],[data-delete-book],[data-cancel-delete],[data-confirm-delete],[data-cancel-cover],[data-reset-cover],[data-save-cover],[data-minhas-modal-backdrop]');
    if (!target) return;
    var root = target.closest('[data-verbum-app]');
    if (!root) return;
    var local = ui(root);

    if (target.hasAttribute('data-minhas-stage')) local.stage = target.getAttribute('data-minhas-stage') || 'all';
    else if (target.hasAttribute('data-minhas-filters')) local.filtersOpen = !local.filtersOpen;
    else if (target.hasAttribute('data-minhas-view')) {
      local.view = target.getAttribute('data-minhas-view') === 'list' ? 'list' : 'grid';
      try { localStorage.setItem(VIEW_KEY, local.view); } catch (error) {}
    } else if (target.hasAttribute('data-minhas-menu')) {
      var menuId = target.getAttribute('data-minhas-menu');
      local.menuBookId = local.menuBookId === menuId ? null : menuId;
    } else if (target.hasAttribute('data-adjust-cover')) {
      var coverId = target.getAttribute('data-adjust-cover');
      local.coverBookId = coverId; local.coverDraft = positionFor(root, coverId); local.menuBookId = null; local.error = '';
    } else if (target.hasAttribute('data-delete-book')) {
      local.deleteBookId = target.getAttribute('data-delete-book'); local.menuBookId = null; local.error = '';
    } else if (target.hasAttribute('data-cancel-delete')) {
      local.deleteBookId = null; local.error = '';
    } else if (target.hasAttribute('data-confirm-delete')) {
      confirmDelete(root, target.getAttribute('data-confirm-delete')); return;
    } else if (target.hasAttribute('data-cancel-cover')) {
      local.coverBookId = null; local.coverDraft = null; local.error = '';
    } else if (target.hasAttribute('data-reset-cover')) {
      local.coverDraft = { x: 50, y: 50, zoom: 1 }; updateCoverPreview(root); return;
    } else if (target.hasAttribute('data-save-cover')) {
      saveCover(root, target.getAttribute('data-save-cover')); return;
    } else if (target.hasAttribute('data-minhas-modal-backdrop') && event.target === target) {
      if (!local.busy) { local.deleteBookId = null; local.coverBookId = null; local.coverDraft = null; local.error = ''; }
    }
    renderLibrary(root);
  }

  function onInput(event) {
    var root = event.target.closest('[data-verbum-app]');
    if (!root) return;
    var local = ui(root);
    if (event.target.matches('[data-minhas-search]')) {
      var cursor = event.target.selectionStart == null ? String(event.target.value || '').length : event.target.selectionStart;
      local.query = event.target.value || '';
      renderLibrary(root);
      window.setTimeout(function () {
        var input = root.querySelector('[data-minhas-search]');
        if (!input) return;
        input.focus();
        if (input.setSelectionRange) input.setSelectionRange(cursor, cursor);
      }, 0);
      return;
    }
    if (event.target.matches('[data-minhas-status]')) { local.status = event.target.value || 'active'; renderLibrary(root); return; }
    if (event.target.matches('[data-minhas-project]')) { local.project = event.target.value || 'all'; renderLibrary(root); return; }
    if (event.target.matches('[data-minhas-genre]')) { local.genre = event.target.value || 'all'; renderLibrary(root); return; }
    if (event.target.matches('[data-minhas-sort]')) { local.sort = event.target.value || 'updated_desc'; renderLibrary(root); return; }
    if (!local.coverDraft) return;
    if (event.target.matches('[data-cover-x]')) local.coverDraft.x = clamp(event.target.value, 0, 100);
    else if (event.target.matches('[data-cover-y]')) local.coverDraft.y = clamp(event.target.value, 0, 100);
    else if (event.target.matches('[data-cover-zoom]')) local.coverDraft.zoom = clamp(event.target.value, 1, 2.5);
    else return;
    updateCoverPreview(root);
  }

  function onPointerDown(event) {
    var preview = event.target.closest('[data-cover-preview]');
    if (!preview) return;
    var root = preview.closest('[data-verbum-app]');
    if (!root) return;
    var local = ui(root);
    if (!local.coverDraft) return;
    local.drag = { root: root, startX: event.clientX, startY: event.clientY, x: local.coverDraft.x, y: local.coverDraft.y, width: preview.clientWidth || 1, height: preview.clientHeight || 1 };
    preview.classList.add('is-dragging');
    if (preview.setPointerCapture && event.pointerId != null) preview.setPointerCapture(event.pointerId);
    event.preventDefault();
  }

  function onPointerMove(event) {
    document.querySelectorAll('[data-verbum-app]').forEach(function (root) {
      var local = ui(root);
      if (!local.drag || !local.coverDraft) return;
      local.coverDraft.x = clamp(local.drag.x - ((event.clientX - local.drag.startX) / local.drag.width) * 100, 0, 100);
      local.coverDraft.y = clamp(local.drag.y - ((event.clientY - local.drag.startY) / local.drag.height) * 100, 0, 100);
      updateCoverPreview(root);
    });
  }

  function onPointerUp() {
    document.querySelectorAll('[data-verbum-app]').forEach(function (root) {
      var local = ui(root);
      if (!local.drag) return;
      local.drag = null;
      var preview = root.querySelector('[data-cover-preview]');
      if (preview) preview.classList.remove('is-dragging');
    });
  }

  function boot(root) {
    polishLabels(root);
    renderLibrary(root);
    loadCoverPositions(root);
    new MutationObserver(function () {
      polishLabels(root);
      var state = root.__vs;
      if (state && state.section === 'library' && !state.workspace && !root.querySelector('.verbum-minhas-obras[data-ready="1"]')) renderLibrary(root);
    }).observe(root, { childList: true, subtree: true });
  }

  document.addEventListener('click', onClick);
  document.addEventListener('input', onInput);
  document.addEventListener('change', onInput);
  document.addEventListener('pointerdown', onPointerDown);
  document.addEventListener('pointermove', onPointerMove);
  document.addEventListener('pointerup', onPointerUp);
  document.addEventListener('pointercancel', onPointerUp);
  document.querySelectorAll('[data-verbum-app]').forEach(boot);
})();
