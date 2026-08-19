(function () {
  'use strict';

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char];
    });
  }

  function config() { return window.VerbumStudioConfig || {}; }
  function api(path, options) {
    options = options || {};
    return fetch(String(config().apiRoot || '/wp-json/verbum/v1').replace(/\/$/, '') + path, {
      method: options.method || 'GET', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config().nonce || '' }
    }).then(function (response) {
      return response.json().then(function (payload) {
        if (!response.ok || !payload || !payload.success) throw new Error(payload && payload.error && payload.error.message || 'Não foi possível concluir a operação.');
        return payload.data;
      });
    });
  }

  function state(root) {
    return root.__trash || (root.__trash = { deleteId: '', busy: false, error: '', notice: '' });
  }

  function trashedBooks(root) {
    return ((root.__vs && root.__vs.library && root.__vs.library.books) || []).filter(function (book) { return book.status === 'trash'; });
  }

  function date(value) {
    if (!value) return 'Data não registrada';
    var parsed = new Date(value);
    return isNaN(parsed.getTime()) ? 'Data não registrada' : parsed.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
  }

  function card(book) {
    var cover = book.coverUrl ? '<img src="' + esc(book.coverUrl) + '" alt="Capa da obra ' + esc(book.title) + '">' : '<span aria-hidden="true">V</span>';
    return '<article class="vt-card"><div class="vt-cover">' + cover + '</div><div class="vt-copy"><small>Enviada para a lixeira em ' + esc(date(book.trashedAt)) + '</small><h2>' + esc(book.title) + '</h2>' + (book.subtitle ? '<p>' + esc(book.subtitle) + '</p>' : '') + '<dl><div><dt>Etapa</dt><dd>' + esc(book.officialStage || 'Identificação') + '</dd></div><div><dt>Capítulos</dt><dd>' + Number(book.chapterCount || 0) + '</dd></div><div><dt>Palavras</dt><dd>' + Number(book.wordCount || 0).toLocaleString('pt-BR') + '</dd></div></dl></div><div class="vt-actions"><button data-vt-restore="' + esc(book.id) + '">Restaurar obra</button><button class="danger" data-vt-delete="' + esc(book.id) + '">Excluir definitivamente</button></div></article>';
  }

  function modal(root) {
    var current = trashedBooks(root).find(function (book) { return String(book.id) === String(state(root).deleteId); });
    if (!current) return '';
    return '<div class="vt-modal-bg" data-vt-backdrop><section class="vt-modal" role="dialog" aria-modal="true"><h2>Excluir obra definitivamente?</h2><p><strong>' + esc(current.title) + '</strong></p><div class="vt-warning">Esta ação apagará a obra e seus registros de trabalho. Ela não poderá ser desfeita.</div>' + (state(root).error ? '<p class="vt-error" role="alert">' + esc(state(root).error) + '</p>' : '') + '<label class="vt-confirm"><input type="checkbox" data-vt-confirm> Confirmo que desejo excluir esta obra definitivamente.</label><footer><button data-vt-close>Cancelar</button><button class="danger" data-vt-confirm-delete="' + esc(current.id) + '" disabled>Excluir definitivamente</button></footer></section></div>';
  }

  function render(root) {
    var app = root.__vs;
    if (!app || app.section !== 'trash' || app.workspace) return;
    var host = root.querySelector('#verbum-main-content') || root.querySelector('.verbum-main');
    if (!host) return;
    var books = trashedBooks(root), ui = state(root);
    host.innerHTML = '<main class="verbum-trash" data-trash-ready="1"><header><div><span aria-hidden="true">♲</span><section><h1>Lixeira</h1><p>Restaure uma obra ou exclua definitivamente somente o que não precisa mais ser preservado.</p></section></div><button data-section="library">Voltar para Minhas Obras</button></header>' + (ui.notice ? '<div class="vt-notice" role="status">' + esc(ui.notice) + '</div>' : '') + (ui.error && !ui.deleteId ? '<div class="vt-error" role="alert">' + esc(ui.error) + '</div>' : '') + (books.length ? '<section class="vt-list">' + books.map(card).join('') + '</section>' : '<section class="vt-empty"><span aria-hidden="true">✓</span><h2>A lixeira está vazia</h2><p>Nenhuma obra aguarda restauração ou exclusão definitiva.</p><button data-section="library">Ir para Minhas Obras</button></section>') + modal(root) + '</main>';
  }

  function refresh(root) {
    return api('/library').then(function (data) { root.__vs.library = data; render(root); });
  }

  function restore(root, id) {
    var ui = state(root); if (ui.busy) return; ui.busy = true; ui.error = '';
    api('/books/' + id + '/trash/restore', { method: 'POST' }).then(function () { ui.notice = 'Obra restaurada para Minhas Obras.'; return refresh(root); }).catch(function (error) { ui.error = error.message; render(root); }).finally(function () { ui.busy = false; });
  }

  function permanentlyDelete(root, id) {
    var ui = state(root); if (ui.busy) return; ui.busy = true; ui.error = '';
    api('/books/' + id + '/permanent', { method: 'DELETE' }).then(function () { ui.deleteId = ''; ui.notice = 'A obra foi excluída definitivamente.'; return refresh(root); }).catch(function (error) { ui.error = error.message; render(root); }).finally(function () { ui.busy = false; });
  }

  function click(event) {
    var target = event.target.closest('[data-vt-restore],[data-vt-delete],[data-vt-close],[data-vt-confirm-delete]');
    if (!target && event.target.matches('[data-vt-backdrop]')) target = event.target;
    if (!target) return;
    var root = target.closest('[data-verbum-app]'); if (!root) return;
    var ui = state(root);
    if (target.hasAttribute('data-vt-restore')) { restore(root, target.dataset.vtRestore); return; }
    if (target.hasAttribute('data-vt-delete')) ui.deleteId = target.dataset.vtDelete;
    else if (target.hasAttribute('data-vt-confirm-delete')) { permanentlyDelete(root, target.dataset.vtConfirmDelete); return; }
    else if (target.hasAttribute('data-vt-close') || target.hasAttribute('data-vt-backdrop')) { ui.deleteId = ''; ui.error = ''; }
    render(root);
  }

  function change(event) {
    if (!event.target.matches('[data-vt-confirm]')) return;
    var root = event.target.closest('[data-verbum-app]');
    var button = root && root.querySelector('[data-vt-confirm-delete]');
    if (button) button.disabled = !event.target.checked;
  }

  function boot(root) {
    render(root);
    new MutationObserver(function () {
      var app = root.__vs;
      if (app && app.section === 'trash' && !app.workspace && !root.querySelector('[data-trash-ready="1"]')) render(root);
    }).observe(root, { childList: true });
  }

  document.addEventListener('click', click);
  document.addEventListener('change', change);
  document.querySelectorAll('[data-verbum-app]').forEach(boot);
})();
