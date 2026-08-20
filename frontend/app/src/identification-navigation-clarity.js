(function(){
'use strict';
var root=document.querySelector('[data-verbum-app]');if(!root)return;
function route(){var p=new URLSearchParams(location.search);return{work:p.get('verbum_work'),stage:p.get('verbum_stage')||''};}
function patch(){var r=route();if(!r.work||r.stage!=='identification')return;var nav=root.querySelector('.verbum-identification-nav');if(nav){var back=nav.querySelector('[data-id-overview] span:last-child');if(back)back.textContent='Minhas Obras';var aria=nav.querySelector('.verbum-id-nav-list');if(aria)aria.setAttribute('aria-label','Navegação da obra');}
var crumb=root.querySelector('.verbum-id-breadcrumb [data-id-library]');if(crumb)crumb.textContent='Minhas Obras';}
function run(){window.requestAnimationFrame?window.requestAnimationFrame(patch):setTimeout(patch,0);}
window.addEventListener('verbum:routechange',run);window.addEventListener('popstate',run);new MutationObserver(run).observe(document.body,{childList:true,subtree:true});run();
})();
