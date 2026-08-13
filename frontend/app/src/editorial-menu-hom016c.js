(function(){
'use strict';
if(window.__verbumHom016CMenuFix)return;
window.__verbumHom016CMenuFix=true;

function text(node){return(node&&node.textContent||'').replace(/\s+/g,' ').trim();}
function writingRoute(){
  var p=new URLSearchParams(location.search);
  return p.get('verbum_stage')==='development'&&!!p.get('verbum_chapter')&&(p.get('verbum_chapter_stage')||'writing')==='writing';
}
function closeMenus(){
  document.querySelectorAll('[data-hom016-menu-pop]').forEach(function(pop){pop.hidden=true;});
}
function hiddenWritingComplete(){return document.querySelector('[data-writing-complete]');}
function writingSaveState(){return document.querySelector('[data-writing-save-state]');}
function isSaving(){return /salvando|aguardando/i.test(text(writingSaveState()));}

function ensureStyles(){
  if(document.getElementById('verbum-hom016c-menu-style'))return;
  var style=document.createElement('style');
  style.id='verbum-hom016c-menu-style';
  style.textContent='\
.verbum-editorial-menu-pop[hidden]{display:none!important}\
body.verbum-hom016-active .verbum-writing-shell.verbum-hom016e-writing .verbum-writing-toolbar{flex-wrap:nowrap!important;align-content:center!important;height:46px!important;min-height:46px!important;max-height:46px!important;padding:4px 10px!important;gap:2px!important;overflow:hidden!important}\
body.verbum-hom016-active .verbum-writing-shell.verbum-hom016e-writing .verbum-writing-toolbar select{height:28px!important;min-width:62px!important;max-width:96px!important;padding:0 20px 0 6px!important;font-size:10px!important}\
body.verbum-hom016-active .verbum-writing-shell.verbum-hom016e-writing .verbum-writing-toolbar button{height:28px!important;min-width:25px!important;padding:0 6px!important;font-size:10px!important}\
.verbum-hom016f-toast{position:fixed;left:50%;top:112px;z-index:10000;max-width:min(520px,calc(100vw - 32px));transform:translateX(-50%);padding:10px 14px;border:1px solid #d9dff5;border-radius:8px;background:#f7f8ff;color:#3f4d7d;font:600 12px/1.45 Inter,system-ui,sans-serif;box-shadow:0 8px 24px rgba(28,35,57,.12)}\
';
  document.head.appendChild(style);
}
function toast(message){
  var old=document.querySelector('[data-hom016f-toast]');if(old)old.remove();
  var node=document.createElement('div');node.className='verbum-hom016f-toast';node.dataset.hom016fToast='1';node.textContent=message;document.body.appendChild(node);
  setTimeout(function(){if(node.parentNode)node.remove();},4200);
}
function showRequirements(){
  var stats=document.querySelector('[data-hom016-right-tab="stats"]');if(stats)stats.click();
  setTimeout(function(){var panel=document.querySelector('.verbum-writing-progress');if(panel)panel.scrollIntoView({behavior:'smooth',block:'start'});},80);
  toast('Antes de concluir a Redação, confira os requisitos obrigatórios na aba Estatísticas.');
}
function waitAndComplete(attempt){
  if(!writingRoute())return;
  var complete=hiddenWritingComplete();
  if(!complete){toast('Não foi possível localizar o controle de conclusão da Redação.');return;}
  if(isSaving()){
    if(attempt>=40){toast('O salvamento ainda está em andamento. Aguarde alguns segundos e tente novamente.');return;}
    setTimeout(function(){waitAndComplete(attempt+1);},200);
    return;
  }
  if(complete.disabled){showRequirements();return;}
  complete.click();
}
function keepTopCompleteInteractive(){
  if(!writingRoute())return;
  var top=document.querySelector('[data-hom016-complete]'),internal=hiddenWritingComplete();
  if(!top||!internal)return;
  var completed=/concluída/i.test(text(internal));
  if(completed){top.disabled=true;top.title='Redação concluída';return;}
  top.disabled=false;
  top.title=internal.disabled?'Clique para ver o que falta para concluir a Redação':'Concluir Redação e seguir para Revisão';
}

ensureStyles();

/* Intercepta antes do handler editorial para evitar a corrida entre autosave e conclusão. */
window.addEventListener('click',function(event){
  if(!writingRoute())return;
  var target=event.target;
  if(!(target instanceof Element))return;
  var complete=target.closest('[data-hom016-complete]');
  if(!complete)return;
  event.preventDefault();
  event.stopPropagation();
  waitAndComplete(0);
},true);

document.addEventListener('click',function(event){
  var target=event.target;
  if(!(target instanceof Element))return;
  if(target.closest('[data-hom016-menu]'))return;

  var pop=target.closest('[data-hom016-menu-pop]');
  if(pop){
    if(target.closest('[data-hom016-save-now],[data-hom016-back-work]'))setTimeout(closeMenus,0);
    return;
  }
  closeMenus();
});

document.addEventListener('keydown',function(event){if(event.key==='Escape')closeMenus();});
window.addEventListener('verbum:routechange',function(){closeMenus();setTimeout(keepTopCompleteInteractive,120);});
window.addEventListener('popstate',function(){closeMenus();setTimeout(keepTopCompleteInteractive,120);});
new MutationObserver(function(){keepTopCompleteInteractive();}).observe(document.body,{childList:true,subtree:true,characterData:true});
setInterval(keepTopCompleteInteractive,500);
keepTopCompleteInteractive();
})();
