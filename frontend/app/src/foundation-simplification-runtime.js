(function(){
'use strict';
var scheduled=false;
function routeIsFoundation(){var p=new URLSearchParams(location.search);return !!p.get('verbum_work')&&(p.get('verbum_stage')||'identification')==='project';}
function field(form,name){var input=form.querySelector('[name="'+name+'"]');return input&&input.closest('.verbum-foundation-field');}
function renameField(node,label){if(!node)return;var span=node.querySelector(':scope > span');if(!span)return;var first=span.firstChild;if(first&&first.nodeType===Node.TEXT_NODE)first.nodeValue=label+' ';}
function hide(node){if(node)node.hidden=true;}
function setSection(card,index,title,description){if(!card)return;var head=card.querySelector('.verbum-foundation-section-head');if(!head)return;var number=head.querySelector(':scope > span');var h=head.querySelector('h3');var p=head.querySelector('p');if(number)number.textContent=String(index).padStart(2,'0');if(h)h.textContent=title;if(p)p.textContent=description;}
function simplify(){
  scheduled=false;
  if(!routeIsFoundation())return;
  var stage=document.querySelector('.verbum-foundation-stage');
  var form=stage&&stage.querySelector('[data-project-form]');
  if(!form||form.dataset.foundationSimplified==='1')return;
  var cards=Array.from(form.children).filter(function(x){return x.matches('.verbum-project-card');});
  var essence=cards.find(function(c){return c.querySelector('[name="theme"]');});
  var objectives=cards.find(function(c){return c.querySelector('[name="generalObjective"]');});
  var reader=cards.find(function(c){return c.querySelector('[name="audienceMain"]');});
  var method=cards.find(function(c){return c.querySelector('[name="differentials"]');});
  var inspiration=form.querySelector('.verbum-foundation-inspiration');
  if(!essence||!objectives||!reader||!method||!inspiration)return;

  form.dataset.foundationSimplified='1';

  var heading=stage.querySelector('.verbum-project-stage-heading p');
  if(heading)heading.textContent='Comece pelo que move você a escrever e, em seguida, defina a essência, o leitor e a transformação que esta obra pretende provocar.';

  var motivation=field(form,'motivation');
  var guiding=field(form,'guidingPhrase');
  var verse=field(form,'verse');
  renameField(motivation,'Motivação para escrever a obra');
  if(motivation)inspiration.appendChild(motivation);
  if(guiding)inspiration.appendChild(guiding);
  if(verse)inspiration.appendChild(verse);
  setSection(inspiration,1,'Inspiração do Autor','Comece pelo motivo que fez esta obra nascer e pelas referências que ajudam a manter sua direção.');
  form.insertBefore(inspiration,essence);

  var question=field(form,'centralQuestion');
  var objective=field(form,'generalObjective');
  renameField(question,'Pergunta central');
  renameField(objective,'Objetivo da obra');
  if(objective)essence.appendChild(objective);
  setSection(essence,2,'Essência da Obra','Defina o núcleo intelectual que sustentará a obra e orientará as decisões das próximas etapas.');
  hide(field(form,'overview'));
  essence.querySelectorAll('.verbum-foundation-legacy').forEach(hide);

  renameField(field(form,'audienceMain'),'Público-alvo');
  var differential=field(form,'differentials');
  if(differential)reader.appendChild(differential);
  setSection(reader,3,'Leitor e Transformação','Defina para quem a obra é escrita, a transformação esperada e aquilo que a torna singular.');
  hide(field(form,'audience'));
  hide(field(form,'secondaryAudience'));
  hide(field(form,'readerNeed'));
  reader.querySelectorAll('.verbum-foundation-legacy').forEach(hide);

  hide(objectives);
  hide(method);

  var progress=stage.querySelector('.verbum-project-progress');
  if(progress){
    var extra=progress.querySelector('.verbum-foundation-progress-extra');
    if(extra)extra.hidden=true;
    if(!progress.querySelector('.verbum-foundation-scope-note')){
      var note=document.createElement('div');
      note.className='verbum-foundation-scope-note';
      note.innerHTML='<strong>Critérios desta etapa</strong><span>Tema, propósito, pergunta central, tese, objetivo da obra e público-alvo definem a conclusão.</span><small>Transformação e diferencial são recomendados. Motivação, frase norteadora e versículo não bloqueiam o avanço.</small>';
      progress.appendChild(note);
    }
  }
}
function schedule(){if(scheduled)return;scheduled=true;if(typeof requestAnimationFrame==='function')requestAnimationFrame(simplify);else setTimeout(simplify,0);}
function ensureStyle(){if(document.getElementById('verbum-foundation-simplification-style'))return;var s=document.createElement('style');s.id='verbum-foundation-simplification-style';s.textContent='.verbum-foundation-stage [hidden]{display:none!important}.verbum-foundation-scope-note{margin-top:16px;padding-top:14px;border-top:1px solid #eceef4;display:grid;gap:5px;color:#596273}.verbum-foundation-scope-note strong{font-size:12px;color:#17213a}.verbum-foundation-scope-note span{font-size:11px;line-height:1.45}.verbum-foundation-scope-note small{font-size:10px;line-height:1.45;color:#7b8496}.verbum-foundation-inspiration{order:0}.verbum-foundation-stage .verbum-project-stage-form{align-content:start}';document.head.appendChild(s);}
function boot(){ensureStyle();schedule();new MutationObserver(schedule).observe(document.body,{childList:true,subtree:true});window.addEventListener('verbum:routechange',schedule);window.addEventListener('popstate',schedule);}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot);else boot();
})();
