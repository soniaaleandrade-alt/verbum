(function(){
'use strict';
var roots=document.querySelectorAll('[data-verbum-app]');if(!roots.length)return;
var groups=[
  {key:'identification',label:'Identificação',legacy:['identification'],route:'identification'},
  {key:'foundation',label:'Fundação',legacy:['project'],route:'project'},
  {key:'structure',label:'Estrutura',legacy:['planning'],route:'planning'},
  {key:'chapters',label:'Capítulos',legacy:['development'],route:'development'},
  {key:'review',label:'Revisão Geral',legacy:['general_review'],route:'general_review'},
  {key:'validation',label:'Validação',legacy:['versions','audit'],route:'versions'},
  {key:'preparation',label:'Preparação Editorial',legacy:['editorial_desk','layout','legal'],route:'editorial_desk'},
  {key:'publication',label:'Publicação',legacy:['publication'],route:'publication'}
];
var scheduled=false;
function ensureStyle(){
  if(document.getElementById('verbum-hom027-journey-style'))return;
  var s=document.createElement('style');s.id='verbum-hom027-journey-style';s.textContent='\
.verbum-workflow[data-hom027="1"]{grid-template-columns:repeat(8,minmax(0,1fr))!important}\
.verbum-workflow[data-hom027="1"]:before{left:calc(100% / 16)!important;right:calc(100% / 16)!important}\
@media(max-width:820px){.verbum-workflow[data-hom027="1"]{grid-template-columns:repeat(8,118px)!important}.verbum-workflow[data-hom027="1"]:before{left:75px!important;right:auto!important;width:calc(7 * 125px)!important}}\
@media(max-width:520px){.verbum-workflow[data-hom027="1"]{grid-template-columns:repeat(8,108px)!important}.verbum-workflow[data-hom027="1"]:before{left:70px!important;width:calc(7 * 115px)!important}}';document.head.appendChild(s);
}
function currentLegacy(){var p=new URLSearchParams(location.search);return p.get('verbum_stage')||'identification';}
function visual(stage){return groups.find(function(g){return g.legacy.indexOf(stage)>=0;})||groups[0];}
function groupStatus(workflow,g){var items=g.legacy.map(function(k){return workflow.find(function(x){return x.key===k;});}).filter(Boolean);if(!items.length)return'locked';if(items.some(function(x){return x.status==='in_progress';}))return'in_progress';if(items.every(function(x){return x.status==='completed';}))return'completed';if(items.some(function(x){return x.status!=='locked';}))return'in_progress';return'locked';}
function patch(root){
  if(currentLegacy()==='identification')return;
  var nav=root.querySelector('.verbum-workflow'),w=root.__vs&&root.__vs.workspace;if(!nav||!w||!Array.isArray(w.workflow))return;
  ensureStyle();
  var selected=visual(currentLegacy()),current=visual(w.currentStage||currentLegacy()),currentIndex=Math.max(0,groups.findIndex(function(g){return g.key===current.key;}));
  var progress=groups.length>1?Math.round(currentIndex/(groups.length-1)*100):0;
  var signature=groups.map(function(g){return g.key+':'+groupStatus(w.workflow,g);}).join('|')+'|selected:'+selected.key+'|current:'+current.key;
  if(nav.dataset.hom027State===signature)return;
  nav.dataset.hom027='1';nav.dataset.hom027State=signature;nav.style.setProperty('--journey-progress',progress+'%');
  nav.innerHTML=groups.map(function(g,index){var status=groupStatus(w.workflow,g),isSelected=g.key===selected.key,isCurrent=g.key===current.key,locked=status==='locked';return'<button type="button" class="verbum-workflow-step is-'+status+(isSelected?' is-selected':'')+(isCurrent?' is-current':'')+'" '+(isCurrent?'aria-current="step" ':'')+(locked?'disabled aria-disabled="true"':'data-stage="'+g.route+'"')+'><span class="verbum-workflow-marker">'+(status==='completed'?'✓':(index+1))+'</span><span>'+g.label+'</span></button>';}).join('');
  var crumb=root.querySelector('.verbum-work-breadcrumb');if(crumb){var html=crumb.innerHTML,last=html.lastIndexOf('›');if(last>=0)crumb.innerHTML=html.slice(0,last+1)+' '+selected.label;}
}
function run(){scheduled=false;roots.forEach(patch);}
function schedule(){if(scheduled)return;scheduled=true;(window.requestAnimationFrame||function(cb){return setTimeout(cb,16);})(run);}
function boot(){run();new MutationObserver(schedule).observe(document.body,{childList:true,subtree:true});window.addEventListener('verbum:routechange',schedule);window.addEventListener('popstate',schedule);}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot);else boot();
})();
