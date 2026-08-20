(function(){
'use strict';
var roots=document.querySelectorAll('[data-verbum-app]');if(!roots.length)return;
var scheduled=false;
function books(root){return root.__vs&&root.__vs.library&&Array.isArray(root.__vs.library.books)?root.__vs.library.books:[];}
function book(root,id){return books(root).find(function(b){return String(b.id)===String(id);})||null;}
function needsIdentification(b){if(!b)return false;var key=String(b.officialStageKey||b.stage||'').toLowerCase();var action=String(b.nextAction||'').toLocaleLowerCase('pt-BR');return key==='identification'||action.indexOf('identifica')>=0;}
function openIdentification(root,id){var u=new URL(location.href);u.searchParams.set('verbum_work',String(id));u.searchParams.set('verbum_stage','identification');u.searchParams.delete('verbum_chapter');history.pushState({},'',u);try{window.dispatchEvent(new PopStateEvent('popstate'));}catch(e){window.dispatchEvent(new Event('popstate'));}}
function normalizeLibrary(root){var page=root.querySelector('.verbum-minhas-obras');if(!page)return;
  var review=page.querySelector('.mo-quick [data-mo-stage="general_review"]');if(review){var node=Array.from(review.childNodes).find(function(n){return n.nodeType===3;});if(node)node.nodeValue='Revisão ';else review.insertAdjacentText('afterbegin','Revisão ');}
  page.querySelectorAll('.mo-path').forEach(function(path){var items=path.querySelectorAll('li');if(items[4]){var label=items[4].querySelector('b');if(label)label.textContent='Revisão';}if(items[0])items[0].style.removeProperty('display');if(items[5])items[5].setAttribute('aria-hidden','true');});
}
function run(){scheduled=false;roots.forEach(normalizeLibrary);}
function schedule(){if(scheduled)return;scheduled=true;(window.requestAnimationFrame||function(cb){return setTimeout(cb,16);})(run);}
roots.forEach(function(root){root.addEventListener('click',function(ev){var target=ev.target.closest('[data-open-work]');if(!target||!target.closest('.verbum-minhas-obras'))return;var id=target.getAttribute('data-open-work');var b=book(root,id);if(!needsIdentification(b))return;ev.preventDefault();ev.stopPropagation();if(typeof ev.stopImmediatePropagation==='function')ev.stopImmediatePropagation();openIdentification(root,id);},true);});
function boot(){run();new MutationObserver(schedule).observe(document.body,{childList:true,subtree:true});window.addEventListener('verbum:library-updated',schedule);window.addEventListener('popstate',schedule);}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot);else boot();
})();
