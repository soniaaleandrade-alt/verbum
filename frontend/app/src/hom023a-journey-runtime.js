(function(){
'use strict';
var roots=document.querySelectorAll('[data-verbum-app]');if(!roots.length)return;
var groups=[
  {key:'identification',label:'Identificação',legacy:['identification'],route:'identification'},
  {key:'foundation',label:'Fundação',legacy:['project'],route:'project'},
  {key:'structure',label:'Estrutura',legacy:['planning'],route:'planning'},
  {key:'chapters',label:'Capítulos',legacy:['development'],route:'development'},
  {key:'review',label:'Revisão',legacy:['general_review','versions','audit'],route:'general_review'},
  {key:'preparation',label:'Preparação Editorial',legacy:['editorial_desk','layout','legal'],route:'editorial_desk'},
  {key:'publication',label:'Publicação',legacy:['publication'],route:'publication'}
];
function currentLegacy(){var p=new URLSearchParams(location.search);return p.get('verbum_stage')||'identification';}
function visualForLegacy(stage){var found=groups.find(function(g){return g.legacy.indexOf(stage)>=0;});return found||groups[0];}
function statusForGroup(workflow,g){var items=g.legacy.map(function(k){return workflow.find(function(x){return x.key===k;});}).filter(Boolean);if(!items.length)return'locked';if(items.some(function(x){return x.status==='in_progress';}))return'in_progress';if(items.every(function(x){return x.status==='completed';}))return'completed';if(items.some(function(x){return x.status!=='locked';}))return'in_progress';return'locked';}
function patchHeader(root){var metrics=root.querySelector('.verbum-work-metrics');if(!metrics)return;Array.from(metrics.children).forEach(function(item){var label=item.querySelector('span');var text=label?(label.textContent||'').trim().toUpperCase():'';if(text==='IMO'||text==='RME')item.remove();});var current=visualForLegacy((root.__vs&&root.__vs.workspace&&root.__vs.workspace.currentStage)||currentLegacy());var first=metrics.children[0];if(first){var strong=first.querySelector('strong');if(strong)strong.textContent=current.label;}}
function patchWorkflow(root){var nav=root.querySelector('.verbum-workflow');var workspace=root.__vs&&root.__vs.workspace;if(!nav||!workspace||!Array.isArray(workspace.workflow))return;var selected=visualForLegacy(currentLegacy());var signature=groups.map(function(g){return g.key+':'+statusForGroup(workspace.workflow,g);}).join('|')+'|'+selected.key;if(nav.dataset.hom023a===signature)return;nav.dataset.hom023a=signature;nav.innerHTML=groups.map(function(g,index){var status=statusForGroup(workspace.workflow,g),isSelected=g.key===selected.key,locked=status==='locked';return'<button type="button" class="verbum-workflow-step is-'+status+(isSelected?' is-selected':'')+'" '+(locked?'disabled aria-disabled="true"':'data-stage="'+g.route+'"')+'><span class="verbum-workflow-marker">'+(status==='completed'?'✓':(index+1))+'</span><span>'+g.label+'</span></button>';}).join('');}
function patchBreadcrumb(root){var crumb=root.querySelector('.verbum-work-breadcrumb');if(!crumb)return;var parts=crumb.childNodes;var current=visualForLegacy(currentLegacy()).label;if(parts.length){var html=crumb.innerHTML;var last=html.lastIndexOf('›');if(last>=0)crumb.innerHTML=html.slice(0,last+1)+' '+current;}}
function patch(root){if(!root.querySelector('.verbum-workspace'))return;patchHeader(root);patchWorkflow(root);patchBreadcrumb(root);}
function boot(){roots.forEach(patch);new MutationObserver(function(){roots.forEach(patch);}).observe(document.body,{childList:true,subtree:true});window.addEventListener('verbum:routechange',function(){window.setTimeout(function(){roots.forEach(patch);},0);});window.addEventListener('popstate',function(){window.setTimeout(function(){roots.forEach(patch);},0);});}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot);else boot();
})();
