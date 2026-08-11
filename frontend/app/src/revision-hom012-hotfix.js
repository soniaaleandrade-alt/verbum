(function(){
'use strict';
if(window.__verbumRevisionHom012FetchGuard)return;
window.__verbumRevisionHom012FetchGuard=true;
var nativeFetch=window.fetch.bind(window);
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
function isRevisionRead(url,method){
  if(method!=='GET')return false;
  try{
    var parsed=new URL(url,document.baseURI);
    return /\/verbum\/v1\/books\/\d+\/chapters\/\d+\/revision\/?$/.test(parsed.pathname);
  }catch(e){return false;}
}
window.fetch=function(input,init){
  var url=requestUrl(input),method=requestMethod(input,init);
  if(!isRevisionRead(url,method))return nativeFetch(input,init);
  var parsed=new URL(url,document.baseURI);
  parsed.searchParams.set('_verbum_nocache',String(Date.now()));
  var next=Object.assign({},init||{});
  next.cache='no-store';
  var inherited=(typeof Request!=='undefined'&&input instanceof Request)?input.headers:undefined;
  var headers=new Headers(next.headers||inherited||{});
  headers.set('Cache-Control','no-cache');
  headers.set('Pragma','no-cache');
  next.headers=headers;
  var target=(typeof Request!=='undefined'&&input instanceof Request)?new Request(parsed.toString(),input):parsed.toString();
  return nativeFetch(target,next);
};
})();
