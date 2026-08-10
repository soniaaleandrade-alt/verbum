(function(){
  var current=document.currentScript;
  if(!current||!current.src){return;}
  ['static-runtime.js','workspace-mobile-runtime.js','identification-runtime.js','project-stage-runtime.js'].forEach(function(file){
    var source=current.src.replace(/build\/verbum-app\.js(?:\?.*)?$/,'frontend/app/src/'+file);
    if(!source||source===current.src){return;}
    var script=document.createElement('script');
    script.src=source;
    script.defer=false;
    document.head.appendChild(script);
  });
})();
