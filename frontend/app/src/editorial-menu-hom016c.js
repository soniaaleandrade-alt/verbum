(function(){
'use strict';
if(window.__verbumHom016CMenuFix)return;
window.__verbumHom016CMenuFix=true;

function closeMenus(){
  document.querySelectorAll('[data-hom016-menu-pop]').forEach(function(pop){pop.hidden=true;});
}

function ensureHiddenRule(){
  if(document.getElementById('verbum-hom016c-menu-style'))return;
  var style=document.createElement('style');
  style.id='verbum-hom016c-menu-style';
  style.textContent='.verbum-editorial-menu-pop[hidden]{display:none!important}';
  document.head.appendChild(style);
}

ensureHiddenRule();

document.addEventListener('click',function(event){
  var target=event.target;
  if(!(target instanceof Element))return;
  if(target.closest('[data-hom016-menu]'))return;

  var pop=target.closest('[data-hom016-menu-pop]');
  if(pop){
    if(target.closest('[data-hom016-save-now],[data-hom016-back-work]')){
      setTimeout(closeMenus,0);
    }
    return;
  }
  closeMenus();
});

document.addEventListener('keydown',function(event){
  if(event.key==='Escape')closeMenus();
});

window.addEventListener('verbum:routechange',closeMenus);
window.addEventListener('popstate',closeMenus);
})();
