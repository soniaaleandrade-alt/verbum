(function () {
  'use strict';

  var MAX_IMAGE_BYTES = 10 * 1024 * 1024;
  var ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
  var lastEditor = null;
  var lastRange = null;
  var imageInput = null;
  var uploadingButton = null;

  function isWritingEditor(node) {
    return node instanceof Element && node.classList.contains('verbum-writing-editor');
  }

  function editorFromNode(node) {
    if (!(node instanceof Node)) return null;
    var element = node.nodeType === Node.ELEMENT_NODE ? node : node.parentElement;
    return element instanceof Element ? element.closest('.verbum-writing-editor') : null;
  }

  function rememberSelection() {
    var selection = window.getSelection();
    if (!selection || selection.rangeCount === 0) return;
    var range = selection.getRangeAt(0);
    var editor = editorFromNode(range.commonAncestorContainer);
    if (!editor) return;
    lastEditor = editor;
    lastRange = range.cloneRange();
  }

  function restoreSelection(editor) {
    editor.focus();
    var selection = window.getSelection();
    if (!selection) return;
    selection.removeAllRanges();
    if (lastRange && editor.contains(lastRange.commonAncestorContainer)) {
      selection.addRange(lastRange);
      return;
    }
    var range = document.createRange();
    range.selectNodeContents(editor);
    range.collapse(false);
    selection.addRange(range);
  }

  function escapeHtml(value) {
    return String(value || '').replace(/[&<>"']/g, function (char) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char] || char;
    });
  }

  function insertHtmlIntoEditor(html) {
    var editor = lastEditor && document.contains(lastEditor) ? lastEditor : null;
    if (!editor) {
      window.alert('Clique primeiro no ponto da Redação onde deseja inserir o conteúdo.');
      return false;
    }
    restoreSelection(editor);
    document.execCommand('insertHTML', false, html);
    editor.dispatchEvent(new InputEvent('input', { bubbles: true, inputType: 'insertText', data: null }));
    rememberSelection();
    return true;
  }

  function ideaText(article) {
    var title = article.querySelector('strong');
    var description = article.querySelector('p');
    return {
      title: title ? title.textContent.trim() : '',
      description: description ? description.textContent.trim() : ''
    };
  }

  function insertIdea(article, button) {
    var idea = ideaText(article);
    var parts = [];
    if (idea.title) parts.push('<strong>' + escapeHtml(idea.title) + '</strong>');
    if (idea.description) parts.push(escapeHtml(idea.description));
    if (parts.length === 0) return;
    var html = '<p class="verbum-writing-inserted-idea">' + parts.join(idea.title && idea.description ? ': ' : '') + '</p><p><br></p>';
    if (!insertHtmlIntoEditor(html)) return;
    article.classList.add('is-used');
    button.textContent = 'Inserir novamente';
    button.dataset.verbumIdeaInserted = '1';
  }

  function decorateIdeaCard(article) {
    if (!(article instanceof Element) || article.dataset.verbumIdeaActions === '1') return;
    var existing = Array.from(article.querySelectorAll('button')).find(function (button) {
      return /Inserir como anota[cç][aã]o|Adicionar novamente [aà]s notas/i.test(button.textContent || '');
    });
    if (!existing) return;

    article.dataset.verbumIdeaActions = '1';
    existing.textContent = /novamente/i.test(existing.textContent || '') ? 'Adicionar novamente às notas' : 'Adicionar às notas';
    existing.classList.add('verbum-writing-idea-note-button');

    var insertButton = document.createElement('button');
    insertButton.type = 'button';
    insertButton.className = 'verbum-writing-idea-insert-button';
    insertButton.textContent = article.classList.contains('is-used') ? 'Inserir novamente' : 'Inserir na Redação';
    insertButton.addEventListener('click', function (event) {
      event.preventDefault();
      event.stopPropagation();
      insertIdea(article, insertButton);
    });
    existing.parentNode.insertBefore(insertButton, existing);
  }

  function decorateIdeas(root) {
    (root || document).querySelectorAll('.verbum-writing-idea-list article').forEach(decorateIdeaCard);
  }

  function wpRestMediaUrl() {
    var config = window.VerbumStudioConfig || {};
    var apiRoot = String(config.apiRoot || '');
    if (!apiRoot) return '';
    var base = apiRoot.replace(/verbum\/v1\/?$/i, '');
    if (base && base.charAt(base.length - 1) !== '/') base += '/';
    return base + 'wp/v2/media';
  }

  function ensureImageInput() {
    if (imageInput) return imageInput;
    imageInput = document.createElement('input');
    imageInput.type = 'file';
    imageInput.accept = 'image/jpeg,image/png,image/webp,image/gif';
    imageInput.hidden = true;
    imageInput.setAttribute('aria-hidden', 'true');
    imageInput.addEventListener('change', function () {
      var file = imageInput.files && imageInput.files[0];
      imageInput.value = '';
      if (file) uploadImage(file);
    });
    document.body.appendChild(imageInput);
    return imageInput;
  }

  function setImageButtonBusy(busy, message) {
    if (!uploadingButton) return;
    if (busy) {
      uploadingButton.dataset.verbumOriginalText = uploadingButton.textContent || 'Imagem';
      uploadingButton.textContent = message || 'Enviando...';
      uploadingButton.disabled = true;
    } else {
      uploadingButton.textContent = uploadingButton.dataset.verbumOriginalText || 'Imagem';
      uploadingButton.disabled = false;
      delete uploadingButton.dataset.verbumOriginalText;
      uploadingButton = null;
    }
  }

  async function uploadImage(file) {
    if (!ALLOWED_IMAGE_TYPES.includes(file.type)) {
      window.alert('Envie uma imagem JPG, PNG, WEBP ou GIF.');
      setImageButtonBusy(false);
      return;
    }
    if (file.size > MAX_IMAGE_BYTES) {
      window.alert('A imagem deve ter no máximo 10 MB.');
      setImageButtonBusy(false);
      return;
    }

    var endpoint = wpRestMediaUrl();
    var config = window.VerbumStudioConfig || {};
    if (!endpoint || !config.nonce) {
      window.alert('Não foi possível preparar o envio da imagem. Atualize a página e tente novamente.');
      setImageButtonBusy(false);
      return;
    }

    setImageButtonBusy(true, 'Enviando...');
    try {
      var formData = new FormData();
      formData.append('file', file, file.name);
      var response = await fetch(endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-WP-Nonce': config.nonce },
        body: formData
      });
      var payload = await response.json().catch(function () { return {}; });
      if (!response.ok) throw new Error(payload.message || 'Não foi possível enviar a imagem.');
      var url = payload.source_url || (payload.guid && payload.guid.rendered) || '';
      if (!url) throw new Error('O WordPress não retornou o endereço da imagem enviada.');
      var alt = file.name.replace(/\.[^.]+$/, '').replace(/[-_]+/g, ' ').trim();
      var html = '<figure class="verbum-writing-uploaded-image"><img src="' + escapeHtml(url) + '" alt="' + escapeHtml(alt) + '" loading="lazy"></figure><p><br></p>';
      if (!insertHtmlIntoEditor(html)) throw new Error('Clique no texto e tente inserir a imagem novamente.');
    } catch (error) {
      window.alert(error instanceof Error ? error.message : 'Não foi possível enviar a imagem.');
    } finally {
      setImageButtonBusy(false);
    }
  }

  function isImageToolbarButton(button) {
    return button instanceof HTMLButtonElement && button.closest('.verbum-writing-toolbar') && (button.textContent || '').trim() === 'Imagem';
  }

  document.addEventListener('focusin', function (event) {
    var editor = editorFromNode(event.target);
    if (editor) {
      lastEditor = editor;
      rememberSelection();
    }
  }, true);

  document.addEventListener('selectionchange', rememberSelection);

  document.addEventListener('click', function (event) {
    var target = event.target instanceof Element ? event.target.closest('button') : null;
    if (!isImageToolbarButton(target)) return;
    event.preventDefault();
    event.stopPropagation();
    if (typeof event.stopImmediatePropagation === 'function') event.stopImmediatePropagation();
    if (!lastEditor || !document.contains(lastEditor)) {
      window.alert('Clique primeiro no ponto da Redação onde deseja inserir a imagem.');
      return;
    }
    uploadingButton = target;
    ensureImageInput().click();
  }, true);

  var observer = new MutationObserver(function (mutations) {
    mutations.forEach(function (mutation) {
      mutation.addedNodes.forEach(function (node) {
        if (!(node instanceof Element)) return;
        if (node.matches('.verbum-writing-idea-list article')) decorateIdeaCard(node);
        decorateIdeas(node);
      });
    });
  });

  function injectStyles() {
    if (document.getElementById('verbum-writing-hom010-styles')) return;
    var style = document.createElement('style');
    style.id = 'verbum-writing-hom010-styles';
    style.textContent = [
      '.verbum-writing-idea-list article{display:flex;flex-direction:column;gap:7px}',
      '.verbum-writing-idea-list article .verbum-writing-idea-insert-button,.verbum-writing-idea-list article .verbum-writing-idea-note-button{width:100%;text-align:left}',
      '.verbum-writing-idea-list article .verbum-writing-idea-insert-button{color:#0f7184;font-weight:700}',
      '.verbum-writing-inserted-idea{padding:12px 14px;border-left:3px solid #91c8cf;background:#f3fafb}',
      '.verbum-writing-uploaded-image{margin:20px auto;text-align:center}',
      '.verbum-writing-uploaded-image img,.verbum-writing-editor img{display:block;max-width:100%;height:auto;margin:0 auto;border-radius:8px}'
    ].join('');
    document.head.appendChild(style);
  }

  injectStyles();
  decorateIdeas(document);
  observer.observe(document.documentElement, { childList: true, subtree: true });
})();
