(function(){
'use strict';
if(window.__verbumRevisionHom012FetchGuard)return;
window.__verbumRevisionHom012FetchGuard=true;
var nativeFetch=window.fetch.bind(window);
var latestRevision=null;
var reconcileTimer=null;

function cfg(){return window.VerbumStudioConfig||{apiRoot:'/wp-json/verbum/v1',nonce:''};}
function esc(value){return String(value==null?'':value).replace(/[&<>"']/g,function(ch){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch];});}
function requestUrl(input){
  if(typeof input==='string')return input;
  if(typeof URL!=='undefined'&&input instanceof URL)return input.toString();
  if(typeof Request!=='undefined'&&input instanceof Request)return input.url;
  return String(input||'');
}
function requestMethod(input,init){
  if(init&&init.method)return String(init.method).toUpperCase();
  if(typeof Request!=='undefined'&&input instanceof Request)return String(input.method||'GET').toUpperCase();
  return 'GET';
}
function revisionRoute(url){
  try{
    var parsed=new URL(url,document.baseURI);
    var match=parsed.pathname.match(/\/verbum\/v1\/books\/(\d+)\/chapters\/(\d+)\/revision(?:\/.*)?$/);
    return match?{book:match[1],chapter:match[2],parsed:parsed}:null;
  }catch(e){return null;}
}
function currentChapter(){var params=new URLSearchParams(location.search);return params.get('verbum_chapter')||'';}
function normalizeRevision(payload){
  if(!payload||payload.success!==true||!payload.data)return null;
  var revision=payload.data.revision||payload.data;
  if(!revision||!Array.isArray(revision.issues))return null;
  return revision;
}
function captureResponse(response){
  try{
    response.clone().json().then(function(payload){
      var revision=normalizeRevision(payload);
      if(!revision)return;
      latestRevision=revision;
      window.__verbumRevisionHom012Latest=revision;
      scheduleReconcile();
    }).catch(function(){});
  }catch(e){}
}
function scheduleReconcile(){
  if(reconcileTimer)clearTimeout(reconcileTimer);
  reconcileTimer=setTimeout(function(){reconcileTimer=null;reconcile();},25);
}
function fingerprint(issues){return JSON.stringify((issues||[]).map(function(issue){return[String(issue.id||''),String(issue.status||''),String(issue.typeLabel||''),String(issue.description||''),String(issue.excerpt||'')];}));}
function directArticles(section){return Array.prototype.filter.call(section.children,function(child){return child&&child.tagName==='ARTICLE';});}
function articleId(article){var button=article.querySelector('[data-revision-issue-status],[data-hom012-issue-status]');return button?String(button.getAttribute('data-revision-issue-status')||button.getAttribute('data-hom012-issue-status')||''):'';}
function articleStatus(article){var button=article.querySelector('[data-revision-issue-status],[data-hom012-issue-status]');if(!button)return'';var next=button.getAttribute('data-status')||button.getAttribute('data-hom012-status')||'';return next==='pending'?'resolved':'pending';}
function domMatches(section,issues){
  var articles=directArticles(section);
  if(articles.length!==issues.length)return false;
  for(var i=0;i<issues.length;i++){
    if(articleId(articles[i])!==String(issues[i].id||''))return false;
    if(articleStatus(articles[i])!==String(issues[i].status||''))return false;
  }
  return true;
}
function issueMarkup(issue){
  var resolved=String(issue.status||'pending')==='resolved';
  return'<article class="'+(resolved?'is-resolved':'')+'" data-hom012-issue="'+esc(issue.id)+'">'
    +'<small>'+esc(issue.typeLabel||issue.type||'Pendência')+'</small>'
    +'<p>'+esc(issue.description||'')+'</p>'
    +(issue.excerpt?'<blockquote>'+esc(issue.excerpt)+'</blockquote>':'')
    +'<div><button type="button" data-hom012-issue-status="'+esc(issue.id)+'" data-hom012-status="'+(resolved?'pending':'resolved')+'">'+(resolved?'Reabrir':'✓ Resolver')+'</button>'
    +'<button type="button" data-hom012-issue-delete="'+esc(issue.id)+'">Excluir</button></div></article>';
}
function revisionApi(path,method,body){
  var headers={'X-WP-Nonce':cfg().nonce||''};
  if(body!==undefined)headers['Content-Type']='application/json';
  return nativeFetch((cfg().apiRoot||'/wp-json/verbum/v1')+path,{method:method,credentials:'same-origin',headers:headers,body:body===undefined?undefined:JSON.stringify(body),cache:'no-store'}).then(function(response){
    return response.json().catch(function(){throw new Error('A API retornou uma resposta inválida.');}).then(function(payload){
      if(!response.ok||!payload||!payload.success)throw new Error(payload&&payload.error&&payload.error.message?payload.error.message:'Não foi possível comunicar com a API.');
      var revision=normalizeRevision(payload);
      if(revision){latestRevision=revision;window.__verbumRevisionHom012Latest=revision;}
      return payload.data;
    });
  });
}
function bindInjected(section){
  section.querySelectorAll('[data-hom012-issue-status]').forEach(function(button){
    button.onclick=function(){
      var params=new URLSearchParams(location.search),book=params.get('verbum_work'),chapter=params.get('verbum_chapter');
      if(!book||!chapter)return;
      button.disabled=true;
      revisionApi('/books/'+book+'/chapters/'+chapter+'/revision/issues/'+button.dataset.hom012IssueStatus,'PATCH',{status:button.dataset.hom012Status}).then(function(){reconcile();}).catch(function(error){button.disabled=false;window.alert(error.message);});
    };
  });
  section.querySelectorAll('[data-hom012-issue-delete]').forEach(function(button){
    button.onclick=function(){
      if(!window.confirm('Excluir esta pendência da Revisão?'))return;
      var params=new URLSearchParams(location.search),book=params.get('verbum_work'),chapter=params.get('verbum_chapter');
      if(!book||!chapter)return;
      button.disabled=true;
      revisionApi('/books/'+book+'/chapters/'+chapter+'/revision/issues/'+button.dataset.hom012IssueDelete,'DELETE').then(function(){reconcile();}).catch(function(error){button.disabled=false;window.alert(error.message);});
    };
  });
}
function reconcileChecklist(){
  if(!latestRevision)return;
  var checklist=document.querySelector('.verbum-revision-checklist');
  if(!checklist)return;
  var head=checklist.querySelector('.verbum-revision-progress-head strong');
  if(head)head.textContent=Number(latestRevision.progress||0)+'%';
  var bar=checklist.querySelector('.verbum-revision-progress span');
  if(bar)bar.style.width=Number(latestRevision.progress||0)+'%';
  (latestRevision.checklist||[]).forEach(function(item){
    var input=checklist.querySelector('[data-revision-flag="'+String(item.key).replace(/"/g,'\\"')+'"]');
    if(!input)return;
    var manual=!item.automatic&&item.key!=='completed';
    var on=manual?input.checked:!!item.completed;
    if(!manual)input.checked=on;
    var label=input.closest('label');
    if(label)label.classList.toggle('is-complete',on);
  });
  var complete=checklist.querySelector('[data-revision-complete]');
  if(complete){
    complete.disabled=!latestRevision.ready||!!latestRevision.completed;
    complete.textContent=latestRevision.completed?'Revisão concluída ✓':'Concluir Revisão';
  }
}
function reconcile(){
  if(!latestRevision||String(latestRevision.chapterId||'')!==String(currentChapter()))return;
  var section=document.querySelector('.verbum-revision-issues');
  if(!section)return;
  var issues=Array.isArray(latestRevision.issues)?latestRevision.issues:[];
  var count=section.querySelector('.verbum-revision-section-head strong');
  if(count)count.textContent=Number(latestRevision.pendingIssueCount||0)+' pendentes';
  var fp=fingerprint(issues);
  if(section.dataset.hom012Fingerprint!==fp){
    if(!domMatches(section,issues)){
      directArticles(section).forEach(function(article){article.remove();});
      var holder=document.createElement('div');
      holder.innerHTML=issues.map(issueMarkup).join('');
      while(holder.firstElementChild)section.appendChild(holder.firstElementChild);
      bindInjected(section);
    }
    section.dataset.hom012Fingerprint=fp;
  }
  reconcileChecklist();
}

window.fetch=function(input,init){
  var url=requestUrl(input),method=requestMethod(input,init),route=revisionRoute(url);
  if(!route)return nativeFetch(input,init);
  var target=input,next=Object.assign({},init||{});
  if(method==='GET'&&/\/revision\/?$/.test(route.parsed.pathname)){
    route.parsed.searchParams.set('_verbum_nocache',String(Date.now()));
    next.cache='no-store';
    var inherited=(typeof Request!=='undefined'&&input instanceof Request)?input.headers:undefined;
    var headers=new Headers(next.headers||inherited||{});
    headers.set('Cache-Control','no-cache');
    headers.set('Pragma','no-cache');
    next.headers=headers;
    target=(typeof Request!=='undefined'&&input instanceof Request)?new Request(route.parsed.toString(),input):route.parsed.toString();
  }
  return nativeFetch(target,next).then(function(response){captureResponse(response);return response;});
};

new MutationObserver(function(){scheduleReconcile();}).observe(document.documentElement,{childList:true,subtree:true});
window.addEventListener('popstate',function(){latestRevision=null;});
window.addEventListener('verbum:routechange',function(){latestRevision=null;});
})();
