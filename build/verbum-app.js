(function(){
  var current=document.currentScript;
  if(!current||!current.src){return;}
  var source=current.src.replace(/build\/verbum-app\.js(?:\?.*)?$/,'frontend/app/src/static-runtime.js');
  if(!source||source===current.src){return;}
  var script=document.createElement('script');
  script.src=source;
  script.defer=false;
  document.head.appendChild(script);
})();
