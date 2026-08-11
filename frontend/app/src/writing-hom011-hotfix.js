(function(){
'use strict';

var REQUIRED = [
  'Introdução desenvolvida',
  'Estrutura principal desenvolvida',
  'Conclusão desenvolvida',
  'Texto revisado pelo autor',
  'Texto pronto para revisão'
];
var RECOMMENDED = ['Tese contemplada', 'Meta de conteúdo analisada'];
var CONDITIONAL = ['Fontes selecionadas utilizadas', 'Citações verificadas'];
var SYSTEM = ['Redação concluída'];

function writingRoute(){
  var p=new URLSearchParams(location.search);
  return p.get('verbum_stage')==='development' && p.get('verbum_chapter') && (p.get('verbum_chapter_stage')||'writing')==='writing';
}
function text(el){return (el&&el.textContent||'').replace(/\s+/g,' ').trim();}
function labels(){return Array.prototype.slice.call(document.querySelectorAll('.verbum-writing-progress label'));}
function labelName(label){var span=label.querySelector('span');return text(span||label).replace(/Obrigatório|Recomendado|Condicional|Automático/g,'').trim();}
function kindFor(name){
  if(REQUIRED.indexOf(name)>=0)return'required';
  if(RECOMMENDED.indexOf(name)>=0)return'recommended';
  if(CONDITIONAL.indexOf(name)>=0)return'conditional';
  if(SYSTEM.indexOf(name)>=0)return'system';
  return'other';
}
function badgeFor(kind){return kind==='required'?'Obrigatório':kind==='recommended'?'Recomendado':kind==='conditional'?'Condicional':kind==='system'?'Automático':'';}
function checkboxFor(name){
  var found=labels().find(function(label){return labelName(label)===name;});
  return found?found.querySelector('input[type="checkbox"]'):null;
}
function missingRequired(){
  return REQUIRED.filter(function(name){var input=checkboxFor(name);return !input||!input.checked;});
}
function saved(){var state=document.querySelector('[data-writing-save-state]');return !state||text(state)==='Salvo';}
function addStyles(){
  if(document.getElementById('verbum-hom011-style'))return;
  var style=document.createElement('style');style.id='verbum-hom011-style';style.textContent='\
.verbum-writing-progress label[data-hom011-kind]{align-items:center;gap:7px;flex-wrap:wrap}\
.verbum-hom011-badge{margin-left:auto;border-radius:999px;padding:2px 7px;font-size:10px;line-height:1.4;font-weight:700;letter-spacing:.02em}\
.verbum-writing-progress label[data-hom011-kind="required"] .verbum-hom011-badge{background:#f8e8eb;color:#8f3047}\
.verbum-writing-progress label[data-hom011-kind="recommended"] .verbum-hom011-badge{background:#eef2f4;color:#65717b}\
.verbum-writing-progress label[data-hom011-kind="conditional"] .verbum-hom011-badge{background:#fff4df;color:#8a671f}\
.verbum-writing-progress label[data-hom011-kind="system"] .verbum-hom011-badge{background:#e7f5f0;color:#287158}\
.verbum-hom011-note{margin:10px 0 8px;padding:10px 11px;border-radius:9px;background:#f7f8f8;color:#59636c;font-size:12px;line-height:1.45}\
.verbum-hom011-note.is-ready{background:#eaf6f2;color:#286a55}\
.verbum-hom011-note strong{display:block;margin-bottom:3px;color:inherit}\
.verbum-hom011-progress-note{margin:7px 0 10px;color:#7b858d;font-size:11px;line-height:1.4}\
';document.head.appendChild(style);
}
function decorateChecklist(){
  labels().forEach(function(label){
    var name=labelName(label),kind=kindFor(name),badge=badgeFor(kind);
    if(kind==='other')return;
    label.dataset.hom011Kind=kind;
    var old=label.querySelector('.verbum-hom011-badge');
    if(!badge){if(old)old.remove();return;}
    if(!old){old=document.createElement('small');old.className='verbum-hom011-badge';label.appendChild(old);}
    old.textContent=badge;
    if(kind==='system'){
      var input=label.querySelector('input[type="checkbox"]');if(input)input.disabled=true;
    }
  });
}
function explain(panel,missing){
  var complete=panel.querySelector('[data-writing-complete]');if(!complete)return;
  var note=panel.querySelector('[data-hom011-note]');
  if(!note){note=document.createElement('p');note.className='verbum-hom011-note';note.dataset.hom011Note='1';complete.parentNode.insertBefore(note,complete);}
  note.classList.toggle('is-ready',missing.length===0);
  note.innerHTML=missing.length
    ? '<strong>Para seguir à Revisão</strong>Falta concluir: '+missing.join(', ')+'. Os itens recomendados e condicionais não bloqueiam a Redação.'
    : '<strong>Requisitos obrigatórios atendidos</strong>Você pode concluir a Redação. Os itens recomendados e condicionais podem permanecer pendentes quando não se aplicarem ao capítulo.';
  var progressNote=panel.querySelector('[data-hom011-progress-note]');
  if(!progressNote){progressNote=document.createElement('p');progressNote.className='verbum-hom011-progress-note';progressNote.dataset.hom011ProgressNote='1';var bar=panel.querySelector('.verbum-writing-progress-bar');if(bar)bar.insertAdjacentElement('afterend',progressNote);}
  if(progressNote)progressNote.textContent='O percentual acompanha também verificações editoriais recomendadas; não é necessário chegar a 100% para concluir a Redação.';
}
function patchInternal(){
  var panel=document.querySelector('.verbum-writing-progress');if(!panel)return;
  decorateChecklist();
  var missing=missingRequired();explain(panel,missing);
  var complete=panel.querySelector('[data-writing-complete]');
  if(complete&&!/concluída/i.test(text(complete))){
    var canFinish=missing.length===0&&saved();
    complete.disabled=!canFinish;
    complete.title=canFinish?'Concluir Redação e seguir para Revisão':'Conclua os itens obrigatórios indicados acima.';
  }
}
function patchFooter(){
  var footer=document.querySelector('.verbum-workspace-footer');if(!footer)return;
  var saveButton=footer.querySelector('.verbum-secondary-button');
  if(saveButton){
    saveButton.disabled=false;
    saveButton.title='Salvar a Redação sem concluir a etapa';
    if(!saveButton.dataset.hom011Bound){saveButton.dataset.hom011Bound='1';saveButton.addEventListener('click',function(e){if(!writingRoute())return;e.preventDefault();e.stopImmediatePropagation();var btn=document.querySelector('[data-writing-save]');if(btn)btn.click();},true);}
  }
  var continueButton=footer.querySelector('.verbum-primary-button');
  if(continueButton&&/salvar e continuar/i.test(text(continueButton))){
    var missing=missingRequired(),canContinue=missing.length===0&&saved();
    continueButton.disabled=!canContinue;
    continueButton.title=canContinue?'Salvar, concluir a Redação e seguir para Revisão':'Falta: '+missing.join(', ');
    if(!continueButton.dataset.hom011Bound){continueButton.dataset.hom011Bound='1';continueButton.addEventListener('click',function(e){if(!writingRoute())return;e.preventDefault();e.stopImmediatePropagation();var complete=document.querySelector('[data-writing-complete]');if(complete&&!complete.disabled)complete.click();},true);}
  }
}
function apply(){if(!writingRoute())return;addStyles();patchInternal();patchFooter();}

document.addEventListener('change',function(e){if(e.target&&e.target.matches&&e.target.matches('[data-writing-flag]'))setTimeout(apply,40);},true);
document.addEventListener('click',function(){setTimeout(apply,120);},true);
window.addEventListener('popstate',function(){setTimeout(apply,0);});
window.addEventListener('verbum:routechange',function(){setTimeout(apply,0);});
new MutationObserver(function(){setTimeout(apply,0);}).observe(document.body,{childList:true,subtree:true});
apply();
})();
