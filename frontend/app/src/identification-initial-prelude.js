(function(){
'use strict';
var root=document.querySelector('[data-verbum-app]');
function active(){var p=new URLSearchParams(location.search);return !!p.get('verbum_work')&&(p.get('verbum_stage')||'identification')==='identification';}
if(!active())return;

// A Identificação Inicial possui um endpoint próprio para salvar rascunhos sem
// regredir obras que já avançaram no fluxo. Mantemos as URLs legadas no runtime
// para compatibilidade e fazemos a ponte apenas enquanto esta tela está ativa.
var nativeFetch=window.fetch;
window.fetch=function(input,init){
  var raw=typeof input==='string'?input:(input&&input.url?input.url:'');
  if(!active()||!raw)return nativeFetch.apply(this,arguments);
  var next=raw
    .replace(/\/books\/(\d+)\/identification\/complete(?=\?|$)/,'/books/$1/identification-initial/complete')
    .replace(/\/books\/(\d+)\/identification(?=\?|$)/,'/books/$1/identification-initial')
    .replace(/\/books\/(\d+)\/workspace(?=\?|$)/,'/books/$1/identification-initial');
  if(next===raw)return nativeFetch.apply(this,arguments);
  if(typeof input==='string')return nativeFetch.call(this,next,init);
  return nativeFetch.call(this,new Request(next,input),init);
};

// Força a nova tela a reidratar a obra pelo endpoint enriquecido em vez de
// reutilizar o snapshot legado já carregado pelo shell.
if(root&&root.__vs)root.__vs.workspace=null;

// O runtime da Identificação já reage a routechange/popstate e monta a tela
// explicitamente. Neutralizamos somente o observer criado por ele para evitar
// remounts causados pelas próprias mutações do novo sidebar.
var NativeObserver=window.MutationObserver;
if(typeof NativeObserver==='function'){
  var armed=true;
  window.MutationObserver=function(callback){
    if(armed){
      armed=false;
      window.MutationObserver=NativeObserver;
      return{observe:function(){},disconnect:function(){},takeRecords:function(){return[];}};
    }
    return new NativeObserver(callback);
  };
  window.MutationObserver.prototype=NativeObserver.prototype;
}
})();
