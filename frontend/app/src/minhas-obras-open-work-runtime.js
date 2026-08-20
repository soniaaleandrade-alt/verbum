(function(){
'use strict';
var roots=document.querySelectorAll('[data-verbum-app]');if(!roots.length)return;
var WORK_STAGES=['identification','project','planning','development','general_review','versions','audit','editorial_desk','layout','legal','publication'];
function books(root){return root.__vs&&root.__vs.library&&Array.isArray(root.__vs.library.books)?root.__vs.library.books:[];}
function findBook(root,id){return books(root).find(function(b){return String(b.id)===String(id);})||null;}
function stageFor(book){if(!book)return'identification';var nextAction=String(book.nextAction||'').toLocaleLowerCase('pt-BR');var official=String(book.officialStageKey||'').toLowerCase();if(official==='identification'||nextAction.indexOf('identifica')>=0)return'identification';var current=String(book.stage||'');return WORK_STAGES.indexOf(current)>=0?current:'identification';}
function openBook(root,id){var book=findBook(root,id);var stage=stageFor(book);var url=new URL(location.href);url.searchParams.set('verbum_work',String(id));url.searchParams.set('verbum_stage',stage);url.searchParams.delete('verbum_chapter');history.pushState({},'',url);try{window.dispatchEvent(new PopStateEvent('popstate'));}catch(e){window.dispatchEvent(new Event('popstate'));}}
function onClick(event){var target=event.target.closest('[data-open-work]');if(!target)return;var root=target.closest('[data-verbum-app]');if(!root||!target.closest('.verbum-minhas-obras'))return;var id=target.getAttribute('data-open-work');if(!id)return;event.preventDefault();event.stopPropagation();if(typeof event.stopImmediatePropagation==='function')event.stopImmediatePropagation();openBook(root,id);}
document.addEventListener('click',onClick,true);
})();
