(function () {
  'use strict';

  var live = { bookId: null, node: null, data: null, busy: false, comparison: null, filter: 'all', search: '' };

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char];
    });
  }

  function config() {
    return window.VerbumStudioConfig || { apiRoot: '/wp-json/verbum/v1', nonce: '' };
  }

  function api(path, options) {
    options = options || {};
    var headers = { 'X-WP-Nonce': config().nonce || '' };
    if (options.body !== undefined) headers['Content-Type'] = 'application/json';
    return fetch(config().apiRoot + path, {
      method: options.method || 'GET',
      credentials: 'same-origin',
      headers: headers,
      body: options.body === undefined ? undefined : JSON.stringify(options.body)
    }).then(function (response) {
      return response.json().catch(function () { throw new Error('A API retornou uma resposta inválida.'); }).then(function (payload) {
        if (!response.ok || !payload || !payload.success) throw new Error(payload && payload.error && payload.error.message ? payload.error.message : 'Não foi possível comunicar com a API.');
        return payload.data;
      });
    });
  }

  function route() {
    var params = new URLSearchParams(location.search);
    return { book: params.get('verbum_work'), stage: params.get('verbum_stage') };
  }

  function navigate(stage) {
    var url = new URL(location.href);
    url.searchParams.set('verbum_stage', stage);
    url.searchParams.delete('verbum_chapter');
    url.searchParams.delete('verbum_chapter_stage');
    location.href = url.toString();
  }

  function showMessage(text) {
    var node = live.node && live.node.querySelector('[data-versions-message]');
    if (!node) return;
    node.hidden = !text;
    node.textContent = text || '';
  }

  function mutate(path, options) {
    if (live.busy) return Promise.resolve(null);
    live.busy = true;
    return api(path, options).then(function (result) {
      live.busy = false;
      live.data = result.versionsStage || result;
      render();
      return result;
    }).catch(function (error) {
      live.busy = false;
      showMessage(error.message);
      throw error;
    });
  }

  function visibleVersions() {
    var term = live.search.trim().toLowerCase();
    return (live.data.versions || []).filter(function (version) {
      var haystack = (version.number + ' ' + version.name + ' ' + version.notes + ' ' + version.typeLabel).toLowerCase();
      var matchesSearch = !term || haystack.indexOf(term) >= 0;
      var filter = live.filter;
      var matchesFilter = filter === 'all'
        || (filter === 'protected' && version.protected)
        || (filter === 'manual' && version.origin === 'manual')
        || (filter === 'automatic' && version.origin !== 'manual')
        || (filter === 'milestone' && version.type === 'milestone')
        || (filter === 'backup' && ['manual_backup', 'before_restore', 'before_change'].indexOf(version.type) >= 0);
      return matchesSearch && matchesFilter;
    });
  }

  function statsHtml() {
    var latest = (live.data.versions || []).find(function (version) { return version.id === live.data.latestVersionId; }) || {};
    var items = [
      ['Versões salvas', (live.data.versions || []).length],
      ['Versão atual', latest.number || '—'],
      ['Última versão', latest.createdAt ? new Date(latest.createdAt).toLocaleDateString('pt-BR') : '—'],
      ['Palavras', Number(latest.wordCount || 0).toLocaleString('pt-BR')],
      ['Capítulos', latest.chapterCount || 0]
    ];
    return items.map(function (item) { return '<article><strong>' + esc(item[1]) + '</strong><span>' + esc(item[0]) + '</span></article>'; }).join('');
  }

  function unversionedHtml() {
    var state = live.data.unversioned || {};
    var title = state.hasChanges ? 'Há alterações desde ' + esc(live.data.latestVersionNumber) : 'Tudo preservado em ' + esc(live.data.latestVersionNumber);
    var description = state.hasChanges
      ? Number(state.changedChapters || 0) + ' capítulo(s) modificados · ' + (state.wordDelta >= 0 ? '+' : '') + Number(state.wordDelta || 0).toLocaleString('pt-BR') + ' palavras'
      : 'Nenhuma diferença detectada desde a última versão formal.';
    return '<div class="verbum-versions-unversioned ' + (state.hasChanges ? 'has-changes' : 'is-clean') + '"><div><span>Alterações não versionadas</span><strong>' + title + '</strong><p>' + description + '</p></div>' + (state.hasChanges ? '<button type="button" data-version-create>Criar nova versão</button>' : '') + '</div>';
  }

  function cardsHtml() {
    return visibleVersions().map(function (version) {
      return '<article class="verbum-version-card ' + (version.auditBaseline ? 'is-audit' : '') + '">' +
        '<div class="verbum-version-marker"><span></span></div>' +
        '<div class="verbum-version-card-main">' +
          '<div class="verbum-version-title"><div><strong>' + esc(version.number) + '</strong><h4>' + esc(version.name) + '</h4></div><div>' +
            (version.protected ? '<span class="is-protected">Protegida</span>' : '') +
            (version.auditBaseline ? '<span class="is-audit">Versão para Auditoria</span>' : '') +
          '</div></div>' +
          '<p>' + esc(version.typeLabel) + ' · ' + new Date(version.createdAt).toLocaleString('pt-BR') + ' · ' + Number(version.wordCount || 0).toLocaleString('pt-BR') + ' palavras · ' + Number(version.chapterCount || 0) + ' capítulos</p>' +
          (version.notes ? '<blockquote>' + esc(version.notes) + '</blockquote>' : '') +
          '<div class="verbum-version-actions">' +
            '<button data-version-view="' + esc(version.id) + '">Visualizar</button>' +
            '<button data-version-compare="' + esc(version.id) + '">Comparar</button>' +
            '<button data-version-audit="' + esc(version.id) + '">Usar na Auditoria</button>' +
            '<button data-version-protect="' + esc(version.id) + '" data-protected="' + (version.protected ? '1' : '0') + '">' + (version.protected ? 'Desproteger' : 'Proteger') + '</button>' +
            '<button data-version-duplicate="' + esc(version.id) + '">Duplicar</button>' +
            '<button class="is-danger" data-version-delete="' + esc(version.id) + '" ' + ((version.protected || version.auditBaseline) ? 'disabled' : '') + '>Excluir</button>' +
          '</div>' +
        '</div>' +
      '</article>';
    }).join('');
  }

  function checklistHtml() {
    var rows = (live.data.checklist || []).map(function (item) {
      var manual = !item.automatic && item.key !== 'completed';
      var checked = manual ? !!live.data.flags[item.key] : !!item.completed;
      return '<label class="' + (checked ? 'is-complete' : '') + '"><input type="checkbox" data-version-flag="' + esc(item.key) + '" ' + (checked ? 'checked' : '') + ' ' + (!manual ? 'disabled' : '') + '><span>' + esc(item.label) + '</span></label>';
    }).join('');
    return '<aside class="verbum-versions-progress-card"><div class="verbum-versions-section-head"><h3>Progresso</h3><strong>' + Number(live.data.progress || 0) + '%</strong></div><div class="verbum-versions-progress"><span style="width:' + Number(live.data.progress || 0) + '%"></span></div>' + rows + '<button type="button" class="verbum-primary-button" data-version-complete ' + ((!live.data.ready || live.data.completed || live.busy) ? 'disabled' : '') + '>' + (live.data.completed ? 'Etapa concluída ✓' : 'Concluir Controle de Versões ›') + '</button>' + (!live.data.auditBaselineId ? '<small>Selecione uma versão atual como “Versão para Auditoria”.</small>' : '') + '</aside>';
  }

  function comparisonHtml(result) {
    var comparison = result.comparison;
    var chapters = comparison.chapters.map(function (chapter) {
      var previous = chapter.previousTitle && chapter.previousTitle !== chapter.title ? '<p>Renomeado de: ' + esc(chapter.previousTitle) + '</p>' : '';
      var added = chapter.paragraphs && chapter.paragraphs.added && chapter.paragraphs.added.length
        ? '<div class="diff-added"><b>Adicionado</b>' + chapter.paragraphs.added.map(function (text) { return '<p>' + esc(text) + '</p>'; }).join('') + '</div>'
        : '';
      var removed = chapter.paragraphs && chapter.paragraphs.removed && chapter.paragraphs.removed.length
        ? '<div class="diff-removed"><b>Removido</b>' + chapter.paragraphs.removed.map(function (text) { return '<p>' + esc(text) + '</p>'; }).join('') + '</div>'
        : '';
      var label = chapter.status === 'unchanged' ? 'Sem alteração' : chapter.status === 'added' ? 'Adicionado' : chapter.status === 'removed' ? 'Removido' : 'Alterado';
      return '<details class="is-' + esc(chapter.status) + '"><summary><strong>' + esc(chapter.title) + '</strong><span>' + label + '</span></summary>' + previous + added + removed + '</details>';
    }).join('');
    return '<div class="verbum-versions-comparison"><header><strong>' + esc(result.from.number) + ' → ' + esc(result.to.number) + '</strong><span>' + comparison.summary.changedChapters + ' alterados · ' + comparison.summary.addedChapters + ' adicionados · ' + comparison.summary.removedChapters + ' removidos · ' + (comparison.summary.wordDelta >= 0 ? '+' : '') + Number(comparison.summary.wordDelta).toLocaleString('pt-BR') + ' palavras</span></header>' + chapters + '</div>';
  }

  function comparePanelHtml() {
    var versions = (live.data.versions || []).slice().reverse();
    var options = versions.map(function (version) { return '<option value="' + esc(version.id) + '">' + esc(version.number) + ' — ' + esc(version.name) + '</option>'; }).join('');
    return '<section class="verbum-versions-compare-panel"><div class="verbum-versions-section-head"><div><h3>Comparar versões</h3><p>Compare estrutura, capítulos, palavras e diferenças de parágrafos.</p></div></div><div class="verbum-versions-compare-controls"><select data-version-from>' + options + '</select><span>→</span><select data-version-to>' + options + '</select><button type="button" data-version-run-compare>Comparar</button></div>' + (live.comparison ? comparisonHtml(live.comparison) : '') + '</section>';
  }

  function pageHtml() {
    var integrity = (live.data.integrityErrors || []).length ? '<div class="verbum-versions-integrity is-error"><strong>Falha de integridade detectada</strong><p>Verifique: ' + esc(live.data.integrityErrors.join(', ')) + '.</p></div>' : '';
    return '<header class="verbum-versions-heading"><div><span class="verbum-eyebrow">Etapa 6 de 11</span><h2>Controle de Versões</h2><p>Gerencie o histórico editorial e preserve versões importantes da obra.</p></div><button type="button" class="verbum-primary-button" data-version-create>+ Criar versão</button></header>' +
      '<div class="verbum-versions-stats">' + statsHtml() + '</div>' +
      unversionedHtml() + integrity +
      '<p class="verbum-versions-message is-error" data-versions-message hidden></p>' +
      '<div class="verbum-versions-layout"><main><div class="verbum-versions-toolbar"><input data-version-search value="' + esc(live.search) + '" placeholder="Pesquisar versões..."><select data-version-filter><option value="all">Todas</option><option value="milestone">Marcos editoriais</option><option value="backup">Backups</option><option value="automatic">Automáticas</option><option value="manual">Manuais</option><option value="protected">Protegidas</option></select></div><section class="verbum-versions-timeline"><div class="verbum-versions-section-head"><div><h3>Histórico da Obra</h3><p>Marcos editoriais e backups preservados como snapshots imutáveis.</p></div></div>' + cardsHtml() + '</section>' + comparePanelHtml() + '</main>' + checklistHtml() + '</div>';
  }

  function render() {
    if (!live.node || !live.data) return;
    live.node.className = 'verbum-stage-content verbum-versions-stage';
    live.node.innerHTML = pageHtml();
    var filter = live.node.querySelector('[data-version-filter]');
    if (filter) filter.value = live.filter;
    var from = live.node.querySelector('[data-version-from]');
    var to = live.node.querySelector('[data-version-to]');
    var versions = (live.data.versions || []).slice().reverse();
    if (from && versions.length) from.value = versions[0].id;
    if (to && versions.length) to.value = versions[versions.length - 1].id;
    bind();
    updateFooter();
  }

  function updateFooter() {
    var footer = document.querySelector('.verbum-workspace-footer');
    if (!footer || !live.data) return;
    footer.innerHTML = '<button type="button" class="verbum-workspace-previous" data-version-prev>‹ Etapa anterior</button><div class="verbum-workspace-save-actions"><button type="button" class="verbum-primary-button" data-version-complete-footer ' + ((!live.data.ready || live.data.completed) ? 'disabled' : '') + '>' + (live.data.completed ? 'Etapa concluída ✓' : 'Concluir Controle de Versões ›') + '</button></div>';
    footer.querySelector('[data-version-prev]').onclick = function () { navigate('general_review'); };
    footer.querySelector('[data-version-complete-footer]').onclick = complete;
  }

  function bind() {
    var node = live.node;
    if (!node) return;
    node.querySelectorAll('[data-version-create]').forEach(function (button) { button.onclick = createModal; });
    var search = node.querySelector('[data-version-search]');
    if (search) search.onchange = function () { live.search = search.value; render(); };
    var filter = node.querySelector('[data-version-filter]');
    if (filter) filter.onchange = function () { live.filter = filter.value; render(); };
    node.querySelectorAll('[data-version-view]').forEach(function (button) { button.onclick = function () { view(button.dataset.versionView); }; });
    node.querySelectorAll('[data-version-compare]').forEach(function (button) {
      button.onclick = function () {
        var from = node.querySelector('[data-version-from]');
        var to = node.querySelector('[data-version-to]');
        if (!from || !to) return;
        var latest = (live.data.versions || [])[0];
        if (latest) from.value = latest.id;
        to.value = button.dataset.versionCompare;
        runCompare(from.value, to.value);
      };
    });
    node.querySelectorAll('[data-version-audit]').forEach(function (button) { button.onclick = function () { mutate('/books/' + live.bookId + '/versions-stage/versions/' + button.dataset.versionAudit + '/audit-baseline', { method: 'POST' }); }; });
    node.querySelectorAll('[data-version-protect]').forEach(function (button) { button.onclick = function () { mutate('/books/' + live.bookId + '/versions-stage/versions/' + button.dataset.versionProtect, { method: 'PATCH', body: { protected: button.dataset.protected !== '1' } }); }; });
    node.querySelectorAll('[data-version-duplicate]').forEach(function (button) { button.onclick = function () { mutate('/books/' + live.bookId + '/versions-stage/versions/' + button.dataset.versionDuplicate + '/duplicate', { method: 'POST', body: {} }); }; });
    node.querySelectorAll('[data-version-delete]').forEach(function (button) { button.onclick = function () { if (window.confirm('Esta ação removerá apenas o snapshot histórico selecionado. Continuar?')) mutate('/books/' + live.bookId + '/versions-stage/versions/' + button.dataset.versionDelete, { method: 'DELETE' }); }; });
    node.querySelectorAll('[data-version-flag]').forEach(function (checkbox) {
      checkbox.onchange = function () {
        var flags = Object.assign({}, live.data.flags);
        flags[checkbox.dataset.versionFlag] = checkbox.checked;
        mutate('/books/' + live.bookId + '/versions-stage', { method: 'PATCH', body: { flags: flags } });
      };
    });
    var compareButton = node.querySelector('[data-version-run-compare]');
    if (compareButton) compareButton.onclick = function () {
      var from = node.querySelector('[data-version-from]');
      var to = node.querySelector('[data-version-to]');
      if (from && to) runCompare(from.value, to.value);
    };
    var completeButton = node.querySelector('[data-version-complete]');
    if (completeButton) completeButton.onclick = complete;
  }

  function runCompare(fromId, toId) {
    if (!fromId || !toId || fromId === toId) { showMessage('Selecione duas versões diferentes para comparar.'); return; }
    live.busy = true;
    api('/books/' + live.bookId + '/versions-stage/compare', { method: 'POST', body: { from_id: fromId, to_id: toId } }).then(function (result) {
      live.comparison = result;
      return api('/books/' + live.bookId + '/versions-stage');
    }).then(function (data) {
      live.busy = false;
      live.data = data;
      render();
    }).catch(function (error) {
      live.busy = false;
      showMessage(error.message);
    });
  }

  function createModal() {
    var overlay = document.createElement('div');
    overlay.className = 'verbum-versions-modal-backdrop';
    var options = (live.data.types || []).map(function (type) { return '<option value="' + esc(type.key) + '">' + esc(type.label) + '</option>'; }).join('');
    overlay.innerHTML = '<div class="verbum-versions-modal"><header><div><span class="verbum-eyebrow">Novo marco editorial</span><h3>Criar versão</h3></div><button type="button" data-close>×</button></header><label>Nome da versão<input data-name placeholder="Ex.: Ajustes após leitura crítica"></label><label>Tipo<select data-type>' + options + '</select></label><label>Notas da versão<textarea data-notes placeholder="Descreva as principais alterações..."></textarea></label><label class="is-inline"><input type="checkbox" data-protected> Proteger contra exclusão</label><label class="is-inline"><input type="checkbox" data-major> Nova versão principal (ex.: v2.0)</label><footer><button type="button" data-cancel>Cancelar</button><button type="button" class="verbum-primary-button" data-create>Criar versão</button></footer></div>';
    document.body.appendChild(overlay);
    function close() { overlay.remove(); }
    overlay.querySelector('[data-close]').onclick = close;
    overlay.querySelector('[data-cancel]').onclick = close;
    overlay.onclick = function (event) { if (event.target === overlay) close(); };
    overlay.querySelector('[data-create]').onclick = function () {
      var body = {
        name: overlay.querySelector('[data-name]').value,
        type: overlay.querySelector('[data-type]').value,
        notes: overlay.querySelector('[data-notes]').value,
        protected: overlay.querySelector('[data-protected]').checked,
        major: overlay.querySelector('[data-major]').checked
      };
      api('/books/' + live.bookId + '/versions-stage/versions', { method: 'POST', body: body }).then(function (result) {
        live.data = result.versionsStage;
        close();
        render();
      }).catch(function (error) {
        if (error.message.indexOf('Não foram encontradas alterações') >= 0 && window.confirm(error.message + '\n\nDeseja criar a versão mesmo assim?')) {
          body.force = true;
          api('/books/' + live.bookId + '/versions-stage/versions', { method: 'POST', body: body }).then(function (result) { live.data = result.versionsStage; close(); render(); }).catch(function (secondError) { showMessage(secondError.message); });
        } else showMessage(error.message);
      });
    };
  }

  function view(versionId) {
    api('/books/' + live.bookId + '/versions-stage/versions/' + versionId).then(function (result) {
      var version = result.version;
      var snapshot = result.snapshot;
      var overlay = document.createElement('div');
      overlay.className = 'verbum-versions-modal-backdrop';
      var navigation = (snapshot.chapters || []).map(function (chapter) { return '<a href="#version-chapter-' + esc(chapter.id) + '">Cap. ' + chapter.number + ' — ' + esc(chapter.title) + '</a>'; }).join('');
      var chapters = (snapshot.chapters || []).map(function (chapter) { return '<section id="version-chapter-' + esc(chapter.id) + '"><h2>Capítulo ' + chapter.number + '</h2><h3>' + esc(chapter.title) + '</h3><div>' + chapter.content + '</div></section>'; }).join('');
      var front = snapshot.frontMatter || {};
      overlay.innerHTML = '<div class="verbum-version-reader"><header><div><span>Você está visualizando uma versão histórica.</span><h3>' + esc(version.number) + ' — ' + esc(version.name) + '</h3><p>' + Number(version.wordCount || 0).toLocaleString('pt-BR') + ' palavras · ' + Number(version.chapterCount || 0) + ' capítulos</p></div><button data-close>×</button></header><div class="verbum-version-reader-body"><nav>' + navigation + '</nav><main>' + (front.preface ? '<section><h2>Prefácio</h2><div>' + front.preface + '</div></section>' : '') + (front.presentation ? '<section><h2>Apresentação</h2><div>' + front.presentation + '</div></section>' : '') + (front.introduction ? '<section><h2>Introdução</h2><div>' + front.introduction + '</div></section>' : '') + chapters + (front.conclusion ? '<section><h2>Conclusão</h2><div>' + front.conclusion + '</div></section>' : '') + '</main></div><footer><button data-duplicate>Criar nova versão a partir desta</button><button class="is-danger" data-restore>Restaurar esta versão</button></footer></div>';
      document.body.appendChild(overlay);
      overlay.querySelector('[data-close]').onclick = function () { overlay.remove(); };
      overlay.onclick = function (event) { if (event.target === overlay) overlay.remove(); };
      overlay.querySelector('[data-duplicate]').onclick = function () { api('/books/' + live.bookId + '/versions-stage/versions/' + versionId + '/duplicate', { method: 'POST', body: {} }).then(function (response) { live.data = response.versionsStage; overlay.remove(); render(); }).catch(function (error) { showMessage(error.message); }); };
      overlay.querySelector('[data-restore]').onclick = function () {
        if (!window.confirm('Restaurar a obra para ' + version.number + '? O estado atual será preservado automaticamente antes da restauração.')) return;
        api('/books/' + live.bookId + '/versions-stage/versions/' + versionId + '/restore', { method: 'POST' }).then(function (response) { live.data = response.versionsStage; overlay.remove(); render(); }).catch(function (error) { showMessage(error.message); });
      };
    }).catch(function (error) { showMessage(error.message); });
  }

  function complete() {
    mutate('/books/' + live.bookId + '/versions-stage/complete', { method: 'POST' }).then(function (result) { if (result) navigate('audit'); });
  }

  function mount(force) {
    var current = route();
    var node = document.querySelector('.verbum-stage-content');
    if (!current.book || current.stage !== 'versions' || !node) return;
    if (!force && node.dataset.versionsMounted === current.book) return;
    node.dataset.versionsMounted = current.book;
    node.className = 'verbum-stage-content verbum-versions-state';
    node.textContent = 'Carregando Controle de Versões...';
    live.bookId = current.book;
    live.node = node;
    api('/books/' + current.book + '/versions-stage').then(function (data) { live.data = data; render(); }).catch(function (error) { node.className = 'verbum-stage-content verbum-versions-state is-error'; node.textContent = error.message; });
  }

  new MutationObserver(function () { mount(false); }).observe(document.body, { childList: true, subtree: true });
  window.addEventListener('popstate', function () { mount(true); });
  mount(false);
})();
