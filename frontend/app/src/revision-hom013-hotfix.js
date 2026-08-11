(function(){
'use strict';
if(window.__verbumRevisionHom013)return;
window.__verbumRevisionHom013=true;

var state={mode:'content',flags:{},book:null,chapter:null,root:null,loading:false,saving:false,scheduled:false};
var GROUPS={
  content:{label:'Conteúdo',help:'Objetivo, pergunta central, tese, argumentação, lacunas e alinhamento do conteúdo.',items:[
    ['objective_checked','Objetivo do capítulo conferido','core'],
    ['central_question_answered','Pergunta central respondida','core'],
    ['thesis_developed','Tese desenvolvida','core'],
    ['argumentation_coherent','Argumentação coerente','hom'],
    ['gaps_checked','Lacunas verificadas','hom'],
    ['content_aligned','Conteúdo alinhado à proposta do capítulo','hom']
  ]},
  structure:{label:'Estrutural',help:'Introdução, sequência lógica, tópicos, transições, equilíbrio e conclusão.',items:[
    ['introduction_adequate','Introdução adequada','hom'],
    ['sequence_logical','Sequência lógica conferida','hom'],
    ['transitions_checked','Transições verificadas','hom'],
    ['balance_checked','Equilíbrio entre as partes','hom'],
    ['conclusion_adequate','Conclusão adequada','hom'],
    ['structure_reviewed','Revisão estrutural concluída','core']
  ]},
  clarity:{label:'Clareza e Estilo',help:'Clareza, repetições, redundâncias, vocabulário, tom e fluidez.',items:[
    ['repetitions_checked','Repetições verificadas','hom'],
    ['redundancies_corrected','Redundâncias corrigidas','hom'],
    ['vocabulary_adequate','Vocabulário adequado','hom'],
    ['tone_style_coherent','Tom e estilo coerentes','hom'],
    ['fluency_reviewed','Fluidez do texto revisada','hom'],
    ['clarity_reviewed','Clareza e estilo concluídos','core']
  ]},
  language:{label:'Linguística',help:'Ortografia, gramática, concordância, pontuação e digitação.',items:[
    ['orthography_reviewed','Ortografia revisada','hom'],
    ['grammar_reviewed','Gramática revisada','hom'],
    ['agreement_reviewed','Concordância revisada','hom'],
    ['punctuation_reviewed','Pontuação revisada','hom'],
    ['typing_reviewed','Digitação revisada','hom'],
    ['language_reviewed','Revisão linguística concluída','core']
  ]},
  final:{label:'Validação Final',help:'Conferência final de fontes, citações, pendências e prontidão para concluir o capítulo.',items:[
    ['sources_verified','Fontes conferidas','auto'],
    ['citations_checked','Citações conferidas','core'],
    ['issues_resolved','Pendências resolvidas','auto'],
    ['ready_to_finish','Capítulo pronto para conclusão','core'],
    ['completed','Revisão concluída','auto']
  ]}
};
var GROUP_ORDER=['content','structure','clarity','language','final'];
var CORE_PREREQS={
  structure_reviewed:['introduction_adequate','sequence_logical','transitions_checked','balance_checked','conclusion_adequate'],
  clarity_reviewed:['repetitions_checked','redundancies_corrected','vocabulary_adequate','tone_style_coherent','fluency_reviewed'],
  language_reviewed:['orthography_reviewed','grammar_reviewed','agreement_reviewed','punctuation_reviewed','typing_reviewed']
};

function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];});}
function cfg(){return window.VerbumStudioConfig||{apiRoot:'/wp-json/verbum/v1',nonce:''};}
function route(){var p=new URLSearchParams(location.search);return{book:p.get('verbum_work'),chapter:p.get('verbum_chapter'),stage:p.get('verbum_stage')};}
function api(path,opt){opt=opt||{};var headers={'X-WP-Nonce':cfg().nonce||''};if(opt.body!==undefined)headers['Content-Type']='application/json';return fetch((cfg().apiRoot||'/wp-json/verbum/v1')+path,{method:opt.method||'GET',credentials:'same-origin',headers:headers,cache:'no-store',body:opt.body===undefined?undefined:JSON.stringify(opt.body)}).then(function(r){return r.json().then(function(p){if(!r.ok||!p||!p.success)throw new Error(p&&p.error&&p.error.message?p.error.message:'Não foi possível comunicar com a API.');return p.data;});});}
function revisionFrom(data){return data&&data.revision?data.revision:data||{};}
function coreInput(key){return state.root&&state.root.querySelector('.verbum-revision-checklist input[data-revision-flag="'+key+'"]');}
function itemValue(item){var key=item[0],kind=item[2];if(kind==='hom')return !!state.flags[key];var el=coreInput(key);return !!(el&&el.checked);}
function prerequisitesReady(key){var list=CORE_PREREQS[key]||[];return list.every(function(k){return !!state.flags[k];});}
function groupProgress(name){var items=GROUPS[name].items,done=items.filter(itemValue).length;return{done:done,total:items.length,pct:Math.round((done/Math.max(1,items.length))*100)};}
function overallProgress(){var items=[];GROUP_ORDER.forEach(function(name){GROUPS[name].items.forEach(function(i){if(i[0]!=='completed')items.push(i);});});var done=items.filter(itemValue).length;return{done:done,total:items.length,pct:Math.round((done/Math.max(1,items.length))*100)};}
function issueStatus(){var pending=0,resolved=0;if(!state.root)return{pending:0,resolved:0};state.root.querySelectorAll('.verbum-revision-issues article').forEach(function(a){if(a.classList.contains('is-resolved'))resolved++;else pending++;});return{pending:pending,resolved:resolved};}
function automaticDetail(key){var issues=issueStatus();if(key==='issues_resolved')return issues.pending===0?(issues.resolved?issues.resolved+' resolvida(s), nenhuma pendente':'Nenhuma pendência aberta'):issues.pending+' pendente(s)';if(key==='sources_verified'){var used=state.root?state.root.querySelectorAll('.verbum-revision-sources input[type="checkbox"]').length:0;return used?((itemValue(['sources_verified','', 'auto'])?'Todas conferidas':'Há fontes para conferir')):'Nenhuma fonte utilizada';}if(key==='completed')return itemValue(['completed','', 'auto'])?'Concluída':'Aguardando conclusão';return '';} 
function groupSummaryHtml(){return'<div class="verbum-hom013-summary">'+GROUP_ORDER.map(function(name){var p=groupProgress(name);return'<button type="button" data-hom013-jump="'+name+'" class="'+(state.mode===name?'is-active':'')+'"><span>'+esc(GROUPS[name].label)+'</span><strong>'+p.pct+'%</strong></button>';}).join('')+'</div>';}
function rowHtml(item){var key=item[0],label=item[1],kind=item[2],on=itemValue(item),disabled=false,detail='';if(kind==='auto'){disabled=true;detail=automaticDetail(key);}if(kind==='core'&&CORE_PREREQS[key]&&!prerequisitesReady(key)){disabled=true;detail='Conclua os critérios acima para liberar.';}return'<label class="verbum-hom013-item '+(on?'is-complete':'')+' '+(kind==='auto'?'is-automatic':'')+'"><input type="checkbox" '+(kind==='hom'?'data-hom013-flag="'+key+'"':'data-hom013-core="'+key+'"')+' '+(on?'checked':'')+' '+(disabled?'disabled':'')+'><span><b>'+esc(label)+'</b>'+(detail?'<small>'+esc(detail)+'</small>':'')+'</span>'+(kind==='auto'?'<em>Automático</em>':'')+'</label>';}
function panelHtml(){var overall=overallProgress(),group=GROUPS[state.mode],gp=groupProgress(state.mode);return'<div class="verbum-hom013-panel" data-hom013-panel><div class="verbum-hom013-overall"><div><span>Revisão geral do capítulo</span><strong>'+overall.pct+'%</strong></div><div class="verbum-hom013-progress"><i style="width:'+overall.pct+'%"></i></div><small>'+overall.done+' de '+overall.total+' critérios concluídos</small></div>'+groupSummaryHtml()+'<div class="verbum-hom013-current"><div class="verbum-hom013-current-head"><div><span>Checklist atual</span><h4>'+esc(group.label)+'</h4></div><strong>'+gp.pct+'%</strong></div><p>'+esc(group.help)+'</p><div class="verbum-hom013-progress"><i style="width:'+gp.pct+'%"></i></div><div class="verbum-hom013-items">'+group.items.map(rowHtml).join('')+'</div></div>'+(state.mode==='final'?'<div class="verbum-hom013-final-note">A validação final reúne critérios gerais. Itens automáticos mudam conforme fontes, pendências e conclusão da Revisão.</div>':'')+'</div>';}
function modeHelp(){var p=state.root&&state.root.querySelector('.verbum-revision-mode-help');if(p)p.textContent=GROUPS[state.mode].help;}
function ensureTabs(){if(!state.root)return;var modes=state.root.querySelector('.verbum-revision-modes');if(!modes)return;var final=modes.querySelector('[data-hom013-mode="final"]');if(!final){final=document.createElement('button');final.type='button';final.dataset.hom013Mode='final';final.textContent='Validação Final';modes.appendChild(final);}modes.querySelectorAll('button').forEach(function(b){var name=b.dataset.hom013Mode||b.dataset.revisionMode||'';b.classList.toggle('is-active',name===state.mode);});}
function signature(){var core={};if(state.root)state.root.querySelectorAll('.verbum-revision-checklist input[data-revision-flag]').forEach(function(i){core[i.dataset.revisionFlag]=!!i.checked;});return JSON.stringify({mode:state.mode,flags:state.flags,core:core,issues:issueStatus()});}
function decorate(){state.scheduled=false;var r=route();if(r.stage!=='development'||!r.book||!r.chapter)return;var root=document.querySelector('.verbum-chapter-revision-host');if(!root)return;state.root=root;ensureTabs();modeHelp();var box=root.querySelector('.verbum-revision-checklist');if(!box)return;box.classList.add('verbum-hom013-checklist');box.classList.toggle('is-final',state.mode==='final');var sig=signature(),panel=box.querySelector('[data-hom013-panel]');if(panel&&panel.dataset.signature===sig)return;var holder=document.createElement('div');holder.innerHTML=panelHtml();var next=holder.firstElementChild;next.dataset.signature=sig;if(panel)panel.replaceWith(next);else{var btn=box.querySelector('[data-revision-complete]');box.insertBefore(next,btn||null);}bindPanel(next);}
function scheduleDecorate(){if(state.scheduled)return;state.scheduled=true;setTimeout(decorate,40);}
function saveHomFlag(key,on){if(state.saving)return;state.saving=true;state.flags[key]=on;scheduleDecorate();api('/books/'+state.book+'/chapters/'+state.chapter+'/revision',{method:'PATCH',body:{hom013_flags:state.flags,save_mode:'autosave'}}).then(function(data){var rev=revisionFrom(data);state.flags=Object.assign({},rev.hom013Flags||state.flags);state.saving=false;scheduleDecorate();}).catch(function(){state.flags[key]=!on;state.saving=false;scheduleDecorate();});}
function bindPanel(panel){panel.querySelectorAll('[data-hom013-jump]').forEach(function(b){b.onclick=function(){state.mode=b.dataset.hom013Jump;ensureTabs();modeHelp();scheduleDecorate();};});panel.querySelectorAll('[data-hom013-flag]').forEach(function(c){c.onchange=function(){saveHomFlag(c.dataset.hom013Flag,c.checked);};});panel.querySelectorAll('[data-hom013-core]').forEach(function(c){c.onchange=function(){var original=coreInput(c.dataset.hom013Core);if(!original||original.disabled){scheduleDecorate();return;}original.checked=c.checked;original.dispatchEvent(new Event('change',{bubbles:true}));scheduleDecorate();};});}
function loadState(){var r=route();if(r.stage!=='development'||!r.book||!r.chapter)return;state.book=r.book;state.chapter=r.chapter;state.loading=true;api('/books/'+r.book+'/chapters/'+r.chapter+'/revision?_hom013='+Date.now()).then(function(data){var rev=revisionFrom(data);state.flags=Object.assign({},rev.hom013Flags||{});state.loading=false;scheduleDecorate();}).catch(function(){state.loading=false;scheduleDecorate();});}

document.addEventListener('click',function(e){var b=e.target&&e.target.closest?e.target.closest('[data-revision-mode],[data-hom013-mode]'):null;if(!b)return;var name=b.dataset.hom013Mode||b.dataset.revisionMode;if(!GROUPS[name])return;state.mode=name;setTimeout(scheduleDecorate,0);},true);
new MutationObserver(scheduleDecorate).observe(document.body,{childList:true,subtree:true});
window.addEventListener('popstate',function(){setTimeout(loadState,50);});
window.addEventListener('verbum:routechange',function(){setTimeout(loadState,50);});
setTimeout(loadState,80);
})();
