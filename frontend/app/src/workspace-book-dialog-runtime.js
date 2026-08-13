(function () {
  'use strict';
  var LEGACY = 'Projeto Teste Verbum';

  function patch(root) {
    var dialog = root.querySelector('.verbum-dialog[aria-labelledby="verbum-book-dialog-title"]');
    if (!dialog) return;
    var field = dialog.querySelector('.verbum-form-grid>label.verbum-field-span-2:first-child');
    if (!field) return;
    if (field.getAttribute('data-workspace-field-ready') !== '1') {
      field.style.setProperty('display', 'grid', 'important');
      var label = field.querySelector('span');
      if (label) label.textContent = 'Área de Trabalho *';
      field.setAttribute('data-workspace-field-ready', '1');
    }
    var select = field.querySelector('select');
    if (!select || select.dataset.workspaceReady === '1' || !window.VerbumWorkspaceUI) return;
    select.dataset.workspaceReady = '1';
    window.VerbumWorkspaceUI.getLibrary(false).then(function (library) {
      var projects = window.VerbumWorkspaceUI.projects(library);
      Array.from(select.options).forEach(function (option) {
        if ((option.textContent || '').trim() === LEGACY) option.remove();
      });
      if (select.options[0] && !select.options[0].value) select.options[0].textContent = 'Selecione uma Área de Trabalho';
      if (projects.length && !select.value) {
        select.value = String(projects[0].id);
        select.dispatchEvent(new Event('change', { bubbles: true }));
      }
    }).catch(function () {});
  }

  function boot() {
    function run() { document.querySelectorAll('[data-verbum-app]').forEach(patch); }
    run();
    new MutationObserver(run).observe(document.documentElement, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
