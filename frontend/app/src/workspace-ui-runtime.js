(function () {
  'use strict';

  var cache = null;
  var FAVORITES_KEY = 'verbum_workspace_favorites';
  var TASKS_KEY = 'verbum_workspace_tasks';
  var SESSION_KEY = 'verbum_workspace_session_started';
  var STAGES = ['identification','project','planning','development','general_review','versions','audit','editorial_desk','layout','legal','publication'];
  var STAGE_LABELS = {
    identification:'Identificação', project:'Projeto da Obra', planning:'Planejamento', development:'Desenvolvimento',
    general_review:'Revisão Geral', versions:'Controle de Versões', audit:'Auditoria', editorial_desk:'Mesa Editorial',
    layout:'Diagramação', legal:'Trâmites Legais', publication:'Publicação'
  };

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char];
    });
  }

  function apiRoot() { return String((window.VerbumStudioConfig || {}).apiRoot || '/wp-json/verbum/v1').replace(/\/$/, ''); }
  async function api(path) {
    var config = window.VerbumStudioConfig || {};
    var response = await fetch(apiRoot() + path, { method: 'GET', credentials: 'same-origin', headers: { 'X-WP-Nonce': config.nonce || '' } });
    var payload = await response.json();
    if (!response.ok || !payload.success) throw new Error(payload.error ? payload.error.message : 'Falha na API.');
    return payload.data;
  }
  function getLibrary(force) {
    if (cache && !force) return Promise.resolve(cache);
    return api('/library').then(function (data) { cache = data || { projects: [], books: [] }; return cache; });
  }
  function main(root) { return root.querySelector('#verbum-main-content') || root.querySelector('.verbum-main'); }

  function readJson(key, fallback) {
    try { var raw = localStorage.getItem(key); return raw ? JSON.parse(raw) : fallback; } catch (e) { return fallback; }
  }
  function writeJson(key, value) { try { localStorage.setItem(key, JSON.stringify(value)); } catch (e) {} }
  function favoriteIds() { var list = readJson(FAVORITES_KEY, []); return Array.isArray(list) ? list.map(String) : []; }
  function tasks() { var list = readJson(TASKS_KEY, []); return Array.isArray(list) ? list : []; }

  function todayKey() {
    var d = new Date();
    return 'verbum_workspace_daily_' + d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  }
  function settleSession() {
    var started = Number(localStorage.getItem(SESSION_KEY) || 0);
    if (!started) return;
    var elapsed = Math.round((Date.now() - started) / 60000);
    if (elapsed > 0 && elapsed <= 240) {
      var key = todayKey();
      var daily = readJson(key, { words: 0, minutes: 0, chapters: 0, wordGoal: 1500, minuteGoal: 60, chapterGoal: 1 });
      daily.minutes = Number(daily.minutes || 0) + elapsed;
      writeJson(key, daily);
    }
    try { localStorage.removeItem(SESSION_KEY); } catch (e) {}
  }
  function dailyMetrics() {
    settleSession();
    var daily = readJson(todayKey(), { words: 0, minutes: 0, chapters: 0, wordGoal: 1500, minuteGoal: 60, chapterGoal: 1 });
    return {
      words: Number(daily.words || 0), minutes: Number(daily.minutes || 0), chapters: Number(daily.chapters || 0),
      wordGoal: Number(daily.wordGoal || 1500), minuteGoal: Number(daily.minuteGoal || 60), chapterGoal: Number(daily.chapterGoal || 1)
    };
  }

  function stageProgress(stage) { var index = STAGES.indexOf(String(stage || '')); return index < 0 ? 0 : Math.round(((index + 1) / STAGES.length) * 100); }
  function stageNumber(stage) { var index = STAGES.indexOf(String(stage || '')); return index < 0 ? 1 : index + 1; }
  function stageLabel(stage) { return STAGE_LABELS[stage] || 'Identificação'; }
  function relativeDate(value) {
    if (!value) return 'sem edição recente';
    var date = new Date(value), now = new Date(), diff = Math.max(0, Math.floor((now - date) / 86400000));
    if (diff === 0) return 'hoje';
    if (diff === 1) return 'há 1 dia';
    if (diff < 30) return 'há ' + diff + ' dias';
    return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
  }
  function activeBooks(library) {
    return (library.books || []).filter(function (book) { return book.status === 'active'; }).sort(function (a, b) {
      return new Date(b.updatedAt || b.createdAt || 0) - new Date(a.updatedAt || a.createdAt || 0);
    });
  }

  function icon(name) {
    var paths = {
      target:'<circle cx="12" cy="12" r="7"/><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/>',
      clock:'<circle cx="12" cy="12" r="8"/><path d="M12 7v5l3 2"/>',
      book:'<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H11v16H6.5A2.5 2.5 0 0 0 4 21.5z"/><path d="M20 5.5A2.5 2.5 0 0 0 17.5 3H13v16h4.5A2.5 2.5 0 0 1 20 21.5z"/>',
      star:'<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-2.9-5.6 2.9 1.1-6.2L3 9.6l6.2-.9z"/>',
      plus:'<path d="M12 5v14M5 12h14"/>', calendar:'<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M8 3v4M16 3v4M4 9h16"/>',
      audit:'<rect x="5" y="4" width="14" height="16" rx="2"/><path d="M9 4.5h6M8 10h8M8 14h5"/>',
      dashboard:'<rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/>',
      globe:'<circle cx="12" cy="12" r="8"/><path d="M4 12h16M12 4c2.2 2.2 3.3 4.9 3.3 8S14.2 17.8 12 20M12 4C9.8 6.2 8.7 8.9 8.7 12S9.8 17.8 12 20"/>',
      settings:'<circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 0 0-.1-1l2-1.5-2-3.4-2.4 1a8 8 0 0 0-1.7-1L14.5 3h-5l-.3 3.1a8 8 0 0 0-1.7 1l-2.4-1-2 3.4 2 1.5a7 7 0 0 0 0 2l-2 1.5 2 3.4 2.4-1a8 8 0 0 0 1.7 1l.3 3.1h5l.3-3.1a8 8 0 0 0 1.7-1l2.4 1 2-3.4-2-1.5a7 7 0 0 0 .1-1z"/>',
      pen:'<path d="M4 20l4.5-1 10-10-3.5-3.5-10 10z"/><path d="m13.5 6.5 4 4"/>'
    };
    return '<svg viewBox="0 0 24 24" aria-hidden="true">' + (paths[name] || paths.book) + '</svg>';
  }
  function progress(current, goal) { return !goal ? 0 : Math.max(0, Math.min(100, Math.round((Number(current || 0) / goal) * 100))); }
  function metricHtml(iconName, current, goal, suffix) {
    return '<article class="vs-workspace-goal-item"><span class="vs-workspace-goal-icon">' + icon(iconName) + '</span><strong>' + Number(current || 0).toLocaleString('pt-BR') + '</strong><small>/ ' + Number(goal || 0).toLocaleString('pt-BR') + ' ' + suffix + '</small><div class="vs-workspace-goal-bar"><i style="width:' + progress(current, goal) + '%"></i></div></article>';
  }

  function miniBook(book, favorite) {
    var p = stageProgress(book.stage);
    return '<article class="vs-workspace-book-row"><button type="button" class="vs-workspace-book-open" data-open-work="' + esc(book.id) + '">' +
      '<span class="vs-workspace-book-cover">' + (book.coverUrl ? '<img src="' + esc(book.coverUrl) + '" alt="">' : icon('book')) + '</span>' +
      '<span class="vs-workspace-book-copy"><strong title="' + esc(book.title) + '">' + esc(book.title) + '</strong><small>Etapa ' + stageNumber(book.stage) + ' · ' + esc(stageLabel(book.stage)) + '</small><em>' + esc(relativeDate(book.updatedAt)) + '</em></span>' +
      '<span class="vs-workspace-book-progress"><b>' + p + '%</b><small>progresso</small></span><span class="vs-workspace-chevron">›</span></button>' +
      '<button type="button" class="vs-workspace-favorite' + (favorite ? ' is-active' : '') + '" data-area-favorite="' + esc(book.id) + '" aria-label="' + (favorite ? 'Remover dos favoritos' : 'Adicionar aos favoritos') + '">' + icon('star') + '</button></article>';
  }
  function favoritesHtml(books, ids) {
    var favorites = books.filter(function (book) { return ids.indexOf(String(book.id)) >= 0; }).slice(0, 3);
    if (!favorites.length) return '<div class="vs-workspace-empty"><span>' + icon('star') + '</span><p>Nenhuma obra favorita. Clique na estrela para favoritar.</p></div>';
    return '<div class="vs-workspace-favorite-list">' + favorites.map(function (book) { return miniBook(book, true); }).join('') + '</div>';
  }
  function tasksHtml(list) {
    var open = list.filter(function (item) { return !item.done; });
    if (!open.length) return '<div class="vs-workspace-empty compact"><p>Nenhuma tarefa pendente.</p></div>';
    return '<div class="vs-workspace-task-list">' + open.slice(0, 6).map(function (item) {
      return '<label><input type="checkbox" data-area-task-toggle="' + esc(item.id) + '"><span>' + esc(item.text) + '</span><button type="button" data-area-task-remove="' + esc(item.id) + '" aria-label="Excluir tarefa">×</button></label>';
    }).join('') + '</div>';
  }
  function activityHtml(books) {
    if (!books.length) return '<div class="vs-workspace-empty compact"><p>Nenhuma atividade recente.</p></div>';
    return '<div class="vs-workspace-activity-list">' + books.slice(0, 4).map(function (book) {
      return '<button type="button" data-open-work="' + esc(book.id) + '"><span class="vs-workspace-activity-icon">' + icon('book') + '</span><span><strong>Obra atualizada</strong><small>' + esc(book.title) + '</small></span><em>' + esc(relativeDate(book.updatedAt)) + '</em></button>';
    }).join('') + '</div>';
  }
  function quickAction(iconName, label, action, extra) {
    return '<button type="button" class="vs-workspace-quick" data-area-quick="' + action + '"' + (extra ? ' data-area-target="' + esc(extra) + '"' : '') + '><span>' + icon(iconName) + '</span><strong>' + esc(label) + '</strong></button>';
  }

  function activateShell(root) {
    root.querySelectorAll('.verbum-nav-item').forEach(function (button) { button.classList.remove('is-active'); });
    var area = Array.from(root.querySelectorAll('.verbum-nav-item')).find(function (button) {
      var label = (button.textContent || '').trim();
      return label === 'Área de Trabalho' || label === 'Mesa de Trabalho' || label === 'Workspace';
    });
    if (area) area.classList.add('is-active');
    var header = root.querySelector('.verbum-header');
    if (header) {
      var eyebrow = header.querySelector('.verbum-eyebrow'), title = header.querySelector('h1');
      if (eyebrow) eyebrow.textContent = 'Workspace editorial';
      if (title) title.textContent = 'Área de Trabalho';
    }
  }

  function render(root, library) {
    var books = activeBooks(library), ids = favoriteIds(), todo = tasks(), daily = dailyMetrics(), current = books.slice(0, 3), latest = books[0] || null;
    main(root).innerHTML = '<section class="vs-workspace-page">' +
      '<section class="vs-workspace-goals"><h2>Meta de Hoje</h2><div>' +
        metricHtml('target', daily.words, daily.wordGoal, 'palavras') + metricHtml('clock', daily.minutes, daily.minuteGoal, 'minutos') + metricHtml('book', daily.chapters, daily.chapterGoal, daily.chapterGoal === 1 ? 'capítulo' : 'capítulos') +
      '</div></section>' +
      '<section class="vs-workspace-top-grid">' +
        '<article class="vs-workspace-card vs-workspace-continue"><header><h2>Continuar Trabalhando</h2></header><div class="vs-workspace-book-list">' + (current.length ? current.map(function (book) { return miniBook(book, ids.indexOf(String(book.id)) >= 0); }).join('') : '<div class="vs-workspace-empty compact"><p>Nenhuma obra ativa. Crie uma obra para iniciar seu trabalho editorial.</p></div>') + '</div></article>' +
        '<article class="vs-workspace-card"><header><h2><span class="vs-title-icon">' + icon('star') + '</span>Obras Favoritas</h2></header>' + favoritesHtml(books, ids) + '</article>' +
        '<article class="vs-workspace-card"><header><h2>Tarefas do Dia</h2><button type="button" class="vs-workspace-card-add" data-area-add-task aria-label="Adicionar tarefa">+</button></header>' + tasksHtml(todo) + '</article>' +
      '</section>' +
      '<section class="vs-workspace-bottom-grid">' +
        '<article class="vs-workspace-card vs-workspace-quick-card"><header><h2>Acesso Rápido</h2></header><div class="vs-workspace-quick-grid">' +
          quickAction('plus', 'Nova Obra', 'new-book') + quickAction('book', 'Obras', 'library') + quickAction('calendar', 'Calendário Editorial', 'calendar') + quickAction('audit', 'Auditoria', 'stage', latest ? latest.id + '|audit' : '') + quickAction('dashboard', 'Painel', 'dashboard') + quickAction('globe', 'Publicações', 'stage', latest ? latest.id + '|publication' : '') + quickAction('settings', 'Configurações', 'settings') +
        '</div></article>' +
        '<article class="vs-workspace-card vs-workspace-activity-card"><header><h2><span class="vs-title-icon is-teal">⌁</span>Atividades Recentes</h2></header>' + activityHtml(books) + '</article>' +
      '</section>' +
      '<button type="button" class="vs-workspace-start-session" data-area-start-session' + (latest ? ' data-book="' + esc(latest.id) + '"' : '') + '>' + icon('pen') + '<span>Iniciar Sessão de Escrita</span></button>' +
      '<div class="vs-workspace-toast" data-area-toast hidden></div>' +
    '</section>';
    activateShell(root);
  }

  function toast(root, text) {
    var node = root.querySelector('[data-area-toast]'); if (!node) return;
    node.textContent = text; node.hidden = false; clearTimeout(node.__timer); node.__timer = setTimeout(function () { node.hidden = true; }, 2800);
  }
  function navButton(root, labels) {
    return Array.from(root.querySelectorAll('.verbum-nav-item')).find(function (button) { return labels.indexOf((button.textContent || '').trim()) >= 0; });
  }
  function goLibrary(root, openNew) {
    var button = navButton(root, ['Minhas Obras', 'Obras']); if (!button) return; button.click();
    if (openNew) setTimeout(function () { var create = root.querySelector('[data-new-book], .verbum-minhas-obras-new'); if (create && !create.disabled) create.click(); }, 120);
  }
  function goDashboard(root) { var button = navButton(root, ['Painel']); if (button) button.click(); }
  function openStage(bookId, stage) {
    if (!bookId) return; var url = new URL(window.location.href); url.searchParams.set('verbum_work', bookId); url.searchParams.set('verbum_stage', stage); window.location.href = url.toString();
  }
  function addTask(root) {
    var text = window.prompt('Escreva a tarefa de hoje:'); if (!text || !text.trim()) return;
    var list = tasks(); list.push({ id: String(Date.now()), text: text.trim(), done: false }); writeJson(TASKS_KEY, list); render(root, cache || { projects: [], books: [] });
  }
  function toggleFavorite(root, id) {
    var ids = favoriteIds(), index = ids.indexOf(String(id)); if (index >= 0) ids.splice(index, 1); else ids.push(String(id));
    writeJson(FAVORITES_KEY, ids); render(root, cache || { projects: [], books: [] });
  }
  function updateTask(root, id, remove) {
    var list = tasks();
    if (remove) list = list.filter(function (item) { return String(item.id) !== String(id); });
    else list.forEach(function (item) { if (String(item.id) === String(id)) item.done = true; });
    writeJson(TASKS_KEY, list); render(root, cache || { projects: [], books: [] });
  }
  function handleQuick(root, button) {
    var action = button.dataset.areaQuick;
    if (action === 'new-book') return goLibrary(root, true);
    if (action === 'library') return goLibrary(root, false);
    if (action === 'dashboard') return goDashboard(root);
    if (action === 'stage') { var target = String(button.dataset.areaTarget || '').split('|'); if (target[0]) return openStage(target[0], target[1] || 'identification'); return toast(root, 'Crie uma obra para acessar esta etapa.'); }
    if (action === 'calendar') { var calendar = navButton(root, ['Calendário Editorial']); if (calendar && !calendar.disabled) calendar.click(); else toast(root, 'Calendário Editorial será conectado nesta etapa do sistema.'); return; }
    if (action === 'settings') { var config = Array.from(root.querySelectorAll('button')).find(function (item) { return (item.textContent || '').trim() === 'Configurações'; }); if (config) config.click(); else toast(root, 'Configurações ficam disponíveis no menu da sua conta.'); }
  }

  async function open(root) {
    main(root).innerHTML = '<div class="vs-workspace-loading">Carregando Área de Trabalho...</div>'; activateShell(root);
    try { render(root, await getLibrary(true)); }
    catch (error) { main(root).innerHTML = '<div class="vs-workspace-error">' + esc(error.message) + '</div>'; activateShell(root); }
  }

  document.addEventListener('click', function (event) {
    var target = event.target; if (!(target instanceof Element)) return; var root = target.closest('[data-verbum-app]'); if (!root) return;
    var favorite = target.closest('[data-area-favorite]'); if (favorite) { event.preventDefault(); event.stopPropagation(); toggleFavorite(root, favorite.dataset.areaFavorite); return; }
    if (target.closest('[data-area-add-task]')) { event.preventDefault(); addTask(root); return; }
    var remove = target.closest('[data-area-task-remove]'); if (remove) { event.preventDefault(); updateTask(root, remove.dataset.areaTaskRemove, true); return; }
    var toggle = target.closest('[data-area-task-toggle]'); if (toggle) { updateTask(root, toggle.dataset.areaTaskToggle, false); return; }
    var quick = target.closest('[data-area-quick]'); if (quick) { event.preventDefault(); handleQuick(root, quick); return; }
    var session = target.closest('[data-area-start-session]');
    if (session) {
      event.preventDefault(); var bookId = session.dataset.book; if (!bookId) { goLibrary(root, true); return; }
      try { localStorage.setItem(SESSION_KEY, String(Date.now())); } catch (e) {}
      var fake = document.createElement('button'); fake.type = 'button'; fake.dataset.openWork = bookId; fake.hidden = true; root.appendChild(fake); fake.click(); fake.remove();
    }
  }, true);

  window.VerbumWorkspaceUI = { open: open, getLibrary: getLibrary };
})();
