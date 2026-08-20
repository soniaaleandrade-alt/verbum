(function(){
'use strict';
var root=document.querySelector('[data-verbum-app]');if(!root)return;
var running=false,lastKey='';
function route(){var p=new URLSearchParams(location.search);return{id:p.get('verbum_work'),stage:p.get('verbum_stage')||'identification'};}
function active(){var r=route();return!!r.id&&r.stage==='identification';}
function api(path){var c=window.VerbumStudioConfig||{},base=String(c.apiRoot||'/wp-json/verbum/v1').replace(/\/$/,'');return fetch(base+path,{method:'GET',credentials:'same-origin',headers:{'X-WP-Nonce':c.nonce||''}}).then(function(r){return r.json().then(function(p){if(!r.ok||!p||!p.success)throw new Error(p&&p.error&&p.error.message||'Não foi possível abrir a Identificação Inicial.');return p.data;});});}
function hasOfficialPage(){return!!root.querySelector('.verbum-identification-initial [data-id-form]');}
function clearLegacy(){if(root.__vs)root.__vs.workspace=null;}
function boot(){if(!active()){lastKey='';return;}var r=route(),key=String(r.id);if(hasOfficialPage()){lastKey=key;return;}if(running||lastKey===key)return;running=true;clearLegacy();api('/books/'+encodeURIComponent(r.id)+'/identification-initial').then(function(workspace){if(!active())return;root.__vs=root.__vs||{};root.__vs.workspace=workspace;root.__vs.selectedStage='identification';lastKey=key;window.dispatchEvent(new CustomEvent('verbum:identification-initial-ready',{detail:{workspace:workspace}}));window.dispatchEvent(new Event('verbum:routechange'));}).catch(function(err){lastKey='';console.error('[Verbum] Identificação Inicial:',err);}).finally(function(){running=false;});}
window.addEventListener('verbum:routechange',boot);window.addEventListener('popstate',boot);setTimeout(boot,0);
})();
