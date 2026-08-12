(function(){
'use strict';
if(window.__verbumHom016BReferenceLayout)return;
window.__verbumHom016BReferenceLayout=true;

function brandHtml(){
  return '<div class="verbum-editorial-brand" data-hom016b-brand aria-label="Verbum Studio">'+
    '<svg viewBox="0 0 48 48" aria-hidden="true" fill="none">'+
      '<path d="M39.5 5.5C30.7 6.3 21.1 11.2 15.7 18.2c-4.8 6.2-6.8 13-7.4 19.8 4.1-5.6 8.1-9.8 12.2-13.2 5.8-4.8 11.4-8.5 18.2-12.2-4.8 4.4-9.8 8.9-14.8 14-3.9 4-7.5 8-10.6 12.1 6.2-1 12.3-3.6 17.3-8 7.1-6.2 9.5-15 8.9-25.2Z" fill="currentColor"/>'+
      '<path d="M7 41c8.5-10.2 17.5-18.1 29.2-25.2" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>'+
    '</svg>'+
    '<span class="verbum-editorial-brand-copy"><strong>VERBUM</strong><small>STUDIO</small></span>'+
  '</div>';
}

function ensureBrand(){
  var header=document.querySelector('.verbum-editorial-header');
  if(!header||header.querySelector('[data-hom016b-brand]'))return;
  header.insertAdjacentHTML('afterbegin',brandHtml());
}

function decoratePaper(){
  document.querySelectorAll('.verbum-writing-paper,.verbum-revision-paper').forEach(function(paper){
    paper.dataset.hom016bBookPage='1';
    paper.setAttribute('aria-label','Página do livro em edição');
  });
  document.querySelectorAll('.verbum-writing-editor,.verbum-revision-editor').forEach(function(editor){
    editor.setAttribute('spellcheck','true');
    editor.setAttribute('autocapitalize','sentences');
  });
}

function toolbarFor(type){
  return document.querySelector(type==='writing'?'.verbum-writing-toolbar':'.verbum-revision-toolbar');
}
function classifyType(){
  if(document.querySelector('.verbum-writing-shell'))return'writing';
  if(document.querySelector('.verbum-revision-shell'))return'revision';
  return'';
}
function separator(){var s=document.createElement('span');s.className='verbum-editorial-toolbar-separator';s.setAttribute('aria-hidden','true');return s;}
function ensureToolbarReference(){
  var type=classifyType(),bar=toolbarFor(type);if(!bar||bar.dataset.hom016bToolbar==='1')return;
  bar.dataset.hom016bToolbar='1';

  /* No editor de Redação, Título/Subtítulo já existem no seletor Parágrafo. */
  bar.querySelectorAll('button[data-cmd="formatBlock"][data-value="h2"],button[data-cmd="formatBlock"][data-value="h3"]').forEach(function(b){b.hidden=true;});
  if(type==='writing'){
    var comment=bar.querySelector('button[data-writing-comment]');
    var note=bar.querySelector('button[data-writing-note]');
    if(comment)comment.hidden=true;
    if(note)note.hidden=true;
  }

  var undo=bar.querySelector('button[data-cmd="undo"]'),redo=bar.querySelector('button[data-cmd="redo"]');
  var format=bar.querySelector('select[data-hom016-format]'),font=bar.querySelector('select[data-hom016-font]'),size=bar.querySelector('select[data-hom016-size]');
  var first=bar.firstChild;
  if(undo)bar.insertBefore(undo,first);
  if(redo)bar.insertBefore(redo,undo?undo.nextSibling:first);
  var anchor=redo||undo;
  if(anchor){var sep1=separator();bar.insertBefore(sep1,anchor.nextSibling);anchor=sep1;}
  [format,font,size].forEach(function(node){if(node){bar.insertBefore(node,anchor?anchor.nextSibling:bar.firstChild);anchor=node;}});
  if(anchor){var sep2=separator();bar.insertBefore(sep2,anchor.nextSibling);}

  var more=document.createElement('button');
  more.type='button';more.className='verbum-editorial-toolbar-more';more.setAttribute('aria-label','Mais opções');more.textContent='•••';
  bar.appendChild(more);
}

function improveOutline(){
  document.querySelectorAll('.verbum-editorial-outline nav button').forEach(function(button){
    button.title=(button.querySelector('strong')&&button.querySelector('strong').textContent||'').trim();
  });
}

function focusBookWhenBlank(){
  document.querySelectorAll('.verbum-writing-paper[data-hom016b-book-page],.verbum-revision-paper[data-hom016b-book-page]').forEach(function(paper){
    if(paper.dataset.hom016bFocusBound==='1')return;
    paper.dataset.hom016bFocusBound='1';
    paper.addEventListener('click',function(event){
      if(event.target!==paper)return;
      var editors=Array.from(paper.querySelectorAll('.verbum-writing-editor,.verbum-revision-editor'));
      var target=editors.find(function(e){return !(e.textContent||'').trim();})||editors[editors.length-1];
      if(target)target.focus();
    });
  });
}

function decorate(){
  if(!document.body.classList.contains('verbum-hom016-active'))return;
  document.body.classList.add('verbum-hom016b-reference');
  ensureBrand();
  decoratePaper();
  ensureToolbarReference();
  improveOutline();
  focusBookWhenBlank();
}

var scheduled=false;
function schedule(){if(scheduled)return;scheduled=true;requestAnimationFrame(function(){scheduled=false;decorate();});}
new MutationObserver(schedule).observe(document.documentElement,{subtree:true,childList:true,attributes:true,attributeFilter:['class']});
window.addEventListener('verbum:routechange',schedule);
window.addEventListener('popstate',schedule);
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',schedule);else schedule();
})();
