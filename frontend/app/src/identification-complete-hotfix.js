(function(){
'use strict';
var root=document.querySelector('[data-verbum-app]');if(!root)return;
var scheduled=false;
var keywordDraft='';
var keywordBookId='';
var selectOptions={
  genre:{placeholder:'Selecione o gênero',items:['Não ficção','Ficção','Romance','Conto','Crônica','Poesia','Biografia','Autobiografia','Memórias','Ensaio','Acadêmico','Religioso/Espiritual','Infantil','Juvenil','Técnico','Outro']},
  approach:{placeholder:'Selecione a abordagem',items:['Prática','Teórica','Formativa','Inspiracional','Reflexiva','Acadêmica','Técnica','Narrativa','Devocional','Pastoral','Outra']},
  audience:{placeholder:'Selecione o público',items:['Público geral','Leigos','Profissionais','Estudantes','Acadêmicos','Especialistas','Educadores','Jovens','Adultos','Famílias','Clero','Religiosos','Outro']},
  language_tone:{placeholder:'Selecione a linguagem e o tom',items:['Formal e técnica','Formal e acessível','Didática','Informal e acessível','Conversacional','Reflexiva','Inspiradora','Acadêmica','Pastoral','Devocional','Literária','Outra']},
  intended_format:{placeholder:'Selecione o formato',items:['Impresso','Digital (e-book)','Impresso e digital']},
  estimated_extent:{placeholder:'Selecione a extensão',items:['Até 15 mil palavras','15 a 30 mil palavras','30 a 60 mil palavras','60 a 90 mil palavras','90 mil palavras ou mais']},
  workflow_status:{placeholder:'Selecione o status',items:['Ideia inicial','Planejamento','Em desenvolvimento','Em redação','Em revisão','Em preparação editorial','Concluída']}
};
var errorMessages={
  title:'Informe o título provisório da obra.',
  author_name:'Informe o nome da autoria.',
  theme:'Informe o tema central da obra.',
  genre:'Selecione o gênero da obra.',
  approach:'Selecione a abordagem da obra.',
  audience:'Selecione o público principal.',
  language_tone:'Selecione a linguagem e o tom.',
  intended_format:'Selecione o formato pretendido.',
  estimated_extent:'Selecione a extensão estimada.',
  workflow_status:'Selecione o status da obra.'
};
function active(){var p=new URLSearchParams(location.search);return!!p.get('verbum_work')&&(p.get('verbum_stage')||'identification')==='identification';}
function bookId(){return new URLSearchParams(location.search).get('verbum_work')||'';}
function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];});}
function normalizeKeywords(text){var seen={};return String(text||'').split(',').map(function(item){return item.trim().replace(/\s+/g,' ');}).filter(function(item){if(!item)return false;var key=item.toLocaleLowerCase('pt-BR');if(seen[key])return false;seen[key]=true;return true;});}
function patchIdentificationRequest(input,init){
  if(!active()||!init||String(init.method||'GET').toUpperCase()!=='PATCH'||typeof init.body!=='string')return{input:input,init:init};
  var raw=typeof input==='string'?input:(input&&input.url?input.url:'');
  if(!/\/books\/\d+\/identification(?:-initial)?(?=\?|$)/.test(raw))return{input:input,init:init};
  try{
    var body=JSON.parse(init.body);
    if(keywordBookId===bookId())body.keywords=normalizeKeywords(keywordDraft);
    return{input:input,init:Object.assign({},init,{body:JSON.stringify(body)})};
  }catch(e){return{input:input,init:init};}
}
var previousFetch=window.fetch;
window.fetch=function(input,init){var patched=patchIdentificationRequest(input,init);return previousFetch.call(this,patched.input,patched.init);};
function renderedKeywords(field){return Array.prototype.map.call(field.querySelectorAll(':scope > span'),function(span){var node=span.firstChild;return node&&node.nodeType===3?node.textContent.trim():span.textContent.replace('×','').trim();}).filter(Boolean);}
function patchKeywords(page){
  var field=page.querySelector('.verbum-id-tags');if(!field)return;
  var input=field.querySelector('[data-keyword-input]');if(!input)return;
  var id=bookId();
  if(keywordBookId!==id){keywordBookId=id;keywordDraft=renderedKeywords(field).join(', ');}
  field.classList.add('is-reference-keywords');
  field.querySelectorAll(':scope > span').forEach(function(span){span.hidden=true;});
  if(input.value!==keywordDraft)input.value=keywordDraft;
  input.placeholder='Digite palavras-chave separadas por vírgula';
  input.setAttribute('aria-label','Palavras-chave');
  input.onkeydown=function(event){if(event.key==='Enter')event.preventDefault();};
  input.onblur=function(){};
  input.oninput=function(){keywordDraft=input.value;};
}
function patchSelect(select,config){
  if(!select||select.dataset.referenceOptions==='1')return;
  var current=select.value;
  var items=config.items.slice();
  if(current&&items.indexOf(current)<0)items.unshift(current);
  select.innerHTML='<option value="">'+esc(config.placeholder)+'</option>'+items.map(function(item){return'<option value="'+esc(item)+'"'+(item===current?' selected':'')+'>'+esc(item)+'</option>';}).join('');
  select.dataset.referenceOptions='1';
}
function patchStatus(){
  var status=root.querySelector('.verbum-id-status');if(!status)return;
  var workspace=root.__vs&&root.__vs.workspace;
  var completed=!!(workspace&&workspace.identification&&workspace.identification.completed);
  var label=completed?'Concluída':'Rascunho';
  if(status.textContent.trim()!==label)status.innerHTML='<i></i>'+label;
}
function patchErrors(page){Object.keys(errorMessages).forEach(function(key){var error=page.querySelector('#id-error-'+key);if(error&&error.textContent!==errorMessages[key])error.textContent=errorMessages[key];});}
function patch(){
  scheduled=false;
  if(!active())return;
  var page=root.querySelector('.verbum-identification-initial');if(!page)return;
  var legacyPanel=page.querySelector('.verbum-id-complete-panel');if(legacyPanel)legacyPanel.remove();
  page.querySelectorAll('.verbum-id-grid label>span b').forEach(function(mark){mark.remove();});
  Object.keys(selectOptions).forEach(function(name){patchSelect(page.querySelector('select[name="'+name+'"]'),selectOptions[name]);});
  patchKeywords(page);
  patchErrors(page);
  patchStatus();
  var message=page.querySelector('.verbum-id-form-message');
  if(message&&message.textContent.trim()==='Rascunho salvo')message.textContent='Rascunho salvo com sucesso.';
  var button=root.querySelector('.verbum-identification-initial-footer [data-save-continue]');
  if(button){var span=button.querySelector('span');if(span&&span.textContent!=='Salvando...'&&span.textContent!=='Salvar e continuar')span.textContent='Salvar e continuar';button.setAttribute('aria-label','Salvar a Identificação e continuar para Fundação');}
}
function schedule(){if(scheduled)return;scheduled=true;(window.requestAnimationFrame||function(cb){return setTimeout(cb,16);})(patch);}
window.addEventListener('verbum:routechange',function(){keywordBookId='';keywordDraft='';schedule();});
window.addEventListener('popstate',function(){keywordBookId='';keywordDraft='';schedule();});
new MutationObserver(schedule).observe(document.body,{childList:true,subtree:true});
schedule();
})();
